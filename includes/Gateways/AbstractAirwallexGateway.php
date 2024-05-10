<?php

namespace Airwallex\Gateways;

use Action_Scheduler\Migration\Controller;
use Airwallex\Struct\Refund;
use Airwallex\Client\GatewayClient;
use Airwallex\Controllers\ControllerFactory;
use Airwallex\Controllers\QuoteController;
use Airwallex\Gateways\Settings\AirwallexSettingsTrait;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Services\OrderService;
use Airwallex\Services\ServiceFactory;
use Airwallex\Controllers\OrderController;
use Airwallex\Services\Util;
use Exception;
use WC_Payment_Gateway;
use WP_Error;
use WC_HTTPS;

defined( 'ABSPATH' ) || exit;

abstract class AbstractAirwallexGateway extends WC_Payment_Gateway {
	use AirwallexSettingsTrait;

	const PAYMENT_METHOD_TYPE_CACHE_KEY = 'paymentMethodTypes';
	const CURRENCY_SETTINGS_CACHE_KEY = 'currencySettings';

	protected $logService;
	protected $cacheService;
	protected $orderService;
	protected $gatewayClient;
	protected $quoteController;
	protected $orderController;
	public $paymentMethodType;
	public $paymentMethodName;

	public function __construct() {
		$this->logService = ServiceFactory::createLogService();
		$this->cacheService = ServiceFactory::createCacheService(Util::getClientSecret());
		$this->orderService = ServiceFactory::createOrderService();
		$this->gatewayClient = GatewayClient::getInstance();
		$this->quoteController = ControllerFactory::createQuoteController();
		$this->orderController = ControllerFactory::createOrderController();

		$this->plugin_id   = AIRWALLEX_PLUGIN_NAME;
		$this->init_settings();
		$this->enabled = 'yes' === $this->enabled ? $this->isAvailable() : 'no';
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->registerHooks();
	}

	public function registerHooks() {
		add_filter( 'wc_airwallex_settings_nav_tabs', array( $this, 'adminNavTab' ), 14 );
		add_action( 'woocommerce_airwallex_settings_checkout_' . $this->id, array( $this, 'enqueueAdminScripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function needs_setup() {
		return true;
	}

	public function isAvailable() {
		return isset($this->getPaymentMethodTypesNew()[$this->paymentMethodType]) ? 'yes' : 'no';
	}

	public function get_icon() {
		$icon = $this->getIcon();

		if ( $icon['url'] ) {
			$icon = '<img src="' . WC_HTTPS::force_https_url( $icon['url'] ) . '" class="airwallex-card-icon" alt="' . esc_attr( $this->get_title() ) . '" />';

			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id ); // phpcs:ignore
		} else {
			return parent::get_icon();
		}
	}

	public function getIcon() {
		$paymentMethodTypes = $this->getPaymentMethodTypesNew();
		$iconUrl = '';
		if ( ! empty( $paymentMethodTypes[$this->paymentMethodType]['oneoff']['resources']['logos']['svg'] ) ) {
			$iconUrl = $paymentMethodTypes[$this->paymentMethodType]['oneoff']['resources']['logos']['svg'];
		} else if ( ! empty( $paymentMethodTypes[$this->paymentMethodType]['recurring']['resources']['logos']['svg'] ) ) {
			$iconUrl = $paymentMethodTypes[$this->paymentMethodType]['recurring']['resources']['logos']['svg'];
		}

		return [
			'url' => $iconUrl,
			'alt' => $this->title,
		];
	}

	/**
	 * Get raw active payment method types
	 * 
	 * @return array|null
	 */
	public function getPaymentMethodTypesNew() {
		$paymentMethodTypes = $this->cacheService->get( self::PAYMENT_METHOD_TYPE_CACHE_KEY );

		if ( empty( $paymentMethodTypes ) ) {
			try {
				$pageNum = 0;
				$paymentMethodTypes = [];
				do {
					$data = $this->gatewayClient->getActivePaymentMethodTypes($pageNum);
					if ( isset( $data['items'] ) ) {
						foreach( $data['items'] as $methodType ) {
							$paymentMethodTypes[$methodType['name']][$methodType['transaction_mode']] = $methodType;
						}
					}
					$pageNum++;
				} while ( isset( $data['has_more'] ) && $data['has_more'] );

				$this->cacheService->set( self::PAYMENT_METHOD_TYPE_CACHE_KEY, $paymentMethodTypes, HOUR_IN_SECONDS );
			} catch ( Exception $e ) {
				$this->logService->error(__METHOD__ . ' Failed to get payment method types.', $e->getMessage());
			}
		}

		return $paymentMethodTypes;
	}

	public function getCurrencySettings() {
		$currencySettings = $this->cacheService->get( self::CURRENCY_SETTINGS_CACHE_KEY );

		if ( is_null( $currencySettings ) ) {
			try {
				$pageNum = 0;
				$currencySettings = [];
				do {
					$data = $this->gatewayClient->getCurrencySettings($pageNum);
					if ( isset( $data['items'] ) ) {
						foreach( $data['items'] as $item ) {
							if ( isset( $item['type'] ) ) {
								$currencySettings[$item['type']] = $item;
							}
						}
					}
					$pageNum++;
				} while ( isset( $data['has_more'] ) && $data['has_more'] );

				$this->cacheService->set( self::CURRENCY_SETTINGS_CACHE_KEY, $currencySettings, HOUR_IN_SECONDS );
			} catch (Exception $e) {
				$this->logService->error(__METHOD__ . ' Failed to get currency settings.', $e->getMessage());
			}
		}

		return $currencySettings;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order           = wc_get_order( $order_id );
		$paymentIntentId = $order->get_transaction_id();
		try {
			$refund  = $this->gatewayClient->createRefund( $paymentIntentId, $amount, $reason );
			$metaKey = $refund->getMetaKey();
			if ( ! $order->meta_exists( $metaKey ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: Placeholder 1: Airwallex refund ID. */
						__( 'Airwallex refund initiated: %s', 'airwallex-online-payments-gateway' ),
						$refund->getId()
					)
				);
				$order->add_meta_data( $metaKey, array( 'status' => Refund::STATUS_CREATED ) );
				$order->save();
			} else {
				throw new Exception( "refund {$refund->getId()} already exist.", '1' );
			}
			$this->logService->debug( __METHOD__ . " - Order: {$order_id}, refund initiated, {$refund->getId()}" );
		} catch ( \Exception $e ) {
			$this->logService->debug( __METHOD__ . " - Order: {$order_id}, refund failed, {$e->getMessage()}" );
			return new WP_Error( $e->getCode(), 'Refund failed, ' . $e->getMessage() );
		}

		return true;
	}
}
