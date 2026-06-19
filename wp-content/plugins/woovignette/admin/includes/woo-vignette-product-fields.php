<?php

namespace Woo_Vignette\admin\includes;
use Woo_Vignette\includes\core\Woo_Vignette_Base;

class Woo_Vignette_Product_Fields
{
    private $base;
    private $vignette_fields = [];
    public function __construct()
    {
        $this->base = Woo_Vignette_Base::instance(); 
        $this->vignette_fields = [
                [
                    'id'          => '_wv_product_type', 
                    'label'       => __( 'Product Type', 'woo-vignette' ), 
                    'type'        => 'select',
                    'options'     => [
                            ''          =>  __( 'Select', 'woo-vignette' ),
                            'vignette'  => __( 'Vignette', 'woo-vignette' ),
                            'toll'      => __( 'Toll/Tunnel', 'woo-vignette' ), 
                            'rapid-process'      => __( 'Rapid Processing', 'woo-vignette' ), 
                        ]
                ],
                [
                    'id'          => '_wv_country', 
                    'label'       => __( 'Country', 'woo-vignette' ), 
                    'desc_tip'    => 'true',
                    'type'        => 'select',  // Default type is text
                    'description' => __( 'Select an option for the advanced settings', 'woo-vignette' )
                ],
                [
                    'id'          => '_wv_product_reference', 
                    'label'       => __( 'Product Reference', 'woo-vignette' ), 
                    'type'        => 'text',  // Default type is text
                    'data_type'   => 'text',  // Text-based field
                ],
            ];
		add_action('woocommerce_product_after_variable_attributes', [$this,'add_additional_charges_to_variations'], 10, 3);
		add_action('woocommerce_save_product_variation', [$this,'save_additional_charges_variations'], 10, 2);
		//add_action( 'woocommerce_product_options_advanced', [$this,'add_advanced_fields'] );
		add_action( 'woocommerce_process_product_meta', [$this,'save_advanced_field'] );
		add_action('add_meta_boxes', [$this,'add_meta_box']);
        add_action('woocommerce_process_shop_order_meta', [$this,'save_uploaded_file_for_order_items'], 10, 2);
        add_action('admin_notices',[$this,'display_admin_notices']);
        add_action('wp_ajax_remove_uploaded_file', [$this, 'handle_remove_file_ajax']);
        add_action('woocommerce_hidden_order_itemmeta', [$this, 'hidden_order_itemmeta'],10,2);
        add_action( 'before_delete_post', [$this,'delete_product'],10,2 );
        add_action( 'save_post', [$this,'save_product'],10,3 );
    }
    
    public function delete_product( $post_id, $post ){
        $this->deleteTransientData();
    }
    public function save_product( $post_id, $post, $update ){
        $this->deleteTransientData();
    }
    
    private function deleteTransientData(){
        if (function_exists("icl_get_languages")) {
            $languages = apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );
            if( !empty( $languages ) ){
                $languages['en'] = []; 
            }
            foreach( $languages as $lang => $data ){
                $product_key = 'wv_products_'.$lang;
                $attribute_key = 'wv_attributes_'.$lang;
                $product_type_key = 'wv_product_type_'.$lang;
                $product_name_key = 'wv_product_name_'.$lang;
                delete_transient($product_key);
                delete_transient($attribute_key);
                delete_transient($product_type_key);
                delete_transient($product_name_key);
            }
        }
    }
    
    public function hidden_order_itemmeta( $hidden_meta ){
        $hidden_meta[] = 'period_type';
        $hidden_meta[] = 'product_country';
        $hidden_meta[] = 'vignette_name';
        //echo "<pre>";print_r($hidden_meta);die;
        return $hidden_meta;
    }
    
    public function handle_remove_file_ajax() {
        if ( ! isset($_POST['item_id']) || ! isset($_POST['post_id']) ) {
            wp_send_json_error([
                'message' => __('Invalid request.', 'woocommerce')
            ]);
        }
    
        $item_id = intval($_POST['item_id']);
        $post_id = intval($_POST['post_id']);
    
        // Verify if the user has permission to edit the order
        if (!current_user_can('edit_shop_order', $post_id)) {
            wp_send_json_error([
                'message' => __('You do not have permission to remove the file.', 'woocommerce')
            ]);
        }
    
        // Get the file path and URL from order item meta
        $file_path = get_post_meta($item_id, '_uploaded_file_path', true);
    
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error([
                'message' => __('File not found.', 'woocommerce')
            ]);
        }
    
        // Delete the file
        if (unlink($file_path)) {
            // Remove file metadata from the order item
            delete_post_meta($item_id, '_uploaded_file');
            delete_post_meta($item_id, '_uploaded_file_path');
    
            wp_send_json_success([
                'message' => __('File removed successfully.', 'woocommerce')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Error deleting the file.', 'woocommerce')
            ]);
        }
    }
    
    public function set_admin_notice($message, $type = 'error', $expiration = 240) {
        set_transient('vignette_admin_notice', [
        'message' => $message,
        'type'    => $type
        ], $expiration);
    }
    
    public function display_admin_notices() {
        $notice = get_transient('vignette_admin_notice');

        if ($notice) {
            // Display the notice
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">
                    <p>' . esc_html($notice['message']) . '</p>
                  </div>';
    
            // Optionally delete the transient after displaying
            delete_transient('vignette_admin_notice');
        }
    }
   
    public function add_meta_box() {
            // Add the meta box to the order edit page
            add_meta_box(
                'file_upload_postbox',                      // Unique ID for the postbox
                __('File Uploads for Order Items', 'woo-vignette'), // Title of the postbox
                [$this, 'render_file_upload_postbox'],       // Callback function to render content
                wc_get_page_screen_id( 'shop-order' ),                                // Post type: shop_order (WooCommerce orders)
                'normal',                                    // Display in the normal context (main content area)
                'core'                                       // Priority: high (appears near the top)
            );
            
            add_meta_box(
                'wv_product_postbox',                      // Unique ID for the postbox
                __('Vignette Fields', 'woo-vignette'), // Title of the postbox
                [$this, 'add_advanced_fields'],       // Callback function to render content
                'product',                                // Post type: shop_order (WooCommerce orders)
                'side',                                    // Display in the normal context (main content area)
                'high'                                       // Priority: high (appears near the top)
            );
    }
    
    public function render_file_upload_postbox($post) {
        $order = wc_get_order($post->ID); // Get the WooCommerce order object
    
        // Display the file upload form inside the postbox
        echo '<div class="file-upload-box">';
    
        // Loop through each order item
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_type = get_post_meta( $product->get_id(), '_wv_product_type', true );
            if( $product_type == "rapid-process" ){
                continue;
            }
            $validities = $item->get_meta('validity');
            $vignette_name = $item->get_meta('vignette_name');
            $product_name = $vignette_name . " ".$product->get_name();
            echo '<h4>' . esc_html($product_name) . '</h4>';
            foreach( $validities as $validity ){
                $file_id = $item_id.(str_replace('-','',$validity['start']));
                $file_url = get_post_meta($file_id, '_uploaded_file', true); // Retrieve file URL if it exists
                $label = ( date("d-m-Y",strtotime( $validity['start'])) . " to " . date("d-m-Y",strtotime( $validity['end'] ) ) );
            ?>
            <?php
            if ($file_url) {?>
                <div class="vignette-upload-file">
                    <strong><?= $label?></strong>
                <a href="<?= esc_url($file_url) ?>" target="_blank" class="button">
                    <?=  __('View', 'woo-vignette')?>
                    </a>
                <button type="button" class="remove-file-btn button" data-item-id="<?php echo $file_id; ?>">
                    <?= __('Delete', 'woo-vignette')?>
                </button>
                </div>
            <?php }else{?>
            <div class="vignette-upload-file">
                <strong><?= $label?></strong>
                <input type="file" name="order_item_file[<?php echo $file_id; ?>]" />
            </div>
            <?php }
            }
        }
        echo '</div>';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
            // Make sure the enctype is set for the form in the admin order page
            $('form#order').attr('enctype', 'multipart/form-data');
            // Handle remove file via AJAX
            $('.remove-file-btn').on('click', function() {
                var item_id = $(this).data('item-id');
                var data = {
                    action: 'remove_uploaded_file', // AJAX action
                    item_id: item_id,
                    post_id: <?php echo $post->ID; ?> // Pass the order ID to the AJAX request
                };

                // Send the AJAX request
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message); // Show success message
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert(response.data.message); // Show error message
                    }
                });
            });
        });
        </script>
        <?php 
    }
    
    public function save_uploaded_file_for_order_items($order_id, $order) {
        // Ensure WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return; // Exit if WooCommerce is not active
        }

        // Check if there are uploaded files
        if (isset($_FILES['order_item_file']) && !empty($_FILES['order_item_file']['name'])) {
            
            // Loop through each file uploaded
            $attachments = [];
            foreach ($_FILES['order_item_file']['name'] as $item_id => $file_name) {
                // Check if a file was actually uploaded for this item
                if (!empty($file_name) && $_FILES['order_item_file']['error'][$item_id] === 0) {
                    // Retrieve the file details
                    $file = [
                        'name'     => $file_name,
                        'type'     => $_FILES['order_item_file']['type'][$item_id],
                        'tmp_name' => $_FILES['order_item_file']['tmp_name'][$item_id],
                        'error'    => $_FILES['order_item_file']['error'][$item_id],
                        'size'     => $_FILES['order_item_file']['size'][$item_id]
                    ];

                    // Validate file type (only allow PDFs for now)
                    $allowed_mimes = ['pdf' => 'application/pdf'];
                    $file_type = wp_check_filetype($file['name']);
                    if (!in_array($file_type['type'], $allowed_mimes)) {
                        // Display an error message if the file type is not allowed
                        $this->set_admin_notice(__('Invalid file type. Only PDF files are allowed.', 'woo-vignette'), 'error');
                        return;
                    }

                    // Check file size (max 10MB)
                    if ($file['size'] > 10485760) { // 10MB
                        $this->set_admin_notice(__('The file is too large. Maximum allowed size is 10MB.', 'woo-vignette'), 'error');
                        return;
                    }

                    // Set the upload directory: wp-content/uploads/vignette
                    $upload_dir = wp_upload_dir(); // Get WordPress uploads directory
                    $vignette_dir = $upload_dir['basedir'] . '/vignette-files'; // Custom directory for uploaded files

                    // Ensure the vignette directory exists
                    if (!file_exists($vignette_dir)) {
                        wp_mkdir_p($vignette_dir);
                    }

                    // Generate a unique file name to avoid collisions
                    $file_name_unique = wp_unique_filename($vignette_dir, $file['name']);
                    $file_path = $vignette_dir . '/' . $file_name_unique;
                    
                    // Move the uploaded file to the custom directory
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Get the file URL
                        $file_url = $upload_dir['baseurl'] . '/vignette-files/' . $file_name_unique;
                        $attachments[] = $file_path;
                        // Save the file URL in the order item meta
                        update_post_meta($item_id, '_uploaded_file', $file_url);
                        update_post_meta($item_id, '_uploaded_file_path', $file_path);
                        
                        // Optionally, add a success message
                        $this->set_admin_notice(__('File uploaded successfully.', 'woo-vignette'), 'success');
                    } else {
                        $this->set_admin_notice(__('There was an error uploading the file. Please try again.', 'woo-vignette'), 'error');
                        return;
                    }
                } 
            }
            do_action( 'woo_vignette_invoice_uploaded', $order_id, $order, $attachments );
        }
    }

private function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return __('The uploaded file exceeds the maximum file size.', 'woocommerce');
        case UPLOAD_ERR_PARTIAL:
            return __('The file was only partially uploaded.', 'woocommerce');
        case UPLOAD_ERR_NO_FILE:
            return __('No file was uploaded.', 'woocommerce');
        case UPLOAD_ERR_NO_TMP_DIR:
            return __('Missing a temporary folder for file uploads.', 'woocommerce');
        case UPLOAD_ERR_CANT_WRITE:
            return __('Failed to write file to disk.', 'woocommerce');
        case UPLOAD_ERR_EXTENSION:
            return __('A PHP extension stopped the file upload.', 'woocommerce');
        default:
            return __('Unknown upload error occurred.', 'woocommerce');
    }
}
// Helper function to add notices in the admin area
public function add_admin_notice($message, $type = 'error') {
    // Ensure WooCommerce is loaded and we're in the admin context
        add_action('admin_notices', function() use ($message, $type) {
            // Output notice in admin
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
}

    // Save the dropdown field value
    public function save_advanced_field( $post_id ) {
        foreach( $this->vignette_fields as $field ){
            if ( isset( $_POST[$field['id']] ) ) {
                update_post_meta( $post_id, $field['id'], sanitize_text_field( $_POST[$field['id']] ) );
            }
        }
    }

    public function add_advanced_fields() {
        global $post;
        
       
        echo '<div class="options_group">';
        foreach( $this->vignette_fields as $field ){
            $value = get_post_meta( $post->ID, $field['id'], true );
            $field['value'] = $value;
            if( $field['id'] == '_wv_country' ){
                $options[''] = 'Select Country';
                $countries = $this->base->get_wv_countries();
                $options = array_merge($options,$countries);
                $field['options'] = $options;
            }
            $this->create_field($field);
        }
        echo '</div>';
    }

    public function add_additional_charges_to_variations($loop, $variation_data, $variation)
    { 
        // Get all available tax classes
        $tax_classes = \WC_Tax::get_tax_classes();
        $tax_classes[] = 'standard'; // Make sure the standard class is included
        $taxable = get_post_meta($variation->ID, '_is_taxable', true);
        $tax = get_post_meta($variation->ID, '_tax', true);
    ?>
        <div class="form-row form-row-full">
            <label for="processing_fee_<?= $loop; ?>">Processing Fee</label>
            <input type="number" name="processing_fee[<?= $loop; ?>]" id="processing_fee_<?= $loop; ?>" value="<?= get_post_meta($variation->ID, '_processing_fee', true); ?>">
        </div>
        <br>
        <div class="form-row form-row-full">
            <label for="is_taxable_<?= $loop; ?>">Is Taxable</label>
            <select name="is_taxable[<?= $loop; ?>]" id="is_taxable_<?= $loop; ?>">
                <option value="yes" <?= selected($taxable, 'yes', false); ?>>Yes</option>
                <option value="no" <?= selected($taxable, 'no', false); ?>>No</option>
            </select>
        </div>
        <br>
        <div class="form-row form-row-full">
            <label for="tax_<?= $loop; ?>">Tax</label>
            <select name="tax[<?= $loop; ?>]" id="tax_<?= $loop; ?>">
                <?php foreach ($tax_classes as $tax_class) :
                    // Get the human-readable name for each tax class
                    $tax_class_name = ucfirst(str_replace('_', ' ', $tax_class));
                    $tax_class_slug = $tax_class == 'standard'?'':strtolower(str_replace(' ', '-', $tax_class_name));
                    ?>
                    <option value="<?= $tax_class_slug; ?>" <?= selected($tax, $tax_class_slug, false); ?>><?= esc_html($tax_class_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
<?php
    }

    public function save_additional_charges_variations($variation_id, $i)
    {
        if (isset($_POST['processing_fee'][$i])) {
            update_post_meta($variation_id, '_processing_fee', sanitize_text_field($_POST['processing_fee'][$i]));
        }
        if (isset($_POST['is_taxable'][$i])) {
            update_post_meta($variation_id, '_is_taxable', sanitize_text_field($_POST['is_taxable'][$i]));
        }
        if (isset($_POST['tax'][$i])) {
            update_post_meta($variation_id, '_tax', sanitize_text_field($_POST['tax'][$i]));
        }
    }


    // Then, you can use the __() function later, during runtime, like in methods:
    public function get_attribute_label($attribute_key)
    {
        return __($this->attributes[$attribute_key]['label'], 'woo-vignette');
    }

    public function get_field_label($attribute_key, $field_id)
    {
        return __($this->attributes[$attribute_key]['fields'][$field_id]['label'], 'woo-vignette');
    }
    
    private function create_field($field, $label = true)
    {
        // Default value is an empty string if not provided
        $value = $field['value'] ?? '';
        $label = $label ? $field['label'] : '';
        switch ($field['type']) {
            case 'number':
                // Generate a number input field
                woocommerce_wp_text_input([
                    'id'          => $field['id'],
                    'label'       => $label,
                    'type'        => 'number',   // Specific field type
                    'data_type'   => 'number',   // Ensures proper data handling
                    'value'       => $value,     // Set the field value if available
                ]);
                break;

            case 'select':
                // Generate a select dropdown field
                woocommerce_wp_select([
                    'id'          => $field['id'],
                    'label'       => $label,
                    'options'     => $field['options'],  // Array of options for the select dropdown
                    'value'       => $value,              // Set the field value if available
                ]);
                break;

            case 'image':
                // Generate an image input field with a preview
            ?>
                <input type="hidden" id="<?= esc_attr($field['id']) ?>"
                    name="<?= esc_attr($field['id']) ?>"
                    value="<?= esc_attr($value) ?>"
                    class="regular-text">
                <button type="button" class="button upload_image_button" data-target="<?= esc_attr($field['id']) ?>">
                    Upload Image
                </button>
                <?php if (!empty($value)) : ?>
                    <div class="image-preview" style="margin-top: 10px;">
                        <img src="<?= esc_url($value) ?>" alt="Preview" style="max-width: 100px; max-height: 100px;">
                    </div>
                <?php endif; ?>
                <br>
                <br>
        <?php
                break;
            default:
                // Default to a text input field
                woocommerce_wp_text_input([
                    'id'          => $field['id'],
                    'label'       => $label,
                    'type'        => 'text',  // Default type is text
                    'data_type'   => 'text',  // Text-based field
                    'value'       => $value,  // Set the field value if available
                ]);
                break;
        }
    }


    public function woocommerce_not_active_notice()
    {
        echo '<div class="notice notice-error is-dismissible">
            <p><strong>' . esc_html__('WooCommerce is not active!', 'woo-vignette') . '</strong></p>
            <p>' . esc_html__('This plugin requires WooCommerce to be installed and activated.', 'woo-vignette') . '</p>
        </div>';
    }
    
    
}
