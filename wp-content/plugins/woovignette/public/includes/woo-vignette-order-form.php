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
class Woo_Vignette_Order_Form {

	/**
	 * The shortcode name.
	 */
	const SHORTCODE = 'wv_order_form';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
	}

	/**
	 * Shortcode support.
	 *
	 * @param mixed $atts The shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts         = shortcode_atts(
			[
				'selected_countries'    =>  '',
				'tolls'                 =>  '',
				'duration'              =>  '',
				'cart_variations'       =>  ''
			],
			$atts,
			self::SHORTCODE
		);
		$selected_countries = explode(',',$atts['selected_countries']);
		$tolls      = $atts['tolls'];
		$duration   = $atts['duration'];
		$cart_variations   = $atts['cart_variations'];
		$baseClass = new \Woo_Vignette\includes\core\Woo_Vignette_Base;
		$countries = $baseClass->get_available_countries();
		$languages = array_keys($countries);
		$variations = $baseClass->getVariations();
		/*if(isset($variations['weight'])){
			unset($variations['weight']);
		}*/
		ob_start();
		include plugin_dir_path( dirname( __FILE__ ) ) . 'views/order-form.php';
		return ob_get_clean();
	}

	/**
	 * Get singleton instance of Woo_Vignette_Order_Form class.
	 *
	 * @since 8/12/2019
	 *
	 * @return Woo_Vignette_Order_Form
	 */
	public static function instance(): Woo_Vignette_Order_Form {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}

