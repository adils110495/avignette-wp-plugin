<?php
/**
 * Avignette.com Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Avignette.com Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_AVIGNETTE_COM_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'avignette-com-astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_AVIGNETTE_COM_ASTRA_CHILD_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );
add_action( 'after_setup_theme', 'woocommerce_support' );
function woocommerce_support() {
   add_theme_support( 'woocommerce' );
}     
function add_content_to_beginning_of_body() {
    ?>
    <!-- Popup Sidebar -->
    <div id="order-form-popup-sidebar" class="order-form-popup-sidebar">
        <div class="order-form-popup">
            <span id="order-form-popup-close" class="order-form-popup-close">&times;</span>
            <div id="wv-loader" style="display: none;"></div>
            <div class="order-form-popup-content"></div>
        </div>
    </div>
    <?php
}
add_action('wp_body_open', 'add_content_to_beginning_of_body');

function woocommerce_widget_shopping_cart_subtotal() {
	echo '<strong>' . esc_html__( 'Total:', 'woocommerce' ) . '</strong> ' . WC()->cart->get_cart_subtotal(); 
}

function mini_cart_add_to_cart_button() {
    ?>
    <a href="#order-form" class="button sidebar-popup-order-form">
        <?php echo esc_html__( 'Add Product', 'woocommerce' ); ?>
    </a>
    <?php
}
remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
add_action( 'woocommerce_widget_shopping_cart_buttons', 'mini_cart_add_to_cart_button', 10 );


function custom_pdf_header( $headers, $document ): array {
	$headers = array(
		'description'  => __( 'Description', 'woocommerce-pdf-invoices-packing-slips' ),
		'price' => __( 'Price', 'woocommerce-pdf-invoices-packing-slips' ),
		'vat'    => __( 'Value-added tax (VAT)', 'woocommerce-pdf-invoices-packing-slips' ),
		'in_total'    => __( 'In total', 'woocommerce-pdf-invoices-packing-slips' ),
	);
	
	/* if ( 'packing-slip' === $document->get_type() ) {
		unset( $headers['price'] );
	} */
	
	return $headers;
}
add_filter( 'wpo_wcpdf_simple_template_default_table_headers', 'custom_pdf_header', 10,2 );


