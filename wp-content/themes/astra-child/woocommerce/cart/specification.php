<?php

/**
 * Mini-cart in Table Format
 */

defined('ABSPATH') || exit;
if( empty( $specifications ) ){
    return ;
}
?>
<table>
    <thead>
        <tr>
            <th><?= __('Vignette','woocommerce')?></th>
            <th><?= __('Fees','woocommerce')?></th>
            <th><?= __('Fee','woocommerce')?></th>
            <th><?= __('Total','woocommerce')?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach( $specifications as $specification ):?>
        <tr>
            <td><?= $specification['quantity'] . "x" . $specification['name'] ?></td>
		    <td><?= wc_price($specification['line_subtotal']) ?></td>
		    <td><?= wc_price($specification['vignette_price']) ?></td>
		    <td><?= $specification['line_total']?></td>
		</tr>
		<?php endforeach;?>
	</tbody>
</table>