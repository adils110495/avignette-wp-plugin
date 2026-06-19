<?php

/**
 *
 * @package Woo_Vignette\includes\core
 */

namespace Woo_Vignette\includes\core;

/**
 * Base shortcode.
 */
class Woo_Vignette_Base
{
    private $current_lang = 'en';
    private $has_ploylang = false;
    private $has_wpml = false;
    
    public function __construct(){
        if (function_exists("pll_get_post_language")) {
            $this->has_ploylang = true;
            $this->current_lang = pll_current_language( 'slug' );
        }
        if( function_exists("icl_get_languages")){
            $this->has_wpml = true;
            $this->current_lang = apply_filters( 'wpml_current_language', NULL );
        }
    }
    
    private function get_all_product_available_countries_polylang()
    {
        global $wpdb;

        if (!function_exists('pll_get_post_language')) {
            return "Polylang is not active.";
        }
        $results = $wpdb->get_results("
            SELECT DISTINCT t.slug, t.name 
            FROM {$wpdb->prefix}term_relationships tr
            INNER JOIN {$wpdb->prefix}term_taxonomy tt
                ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t
                ON tt.term_id = t.term_id
            INNER JOIN {$wpdb->prefix}posts p
                ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'language'
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
        ");
        $languages = [];
        foreach ($results as $result) {
            $languages[$result->slug] = $result->name;
        }
        return $languages;
    }
    
    public function get_wv_countries(){
        $args = array(
            'post_type'      => 'country', // Replace with your CPT slug
            'posts_per_page' => -1
        );
    
        $query = new \WP_Query($args);
    
        if (!$query->have_posts()) {
            return [];
        }
    
        $countries = [];
        foreach ($query->posts as $post) {
            $country_code = get_post_meta($post->ID, '_country_code', true);
            if ($country_code) {
                $countries[$country_code] = $post->post_title;
            }
        }
        wp_reset_postdata(); // Reset post data
        
        return $countries;
    }
    
    private function get_post_titles_by_country_codes($country_codes) {
        if (empty($country_codes) || !is_array($country_codes)) {
            return [];
        }
    
        $args = array(
            'post_type'      => 'country', // Replace with your CPT slug
            'posts_per_page' => -1,       // Retrieve all matching posts
            'fields'         => 'ids', 
            'meta_query'     => array(
                array(
                    'key'     => '_country_code', // The custom field key
                    'value'   => $country_codes, // The array of country codes
                    'compare' => 'IN'
                )
            )
        );
    
        $query = new \WP_Query($args);
    
        // Return titles with country codes as keys
        if (!$query->have_posts()) {
            return [];
        }
    
        $countries = [];
        foreach ($query->posts as $post_id) {
            $country_code = get_post_meta($post_id, '_country_code', true);
            if ($country_code) {
                $countries[$country_code] = get_the_title($post_id);
            }
        }
        wp_reset_postdata(); // Reset post data
        
        return $countries;
    }
    private function get_all_product_available_countries_wpml()
    {
        if (!function_exists('icl_get_languages')) {
            return "Wpml Plugin is not active.";
        }
        global $wpdb;
        $language_codes = $wpdb->get_col( 
            $wpdb->prepare(
                    "SELECT DISTINCT pm.meta_value 
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s
                    AND p.post_type = %s
                    AND p.post_status = 'publish'",
                    '_wv_country', 'product'
                )
        );
        
        $countries = $this->get_post_titles_by_country_codes($language_codes);
        return $countries;
    }

    public function get_available_countries()
    {
        if ( $this->has_ploylang ) {
            return $this->get_all_product_available_countries_polylang();
        }elseif(  $this->has_wpml ){
            return $this->get_all_product_available_countries_wpml();
        }
        return [];
    }
    
    private function get_product_in_polylang( $languages = [] ){
        global $wpdb;
        $where = '';
        $total_country = count($languages);
        if ($total_country == 1) {
            $languages = implode("','", $languages);
            $where = " AND t.slug = '$languages'";
        } elseif ($total_country > 1) {
            $languages = implode("','", $languages);
            $where = " AND t.slug IN('$languages')";
        }
        
        $results = $wpdb->get_results("
            SELECT p.* 
            FROM {$wpdb->prefix}term_relationships tr
            INNER JOIN {$wpdb->prefix}term_taxonomy tt
                ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t
                ON tt.term_id = t.term_id
            INNER JOIN {$wpdb->prefix}posts p
                ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'language'
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
                $where
        ");
        return $results;
    }

    private function get_product_in_wpml( $languages = [] ){
        if( empty( $languages ) ){
            return [];
        }
        $args = array(
            'post_type'        => 'product',
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'suppress_filters' => true, // bypass WPML language filter — we want all language versions
            'meta_query'       => array(
                'relation' => 'AND',
                array(
                    'key'     => '_wv_country',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_wv_country',
                    'value'   => $languages,
                    'compare' => 'IN',
                ),
            ),
        );
        $query = new \WP_Query( $args);
        $results = [];
        // Check if products exist and loop through them
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = get_post( get_the_ID() );
            }
            wp_reset_postdata();  // Reset the global $post object after the loop
        } 
        return $results;
    }
    
    public function get_all_variations(){
        // Get all product attribute taxonomies (those prefixed with 'pa_')
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attribute_taxonomies_slugs = [];
        $attributes_terms = [];
        // Collect the taxonomies for product attributes
        foreach ($attribute_taxonomies as $attribute) {
            $attributes_terms[$attribute->attribute_name]['name'] = $attribute->attribute_label;
            $attribute_taxonomies_slugs[] = 'pa_' . $attribute->attribute_name;
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
                $attributes_terms[$attribute_name]['terms'][] = $term;
            }
        }
        if( isset( $attributes_terms['e-vignette'] ) ){
            unset($attributes_terms['e-vignette']);
        }
        return $attributes_terms;
    }
    
    public function get_default_variation(){
        $attribute = 'vehicle-type';
        $taxonomy = 'pa_'.$attribute; // Replace with the attribute slug
        $terms = get_terms(array(
            'taxonomy' => $taxonomy, // e.g., pa_color, pa_size, etc.
            'orderby' => 'name', // Order terms by name
            'order'   => 'ASC',  // Order ascending
        ));
        if (!empty($terms) && !is_wp_error($terms)) {
            $attribute_detail = get_taxonomy($taxonomy);
            $attribute_name = !empty($attribute_detail)?$attribute_detail->labels->singular_name:$attribute;
            $variations[$attribute]['variation_name'] = $attribute_name;
            foreach ($terms as $term) {
                $itemData = [];
                $itemData['name'] = $term->name;
                $itemData['value'] = $term->slug;
                $itemData['description'] = $term->description;
                $custom_meta = get_term_meta( $term->term_id );
                unset($custom_meta['order']);
                if( !empty($custom_meta) ){
                    $itemData['fields'] = $custom_meta;
                }
                $variations[$attribute]['items'][] = $itemData;
            }
        }
        return $variations;
    }
    
    public function getVariations($languages = [], $defaultAttributes = [])
    {
        if( empty( $languages ) ){
            return $this->get_default_variation();
        }

        // Fix 4: Transient cache — avoids repeating expensive product + variation queries
        // on every get_variations AJAX call. Keyed by country list + selected attributes.
        $cache_key = 'wv_base_variations_' . md5( serialize( $languages ) . serialize( $defaultAttributes ) );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        if ( $this->has_ploylang ) {
            $results = $this->get_product_in_polylang();
        }elseif(  $this->has_wpml ){
            $results = $this->get_product_in_wpml($languages);
        }
        //echo "<pre>";print_r($results);die;
        $product_attributes = [];
        $attributes_values = [];
        $firstKey = '';
        $firstElement = '';
        if( !empty( $defaultAttributes ) ){
            $firstKey = array_key_first($defaultAttributes);
            $firstElement = $defaultAttributes[$firstKey];
        }
        $variation_order = ['vehicle-type','fuel-type','weight'];
        if (!empty($results)) {
            global $wpdb;

            // Fix 6a: Get product types for all results in ONE query via product_type taxonomy
            // (WooCommerce stores type as a taxonomy term, not in postmeta).
            $product_ids = array_map( fn( $p ) => (int) $p->ID, $results );
            $ids_in      = implode( ',', $product_ids );
            $type_rows   = $wpdb->get_results(
                "SELECT tr.object_id as post_id, t.slug as product_type
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE tt.taxonomy = 'product_type'
                 AND tr.object_id IN ({$ids_in})"
            );
            $product_types = [];
            foreach ( $type_rows as $row ) {
                $product_types[ (int) $row->post_id ] = $row->product_type;
            }

            $variable_ids = array_filter( $product_ids, fn( $id ) => ( $product_types[ $id ] ?? '' ) === 'variable' );

            if ( ! empty( $variable_ids ) ) {
                $var_ids_in = implode( ',', array_map( 'intval', $variable_ids ) );

                // Fix 6b: Batch-fetch variation attributes for all variable products in ONE query
                // — replaces get_available_variations() per product.
                $attr_rows = $wpdb->get_results(
                    "SELECT p.ID as variation_id, p.post_parent, pm.meta_key, pm.meta_value
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = 'product_variation'
                     AND p.post_status = 'publish'
                     AND p.post_parent IN ({$var_ids_in})
                     AND pm.meta_key LIKE 'attribute_pa_%'
                     ORDER BY p.menu_order ASC"
                );

                $attrs_by_product = [];
                foreach ( $attr_rows as $row ) {
                    $attrs_by_product[ (int) $row->post_parent ][ (int) $row->variation_id ][ $row->meta_key ] = $row->meta_value;
                }

                foreach ( $results as $post ) {
                    $post_id = (int) $post->ID;
                    if ( ( $product_types[ $post_id ] ?? '' ) !== 'variable' ) {
                        continue;
                    }
                    foreach ( $attrs_by_product[ $post_id ] ?? [] as $variation_id => $attributes ) {
                        if ( ! empty( $firstKey ) && ! (
                            ! empty( $attributes[ 'attribute_' . $firstKey ] )
                            && $attributes[ 'attribute_' . $firstKey ] === $firstElement
                        ) ) {
                            continue;
                        }
                        unset( $attributes['attribute_pa_e-vignette'] );
                        foreach ( $attributes as $key => $attribute ) {
                            $key = str_replace( 'attribute_pa_', '', $key );
                            $product_attributes[ $key ] = $key;
                            $attributes_values[ $key ][ $attribute ] = $attribute;
                        }
                    }
                }
            }
        }

        $variations        = $this->get_all_variations();
        $ordered_variations = [];

        // Fix 6c: Prime term meta cache for ALL terms in ONE query — replaces get_term_meta() per term.
        $all_term_ids = [];
        foreach ( $variation_order as $variation ) {
            if ( isset( $variations[ $variation ]['terms'] ) ) {
                foreach ( $variations[ $variation ]['terms'] as $item ) {
                    $all_term_ids[] = $item->term_id;
                }
            }
        }
        if ( ! empty( $all_term_ids ) ) {
            \update_meta_cache( 'term', $all_term_ids );
        }

        foreach ($variation_order as $variation) {
            if (isset($variations[$variation]) && isset($product_attributes[$variation])) {
                $ordered_variations[$variation]['variation_name'] = $variations[$variation]['name'];
                foreach ($variations[$variation]['terms'] as $item) {
                    if( !empty ($firstKey) && "pa_".$variation != $firstKey && !isset( $attributes_values[$variation][$item->slug] ) ){
                        continue;
                    }
                    $custom_meta = get_term_meta( $item->term_id ); // uses primed cache — no DB hit
                    unset($custom_meta['order']);
                    $ordered_variations[$variation]['items'][] = [
                        'name'        => $item->name,
                        'value'       => $item->slug,
                        'description' => $item->description,
                        'fields'      => $custom_meta,
                    ];
                }
            }
        }
        set_transient( $cache_key, $ordered_variations, 10 * MINUTE_IN_SECONDS );
        return $ordered_variations;
    }

    public static function instance(): Woo_Vignette_Base {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
