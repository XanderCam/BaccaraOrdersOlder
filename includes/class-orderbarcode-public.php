<?php
if (!defined('ABSPATH')) {
    exit;
}

// Remove server-side barcode generation to avoid errors
// Use client-side JsBarcode library for barcode rendering

class OrderBarcode_Public {
    private static $instance = null;
    private $api;
    private $log_file;
    private $debug_mode = true; // Enable debug mode for logging EAN codes

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api = OrderBarcode_API::get_instance();
        $this->log_file = ORDERBARCODE_PLUGIN_DIR . 'orderbarcode_error.log';
        if (!file_exists($this->log_file)) {
            @touch($this->log_file);
            @chmod($this->log_file, 0666);
        }
        add_shortcode('order_barcode', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_orderbarcode_search', array($this, 'handle_ajax_search'));
        add_action('wp_ajax_orderbarcode_refresh', array($this, 'handle_ajax_refresh'));
        add_action('wp_ajax_orderbarcode_get_products', array($this, 'handle_ajax_get_products'));
    }

    public function enqueue_scripts() {
        if (is_page() || is_single()) {
            wp_enqueue_style('tailwindcss', 'https://cdn.tailwindcss.com', array(), ORDERBARCODE_VERSION);
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
            wp_enqueue_style('orderbarcode-style', ORDERBARCODE_PLUGIN_URL . 'assets/css/orderbarcode.css', array(), ORDERBARCODE_VERSION);
            wp_enqueue_script('jsbarcode', 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js', array('jquery'), '3.11.5', true);
            wp_enqueue_script('orderbarcode-script', ORDERBARCODE_PLUGIN_URL . 'assets/js/orderbarcode.js', array('jquery', 'jsbarcode'), ORDERBARCODE_VERSION, true);
            wp_localize_script('orderbarcode-script', 'orderbarcode_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('orderbarcode_nonce'),
                'no_items_text' => __('No items found', 'order-barcode'),
                'error_text' => __('An error occurred', 'order-barcode'),
                'debug_mode' => $this->debug_mode,
            ));
        }
    }

    public function render_shortcode() {
        ob_start();
        ?>
        <div class="orderbarcode-container max-w-4xl mx-auto p-4">
            <form id="orderbarcode-search-form">
                <div class="flex items-center space-x-2">
                    <input type="text" id="orderbarcode-search-input" name="order_id" placeholder="<?php esc_attr_e('Enter Order ID', 'order-barcode'); ?>" size="20" maxlength="20" class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base" />
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base">
                        <i class="fas fa-search mr-2"></i><?php esc_html_e('Search', 'order-barcode'); ?>
                    </button>
                    <button type="button" id="orderbarcode-refresh-button" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 text-base">
                        <i class="fas fa-sync-alt mr-2"></i><?php esc_html_e('Update', 'order-barcode'); ?>
                    </button>
                </div>
            </form>
            <div id="orderbarcode-latest-orders" class="mt-6">
                <h5 class="text-lg font-semibold mb-3 cursor-pointer" id="orderbarcode-latest-toggle"><?php esc_html_e('Latest 10 Orders', 'order-barcode'); ?> <i class="fas fa-chevron-down"></i></h5>
                <ul class="list-disc list-inside text-gray-700 text-sm leading-relaxed space-y-2" id="orderbarcode-latest-list">
                    <?php
                    $latest_orders = $this->api->get_latest_orders(10);
                    if (is_wp_error($latest_orders)) {
                        echo '<li>' . esc_html__('Unable to retrieve latest orders.', 'order-barcode') . '</li>';
                    } else {
                        foreach ($latest_orders as $order) {
                            $order_id = esc_html($order['id']);
                            $customer_name = '';
                            if (!empty($order['customer']) && !empty($order['customer']['name'])) {
                                $customer_name = esc_html($order['customer']['name']);
                            } else if (!empty($order['billingPerson']) && !empty($order['billingPerson']['name'])) {
                                $customer_name = esc_html($order['billingPerson']['name']);
                            }
                            $company_name = isset($order['customer']['company']) ? esc_html($order['customer']['company']) : '';
                            $display_name = trim($customer_name . ($company_name ? ' (' . $company_name . ')' : ''));
                            echo "<li><a href=\"#\" class=\"orderbarcode-latest-order\" data-order-id=\"{$order_id}\">" . sprintf(__('Order #%s - %s', 'order-barcode'), $order_id, $display_name) . "</a></li>";
                        }
                    }
                    ?>
                </ul>
            </div>
            <div id="orderbarcode-order-details" class="mt-6 hidden">
                <h5 class="text-lg font-semibold mb-3"><?php esc_html_e('Order Details', 'order-barcode'); ?></h5>
                <table class="min-w-full border border-gray-300 rounded text-sm leading-relaxed">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2"><?php esc_html_e('SKU', 'order-barcode'); ?></th>
                            <th class="border border-gray-300 px-4 py-2"><?php esc_html_e('Item Name', 'order-barcode'); ?></th>
                            <th class="border border-gray-300 px-4 py-2"><?php esc_html_e('Quantity', 'order-barcode'); ?></th>
                            <th class="border border-gray-300 px-4 py-2"><?php esc_html_e('EAN Barcode', 'order-barcode'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orderbarcode-items-list">
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#orderbarcode-latest-toggle').on('click', function() {
                    $('#orderbarcode-latest-list').slideToggle();
                    $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
                });
                $('#orderbarcode-refresh-button').on('click', function() {
                    $.ajax({
                        url: orderbarcode_ajax.ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'orderbarcode_refresh',
                            nonce: orderbarcode_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                var $latestList = $('#orderbarcode-latest-list');
                                $latestList.empty();
                                var orders = response.data.orders || [];
                                if (orders.length === 0) {
                                    $latestList.append('<li>' + (orderbarcode_ajax.no_items_text || 'No items found') + '</li>');
                                } else {
                                    orders.forEach(function(order) {
                                        var displayName = '';
                                        if (order.customer && order.customer.name) {
                                            displayName = order.customer.name;
                                        } else if (order.billingPerson && order.billingPerson.name) {
                                            displayName = order.billingPerson.name;
                                        }
                                        if (order.customer && order.customer.company) {
                                            displayName += ' (' + order.customer.company + ')';
                                        }
                                        var listItem = '<li><a href="#" class="orderbarcode-latest-order" data-order-id="' + order.id + '">' + 'Order #' + order.id + ' - ' + displayName + '</a></li>';
                                        $latestList.append(listItem);
                                    });
                                }
                            } else {
                                alert(orderbarcode_ajax.error_text || 'An error occurred');
                            }
                        },
                        error: function() {
                            alert(orderbarcode_ajax.error_text || 'An error occurred');
                        }
                    });
                });

                // New handler for clicking on an order to fetch details and product info
                $('#orderbarcode-latest-list').on('click', '.orderbarcode-latest-order', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('order-id');
                    if (!orderId) return;

                    $.ajax({
                        url: orderbarcode_ajax.ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'orderbarcode_search',
                            nonce: orderbarcode_ajax.nonce,
                            order_id: orderId
                        },
                        success: function(response) {
                            if (response.success) {
                                var items = response.data.items || [];
                                if (items.length === 0) {
                                    $('#orderbarcode-items-list').html('<tr><td colspan="4">' + (orderbarcode_ajax.no_items_text || 'No items found') + '</td></tr>');
                                    $('#orderbarcode-order-details').removeClass('hidden');
                                    return;
                                }

                                // Extract SKUs from items
                                var skus = items.map(function(item) { return item.sku; });

                                // Fetch product info by SKUs
                                $.ajax({
                                    url: orderbarcode_ajax.ajaxurl,
                                    method: 'POST',
                                    data: {
                                        action: 'orderbarcode_get_products',
                                        nonce: orderbarcode_ajax.nonce,
                                        skus: skus
                                    },
                                    success: function(prodResponse) {
                                        if (prodResponse.success) {
                                            var products = prodResponse.data.products || {};
                                            var html = '';
                                            items.forEach(function(item) {
                                                var product = products[item.sku] || {};
                                                var ean = '';
                                                if (product.attributes) {
                                                    for (var i = 0; i < product.attributes.length; i++) {
                                                        if (product.attributes[i].name === 'EAN-kode') {
                                                            ean = product.attributes[i].value;
                                                            break;
                                                        }
                                                    }
                                                }
                                                if (!ean) {
                                                    ean = item.ean;
                                                }
                                                if (orderbarcode_ajax.debug_mode) {
                                                    console.log('EAN for SKU ' + item.sku + ': ' + ean);
                                                }
                                                html += '<tr>' +
                                                    '<td class="border border-gray-300 px-4 py-2">' + item.sku + '</td>' +
                                                    '<td class="border border-gray-300 px-4 py-2">' + item.name + '</td>' +
                                                    '<td class="border border-gray-300 px-4 py-2">' + item.quantity + '</td>' +
                                                    '<td class="border border-gray-300 px-4 py-2"><svg class="barcode" jsbarcode-value="' + ean + '"></svg></td>' +
                                                    '</tr>';
                                            });
                                            $('#orderbarcode-items-list').html(html);
                                            $('#orderbarcode-order-details').removeClass('hidden');
                                            // Trigger JsBarcode rendering
                                            if (typeof JsBarcode !== 'undefined') {
                                                $('.barcode').each(function() {
                                                    JsBarcode(this).init();
                                                });
                                            }
                                        } else {
                                            alert(orderbarcode_ajax.error_text || 'An error occurred fetching product info');
                                        }
                                    },
                                    error: function() {
                                        alert(orderbarcode_ajax.error_text || 'An error occurred fetching product info');
                                    }
                                });
                            } else {
                                alert(orderbarcode_ajax.error_text || 'An error occurred fetching order details');
                            }
                        },
                        error: function() {
                            alert(orderbarcode_ajax.error_text || 'An error occurred fetching order details');
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_ajax_search() {
        check_ajax_referer('orderbarcode_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            $this->log_error('Unauthorized AJAX access attempt.');
            wp_send_json_error(array('message' => __('Unauthorized', 'order-barcode')));
        }

        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';

        if (empty($order_id)) {
            $orders = $this->api->get_latest_orders(10);
            if (is_wp_error($orders)) {
                $this->log_error('Error fetching latest orders: ' . $orders->get_error_message());
                wp_send_json_error(array('message' => $orders->get_error_message()));
            }
            wp_send_json_success(array('orders' => $orders));
        } else {
            $order = $this->api->get_order($order_id);
            if (is_wp_error($order)) {
                $this->log_error('Error fetching order ' . $order_id . ': ' . $order->get_error_message());
                wp_send_json_error(array('message' => $order->get_error_message()));
            }
            // Process items and prepare data for client-side barcode generation
            $items_data = array();
            if (!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $ean_code = '';
                    if (!empty($item['attributes'])) {
                        foreach ($item['attributes'] as $attr) {
                            if (isset($attr['name']) && $attr['name'] === 'EAN-kode') {
                                $ean_code = $attr['value'];
                                break;
                            }
                        }
                    }
                    $ean_code = $this->format_ean_code($ean_code);
                    if (preg_match('/^0+$/', $ean_code)) {
                        $ean_code = 'blank';
                    }
                    $items_data[] = array(
                        'sku' => isset($item['sku']) ? $item['sku'] : '',
                        'name' => isset($item['name']) ? $item['name'] : '',
                        'quantity' => isset($item['quantity']) ? $item['quantity'] : 0,
                        'ean' => $ean_code,
                    );
                }
            }
            wp_send_json_success(array('items' => $items_data));
        }
    }

    public function handle_ajax_get_products() {
        check_ajax_referer('orderbarcode_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            $this->log_error('Unauthorized AJAX access attempt.');
            wp_send_json_error(array('message' => __('Unauthorized', 'order-barcode')));
        }

        $skus = isset($_POST['skus']) ? $_POST['skus'] : array();

        if (!is_array($skus)) {
            wp_send_json_error(array('message' => __('Invalid SKUs provided.', 'order-barcode')));
        }

        $products = $this->api->get_products_by_skus($skus);

        if (is_wp_error($products)) {
            $this->log_error('Error fetching products: ' . $products->get_error_message());
            wp_send_json_error(array('message' => $products->get_error_message()));
        }

        wp_send_json_success(array('products' => $products));
    }

    public function handle_ajax_refresh() {
        check_ajax_referer('orderbarcode_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            $this->log_error('Unauthorized AJAX access attempt.');
            wp_send_json_error(array('message' => __('Unauthorized', 'order-barcode')));
        }

        $orders = $this->api->get_latest_orders(10);
        if (is_wp_error($orders)) {
            $this->log_error('Error fetching latest orders: ' . $orders->get_error_message());
            wp_send_json_error(array('message' => $orders->get_error_message()));
        }
        wp_send_json_success(array('orders' => $orders));
    }

    private function format_ean_code($code) {
        // Ensure EAN code is 13 digits, pad with leading zeros if needed
        $code = preg_replace('/\D/', '', $code);
        return str_pad($code, 13, '0', STR_PAD_LEFT);
    }

    private function log_error($message) {
        if (!empty($this->log_file)) {
            error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $this->log_file);
        }
    }
}
?>
