<?php
/**
 * WeChat payment template
 *
 * @var $paymentIntentId
 * @var $paymentIntentClientSecret
 * @var $confirmationUrl
 * @var $isSandbox
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'airwallex-redirect-element-css' );

//@codingStandardsIgnoreStart
if ( class_exists( 'Fusion_Template_Builder' ) ) {
	global $post;
	$post = 0;
	do_action( 'wp' );
}
//@codingStandardsIgnoreEnd

get_header( 'shop' );
?>
	<div class="airwallex-content-wechat">
		<h2><?php echo esc_html__( 'Your WeChat Payment', 'airwallex-online-payments-gateway' ); ?></h2>
		<div id="airwallex-error-message" class="woocommerce-error" style="display:none;">
			<?php echo esc_html__( 'Your payment could not be authenticated', 'airwallex-online-payments-gateway' ); ?>
		</div>
		<div id='airwallex-wechat'></div>
		<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
			<circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
			<polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
		</svg>
		<div id="success-message" style="display:none;">
			<?php echo esc_html__( 'Please hold on while your order is completed', 'airwallex-online-payments-gateway' ); ?>
		</div>
	</div>
<?php

$airwallexElementConfiguration = [
    'intent' => [
        'id' => $paymentIntentId,
        'client_secret' => $paymentIntentClientSecret
    ],
];
$airwallexRedirectElScriptData = [
    'elementType' => 'wechat',
    'elementOptions' => $airwallexElementConfiguration,
    'containerId' => 'airwallex-wechat',
    'orderId' => $orderId,
    'paymentIntentId' => $paymentIntentId,
];
wp_enqueue_script('airwallex-redirect-js');
wp_add_inline_script('airwallex-redirect-js', 'var awxRedirectElData=' . wp_json_encode($airwallexRedirectElScriptData), 'before');
get_footer( 'shop' );
