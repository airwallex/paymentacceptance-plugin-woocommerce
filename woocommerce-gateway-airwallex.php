<?php
/**
 * Plugin Name: WooCommerce Airwallex Gateway
 * Plugin URI:
 * Description: Official Airwallex Plugin
 * Author: Airwallex
 * Author URI: https://www.airwallex.com
 * Version: 1.0.0
 * Requires at least: 4.0
 * Tested up to:
 * WC requires at least: 3.0
 * WC tested up to:
 * Text Domain: woocommerce-gateway-airwallex
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Required minimums and constants
 */
define('AIRWALLEX_VERSION', '1.0.0');
define('AIRWALLEX_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('AIRWALLEX_PLUGIN_PATH', __DIR__ . '/');
define('AIRWALLEX_PLUGIN_NAME', 'woocommerce-gateway-airwallex');

function airwallex_init()
{

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function(){echo '<div class="error"><p><strong>'.__('Airwallex requires WooCommerce to be installed and active.', AIRWALLEX_PLUGIN_NAME).'</strong></p></div>';});
        return;
    }
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/Main.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/struct/AbstractBase.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/client/AbstractClient.php';
    require_once AIRWALLEX_PLUGIN_PATH . 'includes/gateways/AirwallexGatewayTrait.php';
    foreach (glob(AIRWALLEX_PLUGIN_PATH . 'includes/*/*.php') as $includeFile) {
        require_once $includeFile;
    }
    $airwallex = \Airwallex\Main::getInstance();
    $airwallex->init();

}

add_action('plugins_loaded', 'airwallex_init');

add_action('wp_footer', function () {
    global $airwallexIsCheckout;

$orderService = new \Airwallex\Services\OrderService();
    var_dump($orderService->getRefundByAmountAndTime(41, 5, date('Y-m-d H:i:s')));die;


    $isCheckout          = is_checkout();
    $cardGateway         = new \Airwallex\Gateways\Card();
    $jsUrl               = AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-checkout.js';
    $jsUrlLocal               = AIRWALLEX_PLUGIN_URL . '/assets/js/airwallex-local.js';
    $cssUrl = AIRWALLEX_PLUGIN_URL . '/assets/css/airwallex-checkout.css';
    echo '<script>
            const AirwallexParameters = {
                asyncIntentUrl: \'' . $cardGateway->get_async_intent_url() . '\',
                confirmationUrl: \'' . $cardGateway->get_payment_confirmation_url() . '\',
                isCardInputComplete: false
            };';
    if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
        global $wp;
        if($order_id = (int)$wp->query_vars['order-pay']) {
            $order = wc_get_order($order_id);
            if (is_a($order, 'WC_Order')) {
                echo 'AirwallexParameters.billingFirstName = ' . json_encode($order->get_billing_first_name()) . ';';
                echo 'AirwallexParameters.billingLastName = ' . json_encode($order->get_billing_last_name()) . ';';
                echo 'AirwallexParameters.billingAddress1 = ' . json_encode($order->get_billing_address_1()) . ';';
                echo 'AirwallexParameters.billingAddress2 = ' . json_encode($order->get_billing_address_2()) . ';';
                echo 'AirwallexParameters.billingState = ' . json_encode($order->get_billing_state()) . ';';
                echo 'AirwallexParameters.billingCity = ' . json_encode($order->get_billing_city()) . ';';
                echo 'AirwallexParameters.billingPostcode = ' . json_encode($order->get_billing_postcode()) . ';';
                echo 'AirwallexParameters.billingCountry = ' . json_encode($order->get_billing_country()) . ';';
                echo 'AirwallexParameters.billingEmail = ' . json_encode($order->get_billing_email()) . ';';
            }
        }
    }
    echo '</script>';

    if (!$isCheckout) {
        return;
    }
    echo '<script src = "' . $jsUrl . '" ></script >';
    echo '<script src = "' . $jsUrlLocal . '" ></script >';
    echo '<link rel="stylesheet" href="' . $cssUrl . '" />';
    $errorMessage      = __('An error has occurred. Please check your payment details (%s)', AIRWALLEX_PLUGIN_NAME);
    $incompleteMessage = __('Your credit card details are incomplete', AIRWALLEX_PLUGIN_NAME);
    $environment       = $cardGateway->is_sandbox() ? 'demo' : 'prod';
    $autoCapture = $cardGateway->is_capture_immediately()?'true':'false';
    echo <<<AIRWALLEX
    

    <script>
    
    
    jQuery( document.body ).on( 'checkout_error' , function(e, msg){
        if(msg && msg.indexOf('<!--Airwallex payment processing-->') !== -1){
            jQuery('form.checkout').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
            confirmSlimCardPayment();
        }
    });
    
    //this is for payment changes after order placement
    jQuery('#order_review').on( 'submit', function(e){
        let airwallexCardPaymentOption = jQuery('#payment_method_airwallex_card');
        if(airwallexCardPaymentOption.length && airwallexCardPaymentOption.is(':checked')){
            if(jQuery('#airwallex-card').length){
                e.preventDefault();
                confirmSlimCardPayment();
            }
        }
    });
    
        Airwallex.init({
            env: '$environment',
            origin: window.location.origin, // Setup your event target to receive the browser events message
        });

        const airwallexSlimCard = Airwallex.createElement('card');
        airwallexSlimCard.mount('airwallex-card');
        
     function confirmSlimCardPayment(){
         if(!AirwallexParameters.isCardInputComplete){
            AirwallexClient.displayCheckoutError('$incompleteMessage');
            return;
         }
         
         AirwallexClient.ajaxGet(AirwallexParameters.asyncIntentUrl, function(data){
             if(!data){
                 AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', ''));
             }
             Airwallex.confirmPaymentIntent({
               element: airwallexSlimCard,
               id: data.paymentIntent,
               client_secret: data.clientSecret,
               payment_method:{
                 card:{
                   name: AirwallexClient.getCardHolderName()
                 },
                 billing: AirwallexClient.getBillingInformation()
               },
               payment_method_options: {
                   card: {
                     auto_capture: $autoCapture,
                   },
               }
             }).then((response) => {
                location.href = AirwallexParameters.confirmationUrl;
             }).catch(err => {
                 console.log(err);
                 jQuery('form.checkout').unblock();
                 AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', err.message||''));
             })
           });
         }
         
      window.addEventListener('onChange', (event) => {
        if(!event.detail){
            return;
        }
        const { complete } = event.detail;
        AirwallexParameters.isCardInputComplete = complete;
      });
     
     window.addEventListener('onError', (event) => {
        if(!event.detail){
            return;
        }
        const { error } = event.detail;
        AirwallexClient.displayCheckoutError(String('$errorMessage').replace('%s', error.message||''));
      });
  
        

</script>
AIRWALLEX;
});

