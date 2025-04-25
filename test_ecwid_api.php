<?php
// Test script to verify Ecwid API credentials and fetch latest orders

require_once 'orderbarcode/includes/class-orderbarcode-api.php';
require_once 'orderbarcode/includes/class-orderbarcode-settings.php';

// Manually set credentials for testing
update_option('orderbarcode_store_id', '519170');
update_option('orderbarcode_public_token', 'public_G4AP5yesMRfQKyzieT9AQZbuhnFEXRD6');
update_option('orderbarcode_hidden_token', 'secret_JbKwWxW7LBheduPPJ8yGj5EwujNQmcWz');

$api = OrderBarcode_API::get_instance();

$result = $api->verify_credentials();
if (is_wp_error($result)) {
    echo "Credential verification failed: " . $result->get_error_message() . PHP_EOL;
    exit(1);
} else {
    echo "Credentials verified successfully." . PHP_EOL;
}

$orders = $api->get_latest_orders(5);
if (is_wp_error($orders)) {
    echo "Failed to fetch latest orders: " . $orders->get_error_message() . PHP_EOL;
    exit(1);
} else {
    echo "Latest orders fetched successfully:" . PHP_EOL;
    print_r($orders);
}

$order_id = 6641; // example order id to test
$order = $api->get_order($order_id);
if (is_wp_error($order)) {
    echo "Failed to fetch order {$order_id}: " . $order->get_error_message() . PHP_EOL;
    exit(1);
} else {
    echo "Order {$order_id} details:" . PHP_EOL;
    print_r($order);
}
?>
