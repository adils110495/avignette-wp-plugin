<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://heservices.in
 * @since      1.0.0
 *
 * @package    Woo_Vignette
 * @subpackage Woo_Vignette/frontend
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Woo_Vignette
 * @subpackage Woo_Vignette/frontend
 * @author     HES <admin@heservices.in>
 */
class Woo_Vignette_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	 
    private $current_lang = 'en';
    private $has_ploylang = false;
    private $has_wpml = false;
    
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
        if (function_exists("pll_get_post_language")) {
            $this->has_ploylang = true;
            $this->current_lang = pll_current_language('slug');
        }elseif (function_exists("icl_get_languages")) {
            $this->has_wpml = true;
            $this->current_lang = apply_filters('wpml_current_language', NULL);
        }
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_style( $this->plugin_name."_select2", plugin_dir_url( __FILE__ ) . 'css/woo-vignette-select2.min.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name."-datepicker", plugin_dir_url( __FILE__ ) . 'css/bootstrap-datepicker.min.css', array(),filemtime(plugin_dir_path(__FILE__) . 'css/bootstrap-datepicker.min.css'), 'all' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woo-vignette-public.css', array(),filemtime(plugin_dir_path(__FILE__) . 'css/woo-vignette-public.css'), 'all' );
		wp_enqueue_style( "dashicons", includes_url('css/dashicons.min.css'), array(),$this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$frontendAjax = Woo_Vignette\frontend\Woo_Vignette_Ajax::instance();
        $map_api_key = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_google_maps_api_key();
        $map_url = "https://maps.googleapis.com/maps/api/js?key=" . urlencode( $map_api_key ) . "&loading=async&libraries=geometry,places,marker&v=quarterly&language=" . $this->current_lang;
		wp_enqueue_script( $this->plugin_name.'-select2', plugin_dir_url( __FILE__ ) . 'js/woo-vignette-select2.min.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script($this->plugin_name."-map-api",$map_url,array('jquery'),$this->version,false);
		wp_enqueue_script($this->plugin_name.'-popper',plugin_dir_url(__FILE__) . 'js/popper.min.js',array('popper'),filemtime(plugin_dir_path(__FILE__) . 'js/popper.min.js'),false);
		wp_enqueue_script($this->plugin_name.'-bootstrap-datepicker',plugin_dir_url(__FILE__) . 'js/bootstrap-datepicker.min.js',array(),filemtime(plugin_dir_path(__FILE__) . 'js/bootstrap-datepicker.min.js'),false);
		wp_enqueue_script($this->plugin_name,plugin_dir_url(__FILE__) . 'js/woo-vignette-public.js',array('jquery'),filemtime(plugin_dir_path(__FILE__) . 'js/woo-vignette-public.js'),false);
	    wp_localize_script($this->plugin_name, 'wv_settings', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'country_data' => plugin_dir_url( __FILE__ ) . 'countries.json?v=1.1',
			'toll_icon' => plugin_dir_url( __FILE__ ) . 'images/toll.png',
			'current_language'  =>  $this->current_lang,
			'add_to_cart_nonce' => wp_create_nonce('add_to_cart_nonce'),
			'remove_optional_product_nonce' => wp_create_nonce('remove_optional_product_nonce'),
			'product_name' => $frontendAjax->getProductName(),
			'product_type' => $frontendAjax->setProductTypes(),
			'advance_min_days' => \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_advance_min_days(),
		]);
	    add_filter('script_loader_tag', [$this,'add_async_defer_to_map_api'], 10, 2);
	}
	public function add_async_defer_to_map_api($tag, $handle) {
    // Check if the script handle matches the one for the map API
    if ($handle === $this->plugin_name . "-map-api") {
        // Add async and defer to the script tag
        return str_replace(' src', ' async="async" defer="defer" src', $tag);
    }
    return $tag;
}
	public function load_frontend(){
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/includes/woo-vignette-order-form.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/includes/woo-vignette-route-planner-form.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '/public/includes/woo-vignette-ajax.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . '/public/includes/woo-filters.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/includes/woo-vignette-checkout-fields.php';
        Woo_Vignette\frontend\Woo_Vignette_Order_Form::instance();
        Woo_Vignette\frontend\Woo_Vignette_Route_Planner_Form::instance();
        Woo_Vignette\frontend\Woo_Filters::instance();
        $checkout_fields = Woo_Vignette\admin\includes\Woo_Vignette_Checkout_Fields::instance();
        $frontendAjax = Woo_Vignette\frontend\Woo_Vignette_Ajax::instance();
        add_action( 'wp_ajax_nopriv_get_variations', [$frontendAjax,'get_variations'] );
        add_action( 'wp_ajax_get_variations', [$frontendAjax,'get_variations'] );
        
        add_action( 'wp_ajax_nopriv_wv_cart', [$frontendAjax,'add_to_cart'] );
        add_action( 'wp_ajax_wv_cart', [$frontendAjax,'add_to_cart'] );

        add_action( 'wp_ajax_nopriv_wv_delete_vignette', [$frontendAjax,'delete_vignette'] );
        add_action( 'wp_ajax_wv_delete_vignette', [$frontendAjax,'delete_vignette'] );
        
        add_action('wp_ajax_wv_add_to_cart_optional_product', [$frontendAjax,'add_to_cart_optional_product']);  // For logged-in users
        add_action('wp_ajax_nopriv_wv_add_to_cart_optional_product', [$frontendAjax,'add_to_cart_optional_product']);  // For guests
        
		add_action('wp_ajax_wv_remove_optional_product', [$frontendAjax,'remove_optional_product']);  // For logged-in users
        add_action('wp_ajax_nopriv_wv_remove_optional_product', [$frontendAjax,'remove_optional_product']);  // For guests

        add_action('wp_ajax_nopriv_wv_show_sidebar_popup_order_form', [$frontendAjax,'sidebar_popup_order_form']);
		add_action('wp_ajax_wv_show_sidebar_popup_order_form', [$frontendAjax,'sidebar_popup_order_form']);

		add_action('wp_ajax_nopriv_wv_show_minicart_form', [$frontendAjax,'show_minicart_form']);
		add_action('wp_ajax_wv_show_minicart_form', [$frontendAjax,'show_minicart_form']);

		add_action('wp_ajax_nopriv_wv_checkout_file_upload', [$checkout_fields,'checkout_file_upload']);
		add_action('wp_ajax_wv_checkout_file_upload', [$checkout_fields,'checkout_file_upload']);
	}

}
