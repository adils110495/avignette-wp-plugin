<?php

/**
 * @package Woo_Vignette\admin\includes
 */

namespace Woo_Vignette\admin\includes;

class Woo_Vignette_Settings {

    const OPTION_GOOGLE_MAPS_API_KEY  = 'wv_google_maps_api_key';
    const OPTION_ADVANCE_MIN_DAYS     = 'wv_advance_min_days';
    const OPTION_RAPID_FEE_ENABLED    = 'wv_rapid_processing_fee_enabled';
    const OPTION_RAPID_PRODUCT_ID     = 'wv_rapid_processing_product_id';

    public function __construct() {
        add_filter( 'woocommerce_settings_tabs_array',           [ $this, 'add_settings_tab' ], 50 );
        add_action( 'woocommerce_settings_tabs_wv_settings',     [ $this, 'output' ] );
        add_action( 'woocommerce_update_options_wv_settings',    [ $this, 'save' ] );
        add_action( 'woocommerce_admin_field_wv_api_key',        [ $this, 'render_api_key_field' ] );
        add_action( 'woocommerce_admin_field_wv_product_select', [ $this, 'render_product_select_field' ] );
    }

    public function add_settings_tab( $tabs ) {
        $tabs['wv_settings'] = __( 'Vignette', 'woo-vignette' );
        return $tabs;
    }

    public function output() {
        woocommerce_admin_fields( $this->get_settings() );
        $this->output_toggle_script();
    }

    public function save() {
        // Encrypt and store the API key — never write plain text to the DB.
        $posted_key = isset( $_POST[ self::OPTION_GOOGLE_MAPS_API_KEY ] )
            ? sanitize_text_field( wp_unslash( $_POST[ self::OPTION_GOOGLE_MAPS_API_KEY ] ) )
            : '';

        if ( ! empty( $posted_key ) && $posted_key !== '••••••••' ) {
            update_option( self::OPTION_GOOGLE_MAPS_API_KEY, self::encrypt( $posted_key ) );
        }

        // Save the rapid processing product ID.
        $product_id = isset( $_POST[ self::OPTION_RAPID_PRODUCT_ID ] )
            ? absint( $_POST[ self::OPTION_RAPID_PRODUCT_ID ] )
            : 0;
        update_option( self::OPTION_RAPID_PRODUCT_ID, $product_id );

        // Let WooCommerce save the standard fields (exclude our custom-handled ones).
        $custom_handled = [ self::OPTION_GOOGLE_MAPS_API_KEY, self::OPTION_RAPID_PRODUCT_ID ];
        $standard = array_filter( $this->get_settings(), static function ( $field ) use ( $custom_handled ) {
            return ! in_array( $field['id'] ?? '', $custom_handled, true );
        } );
        woocommerce_update_options( array_values( $standard ) );
    }

    // -------------------------------------------------------------------------
    // Settings definition
    // -------------------------------------------------------------------------

    private function get_settings() {
        return [
            [
                'title' => __( 'Vignette Settings', 'woo-vignette' ),
                'type'  => 'title',
                'desc'  => __( 'Configure the WooVignette plugin options below.', 'woo-vignette' ),
                'id'    => 'wv_section_title',
            ],

            // ----- Google Maps API Key (encrypted) -----
            [
                'title'    => __( 'Google Maps API Key', 'woo-vignette' ),
                'type'     => 'wv_api_key',
                'id'       => self::OPTION_GOOGLE_MAPS_API_KEY,
                'desc_tip' => __( 'Your key is stored encrypted. Enter a new value to replace the current key.', 'woo-vignette' ),
            ],

            // ----- Advance Minimum Days -----
            [
                'title'             => __( 'Advance Minimum Days', 'woo-vignette' ),
                'type'              => 'number',
                'id'                => self::OPTION_ADVANCE_MIN_DAYS,
                'default'           => 3,
                'desc'              => __( 'days', 'woo-vignette' ),
                'desc_tip'          => __( 'Minimum number of days in advance a customer must select a start date (e.g. 3 means today + 3 days is the earliest valid date).', 'woo-vignette' ),
                'css'               => 'width:80px;',
                'custom_attributes' => [ 'min' => 0, 'step' => 1 ],
            ],

            // ----- Rapid Processing Fee toggle -----
            [
                'title'    => __( 'Rapid Processing Fee', 'woo-vignette' ),
                'type'     => 'checkbox',
                'id'       => self::OPTION_RAPID_FEE_ENABLED,
                'label'    => __( 'Enable rapid processing fee for orders', 'woo-vignette' ),
                'default'  => 'no',
                'desc_tip' => __( 'When enabled, a rapid processing fee product is added to orders that require expedited handling.', 'woo-vignette' ),
            ],

            // ----- Rapid Processing Product (shown only when fee is enabled) -----
            [
                'title'    => __( 'Rapid Processing Product', 'woo-vignette' ),
                'type'     => 'wv_product_select',
                'id'       => self::OPTION_RAPID_PRODUCT_ID,
                'desc_tip' => __( 'Select the WooCommerce product that represents the rapid processing fee. Replaces the hardcoded WV_OPTIONAL_PRODUCT_ID constant.', 'woo-vignette' ),
            ],

            [
                'type' => 'sectionend',
                'id'   => 'wv_section_end',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Toggle JS: show/hide product selector based on the checkbox
    // -------------------------------------------------------------------------

    private function output_toggle_script() {
        $checkbox_id = self::OPTION_RAPID_FEE_ENABLED;
        $row_class   = 'wv-rapid-product-row';
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            function wvToggleRapidProductRow() {
                if ($('#<?php echo esc_js( $checkbox_id ); ?>').is(':checked')) {
                    $('.<?php echo esc_js( $row_class ); ?>').show();
                } else {
                    $('.<?php echo esc_js( $row_class ); ?>').hide();
                }
            }
            wvToggleRapidProductRow();
            $('#<?php echo esc_js( $checkbox_id ); ?>').on('change', wvToggleRapidProductRow);
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Custom field renderer: wv_api_key
    // -------------------------------------------------------------------------

    public function render_api_key_field( $field ) {
        $encrypted = get_option( $field['id'], '' );
        $has_key   = ! empty( $encrypted );
        $tip       = ! empty( $field['desc_tip'] ) ? $field['desc_tip'] : '';
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field['id'] ); ?>">
                    <?php echo esc_html( $field['title'] ); ?>
                    <?php echo wc_help_tip( $tip ); ?>
                </label>
            </th>
            <td class="forminp forminp-password">
                <input
                    type="password"
                    id="<?php echo esc_attr( $field['id'] ); ?>"
                    name="<?php echo esc_attr( $field['id'] ); ?>"
                    value="<?php echo $has_key ? esc_attr( '••••••••' ) : ''; ?>"
                    class="input-text regular-text"
                    autocomplete="new-password"
                />
                <p class="description">
                    <?php if ( $has_key ) : ?>
                        <?php esc_html_e( 'A key is currently saved. Enter a new value to replace it, or leave unchanged.', 'woo-vignette' ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'No key saved yet. Enter your Google Maps API key — it will be stored encrypted.', 'woo-vignette' ); ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <?php
    }

    // -------------------------------------------------------------------------
    // Custom field renderer: wv_product_select
    // -------------------------------------------------------------------------

    public function render_product_select_field( $field ) {
        $product_id   = absint( get_option( $field['id'], 0 ) );
        $product_name = '';

        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $product_name = $product->get_formatted_name();
            }
        }

        $tip     = ! empty( $field['desc_tip'] ) ? $field['desc_tip'] : '';
        $visible = self::is_rapid_processing_fee_enabled();
        ?>
        <tr valign="top" class="wv-rapid-product-row" style="<?php echo $visible ? '' : 'display:none;'; ?>">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field['id'] ); ?>">
                    <?php echo esc_html( $field['title'] ); ?>
                    <?php echo wc_help_tip( $tip ); ?>
                </label>
            </th>
            <td class="forminp">
                <select
                    id="<?php echo esc_attr( $field['id'] ); ?>"
                    name="<?php echo esc_attr( $field['id'] ); ?>"
                    class="wc-product-search"
                    style="width:300px;"
                    data-placeholder="<?php esc_attr_e( 'Search for a product…', 'woo-vignette' ); ?>"
                    data-action="woocommerce_json_search_products"
                    data-allow_clear="true"
                >
                    <?php if ( $product_id && $product_name ) : ?>
                        <option value="<?php echo esc_attr( $product_id ); ?>" selected="selected">
                            <?php echo esc_html( $product_name ); ?>
                        </option>
                    <?php endif; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( 'This product will be used as the rapid processing fee item added to the cart.', 'woo-vignette' ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    // -------------------------------------------------------------------------
    // Static helpers — use these anywhere in the plugin
    // -------------------------------------------------------------------------

    public static function get_google_maps_api_key() {
        $encrypted = get_option( self::OPTION_GOOGLE_MAPS_API_KEY, '' );
        return empty( $encrypted ) ? '' : self::decrypt( $encrypted );
    }

    public static function get_advance_min_days() {
        return absint( get_option( self::OPTION_ADVANCE_MIN_DAYS, 3 ) );
    }

    public static function is_rapid_processing_fee_enabled() {
        return get_option( self::OPTION_RAPID_FEE_ENABLED, 'no' ) === 'yes';
    }

    /**
     * Returns the rapid processing product ID from the admin setting,
     * falling back to the WV_OPTIONAL_PRODUCT_ID constant if not configured.
     */
    public static function get_rapid_processing_product_id() {
        $product_id = absint( get_option( self::OPTION_RAPID_PRODUCT_ID, 0 ) );
        if ( ! $product_id && defined( 'WV_OPTIONAL_PRODUCT_ID' ) ) {
            $product_id = (int) WV_OPTIONAL_PRODUCT_ID;
        }
        return $product_id;
    }

    // -------------------------------------------------------------------------
    // AES-256-CBC encryption / decryption
    // -------------------------------------------------------------------------

    private static function get_encryption_key() {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'woo-vignette-fallback-salt-key';
        return hash( 'sha256', $salt, true );
    }

    public static function encrypt( $plaintext ) {
        if ( empty( $plaintext ) || ! function_exists( 'openssl_encrypt' ) ) {
            return '';
        }
        $key        = self::get_encryption_key();
        $iv         = random_bytes( 16 );
        $ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( $ciphertext === false ) {
            return '';
        }
        return base64_encode( $iv . $ciphertext );
    }

    public static function decrypt( $stored ) {
        if ( empty( $stored ) || ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }
        $data = base64_decode( $stored, true );
        if ( $data === false || strlen( $data ) < 17 ) {
            return '';
        }
        $key        = self::get_encryption_key();
        $iv         = substr( $data, 0, 16 );
        $ciphertext = substr( $data, 16 );
        $plaintext  = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $plaintext !== false ? $plaintext : '';
    }
}
