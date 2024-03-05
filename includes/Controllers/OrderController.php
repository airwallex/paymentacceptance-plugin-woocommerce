<?php

namespace Airwallex\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Airwallex\Constants\ExpressCheckoutStates;
use Airwallex\Constants\HongKongStates;
use Airwallex\Services\Util;
use Exception;
use WC_Data_Store;
use WC_Checkout;
use WC_Validation;

class OrderController {

	/**
	 * Get estimated cart details without adding the product to the cart
	 */
	public function getEstimatedCartDetail() {
		check_ajax_referer( 'wc-airwallex-express-checkout-estimate-cart', 'security' );

		$productId   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty         = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1;
		$attributes  = isset( $_POST['attributes'] ) ? wc_clean( wp_unslash( $_POST['attributes'] ) ) : [];

		$data = $this->calculateCartForProduct($productId, $qty, $attributes);
		wp_send_json( $data );
	}

	/**
	 * Perform cart calculation for a given product
	 * 
	 * @param int $productId
	 * @param int $qty
	 * @param array $attributes
	 * @return array Cart details
	 */
	public function calculateCartForProduct($productId, $qty, $attributes) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$cart = clone WC()->cart;
		$cart->empty_cart();
		
		$product     = wc_get_product( $productId );
		$productType = $product->get_type();
		if ( ( 'variable' === $productType || 'variable-subscription' === $productType ) && $attributes ) {
			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			$cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( 'simple' === $productType || 'subscription' === $productType ) {
			$cart->add_to_cart( $product->get_id(), $qty );
		}

		$cart->calculate_totals();

		$data              = $this->getCartBasics($cart);
		$data['orderInfo'] = $this->getDisplayItems($cart);
		$data['success']   = true;

		return $data;
	}
	
	/**
	 * Add product to cart action. Used on product detail page to add the current product into the cart.
	 */
	public function addToCart() {
		check_ajax_referer( 'wc-airwallex-express-checkout-add-to-cart', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( 'simple' === $product_type || 'subscription' === $product_type ) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();
		
		$data              = $this->getCartBasics(WC()->cart);
		$data['orderInfo'] = $this->getDisplayItems(WC()->cart);
		$data['success']   = true;

		wp_send_json( $data );
	}

	/**
	 * Get cart details
	 */
	public function getCartDetails() {
		check_ajax_referer( 'wc-airwallex-express-checkout', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->cart->calculate_totals();

		// Set mandatory payment details.
		$data              = $this->getCartBasics(WC()->cart);
		$data['orderInfo'] = $this->getDisplayItems(WC()->cart);
		$data['success']   = true;

		wp_send_json( $data );
	}

	/**
	 * Get basic information of the cart
	 * 
	 * @param WC_Cart $cart
	 * @return array Cart basics
	 */
	public function getCartBasics($cart) {
		return [
			'requiresShipping' => $cart->needs_shipping(),
			'currencyCode'     => get_woocommerce_currency(),
			'countryCode' => wc_get_base_location()['country'],
		];
	}

	/**
	 * Get the line items to display in the payment sheet
	 * 
	 * @param WC_Cart $cart
	 * @return array Cart details with display items
	 */
	public function getDisplayItems($cart) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items     = [];
		$lines     = [];
		$discounts = 0;

		foreach ( $cart->get_cart() as $key => $cartItem ) {
			$amount        = $cartItem['line_subtotal'];
			$quantityLabel = 1 < $cartItem['quantity'] ? ' (x' . $cartItem['quantity'] . ')' : '';
			$product_name  = $cartItem['data']->get_name();

			$lines[] = [
				'label'  => $product_name . $quantityLabel,
				'price' => wc_format_decimal( $amount, $cart->dp ),
				'type' => 'LINE_ITEM'
			];
		}

		$items = array_merge( $items, $lines );

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.2', '<' ) ) {
			$discounts = wc_format_decimal( $cart->get_cart_discount_total(), $cart->dp );
		} else {
			$appliedCoupons = array_values( $cart->get_coupon_discount_totals() );

			foreach ( $appliedCoupons as $amount ) {
				$discounts += (float) $amount;
			}
		}

		$discounts  = wc_format_decimal( $discounts, $cart->dp );
		$tax        = wc_format_decimal( $cart->tax_total + $cart->shipping_tax_total, $cart->dp );
		$shipping   = wc_format_decimal( $cart->shipping_total, $cart->dp );
		$itemsTotal = wc_format_decimal( $cart->cart_contents_total, $cart->dp ) + $discounts;
		$orderTotal = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.2', '<' ) ? wc_format_decimal( $itemsTotal + $tax + $shipping - $discounts, $cart->dp ) : $cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'airwallex-online-payments-gateway' ) ),
				'price' => $tax,
				'type'   => 'TAX',
			];
		}

		if ( $cart->needs_shipping() ) {
			$items[] = [
				'label'  => esc_html( __( 'Shipping', 'airwallex-online-payments-gateway' ) ),
				'price' => $shipping,
				'type'   => 'LINE_ITEM',
			];
		}

		if ( $cart->has_discount() ) {
			$items[] = [
				'label'  => esc_html( __( 'Discount', 'airwallex-online-payments-gateway' ) ),
				'price' => $discounts,
				'type'   => 'LINE_ITEM',
			];
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = $cart->fees;
		} else {
			$cart_fees = $cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'price' => $fee->amount,
				'type'   => 'LINE_ITEM',
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => get_bloginfo('name'),
				'amount'  => max( 0, $orderTotal ),
			],
		];
	}

	/**
	 * Create order from cart action. Security is handled by WC.
	 */
	public function createOrderFromCart() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'airwallex-online-payments-gateway' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		// Normalizes billing and shipping state values.
		$this->normalizeState();

		// In case the state is required, but is missing, add a more descriptive error notice.
		$this->validateState();

		$paymentMethod = isset( $_POST['payment_method_type'] ) ? wc_clean(wp_unslash($_POST['payment_method_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		WC()->session->set( 'airwallex_express_checkout_payment_method', $paymentMethod );

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Get shipping options action
	 */
	public function getShippingOptions() {
		check_ajax_referer( 'wc-airwallex-express-checkout-shipping', 'security' );

		$shippingAddress = [
			'address'  => isset($_POST['address']) ? wc_clean(wp_unslash($_POST['address'])) : '',
			'address2' => isset($_POST['address2']) ? wc_clean(wp_unslash($_POST['address2'])) : '',
			'country'  => isset($_POST['country']) ? wc_clean(wp_unslash($_POST['country'])) : '',
			'state'    => isset($_POST['state']) ? wc_clean(wp_unslash($_POST['state'])) : '',
			'postcode' => isset($_POST['postcode']) ? wc_clean(wp_unslash($_POST['postcode'])) : '',
			'city'     => isset($_POST['city']) ? wc_clean(wp_unslash($_POST['city'])) : '',
		];

		$data = $this->getAvailableShippingOptions( $shippingAddress );
		wp_send_json($data);
	}

	/**
	 * Update shipping method action
	 */
	public function updateShippingMethod() {
		check_ajax_referer( 'wc-airwallex-express-checkout-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shippingMethods = filter_input( INPUT_POST, 'shippingMethods', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->updateWCShippingMethod( $shippingMethods );

		WC()->cart->calculate_totals();

		$data['success']           = true;
		$data['cart']              = $this->getCartBasics(WC()->cart);
		$data['cart']['orderInfo'] = $this->getDisplayItems(WC()->cart);

		wp_send_json( $data );
	}

	/**
	 * For some countries, it might not provide a state field, so we need to return a more descriptive
	 * error message, indicating that the Payment Request button is not supported for that country.
	 */
	public function validateState() {
		$wc_checkout     = WC_Checkout::instance();
		$posted_data     = $wc_checkout->get_posted_data();
		$checkout_fields = $wc_checkout->get_checkout_fields();
		$countries       = WC()->countries->get_countries();

		$is_supported = true;
		// Checks if billing state is missing and is required.
		if ( ! empty( $checkout_fields['billing']['billing_state']['required'] ) && '' === $posted_data['billing_state'] ) {
			$is_supported = false;
		}

		// Checks if shipping state is missing and is required.
		if ( WC()->cart->needs_shipping_address() && ! empty( $checkout_fields['shipping']['shipping_state']['required'] ) && '' === $posted_data['shipping_state'] ) {
			$is_supported = false;
		}

		if ( ! $is_supported ) {
			wc_add_notice(
				sprintf(
					/* translators: 1) country. */
					__( 'The Express Checkout button is not supported in %1$s because some required fields couldn\'t be verified. Please proceed to the checkout page and try again.', 'airwallex-online-payments-gateway' ),
					isset( $countries[ $posted_data['billing_country'] ] ) ? $countries[ $posted_data['billing_country'] ] : $posted_data['billing_country']
				),
				'error'
			);
		}
	}

	/**
	 * Normalizes billing and shipping state fields.
	 */
	public function normalizeState() {
		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Due to a bug in Apple Pay, the "Region" part of a Hong Kong address is delivered in
		// `shipping_postcode`, so we need some special case handling for that. According to
		// our sources at Apple Pay people will sometimes use the district or even sub-district
		// for this value. As such we check against all regions, districts, and sub-districts
		// with both English and Mandarin spelling.
		//
		// The check here is quite elaborate in an attempt to make sure this doesn't break once
		// Apple Pay fixes the bug that causes address values to be in the wrong place. Because of that the
		// algorithm becomes:
		//   1. Use the supplied state if it's valid (in case Apple Pay bug is fixed)
		//   2. Use the value supplied in the postcode if it's a valid HK region (equivalent to a WC state).
		//   3. Fall back to the value supplied in the state. This will likely cause a validation error, in
		//      which case a merchant can reach out to us so we can either: 1) add whatever the customer used
		//      as a state to our list of valid states; or 2) let them know the customer must spell the state
		//      in some way that matches our list of valid states.
		//
		// This HK specific sanitazation *should be removed* once Apple Pay fix
		if ( 'HK' === $billing_country ) {
			if ( ! HongKongStates::isValidState( strtolower( $billing_state ) ) ) {
				$billing_postcode = ! empty( $_POST['billing_postcode'] ) ? wc_clean( wp_unslash( $_POST['billing_postcode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( HongKongStates::isValidState( strtolower( $billing_postcode ) ) ) {
					$billing_state = $billing_postcode;
				}
			}
		}
		if ( 'HK' === $shipping_country ) {
			if ( ! HongKongStates::isValidState( strtolower( $shipping_state ) ) ) {
				$shipping_postcode = ! empty( $_POST['shipping_postcode'] ) ? wc_clean( wp_unslash( $_POST['shipping_postcode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( HongKongStates::isValidState( strtolower( $shipping_postcode ) ) ) {
					$shipping_state = $shipping_postcode;
				}
			}
		}

		// Finally we normalize the state value we want to process.
		if ( $billing_state && $billing_country ) {
			$_POST['billing_state'] = $this->getNormalizedState( $billing_state, $billing_country );
		}

		if ( $shipping_state && $shipping_country ) {
			$_POST['shipping_state'] = $this->getNormalizedState( $shipping_state, $shipping_country );
		}
	}

	/**
	 * Check whether the given state is normalized
	 *
	 * @param  mixed $state State name
	 * @param  mixed $country Two-letter country code
	 * @return boolean
	 */
	public function isNormalizedState($state, $country) {
		$wc_states = WC()->countries->get_states( $country );
		
		return is_array( $wc_states ) && in_array( $state, array_keys( $wc_states ), true );
	}

	/**
	 * Get the normalized state/county field that WC is expecting
	 *
	 * @param string $state State name
	 * @param string $country Two-letter country code
	 * @return string Normalized state abbreviation
	 */
	public function getNormalizedState($state, $country) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->isNormalizedState( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Google/Apple Pay API list of states.
		$state = $this->getNormalizedStateFromExpressCheckoutStates( $state, $country );

		// If it's normalized, return.
		if ( $this->isNormalizedState( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the list of translated states from WooCommerce.
		$wc_states = WC()->countries->get_states( $country );

		if ( is_array( $wc_states ) ) {
			foreach ( $wc_states as $wc_state_abbr => $wc_state_value ) {
				$a = '/' . preg_quote( $wc_state_value, '/' ) . '/i';
				if ( preg_match( '/' . preg_quote( $wc_state_value, '/' ) . '/i', $state ) ) {
					return $wc_state_abbr;
				}
			}
		}

		return $state;
	}

	/**
	 * Sanitize string for comparison.
	 *
	 * @param string $str String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitizeString( $str ) {
		return trim( wc_strtolower( remove_accents( $str ) ) );
	}

	/**
	 * Get normalized state from Payment Request API dropdown list of states.
	 *
	 * @param string $state   Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function getNormalizedStateFromExpressCheckoutStates( $state, $country ) {
		// Include Payment Request API State list for compatibility with WC countries/states.
		$ecStates = ExpressCheckoutStates::STATES;

		if ( ! isset( $ecStates[ $country ] ) ) {
			return $state;
		}

		$sanitizedStateString = $this->sanitizeString( $state );
		foreach ( $ecStates[ $country ] as $wcStateAbbr => $ecState ) {
			// Checks if input state matches with Payment Request state code (0), name (1) or localName (2).
			if (
				( ! empty( $ecState[0] ) && $sanitizedStateString === $this->sanitizeString( $ecState[0] ) ) ||
				( ! empty( $ecState[1] ) && $sanitizedStateString === $this->sanitizeString( $ecState[1] ) ) ||
				( ! empty( $ecState[2] ) && $sanitizedStateString === $this->sanitizeString( $ecState[2] ) )
			) {
				return $wcStateAbbr;
			}
		}

		return $state;
	}

	/**
	 * Normalizes postcode in case of redacted data from Apple Pay.
	 *
	 * @param string $postcode Postcode.
	 * @param string $country Country.
	 * 
	 * @return string Postcode
	 */
	public function getNormalizedPostcode( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postcodes to the first 4 and 3 characters respectively
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( 'GB' === $country ) {
			// Replaces a redacted string with something like LN10***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 7, '*' );
		}
		if ( 'CA' === $country ) {
			// Replaces a redacted string with something like L4Y***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '*' );
		}

		return $postcode;
	}

	/**
	 * Get available shipping options for specified shipping address
	 *
	 * @param array $shippingAddress
	 * 
	 * @return array Shipping options
	 */
	public function getAvailableShippingOptions($shippingAddress) {
		try {
			$data = [];

			// Remember current shipping method before resetting.
			$chosenShippingMethods = WC()->session->get( 'chosen_shipping_methods' );
			$this->calculateShipping( $shippingAddress );

			WC()->cart->calculate_totals();

			$packages = WC()->shipping->get_packages();
			if (empty($packages) && class_exists('WC_Subscriptions_Cart') && \WC_Subscriptions_Cart::cart_contains_free_trial()) {
				// there is a subscription with a free trial in the cart. Shipping packages will be in the recurring cart.
				$packages = \WC_Subscriptions_Cart::get_recurring_shipping_packages();
				// there is a subscription with a free trial in the cart. Shipping packages will be in the recurring cart.
				\WC_Subscriptions_Cart::set_calculation_type( 'recurring_total' );
				$count = 0;
				if ( isset( WC()->cart->recurring_carts ) ) {
					foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
						foreach ( $recurring_cart->get_shipping_packages() as $i => $base_package ) {
							if ( version_compare( \WC_Subscriptions::$version, '5.1.2', '<' ) ) {
								$packages[ $recurring_cart_key . '_' . $count ] = \WC_Subscriptions_Cart::get_calculated_shipping_for_package( $base_package );
							} else {
								$packages[ $recurring_cart_key . '_' . $count ] = WC()->shipping()->calculate_shipping_for_package( $base_package );
							}
						}
						++$count;
					}
				}
				\WC_Subscriptions_Cart::set_calculation_type( 'none' );
			}

			$shippingRateIds = [];
			if ( ! empty( $packages ) ) {
				foreach ( $packages as $packageKey => $package ) {
					if ( empty( $package['rates'] ) ) {
						continue;
					}

					foreach ( $package['rates'] as $key => $rate ) {
						if ( in_array( $rate->id, $shippingRateIds, true ) ) {
							// The Payment Requests will try to load indefinitely if there are duplicate shipping
							// option IDs.
							throw new Exception( __( 'Unable to provide shipping options for Payment Requests.', 'airwallex-online-payments-gateway' ) );
						}
						$shippingRateIds[]                     = $rate->id;
						$data['shipping']['shippingOptions'][] = [
							'id'     => $rate->get_id(),
							'label'  => $this->getFormattedShippingLabel($rate),
							'description' => '',
							'amount' => $rate->get_cost(),
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'airwallex-online-payments-gateway' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping']['shippingOptions'][0] ) ) {
				if ( isset( $chosenShippingMethods[0] ) && in_array($chosenShippingMethods[0], $shippingRateIds, true)) {
					$chosen_method_id       = $chosenShippingMethods[0];
					$compareShippingOptions = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['id'] === $chosen_method_id ) {
							return -1;
						}

						if ( $b['id'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping']['shippingOptions'], $compareShippingOptions );
				} else {
					$chosenShippingMethods[0] = $data['shipping']['shippingOptions'][0]['id'];
				}
			}

			// set the recurring shipping method
			if (class_exists('WC_Subscriptions_Cart')) {
				foreach (\WC_Subscriptions_Cart::get_recurring_shipping_packages() as $cartKey => $packages) {
					foreach ($packages as $packageKey => $package) {
						$chosenShippingMethods[$packageKey] = $chosenShippingMethods[0];
					}
				}
			}
			$data['shipping']['shippingMethods'] = $chosenShippingMethods;
			$this->updateWCShippingMethod( $chosenShippingMethods );


			WC()->cart->calculate_totals();
			$data['success']           = !empty($data['shipping']['shippingOptions']);
			$data['cart']              = $this->getCartBasics(WC()->cart);
			$data['cart']['orderInfo'] = $this->getDisplayItems(WC()->cart);
		} catch (Exception $e) {
			$data['success'] = false;
			$data['message'] = __( 'No available shipping method for this shipping address.', 'airwallex-online-payments-gateway' );
		}

		return $data;
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shippingMethods Array of selected shipping methods ids.
	 */
	public function updateWCShippingMethod( $shippingMethods ) {
		$chosenShippingMethods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shippingMethods ) ) {
			foreach ( $shippingMethods as $idx => $value ) {
				$chosenShippingMethods[ $idx ] = $value;
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosenShippingMethods );
	}

	/**
	 * Calculate and set shipping method
	 * 
	 * @param array $address Shipping address
	 * 
	 */
	protected function calculateShipping($address = []) {
		$address1 = $address['address'];
		$address2 = $address['address2'];
		$city     = $address['city'];
		$country  = $address['country'];
		$state    = $this->getNormalizedState($address['state'], $country);
		$postcode = $this->getNormalizedPostcode($address['postcode'], $country);

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address1;
		$packages[0]['destination']['address_2'] = $address2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * Get formatted shipping label for display
	 * 
	 * @param WC_Shipping_Rate $rate
	 * @return string Formatted label with dollar sign
	 */
	public function getFormattedShippingLabel($rate) {
		$currencyFormat = Util::getCurrencyFormat();

		return $currencyFormat['currencyPrefix'] . $rate->get_cost() . $currencyFormat['currencySuffix'] . ':' . $rate->get_label();
	}
}
