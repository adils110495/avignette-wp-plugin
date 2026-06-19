<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://heservices.com
 * @since      1.0.0
 *
 * @package    Woo_Vignette
 * @subpackage Woo_Vignette/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woo_Vignette
 * @subpackage Woo_Vignette/admin
 * @author     HES <admin@heservices.com>
 */
class Woo_Vignette_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
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
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woo-vignette-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

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
		wp_enqueue_media();
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/woo-vignette-admin.js',
			['jquery'],
			null,
			true
		);
		/* wp_enqueue_media();
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woo-vignette-admin.js', array(), filemtime(plugin_dir_path(__FILE__) . 'js/woo-vignette-admin.js'), false ); */
	}
	
	public function load_admin(){
        if ( ! class_exists( 'WC_Email' ) ) {
            include_once( WC_ABSPATH . 'includes/emails/class-wc-email.php' );
        }
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/woo-vignette-cpt.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/core/woo-vignette-attributes.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/woo-vignette-product-fields.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-wc-email-invoice-upload.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/woo-vignette-settings.php';
        $attributes = new \Woo_Vignette\includes\core\Woo_Vignette_Attributes;
        $cpt = new \Woo_Vignette\admin\includes\Woo_Vignette_Cpt;
        $productFields = new \Woo_Vignette\admin\includes\Woo_Vignette_Product_Fields;
        new \Woo_Vignette\admin\includes\Woo_Vignette_Settings;

        add_filter( 'woocommerce_email_classes', [$this,'woo_vignette_register_custom_emails'] );
        
	}
	
	public function woo_vignette_register_custom_emails( $email_classes ) {
        $email_classes['WC_Email_Invoice_Upload'] = new WC_Email_Invoice_Upload();
        return $email_classes;
    }
}
