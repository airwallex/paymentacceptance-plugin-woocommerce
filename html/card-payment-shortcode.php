<?php
/**
 * Card payment template for shortcode
 *
 * @var $orderId
 * @var $paymentIntentId
 * @var $paymentIntentClientSecret
 * @var $confirmationUrl
 * @var $isSandbox
 * @var $isSubscription
 * @var $autoCapture
 * @var $airwallexCustomerId
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'airwallex-redirect-element-css' );

?>
	<div class="airwallex-content-card <?php echo esc_attr( $shortcodeAtts['class'] ); ?>" style="<?php echo esc_attr( $shortcodeAtts['style'] ); ?>">
		<h2><?php echo esc_html__( 'Enter your credit card details to pay your order', 'airwallex-online-payments-gateway' ); ?></h2>
		<div id="airwallex-error-message" class="woocommerce-error" style="display:none;">
			<?php echo esc_html__( 'Your payment could not be authenticated', 'airwallex-online-payments-gateway' ); ?>
		</div>
		<div id="airwallex-full-featured-card" style="max-width:500px;margin:0 auto;"></div>
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
    'style' => [
        'popupWidth' => 400,
        'popupHeight' => 549,
    ],
	'autoCapture' => $autoCapture,
]
+ ( $isSubscription ? [
	'mode'             => 'recurring',
	'recurringOptions' => [
		'next_triggered_by'       => 'merchant',
		'merchant_trigger_reason' => 'scheduled',
		'currency'                => $order->get_currency(),
	],
] : [] )
+ ( $airwallexCustomerId ? [ 'customer_id' => $airwallexCustomerId ] : [] );
$airwallexRedirectElScriptData = [
    'elementType' => 'fullFeaturedCard',
    'elementOptions' => $airwallexElementConfiguration,
    'containerId' => 'airwallex-full-featured-card',
    'orderId' => $orderId,
    'paymentIntentId' => $paymentIntentId,
];
wp_enqueue_script('airwallex-redirect-js');
wp_add_inline_script('airwallex-redirect-js', 'var awxRedirectElData=' . wp_json_encode($airwallexRedirectElScriptData), 'before');
