<?php
/**
 * Card payment template
 *
 * @var $paymentIntentId
 * @var $paymentIntentClientSecret
 * @var $confirmationUrl
 * @var $isSandbox
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'airwallex-standalone-css', AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex.css', array(), AIRWALLEX_VERSION );

//prevent errors when using Avada theme and Fusion Builder
//@codingStandardsIgnoreStart
if ( class_exists( 'Fusion_Template_Builder' ) ) {
	global $post;
	$post = 0;
	do_action( 'wp' );
}
//@codingStandardsIgnoreEnd

get_header( 'shop' );
?>
	<div class="airwallex-content-card">
		<h2><?php echo esc_html__( 'Enter your credit card details to pay your order', 'airwallex-online-payments-gateway' ); ?></h2>
		<div id="airwallex-error-message" class="woocommerce-error" style="display:none;">
			<?php echo esc_html__( 'Your payment could not be authenticated', 'airwallex-online-payments-gateway' ); ?>
		</div>
		<div id="full-featured-card" style="max-width:500px;margin:0 auto;"></div>
		<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
			<circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
			<polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
		</svg>
		<div id="success-message" style="display:none;">
			<?php echo esc_html__( 'Please hold on while your order is completed', 'airwallex-online-payments-gateway' ); ?>
		</div>
	</div>
<?php

wp_enqueue_script( 'airwallex-lib-js', 'https://checkout.airwallex.com/assets/elements.bundle.min.js', array(), null, true );
wp_enqueue_script( 'airwallex-local-js', AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js', array(), AIRWALLEX_VERSION, true );
if ( defined( 'AIRWALLEX_INLINE_JS' ) ) {
	wp_add_inline_script( 'airwallex-local-js', AIRWALLEX_INLINE_JS );
}
$airwallex_environment = $isSandbox ? 'demo' : 'prod';
$airwallex_locale      = \Airwallex\Services\Util::getLocale();

$airwallex_inlineJs = <<<AIRWALLEX
        Airwallex.init({
            env: '$airwallex_environment',
            locale: '$airwallex_locale',
            origin: window.location.origin,
        });
        const fullFeaturedCard = Airwallex.createElement('fullFeaturedCard', {
            intent: {
                id: '$paymentIntentId',
                client_secret: '$paymentIntentClientSecret'
            },
            style: {
                popupWidth: 400,
                popupHeight: 549,
            },
        });
        fullFeaturedCard.mount('full-featured-card');
        window.addEventListener('onSuccess', (event) => {
            document.getElementById('full-featured-card').style.display = 'none';
            document.getElementById('airwallex-error-message').style.display = 'none';
            var successCheck = document.getElementById('success-check');
            if(successCheck){
                successCheck.style.display = 'inline-block';
            }

            var successMessage = document.getElementById('success-message');
            if(successMessage){
                successMessage.style.display = 'block';
            }
            location.href = AirwallexParameters.confirmationUrl + 'order_id=$orderId';
        });
        window.addEventListener('onError', (event) => {
            document.getElementById('airwallex-error-message').style.display = 'block';
            console.warn(event.detail);
        });
AIRWALLEX;
wp_add_inline_script( 'airwallex-local-js', $airwallex_inlineJs );
get_footer( 'shop' );
