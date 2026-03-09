<?php
/**
 * 管理画面クラス
 *
 * WordPress管理画面にエクスポート設定ページを追加する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPTE_Admin_Page {

    /**
     * nonceアクション名
     */
    const NONCE_ACTION = 'wpte_export_action';

    /**
     * nonceフィールド名
     */
    const NONCE_FIELD = 'wpte_export_nonce';

    /**
     * 必要な権限
     */
    const REQUIRED_CAPABILITY = 'export';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_export' ) );
        add_action( 'wp_ajax_wpte_count_posts', array( $this, 'ajax_count_posts' ) );
    }

    /**
     * メニューページを追加する
     */
    public function add_menu_page(): void {
        add_management_page(
            __( 'PostText Export', 'wordpress-posttext-export' ),
            __( 'PostText Export', 'wordpress-posttext-export' ),
            self::REQUIRED_CAPABILITY,
            'wpte-export',
            array( $this, 'render_page' )
        );
    }

    /**
     * CSS/JSを読み込む
     *
     * @param string $hook 現在のページフック
     */
    public function enqueue_assets( string $hook ): void {
        if ( 'tools_page_wpte-export' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wpte-admin-style',
            WPTE_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WPTE_VERSION
        );

        wp_enqueue_script(
            'wpte-admin-script',
            WPTE_PLUGIN_URL . 'assets/js/admin-script.js',
            array( 'jquery' ),
            WPTE_VERSION,
            true
        );

        wp_localize_script( 'wpte-admin-script', 'wpteAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpte_count_nonce' ),
            'strings' => array(
                'counting'  => __( '件数を取得中...', 'wordpress-posttext-export' ),
                'result'    => __( '%d 件の投稿が見つかりました', 'wordpress-posttext-export' ),
                'noResults' => __( '条件に一致する投稿がありません', 'wordpress-posttext-export' ),
                'error'     => __( '件数の取得に失敗しました', 'wordpress-posttext-export' ),
            ),
        ) );
    }

    /**
     * エクスポートリクエストを処理する
     */
    public function handle_export(): void {
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            return;
        }

        // nonceチェック
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            wp_die(
                esc_html__( '不正なリクエストです。', 'wordpress-posttext-export' ),
                esc_html__( 'セキュリティエラー', 'wordpress-posttext-export' ),
                array( 'response' => 403, 'back_link' => true )
            );
        }

        // 権限チェック
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            wp_die(
                esc_html__( 'この操作を実行する権限がありません。', 'wordpress-posttext-export' ),
                esc_html__( '権限エラー', 'wordpress-posttext-export' ),
                array( 'response' => 403, 'back_link' => true )
            );
        }

        // パラメータ取得
        $query_params = $this->get_query_params_from_post();
        $fields       = $this->get_fields_from_post();
        $options      = $this->get_options_from_post();
        $format       = isset( $_POST['export_format'] ) ? sanitize_key( $_POST['export_format'] ) : 'txt';
        $custom_name  = isset( $_POST['filename'] ) ? sanitize_text_field( wp_unslash( $_POST['filename'] ) ) : '';

        $filename = WPTE_Exporter::generate_filename( $custom_name, $format, $query_params );

        // 整形オプション
        $formatter_options = $this->get_formatter_options_from_post();

        $exporter = new WPTE_Exporter( $formatter_options );

        if ( 'pdf' === $format ) {
            $exporter->export_pdf( $query_params, $fields, $options, $filename );
        } else {
            $exporter->export_txt( $query_params, $fields, $options, $filename );
        }
    }

    /**
     * AJAX: 投稿件数を取得する
     */
    public function ajax_count_posts(): void {
        check_ajax_referer( 'wpte_count_nonce', 'nonce' );

        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $query_params = array(
            'post_types'    => isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
                ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
                : array( 'post' ),
            'post_statuses' => isset( $_POST['post_statuses'] ) && is_array( $_POST['post_statuses'] )
                ? array_map( 'sanitize_key', wp_unslash( $_POST['post_statuses'] ) )
                : array( 'publish' ),
            'date_from'     => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
            'date_to'       => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
            'keyword'       => isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '',
            'post_ids'      => isset( $_POST['post_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ids'] ) ) : '',
        );

        $query = new WPTE_Post_Query();
        $count = $query->count_posts( $query_params );

        wp_send_json_success( array( 'count' => $count ) );
    }

    /**
     * POSTデータからクエリパラメータを取得する
     *
     * @return array
     */
    private function get_query_params_from_post(): array {
        return array(
            'post_types'    => isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
                ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
                : array( 'post' ),
            'post_statuses' => isset( $_POST['post_statuses'] ) && is_array( $_POST['post_statuses'] )
                ? array_map( 'sanitize_key', wp_unslash( $_POST['post_statuses'] ) )
                : array( 'publish' ),
            'date_from'     => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
            'date_to'       => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
            'keyword'       => isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '',
            'post_ids'      => isset( $_POST['post_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ids'] ) ) : '',
        );
    }

    /**
     * POSTデータから出力フィールドを取得する
     *
     * @return array
     */
    private function get_fields_from_post(): array {
        if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
            $available = array_keys( WPTE_Exporter::get_available_fields() );
            $fields = array_map( 'sanitize_key', wp_unslash( $_POST['fields'] ) );
            return array_intersect( $fields, $available );
        }
        return WPTE_Exporter::get_default_fields();
    }

    /**
     * POSTデータから出力オプションを取得する
     *
     * @return array
     */
    private function get_options_from_post(): array {
        return array(
            'show_labels'      => ! empty( $_POST['show_labels'] ),
            'separator_type'   => isset( $_POST['separator_type'] ) ? sanitize_key( $_POST['separator_type'] ) : 'blank_line',
            'separator_custom' => isset( $_POST['separator_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['separator_custom'] ) ) : '---',
        );
    }

    /**
     * POSTデータからテキスト整形オプションを取得する
     *
     * @return array
     */
    private function get_formatter_options_from_post(): array {
        $defaults = WPTE_Text_Formatter::get_default_options();
        $options = array();

        foreach ( $defaults as $key => $default_value ) {
            // チェックボックスの場合: POSTに含まれていれば true
            $options[ $key ] = ! empty( $_POST[ 'fmt_' . $key ] );
        }

        return $options;
    }

    /**
     * 管理画面ページを描画する
     */
    public function render_page(): void {
        $post_types = WPTE_Post_Query::get_available_post_types();
        $fields     = WPTE_Exporter::get_available_fields();
        $defaults   = WPTE_Exporter::get_default_fields();
        $fmt_opts   = WPTE_Text_Formatter::get_default_options();
        $pdf_notice = WPTE_PDF_Exporter::get_install_notice();
        ?>
        <div class="wrap wpte-wrap">
            <h1><?php esc_html_e( 'PostText Export', 'wordpress-posttext-export' ); ?></h1>
            <p class="wpte-description">
                <?php esc_html_e( '投稿本文を中心に、必要な項目を選択してテキストまたはPDFでエクスポートします。', 'wordpress-posttext-export' ); ?>
            </p>

            <?php if ( $pdf_notice ) : ?>
                <div class="notice notice-info">
                    <p><?php echo wp_kses_post( $pdf_notice ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="wpte-export-form">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                <!-- 対象選択エリア -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( '対象選択', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <!-- 投稿タイプ -->
                        <tr>
                            <th scope="row"><?php esc_html_e( '投稿タイプ', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ( $post_types as $slug => $label ) : ?>
                                        <label class="wpte-checkbox-label">
                                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>"
                                                <?php checked( 'post' === $slug ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                            <span class="wpte-slug">(<?php echo esc_html( $slug ); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- 投稿ステータス -->
                        <tr>
                            <th scope="row"><?php esc_html_e( '投稿ステータス', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php
                                    $statuses = array(
                                        'publish' => __( '公開済み', 'wordpress-posttext-export' ),
                                        'draft'   => __( '下書き', 'wordpress-posttext-export' ),
                                        'private' => __( '非公開', 'wordpress-posttext-export' ),
                                        'future'  => __( '予約投稿', 'wordpress-posttext-export' ),
                                        'pending' => __( 'レビュー待ち', 'wordpress-posttext-export' ),
                                    );
                                    foreach ( $statuses as $status => $label ) :
                                    ?>
                                        <label class="wpte-checkbox-label">
                                            <input type="checkbox" name="post_statuses[]" value="<?php echo esc_attr( $status ); ?>"
                                                <?php checked( 'publish' === $status ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- 期間指定 -->
                        <tr>
                            <th scope="row"><?php esc_html_e( '期間指定', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <label>
                                    <?php esc_html_e( '開始日:', 'wordpress-posttext-export' ); ?>
                                    <input type="date" name="date_from" value="" class="wpte-date-input">
                                </label>
                                <span class="wpte-separator">〜</span>
                                <label>
                                    <?php esc_html_e( '終了日:', 'wordpress-posttext-export' ); ?>
                                    <input type="date" name="date_to" value="" class="wpte-date-input">
                                </label>
                            </td>
                        </tr>

                        <!-- キーワード検索 -->
                        <tr>
                            <th scope="row"><?php esc_html_e( 'キーワード検索', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <input type="text" name="keyword" value="" class="regular-text"
                                    placeholder="<?php esc_attr_e( '検索キーワード（任意）', 'wordpress-posttext-export' ); ?>">
                            </td>
                        </tr>

                        <!-- ID指定 -->
                        <tr>
                            <th scope="row"><?php esc_html_e( '投稿ID指定', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <input type="text" name="post_ids" value="" class="regular-text"
                                    placeholder="<?php esc_attr_e( 'カンマ区切りでID指定（任意）例: 1,2,3', 'wordpress-posttext-export' ); ?>">
                                <p class="description">
                                    <?php esc_html_e( '特定の投稿のみ出力する場合はIDを指定してください。', 'wordpress-posttext-export' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- 件数プレビュー -->
                    <div class="wpte-count-preview">
                        <button type="button" id="wpte-count-btn" class="button">
                            <?php esc_html_e( '対象件数を確認', 'wordpress-posttext-export' ); ?>
                        </button>
                        <span id="wpte-count-result" class="wpte-count-result"></span>
                    </div>
                </div>

                <!-- 出力項目選択エリア -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( '出力項目', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( '出力する項目', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ( $fields as $key => $label ) : ?>
                                        <label class="wpte-checkbox-label">
                                            <input type="checkbox" name="fields[]" value="<?php echo esc_attr( $key ); ?>"
                                                <?php checked( in_array( $key, $defaults, true ) ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php esc_html_e( '「HTML本文」にチェックを入れると、HTML構造を含む本文が出力されます。', 'wordpress-posttext-export' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- ラベル表示 -->
                        <tr>
                            <th scope="row"><?php esc_html_e( 'ラベル表示', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_labels" value="1" checked>
                                    <?php esc_html_e( '各項目にラベル（タイトル:、公開日: 等）を付ける', 'wordpress-posttext-export' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 本文整形オプションエリア -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( '本文整形オプション', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <?php
                        $fmt_labels = array(
                            'paragraph_break'      => __( '段落ごとに改行を入れる', 'wordpress-posttext-export' ),
                            'blank_line'           => __( '段落間に空行を1行入れる', 'wordpress-posttext-export' ),
                            'collapse_blank_lines' => __( '連続する空行を整理する', 'wordpress-posttext-export' ),
                            'heading_break'        => __( '見出しの前後に改行を入れる', 'wordpress-posttext-export' ),
                            'list_break'           => __( 'リスト要素を改行付きで出す', 'wordpress-posttext-export' ),
                            'normalize_spaces'     => __( '不要な空白を正規化する', 'wordpress-posttext-export' ),
                            'trim_lines'           => __( '各行の末尾空白を除去する', 'wordpress-posttext-export' ),
                        );
                        ?>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'テキスト整形', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ( $fmt_labels as $key => $label ) : ?>
                                        <label class="wpte-checkbox-label">
                                            <input type="checkbox" name="fmt_<?php echo esc_attr( $key ); ?>" value="1"
                                                <?php checked( $fmt_opts[ $key ] ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 記事区切り設定 -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( '記事の区切り設定', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( '区切り方法', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="separator_type" value="blank_line" checked>
                                        <?php esc_html_e( '空行で区切る', 'wordpress-posttext-export' ); ?>
                                    </label>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="separator_type" value="line">
                                        <?php esc_html_e( '区切り線（----------------------------------------）', 'wordpress-posttext-export' ); ?>
                                    </label>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="separator_type" value="custom">
                                        <?php esc_html_e( '指定文字列で区切る', 'wordpress-posttext-export' ); ?>
                                    </label>
                                    <div class="wpte-custom-separator" id="wpte-custom-separator">
                                        <input type="text" name="separator_custom" value="---" class="regular-text"
                                            placeholder="<?php esc_attr_e( '区切り文字列を入力', 'wordpress-posttext-export' ); ?>">
                                    </div>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="separator_type" value="none">
                                        <?php esc_html_e( '区切りなし', 'wordpress-posttext-export' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 出力形式選択エリア -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( '出力形式', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'ファイル形式', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <fieldset>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="export_format" value="txt" checked>
                                        <?php esc_html_e( 'TXT（テキストファイル / UTF-8 BOM付き）', 'wordpress-posttext-export' ); ?>
                                    </label>
                                    <label class="wpte-radio-label">
                                        <input type="radio" name="export_format" value="pdf">
                                        <?php esc_html_e( 'PDF', 'wordpress-posttext-export' ); ?>
                                        <?php if ( ! WPTE_PDF_Exporter::is_available() ) : ?>
                                            <span class="wpte-notice-inline">
                                                <?php esc_html_e( '（TCPDFが未導入のため簡易版で出力されます）', 'wordpress-posttext-export' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ファイル名設定エリア -->
                <div class="wpte-section">
                    <h2 class="wpte-section-title"><?php esc_html_e( 'ファイル名', 'wordpress-posttext-export' ); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'ファイル名', 'wordpress-posttext-export' ); ?></th>
                            <td>
                                <input type="text" name="filename" value="" class="regular-text"
                                    placeholder="<?php esc_attr_e( '空欄で自動生成（例: posttext-export_2026-03-09.txt）', 'wordpress-posttext-export' ); ?>">
                                <p class="description">
                                    <?php esc_html_e( '空欄の場合、日付と投稿タイプを含むファイル名が自動生成されます。', 'wordpress-posttext-export' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 実行ボタン -->
                <div class="wpte-submit-section">
                    <?php submit_button( __( 'エクスポート実行', 'wordpress-posttext-export' ), 'primary', 'wpte_submit', false ); ?>
                </div>

            </form>
        </div>
        <?php
    }
}
