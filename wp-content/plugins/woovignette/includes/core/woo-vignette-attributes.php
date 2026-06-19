<?php

namespace Woo_Vignette\includes\core;

class Woo_Vignette_Attributes
{
    // Define attributes without dynamic values in the array (no function calls here)
    private $attributes = [
        'e-vignette' => [
            'label' => 'E-Vignette',
            'fields' => [
                [
                    'type' => 'select',
                    'id' => 'wv_period_type',
                    'label' => 'Period Type', 
                    'options' => [
                        'day'           => 'Day',    
                        'week'          => 'Week',
                        'month'         => 'Month',
                        'year'          => 'Year',
                        'indefinite'    => 'Indefinite'
                    ]
                ],
                [
                    'type' => 'number',
                    'id' => 'wv_time_period',
                    'label' => 'Time Period'  
                ]
            ]
        ],
        'trip' => [
            'label' => 'Trip',
            'fields' => [
                [
                    'type' => 'select',
                    'id' => 'wv_trip',
                    'label' => 'Trip Type', 
                    'options' => [
                        'single'       => 'Signle',    
                        'double'      => 'Double',
                        'multiple'     => 'Multiple',
                    ]
                ]
            ]
        ]
    ];
    private $attribute_fields = [
            'wv_attribute_description'=>[
                'type' => 'textarea',
                'id' => 'wv_attribute_description',
                'label' => 'Description' 
            ]
        ];
    private $terms = [
        'wv_image' => [
            'type' => 'image',
            'id' => 'wv_image',
            'label' => 'Icon'
        ],
        'wv_svg' => [
            'type' => 'textarea',
            'id' => 'wv_svg',
            'label' => 'Icon Svg'
        ],
        
    ];

    // Then, you can use the __() function later, during runtime, like in methods:
    public function get_attribute_label($attribute_key)
    {
        return __($this->attributes[$attribute_key]['label'], 'woo-vignette');
    }

    public function get_field_label($attribute_key, $field_id)
    {
        return __($this->attributes[$attribute_key]['fields'][$field_id]['label'], 'woo-vignette');
    }


    public function __construct()
    {
        add_action('admin_init', [$this, 'register_attributes'], 20);
        $taxonomies = wc_get_attribute_taxonomy_names();
        foreach ($taxonomies as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [$this, 'attribute_add_fields'], 10, 1);
            add_action('edited_' . $taxonomy, [$this, 'save_attribute_fields']);
            add_action('create_' . $taxonomy, [$this, 'save_attribute_fields']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'attribute_edit_fields']);
        }
        //add_action('woocommerce_after_add_attribute_fields', [$this,'add_field_to_attribute']);
        /*add_action('woocommerce_after_edit_attribute_fields', [$this,'edit_field_for_attribute'], 10, 2);
        add_action('woocommerce_attribute_added', [$this,'save_field_for_attribute'], 10, 2);
        add_action('woocommerce_attribute_updated', [$this,'save_field_for_attribute'], 10, 2);*/
    }
    
    /*public function add_field_to_attribute() {
        foreach( $this->attribute_fields as $field ){
            $this->create_field($field);
        }
    }
    
    public function edit_field_for_attribute() {
        foreach( $this->attribute_fields as $field ){
            $id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
            $value = $id ? get_option( "woocommerce_attribute_".$field['id']."_$id" ) : '';
            $field['value'] = $value;
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="<?= $field['id']?>"><?= $field['label']?></label>
            </th>
            <td>
                <?php $this->create_field($field,false);?>
            </td>
        </tr>
        <?php }
    }
    
    function save_field_for_attribute($attribute_id, $attribute) {
        foreach( $this->attribute_fields as $field ){
            if (isset($_POST[$field['id']])) {
                update_option("woocommerce_attribute_".$field['id']."_$attribute_id", sanitize_text_field($_POST[$field['id']]));
            }
        }
    }*/
    
    public function register_attributes()
    {
        if (!class_exists('WooCommerce')) {
            // Add an admin notice if WooCommerce is not active
            add_action('admin_notices', [$this, 'woocommerce_not_active_notice']);
            return; // Exit if WooCommerce is not active
        }

        foreach ($this->attributes as $attribute_slug => $attribute) {
            // Set the attribute name and slug
            $attribute_name = $attribute['label']; // Name of the attribute
            $taxonomy = 'pa_' . $attribute_slug;  // WooCommerce attribute taxonomy (pa_ is required)

            // Get the slugs of all existing attribute taxonomies
            $existing_slugs = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_name');

            // Check if the attribute slug already exists
            if (!in_array($attribute_slug, $existing_slugs)) {
                // Register the attribute taxonomy
                $args = [
                    'slug'        => $attribute_slug,
                    'name'        => $attribute_name,
                    'type'        => 'select',  // This could be 'select' or 'text', depending on the attribute type
                    'order_by'    => 'menu_order',
                    'has_archives' => false,  // Set to true if you want archive pages for this attribute
                ];

                // Insert the attribute taxonomy in WooCommerce
                wc_create_attribute($args);

                // Clear WooCommerce attribute taxonomies cache
                if (function_exists('WC') && WC()->attribute_taxonomies) {
                    WC()->attribute_taxonomies->clear_cache();
                }
            }

            // Hook for adding fields to the new term form
            add_action($taxonomy . '_add_form_fields', [$this, 'add_field_to_new_term_screen'], 10, 2);
            add_action('edited_' . $taxonomy, [$this, 'save_field_for_attribute_terms']);
            add_action('create_' . $taxonomy, [$this, 'save_field_for_attribute_terms']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'add_field_to_attribute_terms']);
        }
    }

    public function attribute_add_fields()
    {
        foreach ($this->terms as $term) {
            $this->create_field($term);
        }
    }

    public function attribute_edit_fields($term)
    {
        foreach ($this->terms as $field) {
            $field_value = get_term_meta($term->term_id, $field['id'], true);
            $field['value'] = $field_value; ?>
            <tr class="form-field">
                <th scope="row" valign="top">
                    <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                </th>
                <td>
                    <?php $this->create_field($field, false); ?>
                </td>
            </tr>
            <?php }
    }

    private function sanitize_svg_field($svg_content) {
        if( stripos($svg_content, '<svg') === false ){
            return sanitize_text_field($svg_content);
        }
        $svg_content = preg_replace('/<svg([^>]*?)\s(width|height)="[^"]*"/i', '<svg$1', $svg_content);
        return $svg_content;
    }


    public function save_attribute_fields($term_id)
    {
        //echo "<pre>";print_r($_POST[$field['id']]);die;
        foreach ($this->terms as $field) {
            if (isset($_POST[$field['id']])) {
                $field_value = $this->sanitize_svg_field($_POST[$field['id']]);
                update_term_meta($term_id, $field['id'], $field_value);
            }
        }
    }

    public function save_field_for_attribute_terms($term_id)
    {
        foreach ($this->attributes as $attribute) {
            if (isset($attribute['fields'])) {
                foreach ($attribute['fields'] as $field) {
                    if (isset($_POST[$field['id']])) {
                        $field_value = sanitize_text_field($_POST[$field['id']]);
                        update_term_meta($term_id, $field['id'], $field_value);
                    }
                }
            }
        }
    }

    function add_field_to_attribute_terms($term)
    {
        $attribute = str_replace('pa_', '', $term->taxonomy);
        if (isset($this->attributes[$attribute])) {
            foreach ($this->attributes[$attribute]['fields'] as $field) {
                $field_value = get_term_meta($term->term_id, $field['id'], true);
                $field['value'] = $field_value; ?>
                <tr class="form-field">
                    <th scope="row" valign="top">
                        <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                    </th>
                    <td>
                        <?php $this->create_field($field, false); ?>
                    </td>
                </tr>
            <?php }
        }
    }

    public function add_field_to_new_term_screen($taxonomy)
    {
        // The taxonomy string should be 'pa_{slug}'
        $attribute_key = str_replace('pa_', '', $taxonomy);
        $fields = $this->attributes[$attribute_key]['fields'] ?? [];

        foreach ($fields as $field) :
            $this->create_field($field);
        endforeach;
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
            case 'textarea':
                // Generate a number input field
                woocommerce_wp_textarea_input([
                    'id'          => $field['id'],
                    'label'       => $label,
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

    public function add_additional_charges_to_variations($loop, $variation_data, $variation)
    { ?>
        <div class="form-row form-row-full">
            <label for="processing_fee_<?= $loop; ?>">Processing Fee</label>
            <input type="number" name="processing_fee[<?= $loop; ?>]" id="processing_fee_<?= $loop; ?>" value="<?= get_post_meta($variation->ID, '_processing_fee', true); ?>">
        </div>
        <br>
        <div class="form-row form-row-full">
            <label for="is_taxable_<?= $loop; ?>">Is Taxable</label>
            <select name="is_taxable[<?= $loop; ?>]" id="is_taxable_<?= $loop; ?>">
                <option value="yes" <?= selected(get_post_meta($variation->ID, '_is_taxable', true), 'yes', false); ?>>Yes</option>
                <option value="no" <?= selected(get_post_meta($variation->ID, '_is_taxable', true), 'no', false); ?>>No</option>
            </select>
        </div>
        <br>
        <div class="form-row form-row-full">
            <label for="tax_<?= $loop; ?>">Tax</label>
            <select name="tax[<?= $loop; ?>]" id="tax_<?= $loop; ?>">
                <option value="standard" <?= selected(get_post_meta($variation->ID, '_tax', true), 'standard', false); ?>>Standard</option>
                <option value="reduced" <?= selected(get_post_meta($variation->ID, '_tax', true), 'reduced', false); ?>>Reduced</option>
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

    public function display_additional_charges_on_variation()
    {
        global $product;
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];

                // Retrieve custom fields
                $processing_fee = get_post_meta($variation_id, '_processing_fee', true);
                $is_taxable = get_post_meta($variation_id, '_is_taxable', true);
                $tax = get_post_meta($variation_id, '_tax', true);

                // Display custom fields
                echo '<div class="variation-custom-fields">';
                if ($processing_fee) {
                    echo '<p>Processing Fee: ' . esc_html($processing_fee) . '</p>';
                }
                if ($is_taxable) {
                    echo '<p>Is Taxable: ' . esc_html($is_taxable) . '</p>';
                }
                if ($tax) {
                    echo '<p>Tax: ' . esc_html($tax) . '</p>';
                }
                echo '</div>';
            }
        }
    }


    // Add custom field value to cart item
    public function add_additional_charges_to_cart_item($cart_item_data, $product_id)
    {
        if (isset($_POST['processing_fee'])) {
            $cart_item_data['processing_fee'] = sanitize_text_field($_POST['processing_fee']);
        }
        if (isset($_POST['is_taxable'])) {
            $cart_item_data['is_taxable'] = sanitize_text_field($_POST['is_taxable']);
        }
        if (isset($_POST['tax'])) {
            $cart_item_data['tax'] = sanitize_text_field($_POST['tax']);
        }

        return $cart_item_data;
    }

    // Display custom field in cart
    public function display_additional_charges_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item['processing_fee'])) {
            $item_data[] = array(
                'name'  => 'Processing Fee',
                'value' => $cart_item['extra_fee'],
            );
        }
        if (isset($cart_item['is_taxable'])) {
            $item_data[] = array(
                'name'  => 'Is Taxable',
                'value' => $cart_item['is_taxable'],
            );
        }
        if (isset($cart_item['tax'])) {
            $item_data[] = array(
                'name'  => 'Tax',
                'value' => $cart_item['tax'],
            );
        }

        return $item_data;
    }

    // Save custom field to order meta
    public function save_additional_charges_to_order_items($item, $cart_item_key, $values, $order)
    {
        if (isset($values['processing_fee'])) {
            $item->add_meta_data('Processing Fee', $values['processing_fee']);
        }
        if (isset($values['is_taxable'])) {
            $item->add_meta_data('Is Taxable', $values['is_taxable']);
        }
        if (isset($values['tax'])) {
            $item->add_meta_data('Tax', $values['tax']);
        }
    }
}
