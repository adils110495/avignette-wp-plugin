<?php

/**
 * Order details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.0.0
 *
 * @var bool $show_downloads Controls whether the downloads table should be rendered.
 */

// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment

defined('ABSPATH') || exit;

$order = wc_get_order($order_id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$custom_order_fields = get_post_meta($order_id);
//echo "<pre>";print_r($custom_order_fields);die;

if (! $order) {
	return;
}

$order_items        = $order->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));
$show_purchase_note = $order->has_status(apply_filters('woocommerce_purchase_note_order_statuses', array('completed', 'processing')));
$downloads          = $order->get_downloadable_items();

// We make sure the order belongs to the user. This will also be true if the user is a guest, and the order belongs to a guest (userID === 0).
$show_customer_details = $order->get_user_id() === get_current_user_id();

if ($show_downloads) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}
?>

<section class="woocommerce-applicaiton-details ">
	<div class="div-50-width-percent">
		<table>
			<tr>
				<th><?= __('Application Number', 'woocommerce') ?></th>
				<td><?= $order->get_id() ?></td>
			</tr>
			<?php if (isset($custom_order_fields['start_date'])): ?>
			<tr>
				<th><?= __('Strat Date', 'woocommerce') ?></th>
				<td><?php echo  date('d M Y', strtotime($custom_order_fields['start_date'][0]))?></td>
			</tr>
			<?php endif;?>
			<?php if (isset($custom_order_fields['end_date'])): ?>
			<tr>
				<th><?= __('End Date', 'woocommerce') ?></th>
				<td><?php echo  date('d M Y', strtotime($custom_order_fields['end_date'][0]))?></td>
			</tr>
			<?php endif;?>
			<tr>
				<th><?= __('Your Name', 'woocommerce') ?></th>
				<td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
			</tr>
			<tr>
				<th><?= __('Email Address', 'woocommerce') ?></th>
				<td><?php echo esc_html($order->get_billing_email()); ?></td>
			</tr>
		</table>
		<h4><?= __('Vehicle Details', 'woocommerce') ?> </h4>
		<table>
			<?php if (isset($custom_order_fields['country_registration'])): ?>
				<tr>
					<th><?= __('Country Of Registration Vehicle', 'woocommerce') ?></th>
					<td><?php echo  $custom_order_fields['country_registration'][0] ?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($custom_order_fields['license_plate'])): ?>
				<tr>
					<th><?= __('Licence Plate', 'woocommerce') ?></th>
					<td><?php echo $custom_order_fields['license_plate'][0]?></td>
				</tr>
			<?php endif; ?>
			<?php if (isset($custom_order_fields['vin'])): ?>
				<tr>
					<th><?= __('Vehicle Identification Number', 'woocommerce') ?></th>
					<td><?php echo $custom_order_fields['vin'][0]?></td>
				</tr>
			<?php endif; ?>
		</table>
	</div>
	<div class="div-50-width-percent">
		<h4><?= __('Application Completed', 'woocommerce') ?></h4>
		<p><?= __('Your applicaiton has been completed.Below you can download the digital
		vignettes and invoice. Sticker vignettes and environmental stickers will be sent by post.', 'woocommerce') ?></p>
		<h4><?= __('Documents', 'woocommerce') ?></h4>
		<?php $pdf_link = do_shortcode('[wcpdf_document_link]'); ?>
		<p>
			<?= __('Invoice', 'woocommerce') ?>
			<a href="<?= $pdf_link ?>" download="<?php echo "Invoice_VIG" . $order->get_id() . ".pdf"; ?>">
				<span class="dashicons dashicons-download"></span>
				<?php echo "Invoice_VIG" . $order->get_id() . ".pdf"; ?>
			</a>
		</p>

		<?php
		foreach ($order_items as $item_id => $item) {
			$item_file = get_post_meta($item_id, '_uploaded_file', true);

			// Check if the file exists
			if (!empty($item_file)) {
		?>
				<p>
					Vignette
					<a href="<?= $item_file ?>" download>
						<span class="dashicons dashicons-download"></span> <?= basename($item_file) ?>
					</a>
				</p>
		<?php
			}
		}
		?>

	</div>
</section>

<section class="woocommerce-order-details">
	<?php //do_action('woocommerce_order_details_before_order_table', $order); 
	?>

	<h2 class="woocommerce-order-details__title"><?php esc_html_e('Products', 'woocommerce'); ?></h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php esc_html_e('Name', 'woocommerce'); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			do_action('woocommerce_order_details_before_order_table_items', $order);
			foreach ($order_items as $item_id => $item) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action('woocommerce_order_details_after_order_table_items', $order);
			?>
		</tbody>

		<tfoot>
			<?php
			/* foreach ($order->get_order_item_totals() as $key => $total) {
				if ("cart_subtotal" != $key):
			?>
					<tr>
						<th scope="row" <?= $total['label'] . $key ?>><?php echo esc_html($total['label']); ?></th>
						<td><?php echo wp_kses_post($total['value']); ?></td>
					</tr>
			<?php
				endif;
			} */
			?>
			<?php if ($order->get_customer_note()) : ?>
				<tr>
					<th><?php esc_html_e('Note:', 'woocommerce'); ?></th>
					<td><?php echo wp_kses(nl2br(wptexturize($order->get_customer_note())), array()); ?></td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

	<?php //do_action('woocommerce_order_details_after_order_table', $order); 
	?>

</section>

<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
do_action('woocommerce_after_order_details', $order);

/* if ($show_customer_details) {
	wc_get_template('order/order-details-customer.php', array('order' => $order));
} */
?>
<!-- <?php if ($custom_order_fields) : ?>
	<h2 class="woocommerce-order-additional-fields-title"><?php esc_html_e('Additional fields', 'woocommerce'); ?></h2>
	<table>
		<thead></thead>
		<tbody>
			<?php foreach ($custom_order_fields as $key => $values) : ?>
				<?php if (!empty($values)) : ?>
					<tr>
						<th>
							<?php
							$formatted_key = str_replace('_', ' ', $key);
							echo esc_html(ucwords($formatted_key));
							?>:
						</th>
						<td>
							<?php
							$first_value = is_array($values) ? reset($values) : $values;
							if ($key === 'registration_date' && strtotime($first_value)) {
								echo esc_html(date('d-m-Y', strtotime($first_value)));
							} elseif ($key === 'vehicle_scan_registration_document' && !empty($first_value) && filter_var($first_value, FILTER_VALIDATE_URL) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $first_value)) {
								echo '<img src="' . esc_url($first_value) . '" alt="Vehicle Registration Document" style="max-width: 300px; height: auto;" />';
							} else {
								echo esc_html($first_value);
							}
							?>
						</td>
					</tr>
				<?php endif; ?>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?> -->