<?php

namespace Airwallex\Gateways;

use Airwallex\Client\CardClient;
use Airwallex\Client\MainClient;
use Airwallex\Gateways\Settings\AirwallexSettingsTrait;
use Airwallex\Services\CacheService;
use Airwallex\Services\LogService;
use Airwallex\Struct\PaymentIntent;
use Airwallex\Struct\Refund;
use Exception;
use WC_Payment_Gateway;
use WP_Error;
use Airwallex\Services\OrderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Main extends WC_Payment_Gateway {

	use AirwallexGatewayTrait;
	use AirwallexSettingsTrait;

	const STATUS_CONNECTED      = 'connected';
	const STATUS__NOT_CONNECTED = 'not connected';
	const STATUS_ERROR          = 'error';
	const ROUTE_SLUG            = 'airwallex_main';
	const GATEWAY_ID = 'airwallex_main';

	public $method_title       = 'Airwallex - All Payment Methods';
	public $method_description = 'Accepts all available payment methods with your Airwallex account, including cards, Apple Pay, Google Pay, and other local payment methods. ';
	public $title              = 'Airwallex - All Payment Methods';
	public $description        = '';
	public $icon               = '';
	public $id                 = self::GATEWAY_ID;
	public $plugin_id;
	public $max_number_of_logos = 5;
	public $supports            = array(
		'products',
		'refunds',
		'subscriptions',
		'subscription_cancellation',
		'subscription_suspension',
		'subscription_reactivation',
		'subscription_amount_changes',
		'subscription_date_changes',
		'multiple_subscriptions',
	);
	public static $status       = null;
	public $logService;

	public function __construct() {
		$this->max_number_of_logos = apply_filters( 'airwallex_max_number_of_logos', $this->max_number_of_logos ); // phpcs:ignore
		$this->plugin_id           = AIRWALLEX_PLUGIN_NAME;
		$this->init_settings();
		$this->description = $this->get_option( 'description' );
		$logos             = $this->getActivePaymentLogosArray();
		if ( $logos && count( $logos ) > $this->max_number_of_logos ) {
			$logoHtml          = '<div class="airwallex-logo-list">' . implode( '', $logos ) . '</div>';
			$logoHtml          = apply_filters( 'airwallex_description_logo_html', $logoHtml, $logos ); // phpcs:ignore
			$this->description = $logoHtml . $this->description;
		}
		if ( $this->get_client_id() && $this->get_api_key() ) {
			$this->form_fields = $this->get_form_fields();
		}
		$this->title      = $this->get_option( 'title' );
		$this->logService = new LogService();
		$this->tabTitle   = 'All Payment Methods';
		$this->registerHooks();
	}

	public function registerHooks() {
		add_filter( 'wc_airwallex_settings_nav_tabs', array( $this, 'adminNavTab' ), 14 );
		add_action( 'woocommerce_airwallex_settings_checkout_' . $this->id, array( $this, 'enqueueAdminScripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'do_subscription_payment' ), 10, 2 );
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'subscription_payment_information' ), 10, 2 );
		}
	}

	public function enqueueAdminScripts() {
	}

	public function getStatus() {
		if ( null === self::$status ) {
			self::$status = 0; //avoid circle
			if ( empty( $this->get_api_key() ) || empty( $this->get_client_id() ) ) {
				self::$status = self::STATUS__NOT_CONNECTED;
			} else {
				$apiClient    = MainClient::getInstance();
				self::$status = $apiClient->testAuth() ? self::STATUS_CONNECTED : self::STATUS_ERROR;
			}
		}
		return self::$status;
	}

	public function get_icon() {
		$logos = $this->getActivePaymentLogosArray();
		if ( $logos && count( $logos ) <= $this->max_number_of_logos ) {
			$return = implode( '', $logos );
			return apply_filters( 'woocommerce_gateway_icon', $return, $this->id ); // phpcs:ignore
		} else {
			return parent::get_icon();
		}
	}

	public function getActivePaymentLogosArray() {
		$returnArray = array();
		$logos       = $this->getPaymentLogos();
		if ( $logos ) {
			$chosenLogos = (array) $this->get_option( 'icons' );
			foreach ( $logos as $logoKey => $logoValue ) {
				if ( in_array( $logoKey, $chosenLogos, true ) ) {
					$returnArray[] = '<img src="' . esc_url( $logoValue ) . '" class="airwallex-card-icon" alt="' . esc_attr( $this->get_title() ) . '" />';
				}
			}
		}
		return $returnArray;
	}

	public function getPaymentLogos() {
		try {
			$cacheService = new CacheService( $this->get_api_key() );
			$logos        = $cacheService->get( 'paymentLogos' );
			if ( empty( $logos ) ) {
				$paymentMethodTypes = $this->getPaymentMethodTypes();
				if ( $paymentMethodTypes ) {
					$logos = array();
					foreach ( $paymentMethodTypes as $paymentMethodType ) {
						if ( 'card' === $paymentMethodType['name'] ) {
							$prefix     = $paymentMethodType['name'] . '_';
							$subMethods = $paymentMethodType['card_schemes'];
						} else {
							$prefix     = '';
							$subMethods = array( $paymentMethodType );
						}
						foreach ( $subMethods as $subMethod ) {
							if ( isset( $subMethod['resources']['logos']['svg'] ) ) {
								$logos[ $prefix . $subMethod['name'] ] = $subMethod['resources']['logos']['svg'];
							}
						}
					}
					$logos = $this->sort_icons( $logos );
					$cacheService->set( 'paymentLogos', $logos, 86400 );
				}
			}
		} catch ( \Exception $e ) {
			( new LogService() )->debug( 'unable to get payment logos', array( 'exception' => $e->getMessage() ) );
			$logos = array();
		}
		return $logos;
	}

	public function getPaymentMethods() {
		try {
			$cacheService = new CacheService( $this->get_api_key() );
			$methods      = $cacheService->get( 'paymentMethods' );
			if ( empty( $methods ) ) {
				$paymentMethodTypes = $this->getPaymentMethodTypes();
				if ( $paymentMethodTypes ) {
					foreach ( $paymentMethodTypes as $paymentMethodType ) {
						if ( empty( $paymentMethodType['name'] ) || empty( $paymentMethodType['display_name'] ) ) {
							continue;
						}
						$methods[ $paymentMethodType['name'] ] = $paymentMethodType['display_name'];
					}
					$cacheService->set( 'paymentMethods', $methods, 14400 );
				}
			}
		} catch ( \Exception $e ) {
			( new LogService() )->debug( 'unable to get payment methods', array( 'exception' => $e->getMessage() ) );
			$methods = array();
		}
		return $methods;
	}


	public function get_form_fields() {
		$isAdmin = isset( $_SERVER['SCRIPT_FILENAME'] ) && '/admin.php' === substr( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ), -10 )
			&& isset( $_GET['page'] ) && 'wc-settings' === $_GET['page']
			&& isset( $_GET['section'] ) && 'airwallex_main' === $_GET['section'];
		$intro   = '';
		if ( $isAdmin ) {
			$cStatus    = $this->getStatus();
			$statusHtml = '<span style="padding: 3px 8px; font-weight:bold; border-radius:3px; background-color: ' . ( self::STATUS_CONNECTED === $cStatus ? '#E0F7E7' : '#FFADAD' ) . '">' . $cStatus . '</span>';
			$intro     .= '<div>' . sprintf(
				/* translators: Placeholder 1: Connection status html. Placeholder 2: API settings url  */
				__( 'Airwallex API settings %1$s <a href="%2$s">edit</a>', 'airwallex-online-payments-gateway' ),
				$statusHtml,
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=airwallex_general' )
			);
		}
		$logos = $this->getPaymentLogos();
		return apply_filters( // phpcs:ignore
			'wc_airwallex_settings', // phpcs:ignore
			array(
				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'airwallex-online-payments-gateway' ),
					'label'       => __( 'Enable Airwallex Payments', 'airwallex-online-payments-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'What title to display for this payment method', 'airwallex-online-payments-gateway' ),
					'default'     => __( 'Pay with cards and more', 'airwallex-online-payments-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'airwallex-online-payments-gateway' ),
					'type'        => 'text',
					'description' => __( 'What subtext to display for this payment method. Can be left blank.', 'airwallex-online-payments-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'icons'       => array(
					'title'    => __( 'Icons to display', 'airwallex-online-payments-gateway' ),
					'label'    => '',
					'type'     => 'logos',
					'desc_tip' => __( 'Choose which payment method logos to display before your payer proceeds to checkout.', 'airwallex-online-payments-gateway' ),
					'options'  => $logos,
					'default'  => '',
				),
				'methods'     => array(
					'title'       => __( 'Payment methods', 'airwallex-online-payments-gateway' ),
					'label'       => '',
					'type'        => 'methods',
					'description' => sprintf(
						/* translators: Placeholder 1: Airwallex payment acceptance document url.  */
						__(
							'Shoppers with different shipping address countries may see different payment methods in their list. (<a href="%s" target="_blank">See details</a>)',
							'airwallex-online-payments-gateway'
						),
						'https://www.airwallex.com/docs/online-payments__overview'
					),
					'options'     => $this->getPaymentMethods(),
					'default'     => '',
				),
				'template'    => array(
					'title'    => __( 'Payment page template', 'airwallex-online-payments-gateway' ),
					'label'    => '',
					'type'     => 'radio',
					'desc_tip' => __( 'Select the way you want to arrange the order details and the payment method list', 'airwallex-online-payments-gateway' ),
					'options'  => array(
						'2col-1' => '',
						'2col-2' => '',
						'2row'   => '',
					),
					'default'  => '2col-1',
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );
			if ( empty( $order ) ) {
				$this->logService->debug( __METHOD__ . ' - can not find order', array( 'orderId' => $order_id ) );
				throw new Exception( 'Order not found: ' . $order_id );
			}

			$apiClient           = MainClient::getInstance();
			$airwallexCustomerId = null;
			$orderService        = new OrderService();
			$isSubscription      = $orderService->containsSubscription( $order->get_id() );
			if ( $order->get_customer_id( '' ) || $isSubscription ) {
				$airwallexCustomerId = $orderService->getAirwallexCustomerId( $order->get_customer_id( '' ), $apiClient );
			}

			$this->logService->debug( __METHOD__ . ' - before create intent', array( 'orderId' => $order_id ) );
			$paymentIntent             = $apiClient->createPaymentIntent( $order->get_total(), $order->get_id(), $this->is_submit_order_details(), $airwallexCustomerId );
			$this->logService->debug(
				__METHOD__ . ' - payment intent created ',
				array(
					'paymentIntent' => $paymentIntent,
					'session'  => array(
						'cookie' => WC()->session->get_session_cookie(),
						'data'   => WC()->session->get_session_data(),
					),
				),
				LogService::CARD_ELEMENT_TYPE
			);

			WC()->session->set( 'airwallex_order', $order_id );
			WC()->session->set( 'airwallex_payment_intent_id', $paymentIntent->getId() );
			$order->update_meta_data( '_tmp_airwallex_payment_intent', $paymentIntent->getId() );
			$order->save();

			$redirectUrl = $this->get_payment_url( 'airwallex_payment_method_all' );
			$redirectUrl .= ( strpos( $redirectUrl, '?' ) === false ) ? '?' : '&';
			$redirectUrl .= 'order_id=' . $order_id;
			return [
				'result'   => 'success',
				'redirect' => $redirectUrl,
			];
		} catch ( Exception $e ) {
			$this->logService->error( __METHOD__ . ' - Drop in create intent failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			throw new Exception( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ) );
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order           = wc_get_order( $order_id );
		$paymentIntentId = $order->get_transaction_id();
		$apiClient       = MainClient::getInstance();
		try {
			$refund  = $apiClient->createRefund( $paymentIntentId, $amount, $reason );
			$metaKey = $refund->getMetaKey();
			if ( ! $order->meta_exists( $metaKey ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: Placeholder 1: Airwallex refund ID */
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

	public function subscription_payment_information( $paymentMethodName, $subscription ) {
		$customerId = $subscription->get_customer_id();
		if ( $subscription->get_payment_method() !== $this->id || ! $customerId ) {
			return $paymentMethodName;
		}
		//add additional payment details
		return $paymentMethodName;
	}

	public function do_subscription_payment( $amount, $order ) {

		try {
			$subscriptionId            = $order->get_meta( '_subscription_renewal' );
			$subscription              = wcs_get_subscription( $subscriptionId );
			$originalOrderId           = $subscription->get_parent();
			$originalOrder             = wc_get_order( $originalOrderId );
			$airwallexCustomerId       = $originalOrder->get_meta( 'airwallex_customer_id' );
			$airwallexPaymentConsentId = $originalOrder->get_meta( 'airwallex_consent_id' );
			$cardClient                = CardClient::getInstance();
			$paymentIntent             = $cardClient->createPaymentIntent( $amount, $order->get_id(), false, $airwallexCustomerId );
			$paymentIntentAfterCapture = $cardClient->confirmPaymentIntent( $paymentIntent->getId(), [ 'payment_consent_reference' => [ 'id' => $airwallexPaymentConsentId ] ] );

			if ( $paymentIntentAfterCapture->getStatus() === PaymentIntent::STATUS_SUCCEEDED ) {
				( new LogService() )->debug( 'capture successful', $paymentIntentAfterCapture->toArray() );
				$order->add_order_note( 'Airwallex payment capture success' );
				$order->payment_complete( $paymentIntent->getId() );
			} else {
				( new LogService() )->error( 'capture failed', $paymentIntentAfterCapture->toArray() );
				$order->add_order_note( 'Airwallex payment failed capture' );
			}
		} catch ( Exception $e ) {
			( new LogService() )->error( 'do_subscription_payment failed', $e->getMessage() );
		}
	}

	public function generate_radio_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>">
					<?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<div style="display: flex;">
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<div style="width:120px; margin-right:10px; text-align:center;">
								<label>
									<div>
										<img style="max-width:100%;" src="<?php echo esc_url( AIRWALLEX_PLUGIN_URL ) . '/assets/images/layout/' . esc_attr( $option_key ) . '.png'; ?>"/>
									</div>
									<input
											type="radio"
											name="<?php echo esc_attr( $field_key ); ?>"
											value="<?php echo esc_attr( $option_key ); ?>"
										<?php checked( (string) $option_key, esc_attr( $this->get_option( $key ) ) ); ?>
									/>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function validate_logos_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : '';
	}

	public function generate_logos_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = (array) $this->get_option( $key, array() );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>">
					<?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<div style="display: flex; flex-wrap: wrap; max-width:430px;">
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<div style="width:60px; margin-right:10px; text-align:center;">
								<label>
									<div>
										<img style="max-width:100%;" src="<?php echo esc_url( $option_value ); ?>"/>
									</div>
									<input
											type="checkbox"
											name="<?php echo esc_attr( $field_key ); ?>[]"
											value="<?php echo esc_attr( $option_key ); ?>"
										<?php checked( in_array( (string) $option_key, $value, true ), true ); ?>
									/>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function validate_methods_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : '';
	}

	public function generate_methods_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = (array) $this->get_option( $key, array() );	
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>">
					<?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
			</th>
			<td class="forminp">
				<?php
				echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<fieldset>
					<div>
						<?php
						foreach ( (array) $data['options'] as $option_key => $option_value ) {
							$toolTip = ( in_array( $option_key, array( 'applepay', 'googlepay' ), true ) ) ? __( 'There are additional steps to set up this payment method. Please refer to the installation guide for more details.', 'airwallex-online-payments-gateway' ) : null;
							?>
							<div>
								<label>
									<input
											type="checkbox"
											name="<?php echo esc_attr( $field_key ); ?>[]"
											value="<?php echo esc_attr( $option_key ); ?>"
										<?php checked( in_array( (string) $option_key, $value, true ), true ); ?>
									/>
									<?php
									echo esc_html( $option_value );
									if ( $toolTip ) {
										echo wc_help_tip( $toolTip );
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

	public function output( $attrs ) {
		if ( is_admin() || empty( WC()->session ) ) {
			$this->logService->debug( 'Update all payment methods shortcode.', array(), LogService::DROP_IN_ELEMENT_TYPE );
			return;
		}

		$shortcodeAtts = shortcode_atts(
			array(
				'style' => '',
				'class' => '',
			),
			$attrs,
			'airwallex_payment_method_all'
		);

		try {
			$orderId = (int) WC()->session->get( 'airwallex_order' );
			if ( empty( $orderId ) ) {
				$orderId = (int) WC()->session->get( 'order_awaiting_payment' );
			}
			if (empty($orderId)) {
				$this->logService->debug(__METHOD__ . ' - Detect order id from URL.');
				$orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
			}
			$order = wc_get_order( $orderId );
			if ( empty( $order ) ) {
				throw new Exception( 'Order not found: ' . $orderId );
			}

			$paymentIntentId = WC()->session->get( 'airwallex_payment_intent_id' );
			$paymentIntentId = empty( $paymentIntentId ) ? $order->get_meta('_tmp_airwallex_payment_intent') : $paymentIntentId;
			$apiClient           = MainClient::getInstance();
			$paymentIntent             = $apiClient->getPaymentIntent( $paymentIntentId );
			$paymentIntentClientSecret = $paymentIntent->getClientSecret();
			$airwallexCustomerId       = $paymentIntent->getCustomerId();
			$confirmationUrl           = $this->get_payment_confirmation_url();
			$isSandbox                 = $this->is_sandbox();
			$orderService = new OrderService();
			$isSubscription = $orderService->containsSubscription( $orderId );

			$this->logService->debug(
				__METHOD__ . ' - Redirect to the dropIn payment page',
				array(
					'orderId'       => $orderId,
					'paymentIntent' => $paymentIntentId,
				),
				LogService::CARD_ELEMENT_TYPE
			);

			include_once AIRWALLEX_PLUGIN_PATH . '/html/drop-in-payment-shortcode.php';
		} catch ( Exception $e ) {
			$this->logService->error( __METHOD__ . ' - Drop in payment page redirect failed', $e->getMessage(), LogService::CARD_ELEMENT_TYPE );
			wc_add_notice( __( 'Airwallex payment error', 'airwallex-online-payments-gateway' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			die;
		}
	}

	public static function getMetaData() {
		$settings = self::getSettings();

		$data = [
			'enabled' => isset($settings['enabled']) ? $settings['enabled'] : 'no',
		];

		return $data;
	}
}
