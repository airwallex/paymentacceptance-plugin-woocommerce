<?php

namespace Airwallex\Gateways;

use Airwallex\Services\Util;

defined( 'ABSPATH' ) || exit();

class Klarna extends AirwallexGatewayLocalPaymentMethod {
    use AirwallexGatewayTrait;

    const GATEWAY_ID = 'klarna';
    const ROUTE_SLUG = 'airwallex_klarna';
    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'AT' => 'EUR',
        'BE' => 'EUR',
        'FI' => 'EUR',
        'FR' => 'EUR',
        'DE' => 'EUR',
        'GR' => 'EUR',
        'IE' => 'EUR',
        'IT' => 'EUR',
        'NL' => 'EUR',
        'PT' => 'EUR',
        'ES' => 'EUR',
        'DK' => 'DKK',
        'NO' => 'NOK',
        'PL' => 'PLN',
        'SE' => 'SEK',
        'CH' => 'CHF',
        'GB' => 'GBP',
        'CZ' => 'CZK',
        'US' => 'USD',
    ];
    const COUNTRY_LANGUAGE = [
        'AT' => ['de'],
        'BE' => ['be', 'nl', 'fr'],
        'CA' => ['fr'],
        'CH' => ['it', 'de', 'fr'],
        'CZ' => ['cs'],
        'DE' => ['de'],
        'DK' => ['da'],
        'ES' => ['es', 'ca'],
        'FI' => ['fi', 'sv'],
        'FR' => ['fr'],
        'GR' => ['el'],
        'IT' => ['it'],
        'NL' => ['nl'],
        'NO' => ['nb'],
        'PL' => ['pl'],
        'PT' => ['pt'],
        'SE' => ['sv'],
        'US' => ['es'],
    ];

    public function __construct() {
        $this->id = 'airwallex_' . self::GATEWAY_ID;
        $this->paymentMethodType = self::GATEWAY_ID;
        $this->paymentMethodName = 'Klarna';
        $this->method_title = __( 'Airwallex - Klarna', 'airwallex-online-payments-gateway' );
        $this->method_description = __( 'Accept Klarna payments with your Airwallex account', 'airwallex-online-payments-gateway' );
        $this->supports    = ['products', 'refunds'];
        $this->tabTitle = __('Klarna', 'airwallex-online-payments-gateway');

        parent::__construct();
    }

    public function get_form_fields() {
		return apply_filters( // phpcs:ignore
			'wc_airwallex_settings', // phpcs:ignore
			[
				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'Enable Airwallex Klarna', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => __( 'Klarna', 'airwallex-online-payments-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'airwallex-online-payments-gateway' ),
					'default'     => __( 'Pay later, or installments with Klarna', 'airwallex-online-payments-gateway' ),
					'desc_tip'    => true,
				),
            ]
		);
	}

    public function getLPMMethodScriptData($data) {
        $data[$this->id] = [
            'supportedCountryCurrency' => self::SUPPORTED_COUNTRY_TO_CURRENCY,
        ];
        $data['paymentMethods'][] = $this->id;

        return $data;
    }

    public function getPaymentMethod($order, $paymentIntentId) {
        $billing = $this->getBillingDetail($order);
        $countryCode = isset($billing['address']['country_code']) ? $billing['address']['country_code'] : '';

        return [
            'type' => 'klarna',
            'klarna' => [
                'billing' => $billing,
                'country_code' => $countryCode,
                'flow' => 'webqr',
                'intent_id' => $paymentIntentId,
                'language' => $this->getLanguage($countryCode),
                'shopper_email' => isset($billing['email']) ? $billing['email'] : '',
                'shopper_name' => $order->get_formatted_billing_full_name(),
                'shopper_phone' => isset($billing['phone_number']) ? $billing['phone_number'] : '',
            ],
        ];
    }

    public function getPaymentMethodOptions() {
        return [
            'klarna' => [
                'auto_capture' => true,
            ]
        ];
    }

    public function getLanguage($countryCode) {
        $language = Util::getLocale();
        $countryCode = strtoupper($countryCode);
        if (isset(self::COUNTRY_LANGUAGE[$countryCode]) && in_array($language, self::COUNTRY_LANGUAGE[$countryCode], true)) {
            return $language;
        }

        return 'en';
    }

    public function getPaymentMethodDocURL() {
        return 'https://help.airwallex.com/hc/en-gb/articles/9514119772047-What-countries-can-I-use-Klarna-in';
    }
}
