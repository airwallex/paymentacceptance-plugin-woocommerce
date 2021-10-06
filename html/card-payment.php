<?php
/**
 * @var $paymentIntentId
 * @var $paymentIntentClientSecret
 * @var $confirmationUrl
 * @var $isSandbox
 **/

if (!defined('ABSPATH')) {
    exit;
}
wp_enqueue_style('airwallex-css', AIRWALLEX_PLUGIN_URL.'/assets/css/airwallex.css');
get_header('shop');
?>
    <div style="max-width:800px; padding:10px; margin: 0 auto; text-align: center;">
        <h2><?php echo __('Enter your credit card details to pay your order', AIRWALLEX_PLUGIN_NAME); ?></h2>
        <div id="full-featured-card" style="max-width:500px;margin:0 auto;"></div>
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
            <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
            <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
        </svg>
        <div id="success-message" style="display:none;">
            <?php echo __('Please hold on while your order is completed', AIRWALLEX_PLUGIN_NAME); ?>
        </div>
    </div>
<?php

wp_enqueue_script('airwallex-lib-js', AIRWALLEX_PLUGIN_URL.'/assets/js/airwallex-checkout.js', [], false, true);
wp_enqueue_script('airwallex-local-js', AIRWALLEX_PLUGIN_URL.'/assets/js/airwallex-local.js', [], false, true);

$environment = $isSandbox?'demo':'prod';
$inlineJs = <<<AIRWALLEX
        Airwallex.init({
            env: '$environment',
            origin: window.location.origin,
        });
        const fullFeaturedCard = Airwallex.createElement('fullFeaturedCard', {
            intent: {
                id: '$paymentIntentId',
                client_secret: '$paymentIntentClientSecret'
            }
        });
        fullFeaturedCard.mount('full-featured-card');
        window.addEventListener('onSuccess', (event) => {
            var successCheck = document.getElementById('success-check');
            if(successCheck){
                successCheck.style.display = 'inline-block';
            }

            var successMessage = document.getElementById('success-message');
            if(successMessage){
                successMessage.style.display = 'block';
            }
            location.href = AirwallexParameters.confirmationUrl;
        });
        window.addEventListener('onError', (event) => {
            location.href = AirwallexParameters.confirmationUrl;
        });
AIRWALLEX;
wp_add_inline_script('airwallex-local-js', $inlineJs);
get_footer('shop');
