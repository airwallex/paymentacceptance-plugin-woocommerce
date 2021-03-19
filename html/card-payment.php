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
get_header('shop');
global $airwallexIsCheckout;
$airwallexIsCheckout = false;
?>
    <link rel="stylesheet" href="<?php echo AIRWALLEX_PLUGIN_URL.'/assets/css/airwallex.css'; ?>" />
    <div style="max-width:800px; padding:10px; margin: 0 auto; text-align: center;">
        <h2><?php echo __('Enter your credit card details to pay your order', 'woocommerce-gateway-airwallex'); ?></h2>
        <div id="full-featured-card" style="max-width:500px;margin:0 auto;"></div>
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
            <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
            <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
        </svg>
        <div id="success-message" style="display:none;">
            <?php echo __('Please hold on while your order is completed', 'woocommerce-gateway-airwallex'); ?>
        </div>
    </div>
    <script src="<?php echo AIRWALLEX_PLUGIN_URL.'/assets/js/airwallex-checkout.js'; ?>"></script>
    <script src="<?php echo AIRWALLEX_PLUGIN_URL.'/assets/js/airwallex-local.js'; ?>"></script>

    <script>
        Airwallex.init({
            env: '<?php echo $isSandbox?'demo':'prod'; ?>',
            origin: window.location.origin,
        });
        const fullFeaturedCard = Airwallex.createElement('fullFeaturedCard', {
            intent: {
                id: '<?php echo $paymentIntentId; ?>',
                client_secret: '<?php echo $paymentIntentClientSecret; ?>'
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
    </script>

<?php get_footer('shop');
