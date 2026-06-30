<?php

/**
 * @package Woo_Vignette\admin\includes
 */

namespace Woo_Vignette\admin\includes;

class Woo_Vignette_Settings {

    const OPTION_GOOGLE_MAPS_API_KEY       = 'wv_google_maps_api_key';
    const OPTION_ADVANCE_MIN_DAYS          = 'wv_advance_min_days';
    const OPTION_RAPID_FEE_ENABLED         = 'wv_rapid_processing_fee_enabled';
    const OPTION_RAPID_PRODUCT_ID          = 'wv_rapid_processing_product_id';
    const OPTION_ACTIVE_COUNTRIES          = 'wv_active_countries';
    const OPTION_RAPID_EXCLUDED_COUNTRIES  = 'wv_rapid_excluded_countries';

    public function __construct() {
        add_filter( 'woocommerce_settings_tabs_array',           [ $this, 'add_settings_tab' ], 50 );
        add_action( 'woocommerce_settings_tabs_wv_settings',     [ $this, 'output' ] );
        add_action( 'woocommerce_update_options_wv_settings',    [ $this, 'save' ] );
        add_action( 'woocommerce_admin_field_wv_api_key',              [ $this, 'render_api_key_field' ] );
        add_action( 'woocommerce_admin_field_wv_product_select',       [ $this, 'render_product_select_field' ] );
        add_action( 'woocommerce_admin_field_wv_country_multiselect',  [ $this, 'render_country_multiselect_field' ] );
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

        // Save active countries multiselect (array; empty array when nothing selected).
        $active_countries = isset( $_POST[ self::OPTION_ACTIVE_COUNTRIES ] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ self::OPTION_ACTIVE_COUNTRIES ] ) )
            : [];
        update_option( self::OPTION_ACTIVE_COUNTRIES, $active_countries );

        // Save rapid processing excluded countries multiselect.
        $rapid_excluded = isset( $_POST[ self::OPTION_RAPID_EXCLUDED_COUNTRIES ] )
            ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ self::OPTION_RAPID_EXCLUDED_COUNTRIES ] ) )
            : [];
        update_option( self::OPTION_RAPID_EXCLUDED_COUNTRIES, $rapid_excluded );

        // Let WooCommerce save the standard fields (exclude our custom-handled ones).
        $custom_handled = [
            self::OPTION_GOOGLE_MAPS_API_KEY,
            self::OPTION_RAPID_PRODUCT_ID,
            self::OPTION_ACTIVE_COUNTRIES,
            self::OPTION_RAPID_EXCLUDED_COUNTRIES,
        ];
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

            // ----- Active Countries (checkout field trigger) -----
            [
                'title'    => __( 'Active Countries', 'woo-vignette' ),
                'type'     => 'wv_country_multiselect',
                'id'       => self::OPTION_ACTIVE_COUNTRIES,
                'desc_tip' => __( 'Countries that trigger vehicle-information fields at checkout (license plate, registration date, etc.). Select the countries whose vignettes you sell.', 'woo-vignette' ),
                'default'  => [ 'at', 'bg', 'hu', 'ro', 'sk', 'sl', 'ch', 'cs', 'fr', 'de', 'md' ],
            ],

            // ----- Rapid Processing Excluded Countries -----
            [
                'title'    => __( 'Rapid Processing Excluded Countries', 'woo-vignette' ),
                'type'     => 'wv_country_multiselect',
                'id'       => self::OPTION_RAPID_EXCLUDED_COUNTRIES,
                'desc_tip' => __( 'Products from these countries are excluded from the rapid processing fee quantity count.', 'woo-vignette' ),
                'default'  => [ 'fr', 'de' ],
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
    // Custom field renderer: wv_country_multiselect
    // -------------------------------------------------------------------------

    public function render_country_multiselect_field( $field ) {
        $default = $field['default'] ?? [];
        $saved   = get_option( $field['id'], null );
        $saved   = ( $saved !== null && is_array( $saved ) ) ? $saved : $default;
        $tip     = ! empty( $field['desc_tip'] ) ? $field['desc_tip'] : '';

        $countries = \Woo_Vignette\includes\core\Woo_Vignette_Base::instance()->get_wv_countries();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field['id'] ); ?>">
                    <?php echo esc_html( $field['title'] ); ?>
                    <?php echo wc_help_tip( $tip ); ?>
                </label>
            </th>
            <td class="forminp">
                <?php if ( empty( $countries ) ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'No countries found. Add countries via the Countries post type first.', 'woo-vignette' ); ?>
                    </p>
                <?php else : ?>
                    <select
                        id="<?php echo esc_attr( $field['id'] ); ?>"
                        name="<?php echo esc_attr( $field['id'] ); ?>[]"
                        multiple="multiple"
                        class="wc-enhanced-select"
                        style="width:350px;"
                        data-placeholder="<?php esc_attr_e( 'Select countries…', 'woo-vignette' ); ?>"
                    >
                        <?php foreach ( $countries as $code => $name ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $saved, true ), true ); ?>>
                                <?php echo esc_html( $name . ' (' . $code . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Hold Ctrl / Cmd to select multiple countries.', 'woo-vignette' ); ?>
                    </p>
                <?php endif; ?>
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

    /**
     * Returns the country codes that trigger checkout vehicle-information fields.
     * Falls back to the original hardcoded list when no setting has been saved yet.
     */
    public static function get_active_countries() {
        $saved = get_option( self::OPTION_ACTIVE_COUNTRIES, null );
        if ( $saved !== null && is_array( $saved ) ) {
            return $saved;
        }
        return [ 'at', 'bg', 'hu', 'ro', 'sk', 'sl', 'ch', 'cs', 'fr', 'de', 'md' ];
    }

    /**
     * Returns the country codes excluded from the rapid processing fee count.
     * Falls back to the original hardcoded list when no setting has been saved yet.
     */
    public static function get_rapid_excluded_countries() {
        $saved = get_option( self::OPTION_RAPID_EXCLUDED_COUNTRIES, null );
        if ( $saved !== null && is_array( $saved ) ) {
            return $saved;
        }
        return [ 'fr', 'de' ];
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
