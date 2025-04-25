<?php
if (!defined('ABSPATH')) {
    exit;
}

class OrderBarcode_Settings {
    private static $instance = null;
    private $encryption_key;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->encryption_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'default_key_change_me';
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_orderbarcode_wipe_cache', array($this, 'handle_wipe_cache'));
        add_action('admin_post_orderbarcode_save_numbers', array($this, 'handle_save_numbers'));
        add_action('admin_post_orderbarcode_import_numbers', array($this, 'handle_import_numbers'));
        add_action('admin_post_orderbarcode_export_numbers', array($this, 'handle_export_numbers'));
    }

    public function add_settings_page() {
        add_options_page(
            __('Order Barcode Settings', 'order-barcode'),
            __('Order Barcode', 'order-barcode'),
            'manage_options',
            'orderbarcode-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('orderbarcode_settings_group', 'orderbarcode_store_id', array($this, 'sanitize_store_id'));
        register_setting('orderbarcode_settings_group', 'orderbarcode_public_token', array($this, 'sanitize_public_token'));
        register_setting('orderbarcode_settings_group', 'orderbarcode_hidden_token', array($this, 'sanitize_hidden_token'));
        // Removed numbers registration as we handle saving manually
    }

    public function sanitize_store_id($input) {
        return sanitize_text_field($input);
    }

    public function sanitize_public_token($input) {
        return sanitize_text_field($input);
    }

    public function sanitize_hidden_token($input) {
        return $this->encrypt(sanitize_text_field($input));
    }

    private function encrypt($data) {
        $key = substr(hash('sha256', $this->encryption_key, true), 0, 32);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt($data) {
        $key = substr(hash('sha256', $this->encryption_key, true), 0, 32);
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public function get_decrypted_hidden_token() {
        $encrypted = get_option('orderbarcode_hidden_token', '');
        if (empty($encrypted)) {
            return '';
        }
        return $this->decrypt($encrypted);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $store_id = get_option('orderbarcode_store_id', '');
        $public_token = get_option('orderbarcode_public_token', '');
        $hidden_token_encrypted = get_option('orderbarcode_hidden_token', '');
        $numbers = get_option('orderbarcode_numbers', array());

        $numbers_saved = isset($_GET['numbers_saved']) && $_GET['numbers_saved'] == 1;
        $numbers_imported = isset($_GET['numbers_imported']) && $_GET['numbers_imported'] == 1;
        $numbers_exported = isset($_GET['numbers_exported']) && $_GET['numbers_exported'] == 1;
        $numbers_import_error = isset($_GET['numbers_import_error']) ? sanitize_text_field($_GET['numbers_import_error']) : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Order Barcode Settings', 'order-barcode'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="#keys" class="nav-tab nav-tab-active" id="tab-keys"><?php esc_html_e('Keys', 'order-barcode'); ?></a>
                <a href="#numbers" class="nav-tab" id="tab-numbers"><?php esc_html_e('Numbers', 'order-barcode'); ?></a>
            </h2>
            <form method="post" action="options.php" id="orderbarcode-settings-form">
                <?php
                settings_fields('orderbarcode_settings_group');
                do_settings_sections('orderbarcode_settings_group');
                ?>
                <div id="keys" class="tab-content" style="display:block;">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="orderbarcode_store_id"><?php esc_html_e('Store ID', 'order-barcode'); ?></label></th>
                            <td><input name="orderbarcode_store_id" type="text" id="orderbarcode_store_id" value="<?php echo esc_attr($store_id); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="orderbarcode_public_token"><?php esc_html_e('Public Token', 'order-barcode'); ?></label></th>
                            <td>
                                <input name="orderbarcode_public_token" type="text" id="orderbarcode_public_token" value="<?php echo esc_attr($public_token); ?>" class="regular-text">
                                <button type="button" class="button" id="show-public-token"><?php esc_html_e('Show', 'order-barcode'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="orderbarcode_hidden_token"><?php esc_html_e('Hidden Token', 'order-barcode'); ?></label></th>
                            <td>
                                <input name="orderbarcode_hidden_token" type="password" id="orderbarcode_hidden_token" value="<?php echo esc_attr($hidden_token_encrypted ? '********' : ''); ?>" class="regular-text">
                                <button type="button" class="button" id="show-hidden-token"><?php esc_html_e('Show', 'order-barcode'); ?></button>
                            </td>
                        </tr>
                    </table>
                    <button type="button" id="save-keys" class="button button-primary"><?php esc_html_e('Save Keys', 'order-barcode'); ?></button>
                    <button type="button" id="wipe-keys" class="button button-secondary"><?php esc_html_e('Wipe Keys', 'order-barcode'); ?></button>
                </div>
            </form>

            <div id="numbers" class="tab-content" style="display:none;">
                <?php if ($numbers_saved): ?>
                    <div id="save-feedback" style="color: green; margin-bottom: 10px;"><?php esc_html_e('Data saved successfully.', 'order-barcode'); ?></div>
                <?php endif; ?>
                <?php if ($numbers_imported): ?>
                    <div id="import-feedback" style="color: green; margin-bottom: 10px;"><?php esc_html_e('CSV imported successfully.', 'order-barcode'); ?></div>
                <?php endif; ?>
                <?php if ($numbers_exported): ?>
                    <div id="export-feedback" style="color: green; margin-bottom: 10px;"><?php esc_html_e('CSV exported successfully.', 'order-barcode'); ?></div>
                <?php endif; ?>
                <?php if ($numbers_import_error): ?>
                    <div id="import-error" style="color: red; margin-bottom: 10px;"><?php echo esc_html($numbers_import_error); ?></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="numbers-form">
                    <?php wp_nonce_field('orderbarcode_save_numbers_nonce'); ?>
                    <input type="hidden" name="action" value="orderbarcode_save_numbers">
                    <table class="wp-list-table widefat fixed striped" id="numbers-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('SKU', 'order-barcode'); ?></th>
                                <th><?php esc_html_e('EAN', 'order-barcode'); ?></th>
                                <th><?php esc_html_e('Number', 'order-barcode'); ?></th>
                                <th><?php esc_html_e('Actions', 'order-barcode'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="numbers-list">
                            <?php if (!empty($numbers)) : ?>
                                <?php foreach ($numbers as $index => $number) : ?>
                                    <tr>
                                        <td><input type="text" name="orderbarcode_numbers[<?php echo $index; ?>][sku]" value="<?php echo esc_attr($number['sku']); ?>" readonly></td>
                                        <td><input type="text" name="orderbarcode_numbers[<?php echo $index; ?>][ean]" value="<?php echo esc_attr($number['ean']); ?>" readonly></td>
                                        <td><input type="number" name="orderbarcode_numbers[<?php echo $index; ?>][number]" value="<?php echo esc_attr($number['number']); ?>" readonly></td>
                                        <td>
                                            <button type="button" class="button edit-row"><?php esc_html_e('Edit', 'order-barcode'); ?></button>
                                            <button type="button" class="button delete-row"><?php esc_html_e('Delete', 'order-barcode'); ?></button>
                                            <button type="button" class="button save-row" style="display:none;"><?php esc_html_e('Save', 'order-barcode'); ?></button>
                                            <button type="button" class="button cancel-row" style="display:none;"><?php esc_html_e('Cancel', 'order-barcode'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4"><?php esc_html_e('No entries found.', 'order-barcode'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button" id="add-row"><?php esc_html_e('Add Row', 'order-barcode'); ?></button>
                    <button type="submit" class="button button-primary" id="save-numbers"><?php esc_html_e('Save Changes', 'order-barcode'); ?></button>
                </form>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="import-csv-form" style="margin-top: 20px;">
                    <?php wp_nonce_field('orderbarcode_import_numbers_nonce'); ?>
                    <input type="hidden" name="action" value="orderbarcode_import_numbers">
                    <input type="file" name="import_csv" accept=".csv" required>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Import CSV', 'order-barcode'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="export-csv-form" style="margin-top: 10px;">
                    <?php wp_nonce_field('orderbarcode_export_numbers_nonce'); ?>
                    <input type="hidden" name="action" value="orderbarcode_export_numbers">
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Export CSV', 'order-barcode'); ?></button>
                </form>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wipe-keys-form">
                <input type="hidden" name="action" value="orderbarcode_wipe_cache">
                <?php wp_nonce_field('orderbarcode_wipe_cache_nonce'); ?>
            </form>

            <p><?php echo esc_html__('Plugin Version: ', 'order-barcode') . ORDERBARCODE_VERSION; ?></p>
        </div>

        <style>
            #numbers-table input[readonly] {
                border: none;
                background: transparent;
                color: #555;
            }
            #numbers-table input {
                width: 100%;
                box-sizing: border-box;
            }
            #numbers-table button {
                margin-right: 5px;
            }
            #save-feedback, #import-feedback, #export-feedback {
                font-weight: bold;
            }
            #import-error {
                font-weight: bold;
                color: red;
            }
        </style>

        <script>
            (function($) {
                function showTab(evt, tabName) {
                    evt.preventDefault();
                    $('.tab-content').hide();
                    $('#' + tabName).show();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(evt.currentTarget).addClass('nav-tab-active');
                }

                $('#tab-keys').on('click', function(e) {
                    showTab(e, 'keys');
                });
                $('#tab-numbers').on('click', function(e) {
                    showTab(e, 'numbers');
                });

                $('#save-keys').on('click', function() {
                    $('#orderbarcode-settings-form').submit();
                });

                $('#wipe-keys').on('click', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to wipe keys?', 'order-barcode')); ?>')) {
                        $('#wipe-keys-form').submit();
                    }
                });

                // Inline editing for numbers list
                $('#numbers-list').on('click', '.edit-row', function() {
                    var $row = $(this).closest('tr');
                    $row.find('input').prop('readonly', false).first().focus();
                    $row.find('.edit-row, .delete-row').hide();
                    $row.find('.save-row, .cancel-row').show();
                });

                $('#numbers-list').on('click', '.cancel-row', function() {
                    var $row = $(this).closest('tr');
                    $row.find('input').each(function() {
                        var originalVal = $(this).attr('value');
                        $(this).val(originalVal);
                    });
                    $row.find('input').prop('readonly', true);
                    $row.find('.edit-row, .delete-row').show();
                    $row.find('.save-row, .cancel-row').hide();
                });

                $('#numbers-list').on('click', '.save-row', function() {
                    var $row = $(this).closest('tr');
                    $row.find('input').prop('readonly', true);
                    $row.find('.edit-row, .delete-row').show();
                    $row.find('.save-row, .cancel-row').hide();
                });

                $('#numbers-list').on('click', '.delete-row', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to delete this row?', 'order-barcode')); ?>')) {
                        $(this).closest('tr').remove();
                    }
                });

                $('#add-row').on('click', function() {
                    var index = $('#numbers-list tr').length;
                    var newRow = '<tr>' +
                        '<td><input type="text" name="orderbarcode_numbers[' + index + '][sku]" value="" ></td>' +
                        '<td><input type="text" name="orderbarcode_numbers[' + index + '][ean]" value="" ></td>' +
                        '<td><input type="number" name="orderbarcode_numbers[' + index + '][number]" value="" ></td>' +
                        '<td>' +
                        '<button type="button" class="button edit-row" style="display:none;"><?php esc_html_e('Edit', 'order-barcode'); ?></button>' +
                        '<button type="button" class="button delete-row"><?php esc_html_e('Delete', 'order-barcode'); ?></button>' +
                        '<button type="button" class="button save-row"><?php esc_html_e('Save', 'order-barcode'); ?></button>' +
                        '<button type="button" class="button cancel-row"><?php esc_html_e('Cancel', 'order-barcode'); ?></button>' +
                        '</td>' +
                        '</tr>';
                    $('#numbers-list').append(newRow);
                });
            })(jQuery);
        </script>
        <?php
    }

    public function handle_save_numbers() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        check_admin_referer('orderbarcode_save_numbers_nonce');

        $numbers = isset($_POST['orderbarcode_numbers']) ? $_POST['orderbarcode_numbers'] : array();

        $sanitized_numbers = array();
        if (is_array($numbers)) {
            foreach ($numbers as $row) {
                $sku = isset($row['sku']) ? sanitize_text_field($row['sku']) : '';
                $ean = isset($row['ean']) ? sanitize_text_field($row['ean']) : '';
                $number = isset($row['number']) ? intval($row['number']) : 0;
                if ($sku !== '' || $ean !== '' || $number !== 0) {
                    $sanitized_numbers[] = array(
                        'sku' => $sku,
                        'ean' => $ean,
                        'number' => $number,
                    );
                }
            }
        }

        update_option('orderbarcode_numbers', $sanitized_numbers);

        wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_saved=1'));
        exit;
    }

    public function handle_import_numbers() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        check_admin_referer('orderbarcode_import_numbers_nonce');

        if (!isset($_FILES['import_csv']) || $_FILES['import_csv']['error'] !== UPLOAD_ERR_OK) {
            $error_message = __('Error uploading file.', 'order-barcode');
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_import_error=' . urlencode($error_message)));
            exit;
        }

        $file = $_FILES['import_csv']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            $error_message = __('Unable to open uploaded file.', 'order-barcode');
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_import_error=' . urlencode($error_message)));
            exit;
        }

        $imported_numbers = array();
        $header = fgetcsv($handle);
        if ($header === false || count($header) < 3) {
            fclose($handle);
            $error_message = __('Invalid CSV format. Expected columns: SKU, EAN, NUMBER.', 'order-barcode');
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_import_error=' . urlencode($error_message)));
            exit;
        }

        $expected_headers = array('SKU', 'EAN', 'NUMBER');
        $header = array_map('trim', $header);
        if (array_map('strtoupper', $header) !== $expected_headers) {
            fclose($handle);
            $error_message = __('CSV headers do not match expected format: SKU, EAN, NUMBER.', 'order-barcode');
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_import_error=' . urlencode($error_message)));
            exit;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }
            $sku = sanitize_text_field($row[0]);
            $ean = sanitize_text_field($row[1]);
            $number = intval($row[2]);
            if ($sku !== '' || $ean !== '' || $number !== 0) {
                $imported_numbers[] = array(
                    'sku' => $sku,
                    'ean' => $ean,
                    'number' => $number,
                );
            }
        }
        fclose($handle);

        if (!empty($imported_numbers)) {
            update_option('orderbarcode_numbers', $imported_numbers);
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_imported=1'));
            exit;
        } else {
            $error_message = __('No valid data found in CSV.', 'order-barcode');
            wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&numbers_import_error=' . urlencode($error_message)));
            exit;
        }
    }

    public function handle_export_numbers() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        check_admin_referer('orderbarcode_export_numbers_nonce');

        $numbers = get_option('orderbarcode_numbers', array());

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orderbarcode_numbers_export_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('SKU', 'EAN', 'NUMBER'));

        foreach ($numbers as $number) {
            fputcsv($output, array($number['sku'], $number['ean'], $number['number']));
        }

        fclose($output);
        exit;
    }

    public function handle_wipe_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }
        check_admin_referer('orderbarcode_wipe_cache_nonce');

        // Wipe stored keys and cache
        delete_option('orderbarcode_store_id');
        delete_option('orderbarcode_public_token');
        delete_option('orderbarcode_hidden_token');

        wp_redirect(admin_url('options-general.php?page=orderbarcode-settings&cache_wiped=1'));
        exit;
    }
}
?>
