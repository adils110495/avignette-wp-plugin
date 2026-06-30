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
class Woo_Filters
{

    public function __construct()
    {
        add_filter('woocommerce_cart_item_subtotal',[$this,'update_line_item_subtotal'],10,3);
        add_filter('woocommerce_cart_item_subtotal_value',[$this,'update_line_item_subtotal_value'],10,3);
        add_action('woocommerce_checkout_create_order_line_item', [$this,'save_data_to_order_item'], 10, 4);
        add_action('woocommerce_cart_shipping_method_full_label', [$this,'cart_shipping_method_full_label'], 10, 2);
        add_filter('woocommerce_order_shipping_to_display_shipped_via', [$this,'display_shipped_via'], 10, 2);
        add_filter('woocommerce_cart_subtotal', [$this,'cart_subtotal'], 10, 3);
        add_filter('woocommerce_order_subtotal_to_display', [$this,'order_subtotal_to_display'], 10, 3);
        add_filter('woocommerce_calculated_total', [$this,'ensure_vignette_fees_in_total'], 20, 2);
    }
    
    public function order_subtotal_to_display( $subtotal, $compound, $order ){
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );
	    $subtotal    = (float) $order->get_subtotal();
	    
        // Loop through the fees and find your custom fee
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_name() === "Processing Fee") {
                $subtotal += $fee->get_total(); // Get the fee amount
                break; // Exit loop once custom fee is found
            }
        }
	    if ( ! $compound ) {
    
	    	if ( 'incl' === $tax_display ) {
	    		$subtotal_taxes = 0;
	    		foreach ( $order->get_items() as $item ) {
	    			$subtotal_taxes += wc_round_tax_total( (float) $item->get_subtotal_tax(), 2 );
	    		}
	    		$subtotal += wc_round_tax_total( $subtotal_taxes,2 );
	    	}
    
	    	$subtotal = wc_price( $subtotal, array( 'currency' => $order->get_currency() ) );
    
	    	if ( 'excl' === $tax_display && $order->get_prices_include_tax() && wc_tax_enabled() ) {
	    		$subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
	    	}
	    } else {
	    	if ( 'incl' === $tax_display ) {
	    		return '';
	    	}
    
	    	// Add Shipping Costs.
	    	$subtotal += (float) $order->get_shipping_total();
    
	    	// Remove non-compound taxes.
	    	foreach ( $order->get_taxes() as $tax ) {
	    		if ( $tax->is_compound() ) {
	    			continue;
	    		}
	    		$subtotal = $subtotal + (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();
	    	}
    
	    	// Remove discounts.
	    	$subtotal = $subtotal - (float) $order->get_total_discount();
	    	$subtotal = wc_price( $subtotal, array( 'currency' => $order->get_currency() ) );
	    }
	    
	    return $subtotal;
    }
    
    public function cart_subtotal( $subtotal, $compound, $cart ){
        $is_inclusive = false;
        $prices_entered_with_tax = get_option( 'woocommerce_prices_include_tax' );
        if ( 'yes' === $prices_entered_with_tax ) {
            $is_inclusive = true;
        }
        $processing_fee = 0;
        foreach( $cart->get_cart() as $cart_item ){
            if( !empty( $cart_item['vignette_fee'] ) ){
                if( $is_inclusive ){
                    $processing_fee += $cart_item['vignette_fee']['price_with_tax']??0;
                }else{
                    $processing_fee += $cart_item['vignette_fee']['price']??0;
                }
            }
        }
        if ( $compound ) {
	    	$subtotal = $cart->get_cart_contents_total() + $cart->get_shipping_total() + $cart->get_taxes_total( false, false );
	    	$subtotal += $processing_fee;
	    	$subtotal = wc_price( $subtotal );
    
	    } elseif ( $cart->display_prices_including_tax() ) {
	    	$subtotal = $cart->get_subtotal() + $cart->get_subtotal_tax();
	    	$subtotal += $processing_fee;
	    	$subtotal = wc_price( $subtotal );
    
	    	if ( $cart->get_subtotal_tax() > 0 && ! wc_prices_include_tax() ) {
	    		$subtotal .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
	    	}
	    } else {
	        $subtotal = $cart->get_subtotal();
	        $subtotal += $processing_fee;
	    	$subtotal = wc_price( $subtotal );
    
	    	if ( $cart->get_subtotal_tax() > 0 && wc_prices_include_tax() ) {
	    		$subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
	    	}
	    }
        
        return $subtotal;
    }
    public function ensure_vignette_fees_in_total( $total, $cart ) {
        // Sum vignette fees already added to WC via add_fee() (keyed by "Service Fee:" prefix).
        $already_added = 0.0;
        foreach ( $cart->get_fees() as $fee ) {
            if ( strpos( $fee->name, 'Service Fee:' ) === 0 ) {
                $already_added += floatval( $fee->amount );
            }
        }

        // Sum the expected vignette fees from cart item meta.
        $expected = 0.0;
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['vignette_fee']['price_with_tax'] ) ) {
                $expected += floatval( $cart_item['vignette_fee']['price_with_tax'] );
            }
        }

        // Only add what is missing — prevents double-counting when add_fee() already worked.
        $gap = $expected - $already_added;
        if ( $gap > 0.001 ) {
            $total += $gap;
        }

        return $total;
    }

    public function display_shipped_via($label, $method){
        return "";
    }
    
    public function cart_shipping_method_full_label($label, $method){
        $label     = $method->get_label();
	    $has_cost  = 0 < $method->cost;
	    $hide_cost = ! $has_cost && in_array( $method->get_method_id(), array( 'free_shipping', 'local_pickup' ), true );

	    if ( $has_cost && ! $hide_cost ) {
	    	if ( WC()->cart->display_prices_including_tax() ) {
	    		$label = wc_price( $method->cost + $method->get_shipping_tax() );
	    		if ( $method->get_shipping_tax() > 0 && ! wc_prices_include_tax() ) {
	    			$label .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
	    		}
	    	} else {
	    		$label .= ': ' . wc_price( $method->cost );
	    		if ( $method->get_shipping_tax() > 0 && wc_prices_include_tax() ) {
	    			$label .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
	    		}
	    	}
	    }
	    return $label;
    }
    
    public function update_line_item_subtotal( $subtotal, $cart_item, $cart_item_key ){
        if( isset( $cart_item['vignette_fee']) && isset($cart_item['vignette_fee']['price_with_tax']) ){
            $subtotal = floatval($cart_item['line_total']) + floatval($cart_item['line_tax']) + floatval( $cart_item['vignette_fee']['price_with_tax'] );
        }else{
            $subtotal = floatval($cart_item['line_total']) + floatval($cart_item['line_tax']);
        }
        return wc_price( $subtotal );
    }
    
    public function update_line_item_subtotal_value( $subtotal, $cart_item, $cart_item_key ){
        if( isset( $cart_item['vignette_fee']) && isset($cart_item['vignette_fee']['price_with_tax']) ){
            $subtotal = $cart_item['line_total'] + $cart_item['line_tax'] + $cart_item['vignette_fee']['price_with_tax'];
        }else{
            $subtotal = $cart_item['line_total'] + $cart_item['line_tax'];
        }
        return $subtotal;
    }
    
    function save_data_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['validity'])) {
            $item->add_meta_data('validity', $values['validity']);
        }
        if (isset($values['vignette_name'])) {
            $item->add_meta_data('vignette_name', $values['vignette_name']);
        }
        if (isset($values['vignette_fee'])) {
            $item->add_meta_data('vignette_fee', $values['vignette_fee']);
        }
        if (isset($values['period_type'])) {
            $item->add_meta_data('period_type', $values['period_type']);
        }
        if (isset($values['product_country'])) {
            $item->add_meta_data('product_country', $values['product_country']);
        }
    }
	/**
	 * Get singleton instance of Woo_Filters class.
	 *
	 * @since 8/12/2019
	 *
	 * @return Woo_Filters
	 */
	public static function instance(): Woo_Filters {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
