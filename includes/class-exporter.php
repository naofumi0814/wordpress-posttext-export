<?php
/**
 * エクスポート処理クラス
 *
 * 投稿データを取得し、指定形式でエクスポートする
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPTE_Exporter {

    /**
     * 投稿クエリ
     *
     * @var WPTE_Post_Query
     */
    private $query;

    /**
     * テキスト整形
     *
     * @var WPTE_Text_Formatter
     */
    private $formatter;

    /**
     * 出力可能なフィールド一覧
     *
     * @var array
     */
    private static $available_fields = array(
        'title'           => 'タイトル',
        'date'            => '公開日',
        'modified'        => '更新日',
        'author'          => '投稿者名',
        'post_type_label' => '投稿タイプ名',
        'id'              => '投稿ID',
        'slug'            => 'スラッグ',
        'permalink'       => 'パーマリンク',
        'excerpt'         => '抜粋',
        'content'         => '本文',
        'html_content'    => 'HTML本文',
        'thumbnail_url'   => 'アイキャッチ画像URL',
    );

    /**
     * デフォルトで選択されるフィールド
     *
     * @var array
     */
    private static $default_fields = array( 'title', 'date', 'content' );

    /**
     * コンストラクタ
     *
     * @param array $formatter_options テキスト整形オプション
     */
    public function __construct( array $formatter_options = array() ) {
        $this->query     = new WPTE_Post_Query();
        $this->formatter = new WPTE_Text_Formatter( $formatter_options );
    }

    /**
     * 出力可能なフィールド一覧を取得
     *
     * @return array
     */
    public static function get_available_fields(): array {
        return self::$available_fields;
    }

    /**
     * デフォルトフィールドを取得
     *
     * @return array
     */
    public static function get_default_fields(): array {
        return self::$default_fields;
    }

    /**
     * エクスポートを実行してTXTファイルを出力する
     *
     * @param array  $query_params  検索条件
     * @param array  $fields        出力フィールド
     * @param array  $options       出力オプション
     * @param string $filename      ファイル名
     */
    public function export_txt( array $query_params, array $fields, array $options, string $filename ): void {
        $posts = $this->query->get_posts( $query_params );

        if ( empty( $posts ) ) {
            wp_die(
                esc_html__( 'エクスポート対象の投稿が見つかりませんでした。', 'wordpress-posttext-export' ),
                esc_html__( 'エクスポートエラー', 'wordpress-posttext-export' ),
                array( 'back_link' => true )
            );
        }

        $content = $this->build_export_content( $posts, $fields, $options );

        // UTF-8 BOM付きで出力（Windowsでの文字化け防止）
        $bom = "\xEF\xBB\xBF";

        // HTTPヘッダー出力
        nocache_headers();
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Length: ' . ( strlen( $bom ) + strlen( $content ) ) );

        echo $bom;
        echo $content;
        exit;
    }

    /**
     * エクスポートを実行してPDFファイルを出力する
     *
     * @param array  $query_params  検索条件
     * @param array  $fields        出力フィールド
     * @param array  $options       出力オプション
     * @param string $filename      ファイル名
     */
    public function export_pdf( array $query_params, array $fields, array $options, string $filename ): void {
        $posts = $this->query->get_posts( $query_params );

        if ( empty( $posts ) ) {
            wp_die(
                esc_html__( 'エクスポート対象の投稿が見つかりませんでした。', 'wordpress-posttext-export' ),
                esc_html__( 'エクスポートエラー', 'wordpress-posttext-export' ),
                array( 'back_link' => true )
            );
        }

        $content = $this->build_export_content( $posts, $fields, $options );

        $pdf_exporter = new WPTE_PDF_Exporter();
        $pdf_exporter->export( $content, $filename );
    }

    /**
     * エクスポート用のテキストコンテンツを構築する
     *
     * @param WP_Post[] $posts   投稿一覧
     * @param array     $fields  出力フィールド
     * @param array     $options 出力オプション
     * @return string
     */
    public function build_export_content( array $posts, array $fields, array $options ): string {
        $show_labels  = ! empty( $options['show_labels'] );
        $separator    = $this->get_separator( $options );
        $parts        = array();

        foreach ( $posts as $post ) {
            $entry = $this->build_post_entry( $post, $fields, $show_labels );
            $parts[] = $entry;
        }

        return implode( $separator, $parts );
    }

    /**
     * 1投稿分のエクスポートテキストを構築する
     *
     * @param WP_Post $post        投稿
     * @param array   $fields      出力フィールド
     * @param bool    $show_labels ラベル表示するか
     * @return string
     */
    private function build_post_entry( WP_Post $post, array $fields, bool $show_labels ): string {
        $lines = array();

        foreach ( $fields as $field ) {
            $value = $this->get_field_value( $post, $field );
            if ( $value === null ) {
                continue;
            }

            if ( $show_labels ) {
                $label = self::$available_fields[ $field ] ?? $field;
                // 本文系は改行して表示
                if ( in_array( $field, array( 'content', 'html_content', 'excerpt' ), true ) ) {
                    $lines[] = $label . ":\n" . $value;
                } else {
                    $lines[] = $label . ': ' . $value;
                }
            } else {
                $lines[] = $value;
            }
        }

        return implode( "\n", $lines );
    }

    /**
     * 投稿から指定フィールドの値を取得する
     *
     * @param WP_Post $post  投稿
     * @param string  $field フィールド名
     * @return string|null
     */
    private function get_field_value( WP_Post $post, string $field ): ?string {
        switch ( $field ) {
            case 'title':
                return get_the_title( $post );

            case 'date':
                return get_the_date( 'Y-m-d H:i:s', $post );

            case 'modified':
                return get_the_modified_date( 'Y-m-d H:i:s', $post );

            case 'author':
                return get_the_author_meta( 'display_name', $post->post_author );

            case 'post_type_label':
                $pt_obj = get_post_type_object( $post->post_type );
                return $pt_obj ? $pt_obj->label : $post->post_type;

            case 'id':
                return (string) $post->ID;

            case 'slug':
                return $post->post_name;

            case 'permalink':
                return get_permalink( $post );

            case 'excerpt':
                $excerpt = $post->post_excerpt;
                if ( empty( $excerpt ) ) {
                    $excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
                }
                return $this->formatter->to_plain_text( $excerpt );

            case 'content':
                return $this->formatter->to_plain_text( $post->post_content );

            case 'html_content':
                return $this->formatter->to_html( $post->post_content );

            case 'thumbnail_url':
                $thumbnail_id = get_post_thumbnail_id( $post );
                if ( $thumbnail_id ) {
                    $url = wp_get_attachment_url( $thumbnail_id );
                    return $url ? $url : null;
                }
                return null;

            default:
                // 拡張用: フィルターで追加フィールドに対応
                return apply_filters( 'wpte_custom_field_value', null, $post, $field );
        }
    }

    /**
     * 区切り文字列を取得する
     *
     * @param array $options
     * @return string
     */
    private function get_separator( array $options ): string {
        $type = $options['separator_type'] ?? 'blank_line';

        switch ( $type ) {
            case 'none':
                return "\n";

            case 'blank_line':
                return "\n\n";

            case 'line':
                return "\n\n" . str_repeat( '-', 40 ) . "\n\n";

            case 'custom':
                $custom = $options['separator_custom'] ?? '---';
                $custom = sanitize_text_field( $custom );
                return "\n\n" . $custom . "\n\n";

            default:
                return "\n\n";
        }
    }

    /**
     * ファイル名を生成する
     *
     * @param string $custom_name  カスタムファイル名（空なら自動生成）
     * @param string $format       出力形式（txt / pdf）
     * @param array  $query_params クエリパラメータ
     * @return string
     */
    public static function generate_filename( string $custom_name, string $format, array $query_params = array() ): string {
        $extension = ( 'pdf' === $format ) ? '.pdf' : '.txt';

        if ( ! empty( $custom_name ) ) {
            $name = sanitize_file_name( $custom_name );
            // 拡張子が既についていなければ付与
            if ( ! preg_match( '/\.' . preg_quote( ltrim( $extension, '.' ), '/' ) . '$/i', $name ) ) {
                $name .= $extension;
            }
            return $name;
        }

        // 自動生成
        $date_part = current_time( 'Y-m-d' );
        $type_part = '';
        if ( ! empty( $query_params['post_types'] ) && is_array( $query_params['post_types'] ) ) {
            $type_part = '_' . implode( '-', array_slice( $query_params['post_types'], 0, 3 ) );
        }

        return 'posttext-export' . $type_part . '_' . $date_part . $extension;
    }
}
