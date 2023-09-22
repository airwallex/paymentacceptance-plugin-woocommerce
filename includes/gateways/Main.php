<?php

namespace Airwallex\Gateways;

use Airwallex\CardClient;
use Airwallex\MainClient;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_Payment_Gateway;
use WP_Error;
use Airwallex\Services\OrderService;

if (!defined('ABSPATH')) {
    exit;
}

class Main extends WC_Payment_Gateway
{
    use AirwallexGatewayTrait;

    const STATUS_CONNECTED = 'connected';
    const STATUS__NOT_CONNECTED = 'not connected';
    const STATUS_ERROR = 'error';
    const ROUTE_SLUG = 'airwallex_main';

    public $method_title = 'Airwallex - All Payment Methods';
    public $method_description = 'Accepts all available payment methods with your Airwallex account, including cards, Apple Pay, Google Pay, and other local payment methods. ';
    public $title = 'Airwallex - All Payment Methods';
    public $description = '';
    public $icon = '';
    public $id = 'airwallex_main';
    public $plugin_id;
    public $max_number_of_logos = 5;
    public $supports = [
        'products',
        'refunds',
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
    ];
    public static $status = null;
    public $logService;

    public function __construct()
    {
        $this->max_number_of_logos = apply_filters('airwallex_max_number_of_logos', $this->max_number_of_logos);
        $this->plugin_id = AIRWALLEX_PLUGIN_NAME;
        $this->init_settings();
        $this->description = $this->get_option('description');
        if (($logos = $this->getActivePaymentLogosArray()) && count($logos) > $this->max_number_of_logos) {
            $logoHtml = '<div class="airwallex-logo-list">' . implode('', $logos) . '</div>';
            $logoHtml = apply_filters('airwallex_description_logo_html', $logoHtml, $logos);
            $this->description = $logoHtml . $this->description;
        }
        if ($this->get_client_id() && $this->get_api_key()) {
            $this->form_fields = $this->get_form_fields();
        } else {
            $this->method_description = '<div class="error" style="padding:10px;">' . sprintf(__('To start using Airwallex payment methods, please enter your credentials first. <br><a href="%s" class="button-primary">API settings</a>', AIRWALLEX_PLUGIN_NAME), admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_general')) . '</div>';
        }
        $this->title = $this->get_option('title');
        $this->logService = new LogService();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, [$this, 'do_subscription_payment'], 10, 2);
            add_filter('woocommerce_my_subscriptions_payment_method', [$this, 'subscription_payment_information'], 10, 2);
        }

    }

    public function getStatus()
    {
        if (self::$status === null) {
            self::$status = 0; //avoid circle
            if (empty($this->get_api_key()) || empty($this->get_client_id())) {
                self::$status = self::STATUS__NOT_CONNECTED;
            } else {
                $apiClient = MainClient::getInstance();
                self::$status = $apiClient->testAuth() ? self::STATUS_CONNECTED : self::STATUS_ERROR;
            }
        }
        return self::$status;
    }

    public function get_icon()
    {
        if (($logos = $this->getActivePaymentLogosArray()) && count($logos) <= $this->max_number_of_logos) {
            $return = implode('', $logos);
            return apply_filters('woocommerce_gateway_icon', $return, $this->id);
        } else {
            return parent::get_icon();
        }
    }

    public function getActivePaymentLogosArray()
    {
        $returnArray = [];
        if ($logos = $this->getPaymentLogos()) {
            $chosenLogos = (array)$this->get_option('icons');
            foreach ($logos as $logoKey => $logoValue) {
                if (in_array($logoKey, $chosenLogos)) {
                    $returnArray[] = '<img src="' . $logos[$logoKey] . '" class="airwallex-card-icon" alt="' . esc_attr($this->get_title()) . '" />';
                }
            }
        }
        return $returnArray;
    }

    public function getPaymentLogos()
    {
        try {
            $cacheService = new CacheService($this->get_api_key());
            $logos = $cacheService->get('paymentLogos');
            if (empty($logos)) {
                $apiClient = CardClient::getInstance();
                if ($paymentMethodTypes = $apiClient->getPaymentMethodTypes()) {
                    $logos = [];
                    foreach ($paymentMethodTypes as $paymentMethodType) {
                        if ($paymentMethodType['name'] === 'card') {
                            $prefix = $paymentMethodType['name'] . '_';
                            $subMethods = $paymentMethodType['card_schemes'];
                        } else {
                            $prefix = '';
                            $subMethods = [$paymentMethodType];
                        }
                        foreach ($subMethods as $subMethod) {
                            if (isset($subMethod['resources']['logos']['svg'])) {
                                $logos[$prefix . $subMethod['name']] = $subMethod['resources']['logos']['svg'];
                            }
                        }
                    }
                    $logos = $this->sort_icons($logos);
                    $cacheService->set('paymentLogos', $logos, 86400);
                }
            }
        } catch (\Exception $e) {
            (new LogService())->debug('unable to get payment logos', ['exception' => $e->getMessage()]);
            $logos = [];
        }
        return $logos;

    }

    public function getPaymentMethods()
    {
        try {
            $cacheService = new CacheService($this->get_api_key());
            $methods = $cacheService->get('paymentMethods');
            if (empty($methods)) {
                $apiClient = CardClient::getInstance();
                if ($paymentMethodTypes = $apiClient->getPaymentMethodTypes()) {
                    foreach ($paymentMethodTypes as $paymentMethodType) {
                        if (empty($paymentMethodType['name']) || empty($paymentMethodType['display_name'])) {
                            continue;
                        }
                        $methods[$paymentMethodType['name']] = $paymentMethodType['display_name'];
                    }
                    $cacheService->set('paymentMethods', $methods, 14400);
                }
            }
        } catch (\Exception $e) {
            (new LogService())->debug('unable to get payment methods', ['exception' => $e->getMessage()]);
            $methods = [];
        }
        return $methods;

    }


    public function get_form_fields()
    {
        $isAdmin = substr($_SERVER['SCRIPT_FILENAME'], -10) === '/admin.php'
            && isset($_GET['page']) && $_GET['page'] === 'wc-settings'
            && isset($_GET['section']) && $_GET['section'] === 'airwallex_main';
        $intro = '';
        if ($isAdmin) {
            $cStatus = $this->getStatus();
            $statusHtml = '<span style="padding: 3px 8px; font-weight:bold; border-radius:3px; background-color: ' . ($cStatus === self::STATUS_CONNECTED ? '#E0F7E7' : '#FFADAD') . '">' . $cStatus . '</span>';
            $intro .= '<div>
                           ' . sprintf(__('Airwallex API settings %s <a href="%s">edit</a>', AIRWALLEX_PLUGIN_NAME), $statusHtml, admin_url('admin.php?page=wc-settings&tab=checkout&section=airwallex_general'));
        }
        $logos = $this->getPaymentLogos();
        return apply_filters(
            'wc_airwallex_settings',
            [
                'info1' => [
                    'type' => 'free',
                    'html' => '
                        <img src="' . AIRWALLEX_PLUGIN_URL . '/assets/images/logo.svg" width="150" alt="Airwallex" /><br><br>
                        ' . $intro,
                    'default' => '',
                ],
                'enabled' => [
                    'title' => __('Enable/Disable', AIRWALLEX_PLUGIN_NAME),
                    'label' => __('Enable Airwallex Payments', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ],
                'title' => [
                    'title' => __('Title', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('What title to display for this payment method', AIRWALLEX_PLUGIN_NAME),
                    'default' => __('Pay with cards and more', AIRWALLEX_PLUGIN_NAME),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', AIRWALLEX_PLUGIN_NAME),
                    'type' => 'text',
                    'description' => __('What subtext to display for this payment method. Can be left blank.', AIRWALLEX_PLUGIN_NAME),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'icons' => [
                    'title' => __('Icons to display', AIRWALLEX_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'logos',
                    'desc_tip' => __('Choose which payment method logos to display before your payer proceeds to checkout.', AIRWALLEX_PLUGIN_NAME),
                    'options' => $logos,
                    'default' => '',
                ],
                'methods' => [
                    'title' => __('Payment methods', AIRWALLEX_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'methods',
                    'description' => sprintf(__('Shoppers with different shipping address countries may see different payment methods in their list. (<a href="%s" target="_blank">See details</a>)', AIRWALLEX_PLUGIN_NAME), 'https://www.airwallex.com/docs/online-payments__overview'),
                    'options' => $this->getPaymentMethods(),
                    'default' => '',
                ],
                'template' => [
                    'title' => __('Payment page template', AIRWALLEX_PLUGIN_NAME),
                    'label' => '',
                    'type' => 'radio',
                    'desc_tip' => __('Select the way you want to arrange the order details and the payment method list', AIRWALLEX_PLUGIN_NAME),
                    'options' => [
                        '2col-1' => '',
                        '2col-2' => '',
                        '2row' => '',
                    ],
                    'default' => '2col-1',
                ],
            ]
        );
    }

    public function process_payment($order_id)
    {
        $return = [
            'result' => 'success',
        ];
        WC()->session->set('airwallex_order', $order_id);
        $return['redirect'] = $this->getPaymentPageUrl('airwallex_payment_method_all');
        return $return;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        $paymentIntentId = $order->get_transaction_id();
        $apiClient = MainClient::getInstance();
        try {
            $refund = $apiClient->createRefund($paymentIntentId, $amount, $reason);
            $metaKey = $refund->getMetaKey();
            if (!$order->meta_exists($metaKey)) {
                $order->add_order_note(sprintf(
                    __('Airwallex refund initiated: %s', AIRWALLEX_PLUGIN_NAME),
                    $refund->getId()
                ));
                add_post_meta($order->get_id(), $metaKey, ['status' => Refund::STATUS_CREATED]);
            } else {
                throw new Exception("refund {$refund->getId()} already exist.", '1');
            }
            $this->logService->debug(__METHOD__ . " - Order: {$order_id}, refund initiated, {$refund->getId()}");
        } catch (\Exception $e) {
            $this->logService->debug(__METHOD__ . " - Order: {$order_id}, refund failed, {$e->getMessage()}");
            return new WP_Error($e->getCode(), 'Refund failed, ' . $e->getMessage());
        }

        return true;
    }

    public function subscription_payment_information($paymentMethodName, $subscription)
    {
        $customerId = $subscription->get_customer_id();
        if ($subscription->get_payment_method() !== $this->id || !$customerId) {
            return $paymentMethodName;
        }
        //add additional payment details
        return $paymentMethodName;
    }

    public function do_subscription_payment($amount, $order)
    {

        try {
            $subscriptionId = $order->get_meta('_subscription_renewal');
            $subscription = wcs_get_subscription($subscriptionId);
            $originalOrderId = $subscription->get_parent();
            $originalOrder = wc_get_order($originalOrderId);
            $airwallexCustomerId = $originalOrder->get_meta('airwallex_customer_id');
            $airwallexPaymentConsentId = $originalOrder->get_meta('airwallex_consent_id');
            $cardClient = new CardClient();
            $paymentIntent = $cardClient->createPaymentIntent($amount, $order->get_id(), false, $airwallexCustomerId);
            $paymentIntentAfterConfirm = $paymentIntentAfterCapture = $cardClient->confirmPaymentIntent($paymentIntent->getId(), $airwallexPaymentConsentId);

            //if ($paymentIntentAfterConfirm->getStatus() === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
            //    $paymentIntentAfterCapture = $cardClient->capture($paymentIntent->getId(), $amount);
            if ($paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED) {
                (new LogService())->debug('capture successful', $paymentIntentAfterCapture->toArray());
                $order->add_order_note('Airwallex payment capture success');
                $order->payment_complete($paymentIntent->getId());
            } else {
                (new LogService())->error('capture failed', $paymentIntentAfterCapture->toArray());
                $order->add_order_note('Airwallex payment failed capture');
            }
            //} else {
            //    (new LogService())->error('intent confirm failed', $paymentIntentAfterConfirm->toArray());
            //    $order->add_order_note('Airwallex payment failed intend confirmation');
            //}
        } catch (Exception $e) {
            (new LogService())->error('do_subscription_payment failed', $e->getMessage());
        }

    }

    public function generate_free_html($key, $data)
    {

        ob_start();
        ?>
        <tr>
            <td colspan="2"><?php echo $data['html']; ?></td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function generate_radio_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'options' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <div style="display: flex;">
                        <?php foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                            <div style="width:120px; margin-right:10px; text-align:center;">
                                <label>
                                    <div>
                                        <img style="max-width:100%;" src="<?php echo AIRWALLEX_PLUGIN_URL . '/assets/images/layout/' . $option_key . '.png'; ?>"/>
                                    </div>
                                    <input
                                            type="radio"
                                            name="<?php echo esc_attr($field_key); ?>"
                                            value="<?php echo esc_attr($option_key); ?>"
                                        <?php checked((string)$option_key, esc_attr($this->get_option($key))); ?>
                                    />
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function validate_logos_field($key, $value)
    {
        return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
    }

    public function generate_logos_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'options' => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $value = (array)$this->get_option($key, []);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <div style="display: flex; flex-wrap: wrap; max-width:430px;">
                        <?php foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                            <div style="width:60px; margin-right:10px; text-align:center;">
                                <label>
                                    <div>
                                        <img style="max-width:100%;" src="<?php echo $option_value; ?>"/>
                                    </div>
                                    <input
                                            type="checkbox"
                                            name="<?php echo esc_attr($field_key); ?>[]"
                                            value="<?php echo esc_attr($option_key); ?>"
                                        <?php checked(in_array((string)$option_key, $value, true), true); ?>
                                    />
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function validate_methods_field($key, $value)
    {
        return is_array($value) ? array_map('wc_clean', array_map('stripslashes', $value)) : '';
    }

    public function generate_methods_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'options' => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $value = (array)$this->get_option($key, []);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.
                    ?></label>
            </th>
            <td class="forminp">
                <?php echo $this->get_description_html($data); // WPCS: XSS ok.
                ?>
                <fieldset>
                    <div>
                        <?php
                        foreach ((array)$data['options'] as $option_key => $option_value) {
                            $toolTip = (in_array($option_key, ['applepay', 'googlepay'])) ? __('There are additional steps to set up this payment method. Please refer to the installation guide for more details.', AIRWALLEX_PLUGIN_NAME) : null;
                            ?>
                            <div>
                                <label>
                                    <input
                                            type="checkbox"
                                            name="<?php echo esc_attr($field_key); ?>[]"
                                            value="<?php echo esc_attr($option_key); ?>"
                                        <?php checked(in_array((string)$option_key, $value, true), true); ?>
                                    />
                                    <?php
                                    echo $option_value;
                                    if ($toolTip) {
                                        echo wc_help_tip($toolTip);
                                    }
                                    ?>
                                </label>
                            </div>
                            <?php
                        }
                        ?>
                    </div>

                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function output($attrs) {
        if (is_admin() || empty(WC()->session)) {
            $this->logService->debug('Update all payment methods shortcode.', [], LogService::DROP_IN_ELEMENT_TYPE);
            return;
        }

        extract(shortcode_atts(
            [
                'style' => ''
            ],
            $attrs,
            'airwallex_payment_method_all'
        ));

        try {
            $orderId = (int)WC()->session->get('airwallex_order');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }
            $order = wc_get_order($orderId);
            if (empty($order)) {
                throw new Exception('Order not found: ' . $orderId);
            }

            $apiClient = MainClient::getInstance();
            $airwallexCustomerId = null;
            $orderService = new OrderService();
            $isSubscription = $orderService->containsSubscription($order->get_id());
            if ($order->get_customer_id('') || $isSubscription) {
                $airwallexCustomerId = $orderService->getAirwallexCustomerId($order->get_customer_id(''), $apiClient);
            }

            $paymentIntent = $apiClient->createPaymentIntent($order->get_total(), $order->get_id(), $this->is_submit_order_details(), $airwallexCustomerId);
            $paymentIntentId = $paymentIntent->getId();
            $paymentIntentClientSecret = $paymentIntent->getClientSecret();
            $confirmationUrl = $this->get_payment_confirmation_url();
            $isSandbox = $this->is_sandbox();
            WC()->session->set('airwallex_payment_intent_id', $paymentIntentId);

            $this->logService->debug('Redirect to the dropIn payment page', [
                'orderId' => $orderId,
                'paymentIntent' => $paymentIntentId,
            ], LogService::CARD_ELEMENT_TYPE);

            include_once(AIRWALLEX_PLUGIN_PATH . '/html/drop-in-payment.php');
        } catch (Exception $e) {
            $this->logService->error('Drop in payment action failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE);
            wc_add_notice(__('Airwallex payment error', AIRWALLEX_PLUGIN_NAME), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }
}
