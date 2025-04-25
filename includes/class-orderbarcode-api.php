<?php
if (!defined('ABSPATH')) {
    exit;
}

class OrderBarcode_API {
    private static $instance = null;
    private $store_id;
    private $public_token;
    private $hidden_token;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->store_id = get_option('orderbarcode_store_id', '');
        $this->public_token = get_option('orderbarcode_public_token', '');
        $settings = OrderBarcode_Settings::get_instance();
        $this->hidden_token = $settings->get_decrypted_hidden_token();
    }

    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->hidden_token,
            'Content-Type' => 'application/json',
        );
    }

    public function verify_credentials() {
        if (empty($this->store_id) || empty($this->public_token) || empty($this->hidden_token)) {
            return new WP_Error('missing_credentials', __('Missing API credentials.', 'order-barcode'));
        }
        $url = "https://app.ecwid.com/api/v3/{$this->store_id}/profile?token={$this->public_token}";
        $response = wp_remote_get($url, array('headers' => $this->get_headers()));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('invalid_credentials', __('Invalid API credentials.', 'order-barcode'));
        }

        return true;
    }

    public function get_latest_orders($limit = 10) {
        if (empty($this->store_id) || empty($this->public_token)) {
            return new WP_Error('missing_credentials', __('Missing API credentials.', 'order-barcode'));
        }
        $url = "https://app.ecwid.com/api/v3/{$this->store_id}/orders?limit={$limit}&token={$this->public_token}&sort=createdDesc";
        $response = wp_remote_get($url, array('headers' => $this->get_headers()));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $message = 'Failed to retrieve orders. HTTP code: ' . $code . '. Response: ' . $body;
            return new WP_Error('api_error', __($message, 'order-barcode'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['items']) ? $data['items'] : array();
    }

    public function get_order($order_id) {
        if (empty($this->store_id) || empty($this->public_token)) {
            return new WP_Error('missing_credentials', __('Missing API credentials.', 'order-barcode'));
        }
        $url = "https://app.ecwid.com/api/v3/{$this->store_id}/orders/{$order_id}?token={$this->public_token}";
        $response = wp_remote_get($url, array('headers' => $this->get_headers()));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $message = 'Failed to retrieve order. HTTP code: ' . $code . '. Response: ' . $body;
            return new WP_Error('api_error', __($message, 'order-barcode'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }

    // New method to get product info by SKU
    public function get_products_by_skus($skus = array()) {
        if (empty($this->store_id) || empty($this->public_token)) {
            return new WP_Error('missing_credentials', __('Missing API credentials.', 'order-barcode'));
        }
        if (empty($skus) || !is_array($skus)) {
            return new WP_Error('invalid_skus', __('Invalid SKUs provided.', 'order-barcode'));
        }

        $products = array();
        foreach ($skus as $sku) {
            $sku_encoded = urlencode($sku);
            $url = "https://app.ecwid.com/api/v3/{$this->store_id}/products?token={$this->public_token}&sku={$sku_encoded}";
            $response = wp_remote_get($url, array('headers' => $this->get_headers()));

            if (is_wp_error($response)) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                $body = wp_remote_retrieve_body($response);
                $message = 'Failed to retrieve product for SKU ' . $sku . '. HTTP code: ' . $code . '. Response: ' . $body;
                return new WP_Error('api_error', __($message, 'order-barcode'));
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['items']) && count($data['items']) > 0) {
                $products[$sku] = $data['items'][0];
            }
        }

        return $products;
    }
}
?>
