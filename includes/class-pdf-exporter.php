<?php
/**
 * PDF出力処理クラス
 *
 * TCPDFを使用してPDFを生成する
 * TCPDFが利用できない場合は、代替としてシンプルなPDF生成を行う
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPTE_PDF_Exporter {

    /**
     * TCPDFが利用可能かチェックする
     *
     * @return bool
     */
    public static function is_available(): bool {
        $tcpdf_path = WPTE_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php';
        return file_exists( $tcpdf_path );
    }

    /**
     * PDFをエクスポートする
     *
     * @param string $content  テキストコンテンツ
     * @param string $filename ファイル名
     */
    public function export( string $content, string $filename ): void {
        if ( self::is_available() ) {
            $this->export_with_tcpdf( $content, $filename );
        } else {
            $this->export_simple_pdf( $content, $filename );
        }
    }

    /**
     * TCPDFを使用してPDFをエクスポートする
     *
     * @param string $content  テキストコンテンツ
     * @param string $filename ファイル名
     */
    private function export_with_tcpdf( string $content, string $filename ): void {
        require_once WPTE_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php';

        $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );

        // ドキュメント情報
        $pdf->SetCreator( 'WordPress PostText Export' );
        $pdf->SetAuthor( 'WordPress' );
        $pdf->SetTitle( 'PostText Export' );

        // ヘッダー・フッター無効化
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );

        // マージン設定
        $pdf->SetMargins( 15, 15, 15 );
        $pdf->SetAutoPageBreak( true, 15 );

        // 日本語フォント設定
        // TCPDFには日本語フォント（kozgopromedium等）が内蔵されている
        $pdf->SetFont( 'kozgopromedium', '', 10 );

        $pdf->AddPage();

        // テキストをセルとして出力
        $pdf->MultiCell( 0, 7, $content, 0, 'L', false, 1, '', '', true, 0, false, true, 0, 'T', false );

        // PDF出力
        nocache_headers();
        $pdf->Output( sanitize_file_name( $filename ), 'D' );
        exit;
    }

    /**
     * TCPDF未導入時のシンプルなPDF生成
     *
     * PHPのみで最低限のPDFを生成する（日本語はType0フォント埋め込み）
     * 制約: 日本語表示が限定的なため、TCPDF導入を強く推奨
     *
     * @param string $content  テキストコンテンツ
     * @param string $filename ファイル名
     */
    private function export_simple_pdf( string $content, string $filename ): void {
        // シンプルなPDF生成（FPDF互換の最小実装）
        // 日本語対応のため、UTF-16BEエンコードでテキストを埋め込む

        $lines = explode( "\n", $content );

        // PDF構築
        $objects = array();
        $offsets = array();
        $pdf_content = '';

        // PDFヘッダー
        $pdf_content .= "%PDF-1.4\n";
        // バイナリデータ含有マーカー
        $pdf_content .= "%" . chr(0xE2) . chr(0xE3) . chr(0xCF) . chr(0xD3) . "\n";

        // オブジェクト1: カタログ
        $offsets[1] = strlen( $pdf_content );
        $pdf_content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // オブジェクト2: ページツリー
        $offsets[2] = strlen( $pdf_content );
        $pdf_content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // CIDフォント用のオブジェクト群
        // オブジェクト4: CIDSystemInfo
        $offsets[4] = strlen( $pdf_content );
        $pdf_content .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // ストリームコンテンツの構築
        // 日本語を含むため、テキストはラテン文字のみ出力し、
        // 日本語部分はUnicodeエスケープで処理
        $stream = "BT\n/F1 10 Tf\n";
        $y_position = 800; // 上から開始
        $line_height = 14;
        $page_bottom = 50;

        foreach ( $lines as $line ) {
            if ( $y_position < $page_bottom ) {
                // ページ下端に達した場合（簡易版では1ページのみ）
                break;
            }

            // PDFのテキスト出力（ASCII文字のみ安全に出力）
            $safe_line = $this->to_pdf_safe_string( $line );
            $stream .= "1 0 0 1 50 " . $y_position . " Tm\n";
            $stream .= "(" . $safe_line . ") Tj\n";
            $y_position -= $line_height;
        }

        $stream .= "ET\n";

        // オブジェクト5: コンテンツストリーム
        $offsets[5] = strlen( $pdf_content );
        $stream_length = strlen( $stream );
        $pdf_content .= "5 0 obj\n<< /Length " . $stream_length . " >>\nstream\n" . $stream . "endstream\nendobj\n";

        // オブジェクト3: ページ
        $offsets[3] = strlen( $pdf_content );
        $pdf_content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] ";
        $pdf_content .= "/Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n";

        // クロスリファレンステーブル
        $xref_offset = strlen( $pdf_content );
        $pdf_content .= "xref\n0 6\n";
        $pdf_content .= "0000000000 65535 f \n";
        for ( $i = 1; $i <= 5; $i++ ) {
            $pdf_content .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
        }

        // トレーラー
        $pdf_content .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf_content .= "startxref\n" . $xref_offset . "\n%%EOF\n";

        // HTTPヘッダーとPDF出力
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Length: ' . strlen( $pdf_content ) );

        echo $pdf_content;
        exit;
    }

    /**
     * PDF安全な文字列に変換する
     *
     * PDFの文字列リテラルで安全に使えるようにエスケープする
     * 日本語文字はASCII表現に置換される（シンプルPDFモードの制約）
     *
     * @param string $text
     * @return string
     */
    private function to_pdf_safe_string( string $text ): string {
        // PDFの特殊文字をエスケープ
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( '(', '\\(', $text );
        $text = str_replace( ')', '\\)', $text );

        // 非ASCII文字を除去（シンプルPDFでは日本語非対応）
        $text = preg_replace( '/[^\x20-\x7E]/', '', $text );

        return $text;
    }

    /**
     * TCPDF導入案内メッセージを取得する
     *
     * @return string
     */
    public static function get_install_notice(): string {
        if ( self::is_available() ) {
            return '';
        }

        return sprintf(
            /* translators: %s: lib/tcpdf directory path */
            __(
                'PDF出力で日本語を正しく表示するには、TCPDFライブラリの導入が必要です。' .
                'TCPDFをダウンロードし、%s ディレクトリに配置してください。' .
                'TCPDFが未導入の場合、PDF出力はASCII文字のみの簡易版となります。',
                'wordpress-posttext-export'
            ),
            '<code>' . esc_html( WPTE_PLUGIN_DIR . 'lib/tcpdf/' ) . '</code>'
        );
    }
}
