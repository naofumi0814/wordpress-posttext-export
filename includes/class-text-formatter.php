<?php
/**
 * テキスト整形クラス
 *
 * HTML本文をプレーンテキストに変換し、各種整形オプションを適用する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPTE_Text_Formatter {

    /**
     * 整形オプション
     *
     * @var array
     */
    private $options;

    /**
     * デフォルトオプション
     *
     * @var array
     */
    private static $default_options = array(
        'paragraph_break'      => true,   // 段落ごとに改行を入れる
        'blank_line'           => true,   // 段落間に空行を1行入れる
        'collapse_blank_lines' => true,   // 連続する空行を整理する
        'heading_break'        => true,   // 見出しの前後に改行を入れる
        'list_break'           => true,   // リスト要素を改行付きで出す
        'normalize_spaces'     => true,   // 不要な空白の正規化
        'trim_lines'           => true,   // 各行の末尾空白を除去
    );

    /**
     * コンストラクタ
     *
     * @param array $options 整形オプション
     */
    public function __construct( array $options = array() ) {
        $this->options = wp_parse_args( $options, self::$default_options );
    }

    /**
     * デフォルトオプションを取得
     *
     * @return array
     */
    public static function get_default_options(): array {
        return self::$default_options;
    }

    /**
     * HTML本文をプレーンテキストに変換する
     *
     * @param string $content HTML本文
     * @return string プレーンテキスト
     */
    public function to_plain_text( string $content ): string {
        // Gutenbergブロックコメントを除去
        $text = $this->remove_gutenberg_comments( $content );

        // ショートコードを除去
        $text = $this->remove_shortcodes( $text );

        // 見出しの処理
        if ( $this->options['heading_break'] ) {
            $text = $this->process_headings( $text );
        }

        // リストの処理
        if ( $this->options['list_break'] ) {
            $text = $this->process_lists( $text );
        }

        // 段落・改行の処理
        if ( $this->options['paragraph_break'] ) {
            $text = $this->process_paragraphs( $text );
        }

        // <br> タグを改行に変換
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );

        // 残りのHTMLタグを除去
        $text = wp_strip_all_tags( $text );

        // HTMLエンティティをデコード
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // &nbsp; などの特殊な空白をスペースに変換
        $text = str_replace( "\xC2\xA0", ' ', $text );

        // 不要な空白の正規化
        if ( $this->options['normalize_spaces'] ) {
            $text = $this->normalize_spaces( $text );
        }

        // 各行の末尾空白を除去
        if ( $this->options['trim_lines'] ) {
            $text = $this->trim_lines( $text );
        }

        // 空行の処理
        if ( $this->options['blank_line'] ) {
            // 段落間に空行を確保
            $text = $this->ensure_blank_lines( $text );
        }

        // 連続する空行を整理
        if ( $this->options['collapse_blank_lines'] ) {
            $text = $this->collapse_blank_lines( $text );
        }

        // 先頭・末尾の空白を除去
        $text = trim( $text );

        return $text;
    }

    /**
     * Gutenbergブロックコメントを除去する
     *
     * @param string $content
     * @return string
     */
    private function remove_gutenberg_comments( string $content ): string {
        // <!-- wp:xxx --> や <!-- /wp:xxx --> を除去
        return preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );
    }

    /**
     * ショートコードを除去する
     *
     * @param string $content
     * @return string
     */
    private function remove_shortcodes( string $content ): string {
        return strip_shortcodes( $content );
    }

    /**
     * 見出しタグを処理する
     *
     * @param string $content
     * @return string
     */
    private function process_headings( string $content ): string {
        // 見出しタグの前後に改行を追加
        $content = preg_replace( '/<h([1-6])[^>]*>/i', "\n\n", $content );
        $content = preg_replace( '/<\/h[1-6]>/i', "\n\n", $content );
        return $content;
    }

    /**
     * リスト要素を処理する
     *
     * @param string $content
     * @return string
     */
    private function process_lists( string $content ): string {
        // ul, ol の前後に改行
        $content = preg_replace( '/<[uo]l[^>]*>/i', "\n", $content );
        $content = preg_replace( '/<\/[uo]l>/i', "\n", $content );

        // li の前に改行、テキスト先頭に「・」付与
        $content = preg_replace( '/<li[^>]*>/i', "\n・", $content );
        $content = preg_replace( '/<\/li>/i', '', $content );

        return $content;
    }

    /**
     * 段落タグを処理する
     *
     * @param string $content
     * @return string
     */
    private function process_paragraphs( string $content ): string {
        // </p> の後に改行2つ（空行）を追加
        $content = preg_replace( '/<\/p>/i', "\n\n", $content );
        // <p> タグは除去
        $content = preg_replace( '/<p[^>]*>/i', '', $content );
        return $content;
    }

    /**
     * 不要な空白を正規化する
     *
     * 全角スペースや特殊文字は保持しつつ、半角スペースの連続を整理
     *
     * @param string $content
     * @return string
     */
    private function normalize_spaces( string $content ): string {
        // 行内の連続半角スペースを1つに（タブも対象）
        // ただし全角スペースは維持する
        $lines = explode( "\n", $content );
        $normalized = array();
        foreach ( $lines as $line ) {
            // 半角スペース・タブの連続を1つの半角スペースに
            $line = preg_replace( '/[ \t]+/', ' ', $line );
            $normalized[] = $line;
        }
        return implode( "\n", $normalized );
    }

    /**
     * 各行の末尾空白を除去する
     *
     * @param string $content
     * @return string
     */
    private function trim_lines( string $content ): string {
        $lines = explode( "\n", $content );
        $trimmed = array_map( 'rtrim', $lines );
        return implode( "\n", $trimmed );
    }

    /**
     * 段落間に空行を確保する
     *
     * @param string $content
     * @return string
     */
    private function ensure_blank_lines( string $content ): string {
        // 既に改行で区切られているため、そのまま維持
        return $content;
    }

    /**
     * 連続する空行を整理する（最大1つの空行にする）
     *
     * @param string $content
     * @return string
     */
    private function collapse_blank_lines( string $content ): string {
        // 3つ以上連続する改行を2つ（= 1空行）に圧縮
        return preg_replace( "/\n{3,}/", "\n\n", $content );
    }

    /**
     * HTML本文をそのまま返す（HTML付きモード用）
     *
     * Gutenbergコメントのみ除去する
     *
     * @param string $content
     * @return string
     */
    public function to_html( string $content ): string {
        $text = $this->remove_gutenberg_comments( $content );
        $text = trim( $text );
        return $text;
    }
}
