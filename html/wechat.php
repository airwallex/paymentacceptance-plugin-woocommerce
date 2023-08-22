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
wp_enqueue_style('airwallex-standalone-css', AIRWALLEX_PLUGIN_URL.'/assets/css/airwallex.css');
// load JS legacy 
wp_enqueue_scripts();

//prevent errors when using Avada theme and Fusion Builder
if(class_exists('Fusion_Template_Builder')){
    global $post;
    $post = 0;
    do_action('wp');
}
?>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <?php include __DIR__ . '/inc/header.php'; ?>
    <div style="max-width:800px; padding:10px; margin: auto; text-align: center;">
        <h2><?php echo __('Your WeChat Payment', AIRWALLEX_PLUGIN_NAME); ?></h2>
        <div id="airwallex-error-message" class="woocommerce-error" style="display:none;">
            <?php echo __('Your payment could not be authenticated', AIRWALLEX_PLUGIN_NAME); ?>
        </div>
        <div id='wechat'></div>
        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
            <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
            <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
        </svg>
        <div id="success-message" style="display:none;">
            <?php echo __('Please hold on while your order is completed', AIRWALLEX_PLUGIN_NAME); ?>
        </div>
    </div>
<?php

wp_enqueue_script('airwallex-lib-js', 'https://checkout.airwallex.com/assets/elements.bundle.min.js');
wp_enqueue_script('airwallex-local-js', AIRWALLEX_PLUGIN_URL.'/assets/js/airwallex-local.js');
if(defined('AIRWALLEX_INLINE_JS')){
    wp_add_inline_script( 'airwallex-local-js', AIRWALLEX_INLINE_JS);
}
$environment = $isSandbox?'demo':'prod';
$locale = \Airwallex\Services\Util::getLocale();

$inlineJs = <<<AIRWALLEX
        Airwallex.init({
            env: '$environment',
            locale: '$locale',
            origin: window.location.origin, // Setup your event target to receive the browser events message
        });
        const weChat = Airwallex.createElement('wechat', {
            intent: {
                id: '$paymentIntentId',
                client_secret: '$paymentIntentClientSecret'
            }
        });
        weChat.mount('wechat');
        window.addEventListener('onSuccess', (event) => {
            document.getElementById('wechat').style.display = 'none';
            document.getElementById('airwallex-error-message').style.display = 'none';
            var successCheck = document.getElementById('success-check');
            if(successCheck){
                successCheck.style.display = 'inline-block';
            }
            var successMessage = document.getElementById('success-message');
            if(successMessage){
                successMessage.style.display = 'block';
            }
            location.href = AirwallexParameters.confirmationUrl;
        })
        window.addEventListener('onError', (event) => {
            document.getElementById('airwallex-error-message').style.display = 'block';
            console.warning(event.detail);
        });
AIRWALLEX;
wp_add_inline_script('airwallex-local-js', $inlineJs);
wp_print_footer_scripts();
?>
<noscript><?php __("You need to enable JavaScript to run this app.", AIRWALLEX_PLUGIN_NAME) ?></noscript>
</body>
