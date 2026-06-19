<?php
//echo "<pre>";print_r($variations);die;
$tooltip_icon = '<svg class="wv-tooltip-icon" xmlns="https://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zM256 400c-18 0-32-14-32-32s13.1-32 32-32c17.1 0 32 14 32 32S273.1 400 256 400zM325.1 258L280 286V288c0 13-11 24-24 24S232 301 232 288V272c0-8 4-16 12-21l57-34C308 213 312 206 312 198C312 186 301.1 176 289.1 176h-51.1C225.1 176 216 186 216 198c0 13-11 24-24 24s-24-11-24-24C168 159 199 128 237.1 128h51.1C329 128 360 159 360 198C360 222 347 245 325.1 258z"></path></svg>';
foreach ($variations as $variation_type => $variation) :
    $taxonomy = "pa_$variation_type";
    $description = '';
    ?>
    <?php if (!empty($variation['items'])): ?>
        <fieldset>
            <legend><label class="wv-form-label"><?= $variation['variation_name'] ?? $variation_type ?></label> 
			</legend>
			 <?php $disabled_variation = isset($cart_variations[$taxonomy])?"disabled":"";?>
            <div class="wv-variation-option" <?= $disabled_variation?>>
                <?php foreach ($variation['items'] as $item):
                    $tooltip_html = '';
                    if( !empty( $item['description'] ) ){
                        //$tooltip_html .= "<h4>{$item['name']}</h4>";
                        $tooltip_html .= "<p>{$item['description']}</p>";
                    }
                    if( empty( $description ) ){
                        $description = isset($item['description'])?$item['description']:'';
                    }
                ?>
                    <input type="radio" name="variations[<?= $taxonomy ?>]"
                        <?php if( ( isset( $selected_variations[$taxonomy] ) && $selected_variations[$taxonomy] == $item['value'] )
                            || ( isset($cart_variations[$taxonomy]) && in_array($item['value'],$cart_variations[$taxonomy] ) )
                        ){
                            $description = isset($item['description'])?$item['description']:'';
                            echo "checked";
                        }?>
                        value="<?= $item['value'] ?>"
                        id="option_<?= $item['value'] ?>"
                        data-description='<?= isset($item['description'])?$item['description']:'' ?>'
                        class="variation-input" required>
                        <label for="option_<?= $item['value'] ?>" class="variation-label">
                            <?php if (!empty($item['fields']['wv_svg'][0])): ?>
                                <?php $svg = $item['fields']['wv_svg'][0];
                                    $svg = preg_replace('/<svg([^>]*?)\s(?:width)="[^"]*"/i', '<svg$1', $svg);
                                    $svg = preg_replace('/<svg([^>]*?)\s(?:height)="[^"]*"/i', '<svg$1', $svg);
                                    $viewBox = "0 0 576 512";
                                    // Check if viewBox already exists
                                    if ( ! preg_match('/\s*viewBox\s*=\s*["\'][^"\']+["\']/i', $svg)) {
                                        // If it doesn't exist, add the viewBox attribute
                                        $svg = preg_replace('/<svg /', '<svg viewBox="' . $viewBox . '" ', $svg);
                                    }
                                ?>
                                <?= $svg?>
                            <?php elseif (!empty($item['fields']['wv_image'][0])): ?>
                               <img src="<?= esc_url($item['fields']['wv_image'][0]) ?>" alt="<?= esc_attr($item['name']) ?>">
                            <?php endif; ?>
                            <div class="wv-variation-name">
                        <?= esc_html($item['name']) ?>
                    <?php if( !empty($tooltip_html) ):?>
            <div class="wv-tooltip" data-direction="bottom">
                <div class="wv-tooltip__initiator">
                  <?= $tooltip_icon?>
                </div>
                <div class="wv-tooltip__item">
                    <?= $tooltip_html;?>
                </div>
            </div>
            <?php endif;?>
                                
                            </div>
                        </label>
                <?php endforeach; ?>
                <!--<p class="wv-variation-description"><?//= $description?></p>-->
            </div>
        </fieldset>
    <?php endif; ?>
<?php endforeach; ?>
<input type="hidden" name="action" value="wv_cart">