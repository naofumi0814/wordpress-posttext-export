<?php
/**
 * Plugin Name: WordPress PostText Export
 * Plugin URI: https://github.com/naofumi0814/wordpress-posttext-export
 * Description: 投稿本文を中心に、必要な項目だけを選択してテキストまたはPDFでエクスポートできるプラグイン
 * Version: 1.0.0
 * Author: naofumi0814
 * Author URI: https://github.com/naofumi0814
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordpress-posttext-export
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPTE_VERSION', '1.0.0' );
define( 'WPTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// クラスファイル読み込み
require_once WPTE_PLUGIN_DIR . 'includes/class-text-formatter.php';
require_once WPTE_PLUGIN_DIR . 'includes/class-post-query.php';
require_once WPTE_PLUGIN_DIR . 'includes/class-exporter.php';
require_once WPTE_PLUGIN_DIR . 'includes/class-pdf-exporter.php';
require_once WPTE_PLUGIN_DIR . 'includes/class-admin-page.php';

/**
 * プラグイン初期化
 */
function wpte_init() {
    if ( is_admin() ) {
        new WPTE_Admin_Page();
    }
}
add_action( 'plugins_loaded', 'wpte_init' );
