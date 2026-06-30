<?php

/**
 * Handle Woo_Vignette shortcode.
 *
 * @package Woo_Vignette\frontend
 */

namespace Woo_Vignette\frontend;

/**
 * Order_Form shortcode.
 */
class Woo_Vignette_Ajax
{
    private $current_lang = 'en';
    private $has_ploylang = false;
    private $has_wpml = false;
    public  $cross_icon = '<i class="dashicons dashicons-no-alt"></i>';

    /** Request-level cache: avoids repeated transient/DB hits within one PHP request. */
    private static $products_cache = null;

    /** Fix 9: Cached woocommerce_prices_include_tax option — one get_option() per request. */
    private static $prices_include_tax = null;

    /** Fix 7: Per-request cache for WPML term-translation queries. */
    private static $vehicle_translation_cache = [];

    /** Fix 1: Per-request cache for get_term_by() calls in rename_cart_item_name(). */
    private static $term_name_cache = [];

    /** Fix 4: Pending cart item data keyed by variation_id, consumed by wv_add_cart_item_data_filter(). */
    private $pending_cart_item_data = [];

    public function __construct()
    {
        if (function_exists("pll_get_post_language")) {
            $this->has_ploylang = true;
            $this->current_lang = pll_current_language('slug');
        }elseif (function_exists("icl_get_languages")) {
            $this->has_wpml = true;
            $this->current_lang = apply_filters('wpml_current_language', NULL)??'en';
        }
        add_action('woocommerce_cart_calculate_fees', [$this,'add_fee_to_all_line_items'], 10, 1 );
        // Fix 4: Register the cart item data filter once here instead of inside add_item_to_cart() loop.
        add_filter('woocommerce_add_cart_item_data', [$this, 'wv_add_cart_item_data_filter'], 10, 3);
        add_filter('woocommerce_cart_item_name', [$this,'rename_cart_item_name'], 10, 3);
        add_filter('woocommerce_order_item_name', [$this,'rename_order_item_name'], 10, 2);
        add_filter('wv_invoice_service_item_name', [$this,'invoice_service_item_name'], 10,2);
        add_filter('wv_invoice_item_name', [$this,'invoice_item_name'], 10,2);
        add_filter('woocommerce_review_item_name', [$this,'rename_review_item_name'], 10, 3);
        add_filter('wv_specification_item_name', [$this,'rename_specification_item_name'], 10, 2);
        add_filter('wv_email_template_item_name', [$this,'email_template_item_name'], 10, 2);
        add_filter('woocommerce_account_orders_columns', [$this,'account_orders_columns'], 10, 1);
        add_filter('woocommerce_my_account_my_orders_actions', [$this,'orders_actions'], 9999, 2);
        add_action( 'woocommerce_thankyou', [$this,'remove_custom_field_after_checkout'],10,1 );
        add_action('woocommerce_cart_item_removed', [$this,'remove_custom_field_when_cart_item_removed'],10,2);
        add_action('woocommerce_cart_emptied', [$this,'remove_custom_field_when_cart_empty']);
    }
    
    public function remove_custom_field_when_cart_item_removed( $removed_cart_item_key, $cart ) {
        $item_count = 0;
        foreach( WC()->cart->get_cart() as $cart_item_key => $cart_item ){
            $item_count++;
        }
        if( $item_count == 1 ){
            $product = $cart_item['data'];
            $product_type = get_post_meta($product->get_id(),'_wv_product_type',true);
            if( $product_type == 'rapid-process' ){
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
        if (!$item_count) {
            WC()->session->__unset('order_date'); 
            WC()->session->__unset('countries'); 
            WC()->session->__unset('selected_variations'); 
        }else{
            $selected_variations = $this->get_vehicle_type_from_cart();
            WC()->session->set('selected_variations', $selected_variations);
        }
    }
    
    public function remove_custom_field_when_cart_empty() {
        if (WC()->cart->is_empty()) {
            WC()->session->__unset('order_date'); 
            WC()->session->__unset('countries'); 
            WC()->session->__unset('selected_variations'); 
        }
    }
    
    public function remove_custom_field_after_checkout($order_id) {
        if ( isset(WC()->session) ) {
            WC()->session->__unset('order_date');
            WC()->session->__unset('countries');
            WC()->session->__unset('selected_variations');
        }
    }

    public function orders_actions( $actions, $order ){
        foreach( $actions as $action => $data ){
            if( $action == 'view' ){
                $actions[$action]['name'] = "View Order";
            }elseif( $action == 'invoice' ){
                $actions[$action]['name'] = "View Invoice";
            }
        }
        //echo "<pre>";print_r($actions);die;
        return $actions;
    }
    
    public function account_orders_columns( $columns ){
        return $columns;
    }
    
    public function getAllAttributes(){
        $transient_key = 'wv_attributes_'.$this->current_lang;
        $cached = get_transient($transient_key);
        $flag = true;
        if( !empty($cached) ){
            foreach( $cached as $cache_data ){
                if( !isset( $cache_data['terms'] ) ){
                    $flag = false;
                    break;
                }
            }
        }
        if ($flag && $cached !== false) {
            return $cached;
        }
        //echo "<pre>";print_r($cached);die;
        // Get all product attribute taxonomies (those prefixed with 'pa_')
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attribute_taxonomies_slugs = [];
        $attributes_terms = [];
        // Collect the taxonomies for product attributes
        foreach ($attribute_taxonomies as $attribute) {
            $taxnomy = 'pa_' . $attribute->attribute_name;
            $attributes_terms[$taxnomy]['name'] = $attribute->attribute_label;
            $attributes_terms[$taxnomy]['slug'] = $attribute->attribute_name;
            $attribute_taxonomies_slugs[] = $taxnomy;
        }

        // Get all terms for all product attributes in a single query
        $args = array(
            'taxonomy'   => $attribute_taxonomies_slugs, // Fetch terms from all product attribute taxonomies
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => false, // Include terms even if they are not assigned to any products
        );
        
        // Get all terms for the given taxonomies
        $terms = get_terms($args);
        
        // Group the terms by attribute
        
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                if( $term->name == 'N/A' ){
                    continue;
                }
                // Determine the attribute (taxonomy) this term belongs to
                $taxonomy = $term->taxonomy;
                $attribute_name = str_replace('pa_', '', $taxonomy); // Remove 'pa_' to get the attribute name
        
                // Group terms by attribute
                if (!isset($attributes_terms[$attribute_name])) {
                    $attributes_terms[$attribute_name] = [];
                }
                $term->custom_meta = get_term_meta($term->term_id);
                $attributes_terms[$taxonomy]['terms'][$term->slug] = $term;
            }
        }
        set_transient($transient_key, $attributes_terms, 30 * DAY_IN_SECONDS);
        //echo "<pre>";print_r($attributes_terms);die;
        return $attributes_terms;
    }
    
    public function getProductName(){
        $transient_key = 'wv_product_name_'.$this->current_lang;
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached;
        }
        $products = $this->getProducts();
        $product_names = [];
        foreach( $products as $product ){
            if( $product['product_type'] == 'toll' ){
                $product_names[$product['product_type']][$product['product_reference']] = $product['product_name'];
            }else{
                $product_names[$product['product_type']][$product['product_country']] = $product['product_name'];
            }
        }
        set_transient($transient_key, $product_names, 30 * DAY_IN_SECONDS);
        return $product_names;
    }
    
    public function getProducts(){
        // Fix 2: Static cache — zero overhead on repeated calls within the same PHP request.
        if ( self::$products_cache !== null ) {
            return self::$products_cache;
        }

        global $wpdb;
        $transient_key   = 'wv_products_' . $this->current_lang;
        $cached_products = get_transient( $transient_key );
        if ( $cached_products !== false ) {
            self::$products_cache = $cached_products;
            return $cached_products;
        }

        // Only load attributes when actually building the full product list.
        $allAttributes = $this->getAllAttributes();

        $query = "
            SELECT wp_posts.ID, wp_posts.post_title
            FROM {$wpdb->posts} wp_posts
            INNER JOIN {$wpdb->prefix}postmeta wp_postmeta ON wp_posts.ID = wp_postmeta.post_id";
        if ( function_exists( 'icl_get_languages' ) ) {
            $query .= " INNER JOIN {$wpdb->prefix}icl_translations wpml_translations
                ON wp_posts.ID = wpml_translations.element_id
                AND wpml_translations.element_type = CONCAT('post_', wp_posts.post_type)";
        }
        $query .= " WHERE wp_posts.post_type = 'product'
                AND wp_posts.post_status = 'publish'
                AND wp_postmeta.meta_key = '_wv_country'";
        if ( function_exists( 'icl_get_languages' ) ) {
            $query .= " AND wpml_translations.language_code = '" . esc_sql( $this->current_lang ) . "'";
        }
        $query .= " GROUP BY wp_posts.ID ORDER BY wp_posts.post_date DESC;";
        $results = $wpdb->get_results( $query );

        $products = [];
        if ( ! empty( $results ) ) {
            $product_ids     = array_map( fn( $r ) => (int) $r->ID, $results );
            $ids_placeholder = implode( ',', $product_ids );

            // Fix 1a: Prime product post-meta cache for ALL products in ONE query.
            update_postmeta_cache( $product_ids );

            // Fix 1b: Fetch ALL variation IDs for ALL products in ONE query.
            $variation_rows = $wpdb->get_results(
                "SELECT ID, post_parent
                 FROM {$wpdb->posts}
                 WHERE post_type = 'product_variation'
                 AND post_status = 'publish'
                 AND post_parent IN ({$ids_placeholder})
                 ORDER BY menu_order ASC"
            );
            $variations_by_product = [];
            foreach ( $variation_rows as $row ) {
                $variations_by_product[ (int) $row->post_parent ][] = (int) $row->ID;
            }

            // Fix 1c: Prime variation post-meta cache for ALL variations in ONE query.
            $all_variation_ids = ! empty( $variations_by_product )
                ? array_merge( ...array_values( $variations_by_product ) )
                : [];
            if ( ! empty( $all_variation_ids ) ) {
                update_postmeta_cache( $all_variation_ids );
            }

            foreach ( $results as $postProduct ) {
                $product_id  = (int) $postProduct->ID;
                $productMeta = get_post_meta( $product_id ); // uses primed cache — no DB hit
                $variations  = $variations_by_product[ $product_id ] ?? [];

                $product = [
                    'product_id'   => $product_id,
                    'product_name' => $postProduct->post_title,
                    'variations'   => $variations,
                ];
                foreach ( $productMeta as $meta_key => $meta_value ) {
                    if ( in_array( $meta_key, [ '_wv_product_type', '_wv_country', '_wv_product_reference' ], true ) ) {
                        $product[ str_replace( '_wv_', '', $meta_key ) ] = $meta_value[0] ?? '';
                    }
                }
                if ( ! empty( $variations ) ) {
                    $variationProducts = $this->getVariationsProduct( $product, $allAttributes );
                    array_push( $products, ...$variationProducts );
                }
            }
        }

        set_transient( $transient_key, $products, 30 * DAY_IN_SECONDS );
        self::$products_cache = $products;
        // Pre-warm dependent transients while data is fresh.
        $this->setProductTypes();
        $this->getProductName();
        return $products;
    }
    
    public function setProductTypes(){
        $transient_key = 'wv_product_type_'.$this->current_lang;
        $cached = get_transient($transient_key);
        //echo "<pre>";print_r($cached);die;
        if ($cached !== false) {
            return $cached;
        }
        $productTypes = [];
        $products = $this->getProducts();
        $allAttributes = $this->getAllAttributes();
        $added = [];
        foreach( $products as $product ){
            if( $product['product_type'] == 'toll'){
                continue;
            }
            foreach( $product['attributes'] as $attribute_slug => $attribute ){
                $taxnomy = $allAttributes[$attribute_slug]['slug'];
                $productTypes[$product['product_country']][$product['product_type']][$taxnomy]['variation_name'] = $allAttributes[$attribute_slug]['name'];
                foreach($allAttributes[$attribute_slug]['terms'] as $term){
                    if( !isset( $added[$product['product_country']][$product['product_type']][$term->slug] ) ){
                        $added[$product['product_country']][$product['product_type']][$term->slug] = 1;
                        $attributeData['name'] = $term->name;
                        $attributeData['value'] = $term->slug;
                        $attributeData['description'] = $term->description;
                        $attributeData['fields'] = $term->custom_meta;
                        $productTypes[$product['product_country']][$taxnomy]['items'][] = $attributeData; 
                    }
                }
            }
        }
        set_transient($transient_key, $productTypes, 30 * DAY_IN_SECONDS);
        //echo "<pre>";print_r($productTypes);die;
        return $productTypes;
    }
    
    private function getVariationsProduct( $product, $allAttributes ){
        extract($product);
        $all_varirations  = [];
        $evignetteTerms = [];
        foreach ($variations as $variation_id) {
            $variationData = get_post_meta($variation_id);
            $filterAttributes = array_filter($variationData, function($value, $key) { 
                return strpos($key, 'attribute_pa') !== false;
            }, ARRAY_FILTER_USE_BOTH);
            $attributes = [];
            foreach( $filterAttributes as  $attribute_key => $filterAttribute ){
                $attribute_key = str_replace('attribute_','',$attribute_key);
                $attributes[$attribute_key] = $filterAttribute[0]??'';
            }
            
            $vignette_data = [];
            $fee_data = $this->variation_fee_data( $variation_id, $country, $variationData );
            $vignette_name = '';
            $period_type = '';
            if (isset($attributes['pa_e-vignette'])) {
                $period_term = $allAttributes['pa_e-vignette']['terms'][$attributes['pa_e-vignette']]??[];
                if( !empty( $period_term ) ){
                    $custom_meta = $period_term->custom_meta;
                    $term_period_type = $custom_meta['wv_period_type'][0] ?? '';
                    $term_time_period = $custom_meta['wv_time_period'][0] ?? 0;
                    $product_variation_days = $this->getDaysByPeriod($term_period_type, $term_time_period);
                    $vignette_data = [
                        'period_type'   =>  $term_period_type,
                        'time_period'   =>  $term_time_period,
                        'days'          =>  $product_variation_days,
                        'name'          =>  $period_term->name
                    ];
                    $period_type = $term_period_type;
                    $vignette_name = $period_term->name;
                }
                
            }
            $all_varirations[] = array(
                'ID'                    =>  $variation_id,
                'product_id'            =>  $product_id,
                'product_name'          =>  $product_name,
                'product_type'          =>  $product_type,
                'period_type'           =>  $period_type,
                'product_country'       =>  $country,
                'product_reference'     =>  $product_reference,
                'attributes'            =>  $attributes,
                'vignette_data'         =>  $vignette_data,
                'vignette_name'         =>  $vignette_name,
                'vignette_fee'          =>  $fee_data,
                'language_code'         =>  $this->current_lang
            );
        }
        return $all_varirations;
    }
    
    private function getSimpleProduct( $product ){
        $product_custom_field = get_post_meta( $product->get_id() );
        $product_type = $product_custom_field['_wv_product_type'][0]??null;
        $product_country = $product_custom_field['_wv_country'][0]??'';
        $product_reference = $product_custom_field['_wv_product_reference'][0]??'';
        $language_code = $this->getProductLanguage($product->get_id());
        $newProduct = array(
                'ID'                    =>  $product->get_id(),
                'product_id'            =>  $product->get_id(),
                'product_type'          =>  $product_type,
                'product_country'       =>  $product_country,
                'product_reference'     =>  $product_reference,
                'language_code'         =>  $language_code,
                'attributes'            =>  [],
                'vignette_data'         =>  [],
                'vignette_name'         =>  '',
                'vignette_fee'          =>  []
            );
        return $newProduct;
    }
    
    public function email_template_item_name($name, $item){
        // Start building the new name
        $new_name = "<div class='vignette-details'><div class='vignette-name'>";
        $validity = [];
        $meta_data = $item->get_meta_data();
        $variations = [];
        $vignette_name = '';
        $period_type = '';
        foreach( $meta_data as $meta ){
            if( $meta->key == 'validity' ){
                $validity = $meta->value;
            }elseif( $meta->key == 'vignette_name' ){
                $vignette_name = $meta->value;
            }elseif( $meta->key == 'period_type' ){
                $period_type = $meta->value;
            }else{
                if( strpos( $meta->key,'pa_' ) !== false ){
                    $variations[$meta->key] = $meta->value;
                }
            }
            
        }
        if( $period_type == 'indefinite' ){
            $new_name .= $name . "</div>";
            $new_name .= "<div class='vignette-item'>
                            <span class='vignette-title'>This vignete is valid indefinitely</span> 
                        </div></div>";
            return $new_name;
        }
        if (!empty($validity)) {
            $filter_variaition = [];
            foreach($variations as $variation_key => $variation){
                if( 'pa_e-vignette' != $variation_key && $variation != 'n-a'){
                    $term = get_term_by('slug', $variation, $variation_key);
                    $filter_variaition[] = $term->name;
                }
            }
            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            $new_name .= $vignette_name . " " . $name . "</div>";
            //$name = "<div class='vignette-name'>".$cart_item['vignette_name'] . " " . $name . "</div>";
            // Loop through the validity periods
            foreach ($validity as $validity_period) {
                if (isset($validity_period['start'], $validity_period['end'])) {
                    // Format start and end dates to 'd/m/Y'
                    $start_date = strtotime($validity_period['start']);
                    $end_date = strtotime($validity_period['end']);
    
                    $formatted_start = date('d.m.Y', $start_date);
                    $formatted_end = date('d.m.Y', $end_date);
                    $vignette = "<div class='vignette-item'>
                                        <span class='vignette-title'>- Valid {$formatted_start} up to {$formatted_end}</span>
                                    </div>";
                    // Create the formatted date range
                    $new_name .= $vignette;
                }
            }
            $new_name .= "</div>";
        }else{
            $new_name .= $name . "</div></div>";
        }
        return $new_name;
    }
    
    public function rename_specification_item_name($name, $cart_item){
        if( !empty($cart_item['period_type']) && $cart_item['period_type'] == 'indefinite' ){
            return $name;
        }
        $name = (isset($cart_item['vignette_name'])?$cart_item['vignette_name']." ":'').$name;
        return $name;
    }
    
    public function remove_optional_product() {
        try{
            if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'remove_optional_product_nonce') ) {
                wp_send_json_error(['message' => 'Nonce verification failed']);
            }
        
            // Ensure WooCommerce is loaded and the cart is not empty
            if ( WC()->cart->is_empty() ) {
                wp_send_json_error(['message' => 'Your cart is empty.']);
            }
        
            // Get the cart item key from the request
            $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
            
            // Remove the product from the cart by cart item key
            if ($cart_item_key) {
                WC()->cart->remove_cart_item($cart_item_key);
                wp_send_json_success(['message' => 'Product removed from cart']);
            } else {
                wp_send_json_error(['message' => 'Invalid product or cart item key.']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die(); 
        
    }

    public function update_optional_product_quantity($product_id) {
        // Check if the product is already in the cart
        if ( ( $optional_cart_item_key = $this->is_product_in_cart($product_id) ) ) {
            $quantity = 0;
            // Loop through cart items and count how many don't belong to excluded countries
            $rapid_excluded = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_excluded_countries();
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                // Check if the current cart item doesn't match the excluded countries
                if (!in_array($cart_item['product_country'], $rapid_excluded) && $optional_cart_item_key != $cart_item_key) {
                    $quantity++; // Increase quantity based on the product's country
                }
            }
            WC()->cart->remove_cart_item($optional_cart_item_key);
            WC()->cart->add_to_cart($product_id, $quantity);
            // Refresh session after updating the cart
            WC()->cart->set_session();
        }
    }

    public function add_to_cart_optional_product() {
        // Verify the nonce for security
        try {
            if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'add_to_cart_nonce') ) {
                wp_send_json_error(['message' => 'Nonce verification failed']);
            }
            
            // Get the product ID and quantity
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $quantity = 0;
            $rapid_excluded = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_excluded_countries();
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if( !in_array( $cart_item['product_country'], $rapid_excluded ) ){
                    $quantity++;//$cart_item['quantity'];
                }
            }
        
            if( $this->is_product_in_cart($product_id) ){
                wp_send_json_error(['message' => 'Product is already in cart.']);
            }
        
            // If the product ID is valid
            if ($product_id > 0) {
                // Add the product to the cart
                $added = WC()->cart->add_to_cart($product_id, $quantity);
                WC()->cart->set_session();
                // If the product was added successfully, send a success response
                if ($added) {
                    wp_send_json_success(['message' => 'Product added to cart']);
                } else {
                    wp_send_json_error(['message' => 'Could not add the product to the cart']);
                }
            } else {
                wp_send_json_error(['message' => 'Invalid product ID']);
            }
    
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die(); 
    }
    
    public function rename_cart_item_name($name, $cart_item, $cart_item_key){
        $_product = $cart_item['data'];
        $remove_link =  "<a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='' data-end=''>".$this->cross_icon."</a>";
        $new_name = "<div class='vignette-details'><div class='vignette-name'>";
        if( isset($cart_item['period_type']) && $cart_item['period_type'] == 'indefinite' ){
            $new_name .= $name . "</div>";
            $new_name .= "<div class='vignette-item'>
                                <a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='' data-end=''>".$this->cross_icon."</a>
                                <span class='vignette-title'>This vignete is valid indefinitely</span>
                        </div></div>";
            return $new_name;
        }
        //echo "<pre>";print_r($cart_item);die;
        if (isset($cart_item['validity']) && is_array($cart_item['validity'])) {
            $filter_variaition = [];
            foreach( $cart_item['variation'] as $key => $variation ){
                if( 'attribute_pa_e-vignette' != $key && $variation != 'n-a'){
                    $taxonomy  = str_replace('attribute_','',$key);
                    $cache_key = $taxonomy . ':' . $variation;
                    if (!isset(self::$term_name_cache[$cache_key])) {
                        $term = get_term_by('slug', $variation, $taxonomy);
                        self::$term_name_cache[$cache_key] = $term ? $term->name : $variation;
                    }
                    $filter_variaition[] = self::$term_name_cache[$cache_key];
                }
            }

            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            $new_name .= $cart_item['vignette_name'] . " " . $name . "</div>";
            // Loop through the validity periods
            foreach ($cart_item['validity'] as $validity_period) {
                if (isset($validity_period['start'], $validity_period['end'])) {
                    // Format start and end dates to 'd/m/Y'
                    $start_date = strtotime($validity_period['start']);
                    $end_date = strtotime($validity_period['end']);
    
                    $formatted_start = date('d.m.Y', $start_date);
                    $formatted_end = date('d.m.Y', $end_date);
                    $vignette = "<div class='vignette-item'>
                                        <a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='{$formatted_start}' data-end='{$formatted_end}'>".$this->cross_icon."</a>
                                        <span class='vignette-title'>- Valid {$formatted_start} up to {$formatted_end}</span>
                                    </div>";
                    // Create the formatted date range
                    $new_name .= $vignette;
                }
            }
            $new_name .= "</div>";
        }else{
            $new_name .= $remove_link . $name  . "</div></div>";
        }
        return $new_name;
    }
    
    public function rename_order_item_name($name, $item){
        //echo "<pre>";print_r($item);die;
        $product = $item->get_product();
        $thumbnail = $product ? $product->get_image() : '';
        $new_name = "<div class='vignette-details'><div class='vignette-name'>";
        // Start building the new name
        $new_name .= $thumbnail;
        $validity = [];
        $meta_data = $item->get_meta_data();
        $variations = [];
        $vignette_name = '';
        $period_type = '';
        foreach( $meta_data as $meta ){
            if( $meta->key == 'validity' ){
                $validity = $meta->value;
            }elseif( $meta->key == 'vignette_name' ){
                $vignette_name = $meta->value;
            }elseif( $meta->key == 'period_type' ){
                $period_type = $meta->value;
            }else{
                if( strpos( $meta->key,'pa_' ) !== false ){
                    $variations[$meta->key] = $meta->value;
                }
            }
            
        }
        if( $period_type == 'indefinite' ){
            $new_name .=  $name . "</div>";
            $new_name .= "<div class='vignette-item'>
                                        <span class='vignette-title'>This vignete is valid indefinitely</span>
                        </div></div>";
            return $new_name;
        }
        if (!empty($validity)) {
            $filter_variaition = [];
            foreach($variations as $variation_key => $variation){
                if( 'pa_e-vignette' != $variation_key && $variation != 'n-a'){
                    $term = get_term_by('slug', $variation, $variation_key);
                    $filter_variaition[] = $term->name;
                }
            }
            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            $new_name .= $vignette_name . " " . $name . "</div>";
            //$name = "<div class='vignette-name'>".$cart_item['vignette_name'] . " " . $name . "</div>";
            // Loop through the validity periods
            foreach ($validity as $validity_period) {
                if (isset($validity_period['start'], $validity_period['end'])) {
                    // Format start and end dates to 'd/m/Y'
                    $start_date = strtotime($validity_period['start']);
                    $end_date = strtotime($validity_period['end']);
    
                    $formatted_start = date('d.m.Y', $start_date);
                    $formatted_end = date('d.m.Y', $end_date);
                    $vignette = "<div class='vignette-item'>
                                        <span class='vignette-title'>- Valid {$formatted_start} up to {$formatted_end}</span>
                                    </div>";
                    // Create the formatted date range
                    $new_name .= $vignette;
                }
            }
            $new_name .= "</div>";
        }else{
            $new_name .= $name . "</div>";
        }
        return $new_name;
    }
    
    public function invoice_item_name($name, $item){
        //echo "<pre>";print_r($item);die;
        $product = $item->get_product();
        $thumbnail = $product ? $product->get_image() : '';
        $new_name = "<div class='vignette-details'><div class='vignette-name'>";
        // Start building the new name
        //$new_name .= $thumbnail;
        $validity = [];
        $meta_data = $item->get_meta_data();
        $variations = [];
        $vignette_name = '';
        $period_type = '';
        foreach( $meta_data as $meta ){
            if( $meta->key == 'validity' ){
                $validity = $meta->value;
            }elseif( $meta->key == 'vignette_name' ){
                $vignette_name = $meta->value;
            }elseif( $meta->key == 'period_type' ){
                $period_type = $meta->value;
            }else{
                if( strpos( $meta->key,'pa_' ) !== false ){
                    $variations[$meta->key] = $meta->value;
                }
            }
            
        }
        if( $period_type == 'indefinite' ){
            $new_name .=  __('Official Fee','woocommerce') . " {$name}</div>";
            $new_name .= "<div class='vignette-item'>
                                        <span class='vignette-title'>This vignete is valid indefinitely</span>
                        </div></div>";
            return $new_name;
        }
        if (!empty($validity)) {
            $filter_variaition = [];
            foreach($variations as $variation_key => $variation){
                if( 'pa_e-vignette' != $variation_key && $variation != 'n-a'){
                    $term = get_term_by('slug', $variation, $variation_key);
                    $filter_variaition[] = $term->name;
                }
            }
            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            $new_name .= __('Official Fee','woocommerce') . " " .  $vignette_name . " " . $name . "</div>";
            //$name = "<div class='vignette-name'>".$cart_item['vignette_name'] . " " . $name . "</div>";
            // Loop through the validity periods
            foreach ($validity as $validity_period) {
                if (isset($validity_period['start'], $validity_period['end'])) {
                    // Format start and end dates to 'd/m/Y'
                    $start_date = strtotime($validity_period['start']);
                    $end_date = strtotime($validity_period['end']);
    
                    $formatted_start = date('d.m.Y', $start_date);
                    $formatted_end = date('d.m.Y', $end_date);
                    $vignette = "<div class='vignette-item'>
                                        <span class='vignette-title'>- Valid {$formatted_start} up to {$formatted_end}</span>
                                    </div>";
                    // Create the formatted date range
                    $new_name .= $vignette;
                }
            }
            $new_name .= "</div>";
        }else{
            $new_name .= __('Official Fee','woocommerce') . " " . $name . "</div>";
        }
        return $new_name;
    }

    public function invoice_service_item_name($name, $cart_item){
        $filter_variaition = [];
            foreach( $cart_item['variation'] as $key => $variation ){
                if( 'attribute_pa_e-vignette' != $key && $variation != 'n-a'){
                    $taxonomy = str_replace('attribute_','',$key);
                    $term = get_term_by('slug', $variation, $taxonomy);
                    $filter_variaition[] = $term->name;
                }
            }
            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            if( $cart_item['period_type'] == 'indefinite' ){
                return $name;
            }
            $name = " " . $cart_item['vignette_name'] . " " . $name;
            return $name;
    }
    
    public function rename_review_item_name($name, $cart_item, $cart_item_key){
        $astra_setting = get_option('astra-settings');
        $_product = $cart_item['data'];
        if( isset( $astra_setting['checkout-order-review-product-images'] ) && $astra_setting['checkout-order-review-product-images'] ){
            $thumbnail = $_product->get_image();
        }else{
            $thumbnail = '';
        }
        
        $remove_link =  "<a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='' data-end=''>".$this->cross_icon."</a>";
        $new_name = "<div class='vignette-name'>".$thumbnail;
        if( $cart_item['period_type'] == 'indefinite' ){
            $new_name .= $cart_item['vignette_name'] . " " . $name . "</div>";
            $new_name .= "<div class='vignette-item'>
                                <a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='' data-end=''>".$this->cross_icon."</a>
                                <span class='vignette-title'>This vignete is valid indefinitely</span>
                        </div></div>";
            return $new_name;
        }
        //echo "<pre>";print_r($cart_item);die;
        if (isset($cart_item['validity']) && is_array($cart_item['validity'])) {
            $filter_variaition = [];
            foreach( $cart_item['variation'] as $key => $variation ){
                if( 'attribute_pa_e-vignette' != $key && $variation != 'n-a'){
                    $taxonomy  = str_replace('attribute_','',$key);
                    $cache_key = $taxonomy . ':' . $variation;
                    if (!isset(self::$term_name_cache[$cache_key])) {
                        $term = get_term_by('slug', $variation, $taxonomy);
                        self::$term_name_cache[$cache_key] = $term ? $term->name : $variation;
                    }
                    $filter_variaition[] = self::$term_name_cache[$cache_key];
                }
            }

            if( count( $filter_variaition ) > 0 ){
                $name .= ' ( '.implode(",",$filter_variaition).' )';
            }
            $new_name .= $cart_item['vignette_name'] . " " . $name . "</div>";
            // Loop through the validity periods
            foreach ($cart_item['validity'] as $validity_period) {
                if (isset($validity_period['start'], $validity_period['end'])) {
                    // Format start and end dates to 'd/m/Y'
                    $start_date = strtotime($validity_period['start']);
                    $end_date = strtotime($validity_period['end']);
    
                    $formatted_start = date('d.m.Y', $start_date);
                    $formatted_end = date('d.m.Y', $end_date);
                    $vignette = "<div class='vignette-item'>
                                        <a href='#' class='vignette-delete' data-item_key='{$cart_item_key}' data-start='{$formatted_start}' data-end='{$formatted_end}'>".$this->cross_icon."</a>
                                        <span class='vignette-title'>- Valid {$formatted_start} up to {$formatted_end}</span>
                                    </div>";
                    // Create the formatted date range
                    $new_name .= $vignette;
                }
            }
        }else{
            $new_name .= $remove_link . $name . "</div>";
        }
        return $new_name;
    }
    
    public function add_fee_to_all_line_items( $cart ) {
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['vignette_fee']['price_with_tax'] ) ) {
                continue;
            }
            $fee_amount = floatval( $cart_item['vignette_fee']['price_with_tax'] );
            if ( $fee_amount <= 0 ) {
                continue;
            }
            $fee_name = "Service Fee: " . $this->invoice_service_item_name( $cart_item['data']->get_name(), $cart_item );
            // Use price_with_tax (tax-inclusive total) as a non-taxable fee so WC does
            // not add extra tax on top — the full amount is already accounted for.
            $cart->add_fee( $fee_name, $fee_amount, false, '' );
        }
    }

    private function getProductLanguage( $product_id ){
        if ( $this->has_ploylang ) {
            return pll_get_post_language( $product_id );
        }elseif ($this->has_wpml) {
            $args = array('element_id' => $product_id, 'element_type' => 'post_product' );
            return apply_filters( 'wpml_element_language_code', null, $args );
        }
    }

    private function updateCart( ){
        $optional_product_cart_key = '';
        $quantity = 0;
        $rapid_product_id = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_processing_product_id();
        $rapid_excluded = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_excluded_countries();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
            $product_id = $_product->get_id();
            if( $product_id != $rapid_product_id ){
                $product_country = $cart_item['product_country']??'';
                if( !in_array( $product_country, $rapid_excluded ) ){
                    $quantity++;
                }
            }else{
                $optional_product_cart_key = $cart_item_key;
            }
        }
        if( !empty( $optional_product_cart_key ) ){
            $cart_item['quantity'] = $quantity;
            WC()->cart->cart_contents[$optional_product_cart_key] = $cart_item;
        }
        // calculate_totals() calls set_session() internally — no need to call it before.
        WC()->cart->calculate_totals();
    }

    /** Renders the mini-cart template and returns the HTML string. */
    private function get_mini_cart_transient_key(): string {
        $hash        = WC()->cart ? WC()->cart->get_cart_hash() : '';
        $customer_id = WC()->session ? WC()->session->get_customer_id() : 0;
        return 'wv_mc_' . md5( $hash . '_' . $customer_id );
    }

    private function render_mini_cart_html(): string {
        $key    = $this->get_mini_cart_transient_key();
        $cached = get_transient( $key );
        if ( $cached !== false ) {
            return $cached;
        }
        ob_start();
        woocommerce_mini_cart();
        $html = ob_get_clean() ?: '';
        set_transient( $key, $html, 10 * MINUTE_IN_SECONDS );
        return $html;
    }
    public function delete_vignette(): string
    {
        try {
            $params = $_GET;
            $output['message'] = 'Invalid Request';
    
            // Sanitize inputs
            $cart_item_key = sanitize_text_field($params['item_key'] ?? '');
            $start_date = sanitize_text_field($params['start_date'] ?? '');
            $end_date = sanitize_text_field($params['end_date'] ?? '');
    
            if ( empty($cart_item_key) ) {
                wp_send_json_error($output);
            }
            if( !empty($start_date) ){
                $start_date = date("Y-m-d", strtotime($start_date));
            }
            if( !empty($end_date) ){
                $end_date = date("Y-m-d", strtotime($end_date));
            }
            $cart = WC()->cart->get_cart();
            $cart_item = $cart[$cart_item_key] ?? [];
            
            if( !empty($cart_item) && ( ( empty($start_date) && empty($end_date) ) || empty($cart_item['validity']) ) ){
                WC()->cart->remove_cart_item($cart_item_key);
                $this->updateCart();
                $output['message'] = 'Vignette has been removed successfully.';
                $output['mini_cart_html'] = $this->render_mini_cart_html();
                wp_send_json_success($output);
            }elseif (!empty($cart_item) && !empty($cart_item['validity'])) {
                
                $validity = $cart_item['validity'];
                $total_vignette = count($validity);
                if ($total_vignette == 1) {
                    // Remove the entire cart item if it's the only vignette
                    WC()->cart->remove_cart_item($cart_item_key);
                    $this->updateCart();
                    $output['message'] = 'Vignette has been removed successfully.';
                    $output['mini_cart_html'] = $this->render_mini_cart_html();
                    wp_send_json_success($output);
                } else {
                    $quantity = $cart_item['quantity']??0;
                    $vignette_fee = $cart_item['vignette_fee']??[];
                    // Filter out the specified vignette
                    $filtered_validity = array_filter($validity, function ($vignette) use ($start_date, $end_date) {
                        return !($vignette['start'] == $start_date && $vignette['end'] == $end_date);
                    });
                    if (count($filtered_validity) < $total_vignette) {
                        // Update validity and quantity
                        $cart_item['validity'] = $filtered_validity;
                        $cart_item['quantity'] = count($filtered_validity);
                        $cart_item['vignette_fee'] = $this->get_vignette_fee($vignette_fee,$cart_item['quantity']);
                        // Replace the cart item
                        WC()->cart->cart_contents[$cart_item_key] = $cart_item;
                        $this->updateCart();
                        $output['message'] = 'Vignette removed successfully.';
                        $output['mini_cart_html'] = $this->render_mini_cart_html();
                        wp_send_json_success($output);
                    } else {
                        $output['message'] = 'No matching vignette found.';
                        wp_send_json_error($output);
                    }
                }
                $output['message'] = 'Vignette has been removed successfully.';
                wp_send_json_success($output);
            }
            $output['message'] = 'Cart item not found or no validity data.';
            wp_send_json_error($output);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die();
    }
    
    // Fix 7: Cache WPML term-translation results — avoids repeated apply_filters + get_term() per taxonomy/slug pair.
    public function get_vehicle_type_translation( $taxonomy, $slug ){
        $cache_key = $taxonomy . ':' . $slug;
        if ( array_key_exists( $cache_key, self::$vehicle_translation_cache ) ) {
            return self::$vehicle_translation_cache[ $cache_key ];
        }

        $languages = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );
        $translations_by_language = [];

        $term = get_term_by( 'slug', $slug, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return self::$vehicle_translation_cache[ $cache_key ] = false;
        }

        if ( ! empty( $languages ) ) {
            foreach ( $languages as $lang_code => $lang_data ) {
                $translated_term_id = apply_filters( 'wpml_object_id', $term->term_id, $taxonomy, false, $lang_code );
                if ( $translated_term_id ) {
                    $translated_term = get_term( $translated_term_id, $taxonomy );
                    if ( ! is_wp_error( $translated_term ) ) {
                        $translations_by_language[] = $translated_term->slug;
                    }
                }
            }
        }

        return self::$vehicle_translation_cache[ $cache_key ] = $translations_by_language;
    }
    
    public function get_variations(): string
    {
        try {
            if ( ! WC()->session ) {
                wp_send_json_error(['message' => 'WooCommerce session not available.']);
            }
            $cart_variations = WC()->session->get('selected_variations');
            $countries = !empty($_GET['wv_countries']) ? array_map('sanitize_text_field', explode(',', $_GET['wv_countries'])) : [];
            $selected_variations = !empty($_GET['variations']) && is_array($_GET['variations']) ? array_map('sanitize_text_field', $_GET['variations']) : [];
            if ( ! class_exists('\Woo_Vignette\includes\core\Woo_Vignette_Base') ) {
                wp_send_json_error(['message' => 'Required class not found.']);
            }
            if( empty( $selected_variations ) ){
                foreach( $cart_variations as $cart_key => $cart_variation ){
                    $selected_variations[$cart_key] = reset( $cart_variation );
                }
            }
            //echo "<pre>";print_r($selected_variations);die;
            $baseClass = new \Woo_Vignette\includes\core\Woo_Vignette_Base;
            $variations = $baseClass->getVariations($countries, $selected_variations);
            //echo "<pre>";print_r($variations);die;
            $view_path = plugin_dir_path(dirname(__FILE__)) . 'views/variations.php';
            if ( ! file_exists($view_path) ) {
                wp_send_json_error(['message' => 'View file not found.']);
            }
            ob_start();
            include $view_path;
            $output = ob_get_clean();
    
            if ($output === false) {
                wp_send_json_error(['message' => 'Failed to capture output.']);
            }

            wp_send_json_success($output);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        wp_die();
    }
    
    public function add_to_cart_tolls( $variations, $product_id, $duration,$total_days ){
        $variations = array_filter($variations, function($item) use ($product_id) {
            return $item['product_id'] === $product_id;
        });
        $products = [];
        if(empty($duration)){
            $duration = 1;
        }
        $trip = $total_days/$duration;
        $selected_toll = null;
        $allAttributes = $this->getAllAttributes();
        foreach ($variations as $variation) {
            // Get variation data (You can customize this)
            $attributes = $variation['attributes'];
                
            $match = false;
            foreach ($attributes as $taxonomy => $term_value) {
                $period_term = $allAttributes[$taxonomy]['terms'][$term_value]??[];
                //echo "<pre>";print_r($period_term);die;
                if( !empty( $period_term ) ){
                    $custom_meta = $period_term->custom_meta;
                    if( isset( $custom_meta['wv_trip'][0] ) ){
                        $variation_trip = 1;
                        switch($custom_meta['wv_trip'][0]){
                            case 'single': $variation_trip = 1;break;
                            case 'double': $variation_trip = 2;break;
                            case 'multiple': $variation_trip = 3;break;
                        }
                        if( $trip >= $variation_trip ){
                            $selected_toll = $variation;
                            $match = true;
                        }
                    }
                }
            }
        }
        if( $selected_toll ){
            $variation = $selected_toll;
        }
        if ($match ) {
            $products[] = $variation;
        }
        //echo "<pre>";print_r($products);die;
        return $products;
    }
    public function add_to_cart(): string
    {
        //$getProduct = $this->getProduct();
        $formData = $_POST;
        $countries = $formData['countries']??[];
        $duration = $formData['duration']??'';
        $selected_reference = !empty($formData['tolls'])?$formData['tolls']:[];
        //echo "<pre>";var_dump($selected_reference);die;
        if( !empty( $selected_reference ) ){
            $selected_reference = explode(",",$selected_reference);
        }
        $required_variations = $formData['variations'] ?? [];
        $total_variations = count($required_variations);
        $start_date = $formData['wv_start_date'];
        $end_date = $formData['wv_end_date'];
        if (empty($countries)) {
            wp_send_json_error(array('message' => 'Please select country.'));
        }
        if ($total_variations == 0 || empty($formData['wv_start_date']) || empty($formData['wv_end_date'])) {
            wp_send_json_error(array('message' => 'Please select all required field.'));
        }
        if( !empty( $start_date ) ){
            $start_date = date("Y-m-d",strtotime($start_date));
        }
        if( !empty( $end_date ) ){
            $end_date = date("Y-m-d",strtotime($end_date));
        }
        $total_days = $this->calculate_days($start_date, $end_date);

        $allProducts = $this->getProducts();
        //echo "<pre>";print_r($allProducts);die;
        $allProducts = array_filter($allProducts,function( $item ) use( $countries ){
            return in_array($item['product_country'],$countries);
        });
        // Initialize an array to store product data
        $products = array();

        // Fix 3: Snapshot the cart once before the duplicate-check loop — avoids M×N get_cart() calls.
        $cart_before_add = WC()->cart->get_cart();

        // Loop through the query results to get product data
        if ( !empty( $allProducts ) ) {
            foreach( $allProducts as $product ) {
                $product_country = $product['product_country'];
                if( $this->is_product_in_cart( $product['product_id'], $start_date, $end_date, $cart_before_add ) ){
                    $message = __("You have already {$product['product_name']} in your cart or same timeline for the {$product['product_name']} product.",'woo-vignette');
                    wp_send_json_error(array('message' => $message));
                    wp_die();
                }
                if( $product['product_type'] == 'toll' && !empty($product['product_reference']) 
                    && in_array($product['product_country'],$countries) 
                    && in_array($product['product_reference'], $selected_reference )){
                    $products[$product['product_id']] = $this->add_to_cart_tolls($allProducts, $product['product_id'] ,$duration, $total_days);
                    continue;
                }
                $attributes = $product['attributes']??[];
                $match = false;
                foreach ($required_variations as $variation_key => $required_variation) {
                    if ( isset($attributes[$variation_key])) {
                        if (empty($attributes[$variation_key]) || $attributes[$variation_key] == "n-a" || $attributes[$variation_key] == $required_variation) {
                            $match = true;
                        } else {
                            $match = false;
                            break;
                        }
                    }
                }
                if ($match ) {
                    $products[$product['product_id']][] = $product;
                }
            }
        }
        //echo "<pre>";print_r($products);die;
        $products = $this->cheapest_vignette($products, $start_date, $end_date);

        //echo "<pre>";print_r($products);die;
        $cart_products = [];
        foreach ($products as $variations) {
            foreach ($variations as $variation) {
                if (!isset($cart_products[$variation['ID']])) {
                    $cart_products[$variation['ID']] = $variation;
                } else {
                    //echo "<pre>";print_r($cart_products[$variation['ID']]);die;
                    $cart_products[$variation['ID']]['quantity']++;
                    $cart_quantity = $cart_products[$variation['ID']]['quantity'];
                    if( !empty( $variation['validity'] ) ){
                        $cart_products[$variation['ID']]['validity'] = array_merge($cart_products[$variation['ID']]['validity'], $variation['validity']);
                    }else{
                        $cart_products[$variation['ID']]['validity'] = [];
                    }
                    $cart_products[$variation['ID']]['vignette_fee'] = $this->get_vignette_fee($variation['vignette_fee'],$cart_quantity);
                }
            }
        }
        foreach ($cart_products as $variation_id => $cart_product) {
            $_POST['product_variations'][$variation_id] = $cart_product['validity'];
            $_POST['vignette_name'][$variation_id] = $cart_product['vignette_name'];
            $_POST['period_type'][$variation_id] = $cart_product['period_type'];
            $_POST['product_country'][$variation_id] = $cart_product['product_country'];
            $this->add_item_to_cart($cart_product);
        }
        $this->update_optional_product_quantity(\Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_processing_product_id());
        // Fix 1 & 2: Single calculate_totals() + set_session() after ALL items are added.
        // Fix 6: Get cart once here and share it with add_data_to_cart_session() to avoid a second get_cart() call.
        $cart_after_add = WC()->cart->get_cart();
        $this->add_data_to_cart_session( $cart_after_add );
        WC()->cart->calculate_totals();
        WC()->cart->set_session();
        // Reset post data after custom query
        wp_reset_postdata();
        //echo "<pre>";print_r(WC()->cart->get_cart());die;
        // Check if we have any products, and return as JSON
        if (!empty($products) || WC()->cart) {
            //add_filter( 'woocommerce_add_cart_item_data', [$this,'wv_woocommerce_add_cart_item_data'], 10, 4 );
            wp_send_json_success([
                'redirect_url' => wc_get_cart_url(),
                'products' => $products,
                'post' => $_POST
            ]); // Send success response with product data
        } else {
            wp_send_json_error(array('message' => 'No products found.'));
        }
        
        
    }
    
    private function get_vignette_fee( $vignette_fee,$quantity ){
        $is_inclusive = self::is_prices_include_tax(); // Fix 9: cached, no DB hit.
        $fee = $vignette_fee['fee'];
        $tax_rates = $vignette_fee['tax_rates'];
        $base_price = $vignette_fee['base_price'];
        $price = floatval($vignette_fee['base_price']) * $quantity;
        $total_fee = $fee * $quantity;
        $taxes = \WC_Tax::calc_tax( $total_fee, $tax_rates, $is_inclusive );
        $tax_amount = array_sum( $taxes );
        if($is_inclusive ){
            $price_with_tax = $total_fee;
        }else{
            $price_with_tax = $price + $tax_amount;
        }
        return [
            'fee'               =>  $fee,
            'quantity'          =>  $quantity,
            'base_price'        =>  $base_price,
            'price'             =>  $price,
            'tax_rates'         =>  $tax_rates, 
            'tax_amount'        =>  $tax_amount,
            'price_with_tax'    =>  $price_with_tax
        ];
    }
    
    /** Fix 4: Single named filter callback — replaces anonymous function registered per-loop-iteration. */
    public function wv_add_cart_item_data_filter( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $this->pending_cart_item_data[ $variation_id ] ) ) {
            $cart_product                       = $this->pending_cart_item_data[ $variation_id ];
            $cart_item_data['validity']         = $cart_product['validity'];
            $cart_item_data['vignette_fee']     = $cart_product['vignette_fee'];
            $cart_item_data['vignette_name']    = $cart_product['vignette_name'];
            $cart_item_data['period_type']      = $cart_product['period_type'];
            $cart_item_data['product_country']  = $cart_product['product_country'];
            unset( $this->pending_cart_item_data[ $variation_id ] );
        }
        return $cart_item_data;
    }

    /** Fix 9: Read woocommerce_prices_include_tax once per request. */
    private static function is_prices_include_tax(): bool {
        if ( self::$prices_include_tax === null ) {
            self::$prices_include_tax = get_option( 'woocommerce_prices_include_tax' ) === 'yes';
        }
        return self::$prices_include_tax;
    }

    public function add_item_to_cart($cart_product)
    {
        // Check if the variation already exists in the cart
        $variation_id = $cart_product['ID'];
        $found = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['variation_id']) && $cart_item['variation_id'] == $variation_id) {
                foreach ($cart_product['validity'] as $validity) {
                    $start_date = $validity['start'];
                    $end_date = $validity['end'];
                    $exist_validity = array_filter($cart_item['validity'], function ($item) use ($start_date, $end_date) {
                        return $item['start'] == $start_date && $item['end'] == $end_date;
                    });
                    if (empty($exist_validity)) {
                        $cart_item['validity'][] = ['start' => $start_date, 'end' => $end_date];
                    }
                }
                // Update the quantity if the variation already exists
                // Replace the cart item
                $cart_quantity = count($cart_item['validity']);
                $cart_item['quantity'] = $cart_quantity;
                $cart_item['vignette_fee'] = $this->get_vignette_fee($cart_item['vignette_fee'],$cart_item['quantity']);
                WC()->cart->cart_contents[$cart_item_key] = $cart_item;
                // Fix 1 & 2: set_session() and calculate_totals() deferred to add_to_cart() — called once after all items.
                $found = true;
                break;
            }
        }

        // If the variation was not found in the cart, add it as a new item
        if (! $found) {
            // Fix 4: Store data for this variation; wv_add_cart_item_data_filter() (registered once in constructor) picks it up.
            $this->pending_cart_item_data[ $variation_id ] = $cart_product;
            WC()->cart->add_to_cart($variation_id, 1);
            // Fix 1 & 2: calculate_totals() / set_session() deferred to add_to_cart().
        }
    }

    public function woocommerce_add_to_cart_validation($passed, $product_id, $quantity, $variation_id)
    {
        // Get the variation attributes
        $product = wc_get_product($product_id);

        if ($product->is_type('variable')) {
            $variation = wc_get_product($variation_id);
            $attributes = $variation->get_attributes();

            // Check if attributes are missing and bypass validation
            foreach ($attributes as $key => $value) {
                if (empty($value)) {
                    $passed = true; // Allow adding to cart even if some attributes are missing
                }
            }
        }

        return $passed;
    }

    // Fix 6: Accept pre-fetched cart items to avoid an extra get_cart() call after the add loop.
    private function get_vehicle_type_from_cart( $cart_items = null ){
        $selected_variations = [];
        $variations = [];
        $items = $cart_items !== null ? $cart_items : WC()->cart->get_cart();
        foreach( $items as $cart_item_key => $cart_item ){
            if( !empty( $cart_item['variation'] ) ){
                foreach( $cart_item['variation'] as $attribute => $term_slug ){
                    $taxonomy = str_replace('attribute_','',$attribute);
                    $variations[$taxonomy] = $term_slug;
                }
            }
        }
        foreach( $variations as $taxnomy => $term_slug ){
            $selected_variations[$taxnomy] = $this->get_vehicle_type_translation($taxnomy,$term_slug);
        }
        return $selected_variations;
    }

    // Fix 6: Accept pre-fetched cart snapshot so get_vehicle_type_from_cart() needs no extra get_cart() call.
    public function add_data_to_cart_session( $cart_items = null )
    {
        $variations = $_POST['variations'];
        $selected_variations = $this->get_vehicle_type_from_cart( $cart_items );
        $dates = [];
        $cart_date = WC()->session->get('order_date');
        if (!empty($old_date)) {
            $dates['start_date'] = strtotime($cart_date['start_date']) < strtotime($_POST['wv_start_date']) ? $cart_date['start_date'] : $_POST['wv_start_date'];
            $dates['end_date'] = strtotime($cart_date['end_date']) > strtotime($_POST['wv_end_date']) ? $cart_date['end_date'] : $_POST['wv_end_date'];
        } else {
            $dates['start_date'] = $_POST['wv_start_date'];
            $dates['end_date'] = $_POST['wv_end_date'];
        }
        $countries = [];
        $cart_countries = WC()->session->get('countries');
        if( !empty( $cart_countries ) ){
            $countries = array_merge($countries,$_POST['countries']);
        }else{
            $countries = $_POST['countries'];
        }
        // Store data in WooCommerce session
        WC()->session->set('order_date', $dates);
        WC()->session->set('countries', $countries);
        WC()->session->set('selected_variations', $selected_variations);
    }

    private function cheapest_vignette($products, $start_date, $end_date)
    {
        $request_days = $this->calculate_days($start_date, $end_date); // Total requested days
        $vignette = [];
        
        foreach ($products as $variations) {
            // Organize variations by duration
            $available_variations = [];
            foreach ($variations as $variation) {
                if (!empty($variation['vignette_data'])) {
                    $available_variations[$variation['vignette_data']['days']] = $variation;
                }else{
                    array_push($vignette, [$variation]);
                }
            }
            // Calculate the cheapest combination
            $products = $this->find_cheapest_combination($available_variations, $request_days, $start_date);
            array_push($vignette, $products);
        }
        //echo "<pre>";print_r($vignette);die;
        return array_filter($vignette); // Remove null results
    }
    
    private function find_cheapest_combination($variations, $request_days, $start_date)
    {
        ksort($variations); // Sort variations by number of days (ascending)
        $best_combination = null;
        $available_days = array_keys($variations);
        //echo "<pre>";print_r($variations);die;
        $day = null;
        foreach ($available_days as $available_day) {
            $timeperiod = $variations[$available_day]['vignette_data']['period_type']??0;
            if ($request_days == $available_day || $timeperiod == 'indefinite' ) {
                $day = $available_day;
                break;
            } elseif ($request_days > $available_day) {
                $day = $available_day;
            } elseif ($request_days < $available_day) {
                $day = $available_day;
                break;
            }
        }
        if (isset($day)) {
            $variation = $variations[$available_day];
            $period_type = $variation['vignette_data']['period_type'];
            $variation['period_type'] = $period_type;
            $time_period = $variation['vignette_data']['time_period'];
            if( $period_type == 'indefinite' ){
                $period_type = 'year';
                $time_period = '100';
            }
            $vegnette_name = $variation['vignette_data']['name'];
            $end = date("Y-m-d", strtotime($start_date . " -1 days"));
            $end = date("Y-m-d", strtotime($end . " +$time_period $period_type" . "s"));
            $validity[] = [
                'start' =>  $start_date,
                'end'   =>  $end,
                'name'  =>  $vegnette_name
            ];
            $variation['validity'] = $validity;
            $best_combination[] = $variation;
        }
        return $best_combination;
    }

    // Fix 3: Accept pre-fetched $cart_items to avoid repeated get_cart() calls.
    // Fix 8: Precompute strtotime() for start/end outside the validity inner loop.
    private function is_product_in_cart( $product_id, $start_date = null, $end_date = null, $cart_items = null )
    {
        $flag = false;
        if (! WC()->cart) {
            return $flag;
        }
        $ts_req_start = $start_date ? strtotime( $start_date ) : null;
        $ts_req_end   = $end_date   ? strtotime( $end_date )   : null;
        $items        = $cart_items !== null ? $cart_items : WC()->cart->get_cart();

        foreach ( $items as $cart_item_key => $cart_item ) {
            if ( $ts_req_start && $ts_req_end && !empty($cart_item['validity']) && $cart_item['product_id'] == $product_id ) {
                foreach ( $cart_item['validity'] as $validity ) {
                    $ts_s = strtotime( $validity['start'] );
                    $ts_e = strtotime( $validity['end'] );
                    if ( ( $ts_s <= $ts_req_start && $ts_req_start <= $ts_e ) ||
                         ( $ts_s <= $ts_req_end   && $ts_req_end   <= $ts_e ) ) {
                        $flag = $cart_item_key;
                    }
                }
            } elseif ( ! $ts_req_start && ! $ts_req_end && $cart_item['product_id'] == $product_id ) {
                $flag = $cart_item_key;
            }
        }
        return $flag;
    }

    private function calculate_days($start_date, $end_date)
    {
        $start_date = strtotime($start_date);
        $end_date = strtotime($end_date);
        $total_days = (($end_date - $start_date) / DAY_IN_SECONDS) + 1;
        return $total_days;
    }

    private function getDaysByPeriod($period_type, $time_period)
    {
        $days = null;
        if( $time_period == -1 ){
            $days = -1;
        }
        switch (strtolower($period_type)) {
            case 'day':
                $days = $time_period;
                break;
            case 'week':
                $days = ($time_period * 7);
                break;
            case 'month':
                $days = ($time_period * 30);
                break;
            case 'year':
                $days = ($time_period * 365);
                break;
            default:
                $days = null;
                return;
        }
        return $days;
    }

    /**
     * Get singleton instance of Woo_Vignette_Order_Form class.
     *
     * @since 8/12/2019
     *
     * @return Woo_Vignette_Order_Form
     */

    public static function instance(): Woo_Vignette_Ajax
    {
        static $instance;

        if (null === $instance) {
            $instance = new self();
        }

        return $instance;
    }

    public function variation_fee_data( $variation_id, $country, $prefetched_meta = null )
    {
        // Fix 5: Use already-fetched meta array when available — avoids 3 extra DB queries
        // per variation. Callers that pass $variationData from get_post_meta() use this path.
        if ( $prefetched_meta !== null ) {
            $processing_fee = $prefetched_meta['_processing_fee'][0] ?? 0;
            $is_taxable     = $prefetched_meta['_is_taxable'][0] ?? false;
            $tax            = $prefetched_meta['_tax'][0] ?? '';
        } else {
            $all_meta       = get_post_meta( $variation_id );
            $processing_fee = $all_meta['_processing_fee'][0] ?? 0;
            $is_taxable     = $all_meta['_is_taxable'][0] ?? false;
            $tax            = $all_meta['_tax'][0] ?? '';
        }
        $processing_fee = !empty($processing_fee) ? $processing_fee : 0;
        $is_taxable = !empty($is_taxable) ? $is_taxable : false;
        if( !$is_taxable ){
            return [
                'fee'               =>  $processing_fee,
                'quantity'          =>  1,
                'base_price'        =>  $processing_fee,
                'tax_rates'         =>  [], 
                'price'             =>  $processing_fee,
                'tax_amount'        =>  0,
                'price_with_tax'    =>  $processing_fee
            ];
        }
        $tax = !empty($tax) ? $tax : '';
        $tax_amount = 0;
        $fee_amount = $processing_fee + $tax_amount;
        $fee = $this->calculate_tax($processing_fee,$tax,$country);
        return $fee;
    }
    
    public function calculate_tax($price, $tax_class,$country){
        $tax_class = strtolower(str_replace(' ','-',$tax_class));
        $tax_class = $tax_class == 'standard'?'':$tax_class;
        $args = [
            'tax_class' =>  $tax_class,
            'country'   =>  $country
            ];
        $tax_rates = \WC_Tax::find_rates( $args );
        if ( empty( $tax_rates ) ) {
            return [
                'fee'               =>  $price,
                'quantity'          =>  1,
                'base_price'        =>  $price,
                'tax_rates'         =>  $tax_rates,
                'price'             =>  $price,
                'tax_amount'        =>  0,
                'price_with_tax'    =>  $price
            ];
        }
        $is_inclusive = self::is_prices_include_tax(); // Fix 9: cached, no DB hit.
        $fee = $price;
        $base_price = floatval($price);
        $taxes = \WC_Tax::calc_tax( $price, $tax_rates, $is_inclusive );
        $tax_amount = array_sum( $taxes );
        if($is_inclusive ){
            $base_price -= $tax_amount;
            $price = $base_price;
            $price_with_tax = $fee;
        }else{
            $price = $base_price;
            $price_with_tax = $fee + $tax_amount;
        }
        return [
            'fee'               =>  $fee,
            'quantity'          =>  1,
            'base_price'        =>  $base_price,
            'tax_rates'         =>  $tax_rates,
            'price'             =>  $price,
            'tax_amount'        =>  $tax_amount,
            'price_with_tax'    =>  $price_with_tax
        ];

    }
    
    public function sidebar_popup_order_form(): string{
        try {
            $selected_countries = isset($_POST['countries']) ? sanitize_text_field($_POST['countries']) : '';
            $tolls = isset($_POST['tolls']) ? sanitize_text_field($_POST['tolls']) : '';
            $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';
            if ( ! WC()->session ) {
                wp_send_json_error(['message' => 'WooCommerce session not available.']);
            }
            $cart_variations = WC()->session->get('selected_variations');
            $params = [];
    
            if ( ! empty($selected_countries) ) {
                $params = [
                    'wv_order_form' => [
                        'selected_countries' => $selected_countries,
                        'tolls'              => $tolls,
                        'duration'           => $duration,
                        'cart_variations'    => $cart_variations
                    ]
                ];
            }
            //echo "<pre>";print_r($params);die;
            if ( ! method_exists($this, 'get_page_content_by_title_wpml') ) {
                wp_send_json_error(['message' => 'Required method not found: get_page_content_by_title_wpml.']);
            }
            $page_content = $this->get_page_content_by_title_wpml('order', $params, $this->current_lang);
            if ( empty($page_content) ) {
                wp_send_json_error(['message' => 'Failed to load page content.']);
            }
            wp_send_json_success(['html' => $page_content]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die();
    }


    public function show_minicart_form(): string {
        try {
            if ( ! function_exists( 'woocommerce_mini_cart' ) ) {
                wp_send_json_error(['message' => 'WooCommerce is not active or mini cart function is unavailable.']);
            }
            wp_send_json_success([ 'html' => $this->render_mini_cart_html() ]);
        } catch ( Exception $e ) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_die();
    }
    
    // Function to get page content by title with WPML support
    function get_page_content_by_title_wpml($page_title, $params = [], $language_code = null) {
        // Get the page object by title in the default language
        $page = get_page_by_title($page_title, OBJECT, 'page');
    
        if ($page) {
            // Get the translated page ID if WPML is active
            $translated_page_id = apply_filters('wpml_object_id', $page->ID, 'page', true, $language_code);
            $translated_page = get_post($translated_page_id);
    
            if ($translated_page) {
                // Get the raw content of the page
                $page_content = $translated_page->post_content;
                foreach($params as $shortcode_name => $atts){
                    $shortcode = "[$shortcode_name]";
                    if (strpos($page_content, $shortcode) !== false) {
                        // If found, replace the shortcode with modified content
                        $replacement = "[$shortcode_name ";
                
                        // Add parameters to the shortcode if provided
                        foreach ($atts as $att => $att_value) {
                            $replacement .= $att . '="' . esc_attr($att_value) . '" ';
                        }
                
                        // Close the shortcode
                        $replacement .= ']';
                
                        // Replace the original shortcode with the modified one
                        $page_content = str_replace($shortcode, $replacement, $page_content);
                
                        // Process the modified content with shortcodes
                    }
                }
                
                $page_content = do_shortcode($page_content);
                return $page_content;
            }
        }
    
        return 'Page not found.';
    }

}
