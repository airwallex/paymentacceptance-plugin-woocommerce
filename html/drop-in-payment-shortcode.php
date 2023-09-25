<?php
/**
 * @var $paymentIntentId
 * @var $orderId
 * @var $paymentIntentClientSecret
 * @var $confirmationUrl
 * @var $isSandbox
 * @var $order
 * @var $isSubscription
 * @var $airwallexCustomerId
 **/

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('airwallex-standalone-css', AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex.css', [], AIRWALLEX_VERSION);

?>
    <div class="airwallex-content-drop-in <?php echo esc_attr($class) ?>" style="<?php echo esc_attr($style) ?>">
        <div class="airwallex-checkout airwallex-tpl-<?php echo $this->get_option('template'); ?>">
            <div class="airwallex-col-1">
                <div class="cart-heading"><?php echo __('Summary', AIRWALLEX_PLUGIN_NAME); ?></div>
                <?php
                include __DIR__ . '/inc/cart.php';
                ?>
            </div>
            <div class="airwallex-col-2">
                <div class="payment-section">
                    <div id="airwallex-error-message" class="woocommerce-error" style="display:none;">
                        <?php echo __('Your payment could not be authenticated', AIRWALLEX_PLUGIN_NAME); ?>
                    </div>
                    <div id="airwallex-drop-in"></div>
                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2" id="success-check" style="display:none;">
                        <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
                        <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
                    </svg>
                    <div id="success-message" style="display:none;">
                        <?php echo __('Please hold on while your order is completed', AIRWALLEX_PLUGIN_NAME); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php

wp_enqueue_script('airwallex-lib-js', 'https://checkout.airwallex.com/assets/elements.bundle.min.js');
wp_enqueue_script('airwallex-local-js', AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js', [], AIRWALLEX_VERSION);
if (defined('AIRWALLEX_INLINE_JS')) {
    wp_add_inline_script('airwallex-local-js', AIRWALLEX_INLINE_JS);
}
$environment = $isSandbox ? 'demo' : 'prod';
$locale = \Airwallex\Services\Util::getLocale();
$methods = $this->get_option('methods');
$airwallexMain = \Airwallex\Main::getInstance();
$merchantCountry = strtoupper(substr($paymentIntentId, 4, 2));
if ($order->has_billing_address()) {
    $airwallexBillingAddress = [
        'city' => $order->get_billing_city(),
        'country_code' => $order->get_billing_country(),
        'postcode' => $order->get_billing_postcode(),
        'state' => $order->get_shipping_state(),
        'street' => $order->get_billing_address_1(),
    ];
    $airwallexBilling['billing'] = [
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'email' => $order->get_billing_email(),
        'phone_number' => $order->get_billing_phone(),
    ];
    if (!empty($airwallexBillingAddress['city']) && !empty($airwallexBillingAddress['country_code']) && !empty($airwallexBillingAddress['street'])) {
        $airwallexBilling['billing']['address'] = $airwallexBillingAddress;
    }
}

$elementConfiguration = json_encode([
        'intent_id' => $paymentIntentId,
        'client_secret' => $paymentIntentClientSecret,
        'currency' => $order->get_currency(),
        'country_code' => $order->get_billing_country(),
        'autoCapture' => true,
        'applePayRequestOptions' => [
            'countryCode' => $merchantCountry,
        ],
        'googlePayRequestOptions' => [
            'countryCode' => $merchantCountry,
        ],
        'style' => [
            'variant' => 'bootstrap',
            'popupWidth' => 400,
            'popupHeight' => 549,
            'base' => [
              'color' => 'black',
            ],
        ],
        'shopper_name' => $order->get_formatted_billing_full_name(),
        'shopper_phone' => $order->get_billing_phone(),
        'shopper_email' => $order->get_billing_email(),
    ]
    + ($airwallexCustomerId?['customer_id' => $airwallexCustomerId]:[])
    + ($isSubscription ? [
        'mode' => 'recurring',
        'recurringOptions' => [
            'card' => [
                'next_triggered_by' => 'merchant',
                'merchant_trigger_reason' => 'scheduled',
                'currency' => $order->get_currency(),
            ],
        ],
    ] : [])
    + (!empty($methods) && is_array($methods) ? [
        'methods' => $methods,
    ] : [])
    + (isset($airwallexBilling) ? $airwallexBilling : [])
);

$inlineJs = <<<AIRWALLEX
        [].forEach.call(document.querySelectorAll('.elementor-menu-cart__container'), function (el) {
          el.style.visibility = 'hidden';
        });
        Airwallex.init({
            env: '$environment',
            locale: '$locale',
            origin: window.location.origin, // Setup your event target to receive the browser events message
        });
        const dropIn = Airwallex.createElement('dropIn', $elementConfiguration);
        dropIn.mount('airwallex-drop-in');
        window.addEventListener('onSuccess', (event) => {
            document.getElementById('airwallex-drop-in').style.display = 'none';
            document.getElementById('airwallex-error-message').style.display = 'none';
            var successCheck = document.getElementById('success-check');
            if(successCheck){
                successCheck.style.display = 'inline-block';
            }
            var successMessage = document.getElementById('success-message');
            if(successMessage){
                successMessage.style.display = 'block';
            }
            location.href = AirwallexParameters.confirmationUrl + 'order_id=$orderId&intent_id=$paymentIntentId';
        })
        window.addEventListener('onError', (event) => {
            document.getElementById('airwallex-error-message').style.display = 'block';
            console.warning(event.detail);
        });
AIRWALLEX;
wp_add_inline_script('airwallex-local-js', $inlineJs);
