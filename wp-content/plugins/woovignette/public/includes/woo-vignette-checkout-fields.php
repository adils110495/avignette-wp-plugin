<?php

namespace Woo_Vignette\admin\includes;

class Woo_Vignette_Checkout_Fields
{
    private $attributes;
    public function __construct()
    {
        add_action('woocommerce_before_checkout_billing_form', [$this, 'add_checkout_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_fields']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_checkout_fields_in_admin']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_checkout_fields_in_admin'], 10, 2);
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_not_active_notice']);
        }

        $countries = require WOO_VIGNETTE_PATH.'european-countries.php';
        $this->attributes = [
            'country_registration' => [
                'type'      => 'select',
                'id'        => 'country_registration',
                'label'     => 'Country of Registration',
                'options'   => $countries,
                'country'   => Woo_Vignette_Settings::get_active_countries(),
                'required'  => true
            ],
            'license_plate' => [
                'type'      => 'input',
                'id'        => 'license_plate',
                'label'     => 'License Plate',
                'country'   => Woo_Vignette_Settings::get_active_countries(),
                'required'  => true
            ],
            'german_sticker_type' =>[
                'type' => 'select',
                'id' => 'german_sticker_type',
                'label' => 'German Sticker', 
                'options' => [
                    'green'   => 'Green',    
                    'yellow'  => 'Yellow',
                    'red'     => 'Red',
                ],
                'country'   => ['de'],
                'required'  => true
            ],
            'france_sticker_type' =>[
                'type' => 'select',
                'id' => 'france_sticker_type',
                'label' => 'France Sticker', 
                'options' => [
                    'green'     => 'Green',    
                    'purple'    => 'Purple',    
                    'yellow'    => 'Yellow',
                    'orange'    => 'Orange',
                    'burgundy'  => 'Burgundy',
                    'grey'      => 'Grey',
                ],
                'country'   => ['fr'],
                'required'  => true
            ],
            'vin' => [
                'type'      => 'input',
                'id'        => 'vin',
                'label'     => 'VIN',
                'country'   => ['fr', 'de'],
                'required'  => true
            ],
            'brand' => [
                'type'      => 'input',
                'id'        => 'brand',
                'label'     => 'Brand',
                'country'   => ['fr'],
                'required'  => true
            ],
            'fuel' => [
                'type'      => 'input',
                'id'        => 'fuel',
                'label'     => 'Fuel',
                'country'   => ['fr'],
                'required'  => true
            ],
            'registration_date' => [
                'type'      => 'date',
                'id'        => 'registration_date',
                'label'     => 'Date of first Registration',
                'country'   => Woo_Vignette_Settings::get_active_countries(),
                'required'  => true
            ],
            'vehicle_scan_registration_document' => [
                'type'      => 'file',
                'id'        => 'vehicle_scan_registration_document',
                'label'     => 'Vehicle Registration Document',
                'country'   => ['fr','de'],
                'required'  => false
            ],
            'document_url' => [
                'type'      => 'hidden',
                'id'        => 'document_url',
                'label'     => 'Vehicle Registration Document',
                'country'   => ['fr','de'],
                'required'  => true
            ]
        ];
    }
    // Handle the file upload via AJAX
    public function checkout_file_upload() {
        // Check if a file was uploaded
        try{
                if (isset($_FILES['vehicle_scan_registration_document']) && !empty($_FILES['vehicle_scan_registration_document']['name'])) {
                $uploaded_file = $_FILES['vehicle_scan_registration_document'];
        
                // Handle file upload errors
                if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    wp_send_json_error(array('message' => 'Upload failed with error code ' . $uploaded_file['error']));
                }
        
                // Validate the file (optional)
                $allowed_types = array('image/jpeg', 'image/png', 'application/pdf');
                if (!in_array($uploaded_file['type'], $allowed_types)) {
                    wp_send_json_error(array('message' => 'Invalid file type. Allowed types are JPEG, PNG, PDF.'));
                }
        
                // Generate a unique file name to avoid overwriting
                $upload_dir = wp_upload_dir();
                $file_name = basename($uploaded_file['name']);
                $target_path = $upload_dir['path'] . '/' . $file_name;
        
                // Move the file to the WordPress uploads folder
                if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                    // Send the file URL back as a JSON response
                    wp_send_json_success(array(
                        'file_url' => $upload_dir['url'] . '/' . $file_name
                    ));
                } else {
                    wp_send_json_error(array('message' => 'Failed to move uploaded file.'));
                }
            } else {
                wp_send_json_error(array('message' => 'No file uploaded.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die();
    }

    public function add_checkout_fields($checkout)
    {
        $session_countries = WC()->session->get('countries') ?? [];
        echo '<div id="custom_checkout_fields"><h3>' . __('Vehicle Information', 'woo-vignette') . '</h3>';
        foreach ($this->attributes as $key => $field) {
            $field['value'] = $checkout->get_value($key) ?? '';
        }
        $this->create_field($this->attributes, $session_countries);
        echo '</div>';
    }

    public function validate_checkout_fields()
    {
        $session_countries = WC()->session->get('countries') ?? [];
        //echo "<pre>";print_r($_POST);
        foreach ($this->attributes as $key => $field) {
            // Get the submitted value
            $value = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
            //echo "<pre>";print_r($value);die;
            if (isset($field['country']) && !empty($field['country'])) {
                // Check if there is any intersection between session countries and allowed countries
                if (empty(array_intersect($session_countries, $field['country']))) {
                    continue; // Skip validation for this field
                }
            }
            // Check for required fields
            if (isset($field['required']) && $field['required'] && empty($value)) {
                wc_add_notice(
                    sprintf(__('Please enter a value for the %s field.', 'woo-vignette'), esc_html($field['label'])),
                    'error'
                );
                continue; // Skip further checks for this field
            }

            // Custom validation rules
            switch ($key) {
                case 'vin':
                    if (!empty($value) && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $value)) {
                        wc_add_notice(
                            __('Please enter a valid VIN (17 characters long, alphanumeric, excluding I, O, and Q).', 'woo-vignette'),
                            'error'
                        );
                    }
                    break;

                case 'registration_date':
                    if (!empty($value) && !strtotime($value)) {
                        wc_add_notice(
                            __('Please enter a valid date for the Registration Date field.', 'woo-vignette'),
                            'error'
                        );
                    }
                    break;

                case 'vehicle_scan_registration_document':
                    if (!empty($_FILES[$key]['name'])) {
                        $file_type = wp_check_filetype($_FILES[$key]['name']);
                        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                        if (!in_array($file_type['type'], $allowed_types, true)) {
                            wc_add_notice(
                                __('Please upload a valid file (JPEG, PNG, or PDF).', 'woo-vignette'),
                                'error'
                            );
                        }
                    }
                    break;
                    // Add other cases as needed for specific field types
            }
        }
        //die();
    }

    public function save_checkout_fields($order_id)
    {
        $order = wc_get_order($order_id);
        if ( ! $order ) {
            return;
        }

        $order_date    = WC()->session->get('order_date');
        $cart_countries = WC()->session->get('countries');

        if ( ! empty($order_date) ) {
            $order->update_meta_data( 'start_date', date( 'Y-m-d', strtotime( $order_date['start_date'] ) ) );
            $order->update_meta_data( 'end_date',   date( 'Y-m-d', strtotime( $order_date['end_date'] ) ) );
        }
        if ( ! empty($cart_countries) ) {
            $order->update_meta_data( 'countries', $cart_countries );
        }

        foreach ( $this->attributes as $key => $field ) {
            if ( isset( $_POST[$key] ) ) {
                $order->update_meta_data( $key, sanitize_text_field( $_POST[$key] ) );
            }
        }

        $order->save();
    }

    // Hook to display editable fields in the admin order page
    public function display_checkout_fields_in_admin($order)
    {
        foreach ($this->attributes as $key => $field) {
            $value = get_post_meta($order->get_id(), $key, true);
            if (!empty($value)) {
                echo '<p><strong>' . esc_html($field['label']) . ':</strong> ';
                switch ($field['type']) {
                    case 'date':
                        echo '<input type="date" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                        break;

                    case 'file':
                        if ($value) {
                            echo '<a href="' . esc_url($value) . '" target="_blank">' . __('View Document') . '</a><br>';
                        }
                        echo '<input type="file" name="' . esc_attr($key) . '" />';
                        break;

                    case 'hidden':
                        if ($value) {
                            echo '<a href="' . esc_url($value) . '" target="_blank">' . __('View') . '</a><br>';
                        }
                        //echo '<input type="file" name="' . esc_attr($key) . '" />';
                        break;

                    case 'select':
                        if (!empty($field['options'])) {
                            echo '<select name="' . esc_attr($key) . '">';
                            foreach ($field['options'] as $option_value => $option_label) {
                                echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>';
                                echo esc_html($option_label);
                                echo '</option>';
                            }
                            echo '</select>';
                        }
                        break;

                    default:
                        echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                        break;
                }
                echo '</p>';
            }
        }
    }

    public function save_checkout_fields_in_admin($post_id)
    {
        $post_type = get_post_type($post_id);
        if ($post_type !== 'shop_order_placehold') {
            return;
        }
        foreach ($this->attributes as $key => $field) {
            // Handle file upload
            if (isset($_FILES[$key])) {
                $uploaded_file = $_FILES[$key];
                if ($uploaded_file['size'] > 0) {
                    $upload = wp_upload_bits($uploaded_file['name'], null, file_get_contents($uploaded_file['tmp_name']));
                    if ($upload['error'] === false) {
                        update_post_meta($post_id, $key, $upload['url']);
                    }
                }
            }
            if (isset($_POST[$key])) {
                $value = sanitize_text_field($_POST[$key]);
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    private function create_field($fields, $session_countries)
    {
        $filtered_fields = [];
        foreach ($fields as $key => $field) {
            if (!isset($field['country']) || empty($field['country'])) {
                $filtered_fields[$key] = $field;
            } elseif (!empty(array_intersect($session_countries, $field['country']))) {
                $filtered_fields[$key] = $field;
            }
        }

        foreach ($filtered_fields as $field) {
            $field_id = $field['id'] ?? '';
            $label = $field['label'] ?? '';
            $value = $field['value'] ?? '';
            $options = $field['options'] ?? [];
            $required = isset($field['required']) && $field['required'] === true ? 'required' : '';
            $class = implode(' ', $field['class'] ?? ['form-field']);

            echo '<div class="' . esc_attr($class) . '">';

            if (!empty($label)) {
                echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
            }

            switch ($field['type']) {
                case 'text':
                case 'number':
                case 'hidden':
                    echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '"
                          name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" class="input-text" ' . $required . ' />';
                    break;

                case 'date':
                    echo '<input type="text" id="' . esc_attr($field_id) . '"
                          name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '"
                          class="input-text wv-checkout-datepicker" placeholder="dd.mm.yyyy" ' . $required . ' />';
                    break;

                case 'select':
                    echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" class="select" ' . $required . '>';
                    foreach ($options as $key => $option_label) {
                        echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>';
                        echo esc_html($option_label);
                        echo '</option>';
                    }
                    echo '</select>';
                    break;

                case 'textarea':
                    echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" class="textarea" ' . $required . '>';
                    echo esc_html($value);
                    echo '</textarea>';
                    break;

                case 'file':
                    echo '<input type="file" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" 
                          accept="' . esc_attr($field['accept'] ?? '*') . '" ' . $required . '>';
                    if (!empty($value)) {
                        echo '<p>Current file: <a href="' . esc_url($value) . '" target="_blank">' . esc_html(basename($value)) . '</a></p>';
                    }
                    break;

                default:
                    echo '<input type="text" id="' . esc_attr($field_id) . '" 
                          name="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" ' . $required . '/>';
                    break;
            }

            echo '</div>';
        }
    }
    public static function instance(): Woo_Vignette_Checkout_Fields
    {
        static $instance;

        if (null === $instance) {
            $instance = new self();
        }

        return $instance;
    }
}
