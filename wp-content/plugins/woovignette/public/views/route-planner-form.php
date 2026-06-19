<div id="wv-route-conatiner">
    <div class="popup-content" id="map-sidebar-content">
                <!--<div class="wp-block-button">
                    <a class="wp-block-button__link has-text-align-center wp-element-button sidebar-popup-order-form"  data-width="80%" href="#order-form" rel="at">
                        <?//= __('Order products', 'woo-vignette') ?> →            
                    </a>
                </div>-->
                
                <form id="map-order-form">
                    <!--<div class="wp-block-button">
                        <a class="wp-block-button__link has-text-align-center wp-element-button sidebar-popup-order-form"  data-width="80%" href="#order-form">
                            <?//= __('Order products', 'woo-vignette') ?> →            
                        </a>
                    </div>-->
                <h2><?= __('Route', 'woo-vignette') ?> </h2>
                <p><?php
                    printf(
                        esc_html__('This route covers %s and the travel time is approximately %s. You will need the following vignettes for your route:', 'woo-vignette'),
                        '<span id="distance"></span>',
                        '<span id="duration"></span>'
                    );
                    ?></p>

                <div class="w-full bg-slate-50 border-slate-200 border vignette-section">
                    <div class="header">
                        <h4><?= __('Vignette', 'woo-vignette') ?></h4>
                    </div>
                    <div class="content" id="vignette-list">

                    </div>
                </div>
                <div class="w-full bg-slate-50 border-slate-200 border toll-section">
                    <div class="header">
                        <h4><?= __('Toll/Tunnel', 'woo-vignette') ?></h4>
                    </div>
                    <div class="content" id="toll-list">

                    </div>
                </div>

                <p class="text-base italic text-justify mt-4">
                    <?= __('Please note: you can drag the route yourself, if you intend to drive a different route than indicated. The required products are calculated based on the route shown. So make sure it is correct.', 'woo-vignette') ?>
                </p>

                    <input type="hidden" id="wv-distance" name="distance" required>
                    <input type="hidden" id="wv-duration" name="duration" required>
                    <input type="hidden" id="wv-countries" name="countries" required>
                    <input type="hidden" id="wv-tolls" name="tolls" required>
                    <div class="wp-block-button">
                        <a class="wp-block-button__link has-text-align-center wp-element-button sidebar-popup-order-form"  data-width="80%" href="#order-form">
                            <?= __('Order products', 'woo-vignette') ?> →            
                        </a>
                    </div>
                </form>

            </div>
    <div id="wv-route-planner-container">
        <form method="post" id="wv-route-planner-form">
            <div class="form-input">
                <input type="text" id="departure" name="departure" placeholder="Departure" required>
            </div>
            <div class="form-input">
            <input type="text" id="destination" name="destination" placeholder="Destination" required>
                </div>
            <div class="wp-block-button">
                <a class="wp-block-button__link has-text-align-center wp-element-button" id="route-planner-submit">
                    <?= __('Route', 'woo-vignette') ?>           
                </a>
            </div>
            <!--<button type="submit" id="route-planner-submit"><?//= __('Route', 'woo-vignette') ?></button>-->
        </form>
    </div>
    <p id="error-message"></p>

    <div id="map-error">
        <p id="message"></p>
    </div>
    <div class="map-conatiner">
        <div id="map">
            <img src="<?= WOO_VIGNETTE_URI . 'public/images/map.png' ?>">
        </div>
    </div>
</div>