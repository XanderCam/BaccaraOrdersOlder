<?php
/**
 * Plugin Name: Order Barcode
 * Description: Search Ecwid orders and display ordered items with barcodes.
 * Version: 1.3.15
 * Author: thaxam.no, using PHP Barcode Generator by wayfarerdb
 * Text Domain: order-barcode
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ORDERBARCODE_VERSION', '1.3.25');
define('ORDERBARCODE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ORDERBARCODE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ORDERBARCODE_PLUGIN_DIR . 'includes/class-orderbarcode-settings.php';
require_once ORDERBARCODE_PLUGIN_DIR . 'includes/class-orderbarcode-api.php';
require_once ORDERBARCODE_PLUGIN_DIR . 'includes/class-orderbarcode-public.php';

function orderbarcode_init() {
    OrderBarcode_Settings::get_instance();
    OrderBarcode_Public::get_instance();
}
add_action('plugins_loaded', 'orderbarcode_init');


// Activation hook to initialize options
function orderbarcode_activate() {
    if (false === get_option('orderbarcode_store_id')) {
        add_option('orderbarcode_store_id', '');
    }
    if (false === get_option('orderbarcode_public_token')) {
        add_option('orderbarcode_public_token', '');
    }
    if (false === get_option('orderbarcode_hidden_token')) {
        add_option('orderbarcode_hidden_token', '');
    }
}
register_activation_hook(__FILE__, 'orderbarcode_activate');
?>
