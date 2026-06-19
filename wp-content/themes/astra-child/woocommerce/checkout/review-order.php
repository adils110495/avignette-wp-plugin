<?php
/**
 * Review order table
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 5.2.0
 */

defined( 'ABSPATH' ) || exit;
$optional_product_id = 3413;

// Get the current language using WPML.
$current_language = apply_filters( 'wpml_current_language', null );

// Get the translated product ID for the current language.
$optional_product_id = icl_object_id($optional_product_id, 'product', false, $current_language);
$optional_product = wc_get_product($optional_product_id);
$specifications = [];
$optional_cart_key = '';
$astra_setting = get_option('astra-settings');
?>
<?php if (! WC()->cart->is_empty()) : ?>
<table class="shop_table woocommerce-checkout-review-order-table">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
			<th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		    if( $optional_product_id == $_product->get_id() ){
		        $optional_cart_key = $cart_item_key;
		    }
		    
		    $product_name = wp_kses_post( apply_filters( 'wv_specification_item_name', $_product->get_name(), $cart_item ) ) . '&nbsp;';
		    $product_total = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
		    $vignette_price = isset($cart_item['vignette_fee']['price_with_tax'])?$cart_item['vignette_fee']['price_with_tax']:0;
		    $specification['name'] = $product_name;
			$specification['quantity'] = $cart_item['quantity'];
			$specification['line_subtotal'] = $cart_item['line_subtotal'];
			$specification['vignette_price'] = $vignette_price;
			$specification['line_total'] = $product_total;
			$specifications[] = $specification;

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				?>
				<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-name">
					    <?php 
					    $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
					    if( isset( $astra_setting['checkout-order-review-product-images'] ) && $astra_setting['checkout-order-review-product-images'] ){
                            $thumbnail = '';
                        }else{
                            $thumbnail = $_product->get_image();
                        }
					    ?>
					    <?php if(!is_checkout() && !empty($thumbnail) ): ?>
					        <div class="ast-product-thumbnail"> <?= $thumbnail?> </div>
					    <?php endif; ?>
						<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;';; ?>
						<?php //echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php //echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
					<td class="product-total">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<?php
			}
		}
		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<!--<tr class="cart-subtotal">
			<th><?php //esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php //wc_cart_totals_subtotal_html(); ?></td>
		</tr>-->

		<!--<?php //foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php //echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php //wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php //wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php //endforeach; ?>-->

		<?php //if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php //do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php //wc_cart_totals_shipping_html(); ?>

			<?php //do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php //endif; ?>

		<?php //foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<!--<tr class="fee">
				<th><?php //echo esc_html( $fee->name ); ?></th>
				<td><?php //wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>-->
		<?php //endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total">
			<th style="display: flex;justify-content: space-between;">
				<a href="#order-form" class="sidebar-popup-order-form" style="text-decoration:underline">
					<?php esc_html_e( 'Add Product', 'woocommerce' ); ?>
				</a>
				<?php esc_html_e( 'Total', 'woocommerce' ); ?>
			</th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>
		<tr class="vignette-specification">
		    <td colspan="2" style="display: flex;justify-content: space-between;">
		        <?php if ( \Woo_Vignette\admin\includes\Woo_Vignette_Settings::is_rapid_processing_fee_enabled() ) : ?>
		        <label>
		            <input type="checkbox" id="optional-product" <?= !empty($optional_cart_key)?'checked':'';?> data-cart_item_key="<?= $optional_cart_key?>" data-product_id="<?= $optional_product->get_id()?>"><?= $optional_product->get_name() . " ( ".wc_price( $optional_product->get_price() )." )"?>
		        </label>
		        <?php else : ?>
		        <span></span>
		        <?php endif; ?>
				<td><p id="show-specification" style="display:flex"><?= __('Specification')?> <i class="dashicons dashicons-arrow-down-alt"></i></p></td>
		    </td>
		</tr>
		<tr class="specification-data" style="display:none">
		    <td colspan="2">
		        <?= wc_get_template('cart/specification.php',['specifications'=>$specifications]); ?>
		    </td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>
<?php endif;?>