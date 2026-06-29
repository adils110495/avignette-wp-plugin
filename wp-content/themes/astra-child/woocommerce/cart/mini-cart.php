<?php

/**
 * Mini-cart in Table Format
 */

defined('ABSPATH') || exit;

$rapid_fee_enabled   = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::is_rapid_processing_fee_enabled();
$optional_product_id = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_rapid_processing_product_id();

// Translate to current language when WPML is active.
if ( $rapid_fee_enabled && $optional_product_id && function_exists( 'icl_object_id' ) ) {
    $current_language    = apply_filters( 'wpml_current_language', null );
    $optional_product_id = icl_object_id( $optional_product_id, 'product', false, $current_language );
}

$optional_product = $rapid_fee_enabled && $optional_product_id ? wc_get_product( $optional_product_id ) : null;
$optional_cart_key = '';
$specifications = [];
do_action('woocommerce_before_mini_cart');

// Prime post meta cache for all cart products and their variations in one query.
if ( ! WC()->cart->is_empty() ) {
    $_all_ids = [];
    foreach ( WC()->cart->get_cart() as $_ci ) {
        $_all_ids[] = $_ci['product_id'];
        if ( ! empty( $_ci['variation_id'] ) ) {
            $_all_ids[] = $_ci['variation_id'];
        }
    }
    if ( $_all_ids ) {
        update_postmeta_cache( array_unique( $_all_ids ) );
    }
    unset( $_all_ids, $_ci );
}
?>

<?php if (! WC()->cart->is_empty()) : ?>

	<table class="woocommerce-mini-cart cart_list product_list_widget <?php echo esc_attr($args['list_class']); ?>" cellspacing="0">
		<thead>
			<tr>
				<th><?php esc_html_e('Product', 'woocommerce'); ?></th>
				<th><?php esc_html_e('Total', 'woocommerce'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			do_action('woocommerce_before_mini_cart_contents');
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				$_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
				$product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
				if ($optional_product_id == $_product->get_id()) {
					$optional_cart_key = $cart_item_key;
				}
				if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key)) {
					$product_name      = apply_filters('wv_specification_item_name', $_product->get_name(), $cart_item);
					$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
					$product_price     = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
					$subtotal          = WC()->cart->get_product_subtotal($_product, $cart_item['quantity']);
					$product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);

					$product_total          = apply_filters('woocommerce_cart_item_subtotal', $subtotal, $cart_item, $cart_item_key); // PHPCS: XSS ok.
					$cart_item_display_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
					$vignette_price = isset($cart_item['vignette_fee']['price_with_tax']) ? $cart_item['vignette_fee']['price_with_tax'] : 0;
					$specification['name'] = $product_name;
					$specification['quantity'] = $cart_item['quantity'];
					$specification['line_subtotal'] = $cart_item['line_subtotal'];
					$specification['vignette_price'] = $vignette_price;
					$specification['line_total'] = $product_total;
					$specifications[] = $specification;
			?>
					<tr class="woocommerce-mini-cart-item <?php echo esc_attr(apply_filters('woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key)); ?>">
						<td>
						    <div class="ast-product-thumbnail"> <?= $thumbnail?> </div>
							<?php echo wp_kses_post($cart_item_display_name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td>
							<?php echo $product_total; // PHPCS: XSS ok. ?>
						</td>
					</tr>
			<?php
				}
			}
			do_action('woocommerce_mini_cart_contents');
			?>
			<tr>
				<td>
					<p class="woocommerce-mini-cart__total total">
						<b>Total :</b> <?php
					$_mini_cart_total = 0;
					foreach ( WC()->cart->get_cart() as $_mct_item ) {
						$_mini_cart_total += floatval( $_mct_item['line_total'] ) + floatval( $_mct_item['line_tax'] );
						if ( isset( $_mct_item['vignette_fee']['price_with_tax'] ) ) {
							$_mini_cart_total += floatval( $_mct_item['vignette_fee']['price_with_tax'] );
						}
					}
					echo wc_price( $_mini_cart_total );
					?>
					</p>
					<?php do_action('woocommerce_widget_shopping_cart_before_buttons'); ?>
					<p class="woocommerce-mini-cart__buttons buttons"><?php do_action('woocommerce_widget_shopping_cart_buttons'); ?></p>
					<?php do_action('woocommerce_widget_shopping_cart_after_buttons'); ?>
				<?php else : ?>
					<p class="woocommerce-mini-cart__empty-message"><?php esc_html_e('No products in the cart.', 'woocommerce'); ?></p>

					<a href="#order-form" class="sidebar-popup-order-form" style="text-decoration:underline">
						<?php esc_html_e( 'Add Product', 'woocommerce' ); ?>
					</a>
					<?php endif; ?>

				<?php do_action('woocommerce_after_mini_cart'); ?>
				<?php do_action('woocommerce_review_order_after_order_total'); ?>
				</td>
			</tr>
		</tbody>
		<tfoot>
			<?php if (! WC()->cart->is_empty()) : ?>
				<?php if ( $rapid_fee_enabled && $optional_product ) : ?>
				<tr>
					<td colspan="2">
						<label>
							<input type="checkbox" id="optional-product" <?= !empty($optional_cart_key) ? 'checked' : ''; ?> data-cart_item_key="<?= $optional_cart_key ?>" data-product_id="<?= $optional_product->get_id() ?>"><?= $optional_product->get_name() . " ( " . wc_price($optional_product->get_price()) . " )" ?>
						</label>
					</td>
				</tr>
				<?php endif; ?>
				<tr class="vignette-specification">
					<th>&nbsp;</th>
					<td>
						<p id="show-specification" style="display:flex" data-specifications="<?= esc_attr( wp_json_encode( $specifications ) ); ?>"><?= ('Specification') ?> <i class="dashicons dashicons-arrow-down-alt"></i></p>
					</td>
				</tr>
				<tr class="specification-data" style="display:none">
					<td colspan="2" class="specification-data-cell"></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>