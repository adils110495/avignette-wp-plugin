<?php if (! defined('ABSPATH')) exit; // Exit if accessed directly 
$order = wc_get_order($order_id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$custom_order_fields = get_post_meta($order_id);
?>


<?php do_action('wpo_wcpdf_before_document', $this->get_type(), $this->order); ?>

<table class="head container">
    <tr>
        <td class="header">
            <?php
            if ($this->has_header_logo()) {
                do_action('wpo_wcpdf_before_shop_logo', $this->get_type(), $this->order);
                $this->header_logo();
                do_action('wpo_wcpdf_after_shop_logo', $this->get_type(), $this->order);
            } else {
                $this->title();
            }
            ?>
        </td>
        <td class="shop-info">
            <?php if ($this->has_header_logo()) : ?>
                <h1 class="document-type-label"><?php $this->title(); ?></h1>
            <?php endif; ?>
        </td>
    </tr>
    <tr class="order-number">
        <td>
            <strong><?php echo esc_html__('Invoice Number', 'woocommerce'); ?>:</strong>
            <?php $this->order_number(); ?>
        </td>
    </tr>
    <tr class="order-date">
        <td>
            <strong><?php echo esc_html__('Date', 'woocommerce'); ?>:</strong>
            <?php echo date('d M Y', strtotime($this->order->get_date_created())); ?>
        </td>
    </tr>
</table>

<table class="order-data-addresses">
    <tr>
        <td class="customer-name">
            <strong><?php echo esc_html($this->order->get_formatted_billing_full_name()); ?></strong>

        </td>
        <td class="order-data">
            <table>
                <?php do_action('wpo_wcpdf_before_shop_name', $this->get_type(), $this->order); ?>
                <div class="shop-name">
                    <h3><?php $this->shop_name(); ?></h3>
                </div>
                <?php do_action('wpo_wcpdf_after_shop_name', $this->get_type(), $this->order); ?>
                <?php do_action('wpo_wcpdf_before_shop_address', $this->get_type(), $this->order); ?>
                <div class="shop-address"><?php $this->shop_address(); ?></div>
                <?php if (isset($this->settings['shop_phone_number'])) : ?>
                    <tr class="cc">
                        <th><?php echo esc_html__('CC', 'woocommerce'); ?> </th>
                        <td><?php $this->number($this->shop_phone_number()); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (isset($this->settings['vat_number'])) : ?>
                    <tr class="invoice-number">
                        <th><?php echo esc_html__('VAT ID number', 'woocommerce'); ?> </th>
                        <td><?php $this->number($this->shop_vat_number()); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<table class="order-details">
    <thead>
        <tr>
            <th><?php echo esc_html__('Dates', 'woocommerce'); ?></th>
            <th><?php echo esc_html__('Vehicle', 'woocommerce'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <?php
                if (isset($custom_order_fields['start_date'], $custom_order_fields['end_date'])) {
                    $start_date = $custom_order_fields['start_date'][0];
                    $end_date = $custom_order_fields['end_date'][0];
                    echo "<strong>From</strong> " . esc_html(date('d M Y', strtotime($start_date))) . "<br>";
                    echo "<strong>Till</strong> " . esc_html(date('d M Y', strtotime($end_date)));
                } else {
                    echo "Dates not available";
                }
                ?>
            </td>

            <td><?php
                if (isset($custom_order_fields['license_plate'])) {
                    echo esc_html($custom_order_fields['license_plate'][0]);
                }
                ?>
            </td>
        </tr>
    </tbody>
</table>
<?php do_action('wpo_wcpdf_before_order_details', $this->get_type(), $this->order); ?>

<table class="order-details">
    <?php $headers = wpo_wcpdf_get_simple_template_default_table_headers($this);
    ?>
    <thead>
        <tr>
            <?php
            foreach ($headers as $column_class => $column_title) {
                printf('<th class="%s">%s</th>', $column_class, $column_title);
            }
            ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $tax_amount = 0;
        $optional_product_id = 3413;
        $current_language = apply_filters('wpml_current_language', null);
        $optional_product_id = icl_object_id($optional_product_id, 'product', false, $current_language);
        $optional_product = wc_get_product($optional_product_id);
        foreach ($this->order->get_items() as $item_id => $item) :
            $vignette_fee = $item->get_meta('vignette_fee');
            $tax_amount += !empty($vignette_fee['tax_amount']) ? round($vignette_fee['tax_amount'], 2) : 0;
            $product_id = $item->get_product_id();
            $is_optional_product = ($product_id == $optional_product_id);
        ?>
            <tr class="<?php echo apply_filters('wpo_wcpdf_item_row_class', 'item-' . $item_id, esc_attr($this->get_type()), $this->order, $item_id); ?>">
                <td class="product">
                    <?php if ($is_optional_product): ?>
                        <p class="item-name"><?php echo esc_html($optional_product->get_name()); ?></p>
                    <?php else: ?>
                        <p class="item-name"><?= __('Service fee in respect of', 'woocommerce') ?><?php echo wp_kses_post(apply_filters('wv_invoice_item_name', $item->get_name(), $item)); ?></p>
                    <?php endif; ?>
                    <?php do_action('wpo_wcpdf_before_item_meta', $this->get_type(), $item, $this->order); ?>
                    <?php do_action('wpo_wcpdf_after_item_meta', $this->get_type(), $item, $this->order); ?>
                </td>
                <td class="total">
                    <?php if ($is_optional_product): ?>
                        <?php echo wc_price($item['total'], 2); ?>
                    <?php else: ?>
                        <?php echo wc_price($vignette_fee['price'], 2); ?>
                    <?php endif; ?>
                </td>
                <td class="rate"><?php
                                    if (!empty($vignette_fee['tax_rates'])) {
                                        foreach ($vignette_fee['tax_rates'] as $tax_rate) {
                                            echo esc_html($tax_rate['rate']) . "% ";
                                        }
                                    } else {
                                        echo '0%';
                                    }
                                    ?></td>
                <td class="price-with-tax">
                    <?php if ($is_optional_product): ?>
                        <?php echo wc_price($item['total'], 2); ?>
                    <?php else: ?>
                        <?php echo wc_price($vignette_fee['price_with_tax'], 2); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tr>
        <th>Paid on your behalf</th>
    </tr>
    <?php foreach ($this->order->get_items() as $item_id => $item) :
        $product_id = $item->get_product_id();
        $is_optional_product = ($product_id == $optional_product_id);
        if ($is_optional_product) {
            continue;
        }
    ?>
        <tr class="<?php echo apply_filters('wpo_wcpdf_item_row_class', 'item-' . $item_id, esc_attr($this->get_type()), $this->order, $item_id); ?>">
            <td class="product">
                <p class="item-name"><?= __('Official Fee', 'woocommerce') ?><?php echo wp_kses_post(apply_filters('wv_invoice_item_name', $item->get_name(), $item)); ?></p>
            </td>
            <td class="total">
                <?php echo wc_price($item['total'], 2); ?>
            </td>
            <td class="rate"><?php
                                if (!empty($vignette_fee['tax_rates'])) {
                                    foreach ($vignette_fee['tax_rates'] as $tax_rate) {
                                        echo esc_html($tax_rate['rate']) . "% ";
                                    }
                                } else {
                                    echo '-';
                                }
                                ?></td>
            <td class="total">
                <?php echo wc_price($item['total'], 2); ?>
            </td>
        </tr>
    <?php endforeach; ?>

</table>

<table class="notes-totals">
    <tbody>
        <tr class="no-borders">
            <td class="no-borders notes-cell">
                <?php do_action('wpo_wcpdf_before_document_notes', $this->get_type(), $this->order); ?>
                <?php if ($this->get_document_notes()) : ?>
                    <div class="document-notes">
                        <h3><?php $this->notes_title(); ?></h3>
                        <?php $this->document_notes(); ?>
                    </div>
                <?php endif; ?>
                <?php do_action('wpo_wcpdf_after_document_notes', $this->get_type(), $this->order); ?>
                <?php do_action('wpo_wcpdf_before_customer_notes', $this->get_type(), $this->order); ?>
                <?php if ($this->get_shipping_notes()) : ?>
                    <div class="customer-notes">
                        <h3><?php $this->customer_notes_title(); ?></h3>
                        <?php $this->shipping_notes(); ?>
                    </div>
                <?php endif; ?>
                <?php do_action('wpo_wcpdf_after_customer_notes', $this->get_type(), $this->order); ?>
            </td>
            <td class="no-borders totals-cell">
                <table class="totals">
                    <tfoot>

                        <?php foreach ($this->get_woocommerce_totals() as $key => $total): ?>
                            <?php
                            if (strpos($key, 'fee_') !== false) {
                                continue;
                            } ?>
                            <tr class="<?php echo esc_attr($key); ?>">
                                <th class="description"><?php echo $total['label']; ?></th>
                                <td class="price"><span class="totals-price"><?php echo $total['value']; ?></span></td>
                            </tr>
                            <?php if ($key == "cart_subtotal"): ?>
                                <tr class="vat">
                                    <th class="description"><?php echo esc_html__('Value added tax (VAT)', 'woocommerce'); ?></th>
                                    <td class="price"><span class="totals-price">
                                            <?php echo wc_price($tax_amount) ?></span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tfoot>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<?php do_action('wpo_wcpdf_after_order_details', $this->get_type(), $this->order); ?>

<div class="bottom-spacer"></div>
<div class="row">
    <p class="text-center-remittance"><?= __('We have received your remittance and would like to thank your for your order.', 'woocommerce') ?></p>
</div>

<?php if ($this->get_footer()) : ?>
    <htmlpagefooter name="docFooter"><!-- required for mPDF engine -->
        <div id="footer">
            <!-- hook available: wpo_wcpdf_before_footer -->
            <?php $this->footer(); ?>
            <!-- hook available: wpo_wcpdf_after_footer -->
        </div>
    </htmlpagefooter><!-- required for mPDF engine -->
<?php endif; ?>

<?php do_action('wpo_wcpdf_after_document', $this->get_type(), $this->order); ?>