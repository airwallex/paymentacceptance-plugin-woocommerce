<?php

namespace Airwallex\Gateways;

use Airwallex\Services\Util;
use Airwallex\Struct\Quote;
use WC_AJAX;
use Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit;

abstract class AirwallexGatewayLocalPaymentMethod extends AbstractAirwallexGateway {

    public function registerHooks() {
        parent::registerHooks();
        // remove_filter( 'wc_airwallex_settings_nav_tabs', [ $this, 'adminNavTab' ] );
		// add_filter( 'wc_airwallex_local_gateways_tab', [ $this, 'adminNavTab' ] );
        add_filter( 'airwallex-lpm-script-data', [ $this, 'getLPMMethodScriptData' ] );
        add_action('wc_ajax_airwallex_currency_switcher_create_quote', [$this->quoteController, 'createQuoteForCurrencySwitching']);
        add_action('wc_ajax_airwallex_get_store_currency', [$this->orderController, 'getStoreCurrency']);
        add_action('woocommerce_review_order_after_order_total', [$this, 'renderCurrencySwitchingHtml']);
        add_action('wp_footer', [$this, 'renderQuoteExpireHtml']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function enqueueScripts() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style('airwallex-css' );
		wp_enqueue_script('airwallex-lpm-js');
		wp_add_inline_script('airwallex-lpm-js', 'var awxEmbeddedLPMData = ' . json_encode($this->getLPMScriptData()), 'before');
	}

    public function enqueueAdminScripts() {
	}

    public function outputSettingsNav() {
		parent::outputSettingsNav();
		// include AIRWALLEX_PLUGIN_PATH . 'includes/Gateways/Settings/views/settings-local-payment-methods-nav.php';
	}

    public function getLPMScriptData() {
        $data = [];
        try {
            $data = [
                'env' => Util::getEnvironment(),
                'ajaxUrl' => WC_AJAX::get_endpoint('%%endpoint%%'),
                'availableCurrencies' => $this->getAvailableCurrencies(),
                'originalCurrency' => get_woocommerce_currency(),
                'nonce' => [
                    'createQuoteCurrencySwitcher' => wp_create_nonce('wc-airwallex-lpm-create-quote-currency-switcher'),
                    'getStoreCurrency' => wp_create_nonce('wc-airwallex-lpm-get-store-currency'),
                ],
                'textTemplate' => [
                    'currencyIneligibleCWOn' => sprintf(
                        /* translators: Placeholder 1: Payment method name. */
                        __('%1$s is not available in $$original_currency$$ for your billing country. We have converted your total to $$converted_currency$$ for you to complete your payment.', 'airwallex-online-payments-gateway'),
                        $this->paymentMethodName,
                    ),
                    'currencyIneligibleCWOff' => sprintf(
                        /* translators: Placeholder 1: Payment method name. */
                        __('%1$s is not available in $$original_currency$$ for your billing country. Please use a different payment method to complete your purchase.', 'airwallex-online-payments-gateway'),
                        $this->paymentMethodName
                    ),
                    'conversionRate' => __('1 $$original_currency$$ = $$conversion_rate$$ $$converted_currency$$', 'airwallex-online-payments-gateway'),
                    'convertedAmount' => __('$$converted_amount$$ $$converted_currency$$', 'airwallex-online-payments-gateway'),
                ],
                'alterBoxIcons' => [
                    'criticalIcon' => AIRWALLEX_PLUGIN_URL . '/assets/images/critical_filled.svg',
                    'warningIcon' => AIRWALLEX_PLUGIN_URL . '/assets/images/warning_filled.svg',
                    'infoIcon' => AIRWALLEX_PLUGIN_URL . '/assets/images/info_filled.svg',
                ],
                'paymentMethods' => [],
            ];
            $data = apply_filters('airwallex-lpm-script-data', $data);
        } catch (Exception $e) {
            $this->logService->error(__METHOD__ . ' Get ' . $this->paymentMethodName . ' script data failed.', $e->getMessage());
        }

		return $data; 
	}

	abstract public function getLPMMethodScriptData( $data );

    public function getAvailableCurrencies() {
        $settings = $this->getCurrencySettings();
        if ( ! empty( $settings['currency_switcher']['currencies'] ) ) {
            return $settings['currency_switcher']['currencies'];
        }

        return []; 
    }

    public function payment_fields() {
        echo '<p style="display: flex; align-items: center;"><span>' . wp_kses_post( $this->description ) . '</span><span class="wc-airwallex-loader"></span></p>';

        $this->renderCountryIneligibleHtml();
		$this->renderCurrencyIneligibleCWOnHtml();
		$this->renderCurrencyIneligibleCWOffHtml();
    }

    public function renderCountryIneligibleHtml() {
		$awxAlertAdditionalClass = 'wc-airwallex-lpm-country-ineligible';
		$awxAlertType            = 'critical';
		$awxAlertText            = sprintf(
            /* translators: Placeholder 1: Payment method name. Placeholder 2: Open link tag. Placeholder 3: Close link tag. */
            __('%1$s is not available in your billing country. Please change your billing address to a %2$s compatible country %3$s or choose a different payment method.', 'airwallex-online-payments-gateway'),
            $this->paymentMethodName,
            '<a target=_blank href="' . $this->getPaymentMethodDocUrl() . '">',
            '</a>'
        );

		include AIRWALLEX_PLUGIN_PATH . 'templates/airwallex-alert-box.php';
	}

	/**
	 * Render the alter box for ineligible currency with currency switching turned on
	 */
	public function renderCurrencyIneligibleCWOnHtml() {
		$awxAlertAdditionalClass = 'wc-airwallex-lpm-currency-ineligible-switcher-on';
		$awxAlertType            = '';
		$awxAlertText            = '';

		include AIRWALLEX_PLUGIN_PATH . 'templates/airwallex-alert-box.php';
	}

	/**
	 * Render the alter box for ineligible currency with currency switching turned off
	 */
	public function renderCurrencyIneligibleCWOffHtml() {
		$awxAlertAdditionalClass = 'wc-airwallex-lpm-currency-ineligible-switcher-off';
		$awxAlertType            = 'critical';
		$awxAlertText            = '';

		include AIRWALLEX_PLUGIN_PATH . 'templates/airwallex-alert-box.php';
	}

    /**
     * Render the currency switching box to display the original amount and the converted amount
     */
    public function renderCurrencySwitchingHtml() {
        include_once AIRWALLEX_PLUGIN_PATH . 'templates/airwallex-currency-switching.php';
    }

    public function renderQuoteExpireHtml() {
        include_once AIRWALLEX_PLUGIN_PATH . 'templates/airwallex-currency-switching-quote-expire.php';
    }

    public function process_payment( $order_id ) {
        $result = [];
        try {
            $deviceData = isset($_POST['airwallex_device_data']) ? json_decode(wc_clean(wp_unslash($_POST['airwallex_device_data']))) : [];
            $targetCurrency = isset($_POST['airwallex_target_currency']) ? wc_clean(wp_unslash($_POST['airwallex_target_currency'])) : get_woocommerce_currency();
            $availableCurrency = $this->getAvailableCurrencies();

            $order = wc_get_order( $order_id );
            if ( empty( $order ) ) {
				$this->logService->debug(__METHOD__ . ' can not find order', [ 'orderId' => $order_id ] );
				throw new Exception( 'Order not found: ' . $order_id );
			}

            $airwallexCustomerId = null;
			if ( $order->get_customer_id( '' ) ) {
				$airwallexCustomerId = $this->orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $this->gatewayClient );
			}

            $this->logService->debug(__METHOD__ . ' create payment intent', [ 'orderId' => $order_id ] );
			$paymentIntent   = $this->gatewayClient->createPaymentIntent( $order->get_total(), $order->get_id(), true, $airwallexCustomerId );
            $this->logService->debug(__METHOD__ . ' payment intent created', [ 'payment intent' => $paymentIntent->toArray() ] );

            $this->logService->debug(__METHOD__ . ' confirm payment intent', [ 'payment intent id' => $paymentIntent->getId() ] );
            $confirmPayload = [
                'device_data' => $deviceData,
                'payment_method' => $this->getPaymentMethod($order, $paymentIntent->getId()),
                'payment_method_options' => $this->getPaymentMethodOptions(),
            ];

            if ($targetCurrency !== $paymentIntent->getBaseCurrency() && false !== array_search($targetCurrency, $availableCurrency)) {
                $this->logService->debug(__METHOD__ . ' - Create quote for ' . $targetCurrency );
                $quote = $this->gatewayClient->createQuoteForCurrencySwitching($paymentIntent->getBaseCurrency(), $targetCurrency, $paymentIntent->getBaseAmount());
                $confirmPayload['currency_switcher'] = [
                    'target_currency' => $targetCurrency,
                    'quote_id' => $quote->getId(),
                ];
                $this->updateOrderDetails($order, $quote);
            }
            $confirmedIntent = $this->gatewayClient->confirmPaymentIntent($paymentIntent->getId(), $confirmPayload);
            $this->logService->debug(__METHOD__ . ' payment intent confirmed', [ 'payment intent' => $confirmedIntent ] );

            $nextAction = $confirmedIntent->getNextAction();
            if (isset($nextAction['type']) && 'redirect' === $nextAction['type']) {
                $result = [
                    'result' => 'success',
                    'redirect' => $nextAction['url'],
                ];
            } else {
                throw new Exception('Not redirect payment method.');
            }

            WC()->session->set( 'airwallex_order', $order_id );
            WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );
			$order->update_meta_data( '_tmp_airwallex_payment_intent', $paymentIntent->getId() );
			$order->save();
        } catch (Exception $e) {
            $this->logService->error(__METHOD__ . ' Some went wrong during checkout.', $e->getMessage());
            $result = [
                'result' => 'failed',
                'message' => $e->getMessage(),
            ];
            wc_add_notice($e->getMessage(), 'error');
        }

        return $result;
	}

    // update order details with the quote currency rate
    public function updateOrderDetails(WC_Order $order, Quote $quote) {
        $rate = $quote->getClientRate();
        $orderItemTypes = array( 'line_item', 'shipping', 'fee', 'tax', 'coupon' );
		foreach ( $orderItemTypes as $type ) {
			foreach ( $order->get_items( $type ) as $item ) {
                switch ($type) {
                    case 'line_item':
                        if (is_callable([$item, 'set_subtotal']) && is_callable([$item, 'get_subtotal'])) {
                            $item->set_subtotal($item->get_subtotal(false) * $rate);
                        }
                        if (is_callable([$item, 'set_total']) && is_callable([$item, 'get_total'])) {
                            $item->set_total($item->get_total(false) * $rate);
                        }
                        break;
                    case 'shipping':
                        if (is_callable([$item, 'set_total']) && is_callable([$item, 'get_total'])) {
                            $item->set_total($item->get_total(false) * $rate);
                        }
                        break;
                    case 'fee':
                        if (is_callable([$item, 'set_total']) && is_callable([$item, 'get_total'])) {
                            $item->set_total($item->get_total(false) * $rate);
                        }
                        if (is_callable([$item, 'set_amount']) && is_callable([$item, 'get_amount'])) {
                            $item->set_amount($item->get_amount(false) * $rate);
                        }
                        break;
                    case 'tax':
                        if (is_callable([$item, 'set_tax_total']) && is_callable([$item, 'get_tax_total'])) {
                            $item->set_tax_total($item->get_tax_total(false) * $rate);
                        }
                        break;
                    case 'coupon':
                        if (is_callable([$item, 'set_discount']) && is_callable([$item, 'get_discount'])) {
                            $item->set_discount($item->get_discount(false) * $rate);
                        }
                        break;
                    default:
                        break;
                }
			}
		}

        $order->calculate_totals();
        $order->set_total( $quote->getTargetAmount());
        $order->set_currency($quote->getTargetCurrency());
        $order->add_meta_data('airwallex_payment_currency', $quote->getTargetCurrency());
    }

    public function getBillingDetail($order) {
        $billing = [];
        if ( $order->has_billing_address() ) {
            $address     = [
                'city'         => $order->get_billing_city(),
                'country_code' => $order->get_billing_country(),
                'postcode'     => $order->get_billing_postcode(),
                'state'        => $order->get_billing_state() ? $order->get_billing_state() : $order->get_shipping_state(),
                'street'       => $order->get_billing_address_1(),
            ];
            $billing = [
                'first_name'   => $order->get_billing_first_name(),
                'last_name'    => $order->get_billing_last_name(),
                'email'        => $order->get_billing_email(),
                'phone_number' => $order->get_billing_phone(),
            ];
            if ( ! empty( $address['city'] ) && ! empty( $address['country_code'] ) && ! empty( $address['street'] ) ) {
                $billing['address'] = $address;
            }
        }

        return $billing;
    }

    abstract public function getPaymentMethod($order, $paymentIntentId);

    abstract public function getPaymentMethodOptions();

    abstract public function getPaymentMethodDocURL();
}
