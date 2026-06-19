<div id="wv-order-container">
    <form method="POST" id="wv-order-form">
        <input type="hidden" id="wv-tolls" name="tolls" value="<?= $tolls??'';?>" required>
        <input type="hidden" id="wv-duration" name="duration" value="<?= $duration??'';?>" required>
        <div class="form-field">
            <!-- Country -->
        <label for="country" class="wv-form-label"><strong>Destination:</strong></label>
        <select id="wv-country" name="countries[]" class="wv-country" multiple="multiple" required>
            <?php foreach( $countries as $country_slug => $country_name ): ?>
                <option value="<?= $country_slug ?>" 
                    <?php echo (in_array($country_slug, $selected_countries)) ? 'selected="selected"' : ''; ?>>
                    <?= $country_name ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>
        
        <div id="wv-variations">
            <?php include 'variations.php';?>
        </div>

        <?php
            $advance_days   = \Woo_Vignette\admin\includes\Woo_Vignette_Settings::get_advance_min_days();
            $min_start_date = date( 'Y-m-d', strtotime( '+' . $advance_days . ' days' ) );
        ?>
        <div id="wv-dates-ele">
            <div class="start-date-ele">
                <!-- Start Date -->
                <label for="start-date" class="wv-form-label">Start Date:</label>
                <input type="text" id="start-date" class="wv-datepicker" name="wv_start_date" data-min-date="<?= esc_attr( $min_start_date ) ?>" placeholder="dd.mm.yyyy" required>
            </div>
            <div class="end-date-ele">
                <label for="end-date" class="wv-form-label">End Date:</label>
                <input type="text" id="end-date" class="wv-datepicker" name="wv_end_date" data-min-date="<?= esc_attr( $min_start_date ) ?>" placeholder="dd.mm.yyyy" required>
                <!--<div id="end-date" 
                     class="input-group end-date" 
                     data-date-format="mm-dd-yyyy">
                    <input class="form-control" 
                           type="text"  name="wv_end_date" readonly />
                    <span class="input-group-addon">
                        <i class="glyphicon glyphicon-calendar"></i>
                    </span>
                </div>-->
            </div>
        </div>
        <div class="wv-messages">
            <p id="error-message"></p>
        </div>
        <div class="wp-block-button">
            <a class="wp-block-button__link has-text-align-center wp-element-button wv-submit" href="#">
                <?= __('Calculate','woo-vignette')?>
            </a>
            <a class="wp-block-button__link has-text-align-center wp-element-button" href="#Cart">
                <?= __('Cart','woocommerce')?>
            </a>
        </div>
        <!--<div class="wv-actions">
            <button type="submit" class="wv-submit"><?//= __('Calculate','woo-vignette')?></button>
        </div>-->
    </form>
</div>

</div>
