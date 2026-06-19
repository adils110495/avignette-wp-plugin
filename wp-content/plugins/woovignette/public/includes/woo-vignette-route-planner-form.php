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
class Woo_Vignette_Route_Planner_Form {

	/**
	 * The shortcode name.
	 */
	const SHORTCODE = 'wv_route_planner_form';

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
			],
			$atts
		);
		$baseClass = new \Woo_Vignette\includes\core\Woo_Vignette_Base;
		ob_start();
		include plugin_dir_path( dirname( __FILE__ ) ) . 'views/route-planner-form.php';
		return ob_get_clean();
	}

	/**
	 * Get singleton instance of Woo_Vignette_Route_Planner_Form class.
	 *
	 * @since 8/12/2019
	 *
	 * @return Woo_Vignette_Route_Planner_Form
	 */
	public static function instance(): Woo_Vignette_Route_Planner_Form {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}

