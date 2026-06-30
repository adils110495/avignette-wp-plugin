<?php if (! defined('ABSPATH')) exit; // Exit if accessed directly 
$order = wc_get_order($order_id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
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
            <strong><?php echo esc_html($this->order->get_formatted_billing_full_name()); ?></strong><br>
            <?php echo wp_kses_post( $this->order->get_formatted_billing_address() ); ?><br>
            <?php if ( $this->order->get_billing_email() ) : ?>
                <?php echo esc_html( $this->order->get_billing_email() ); ?><br>
            <?php endif; ?>
            <?php if ( $this->order->get_billing_phone() ) : ?>
                <?php echo esc_html( $this->order->get_billing_phone() ); ?>
            <?php endif; ?>
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
                $_inv_start = $order->get_meta('start_date');
                $_inv_end   = $order->get_meta('end_date');
                if ( $_inv_start && $_inv_end ) {
                    echo "<strong>From</strong> " . esc_html(date('d M Y', strtotime($_inv_start))) . "<br>";
                    echo "<strong>Till</strong> " . esc_html(date('d M Y', strtotime($_inv_end)));
                } else {
                    echo "Dates not available";
                }
                ?>
            </td>

            <td><?php
                $_inv_plate = $order->get_meta('license_plate');
                if ( $_inv_plate ) {
                    echo esc_html( $_inv_plate );
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
        $optional_product_id = 3413;
        $current_language = apply_filters('wpml_current_language', null);
        $optional_product_id = icl_object_id($optional_product_id, 'product', false, $current_language);
        $optional_product = wc_get_product($optional_product_id);

        // Correct subtotal and VAT: include product line taxes + vignette fee taxes.
        $_inv_subtotal = 0;
        $_inv_tax      = floatval( $order->get_shipping_tax() );
        foreach ( $order->get_items() as $_iid => $_iitem ) {
            $_ivf = $_iitem->get_meta('vignette_fee');
            $_inv_subtotal += floatval( $_iitem['total'] ) + floatval( $_iitem['total_tax'] );
            $_inv_tax      += floatval( $_iitem['total_tax'] );
            if ( ! empty( $_ivf['price_with_tax'] ) ) {
                $_inv_subtotal += floatval( $_ivf['price_with_tax'] );
            }
            if ( ! empty( $_ivf['tax_amount'] ) ) {
                $_inv_tax += floatval( $_ivf['tax_amount'] );
            }
        }
        $tax_amount = round( $_inv_tax, 2 );

        foreach ($this->order->get_items() as $item_id => $item) :
            $vignette_fee = $item->get_meta('vignette_fee');
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
                            // Skip individual fee lines — vignette fees are already rolled into our subtotal.
                            if (strpos($key, 'fee_') !== false) {
                                continue;
                            } ?>
                            <tr class="<?php echo esc_attr($key); ?>">
                                <th class="description"><?php echo $total['label']; ?></th>
                                <td class="price"><span class="totals-price">
                                    <?php if ($key === 'cart_subtotal'): ?>
                                        <?php echo wc_price( $_inv_subtotal ); ?>
                                    <?php else: ?>
                                        <?php echo $total['value']; ?>
                                    <?php endif; ?>
                                </span></td>
                            </tr>
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