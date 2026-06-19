<?php

/**
 *
 * @package Woo_Vignette\admin\includes
 */

namespace Woo_Vignette\admin\includes;

/**
 * Base shortcode.
 */
class Woo_Vignette_Cpt
{
    
    public function __construct(){
        add_action('init', [$this,'create_post_type']);
        add_action('add_meta_boxes', [$this,'meta_boxes']);
        add_action('save_post',[$this,'save_meta_boxes']);
    }
    
    public function create_post_type() {
        $args = array(
            'labels' => array(
                'name' => 'Country',
                'singular_name' => 'Country',
                'add_new' => 'Add Country',
                'add_new_item' => 'Add New Country',
                'edit_item' => 'Edit Country',
                'new_item' => 'New Country',
                'view_item' => 'View Country',
                'all_items' => 'All Countries',
                'search_items' => 'Search Countries',
                'not_found' => 'No Countries found',
                'not_found_in_trash' => 'No Countries found in trash',
                'parent_item_colon' => '',
                'menu_name' => 'Countries',
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'country'),
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest' => true, // Important for Gutenberg support
            'show_ui' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-location',
            'wpml' => true, // This line makes the post type translatable with WPML
        );
    
        register_post_type('country', $args);
    }
    
    // Add Custom Meta Boxes for Event CPT
    public function meta_boxes() {
        add_meta_box(
            'country_code',          // Meta box ID
            'Country Code',          // Title of the box
            [$this,'country_code_meta_box'], // Callback function to display the fields
            'country',               // Post type
            'normal',              // Context (normal, side, advanced)
            'high'                 // Priority
        );
    }


    public function country_code_meta_box($post) {
    // Retrieve the current country code value for this post
        $country_code = get_post_meta($post->ID, '_country_code', true);
        $languages = [];
        // List of countries and their country codes (You can extend this list as needed)
        if( function_exists('icl_get_languages') ){
            $languages = icl_get_languages('skip_missing=0');
        }
        ?>
        <label for="country_code">Country Code:</label>
        <input type="text" name="country_code" id="country_code" max="2" value="<?php echo $country_code?>">
        <!--<select name="country_code" id="country_code">
            <option value="">Select Country</option>
            <?php
            // Loop through the countries and create options for the dropdown
            /* foreach ($languages as $code => $languageDetail) {
                // Check if the current country code is selected
                echo '<option value="' . esc_attr($code) . '" ' . ( $country_code == $code?'selected':'' ) . '>' . esc_html($languageDetail['code']) . '</option>';
            } */
            ?>
        </select>-->
        <?php
    }



    // Save Custom Fields Data
    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;
        
        // Save Event Date
        if (isset($_POST['country_code'])) {
            update_post_meta($post_id, '_country_code', sanitize_text_field($_POST['country_code']));
        }
        
    }



}
