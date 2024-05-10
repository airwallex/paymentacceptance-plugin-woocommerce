import {
	addToCart,
	getCartDetails,
	updateShippingOptions,
	updateShippingDetails,
	createOrder,
	startPaymentSession,
	getEstimatedCartDetails,
} from './api.js';
import {
	deviceSupportApplePay,
	applePayRequiredBillingContactFields,
	applePayRequiredShippingContactFields,
	getAppleFormattedShippingOptions,
	getAppleFormattedLineItems,
	getGoogleFormattedShippingOptions,
	displayLoginConfirmation,
	maskPageWhileLoading,
	removePageMask,
} from './utils.js';

/* global awxExpressCheckoutSettings, Airwallex */
jQuery(function ($) {
	'use strict';

	let awxShippingOptions = [], shippingMethods = [];
	let globalCartDetails = {};

	const airwallexExpressCheckout = {
		init: async function () {
			// if settings are not available, do not proceed
			if (!('awxExpressCheckoutSettings' in window) || Object.keys(awxExpressCheckoutSettings).length === 0) {
				return;
			}

			// get cart details
			globalCartDetails = awxExpressCheckoutSettings.isProductPage ? await getEstimatedCartDetails() : await getCartDetails();
			// if the order/product does not require initial payment, do not proceed
			if (!globalCartDetails?.orderInfo?.total?.amount) return;

			const { button, checkout } = awxExpressCheckoutSettings;
			const mode = button.mode === 'recurring' ? 'recurring' : 'oneoff';
			
			if (awxExpressCheckoutSettings.applePayEnabled
				&& mode in checkout.allowedCardNetworks.applepay
				&& checkout.allowedCardNetworks.applepay[mode].length > 0) {
					// destroy the element first to prevent duplicate
					Airwallex.destroyElement('applePayButton');
					airwallexExpressCheckout.initApplePayButton();
			}
			
			if (awxExpressCheckoutSettings.googlePayEnabled
				&& mode in checkout.allowedCardNetworks.googlepay
				&& checkout.allowedCardNetworks.googlepay[mode].length > 0) {
					// destroy the element first to prevent duplicate
					Airwallex.destroyElement('googlePayButton');
					airwallexExpressCheckout.initGooglePayButton();
			}
		},

		initGooglePayButton: async function() {
			const googlePayRequestOptions = await airwallexExpressCheckout.getGooglePayRequestOptions();
			const googlepay = Airwallex.createElement('googlePayButton', googlePayRequestOptions);
			const domElement = googlepay.mount('awx-ec-google-pay-btn');

			googlepay.on('ready', (event) => {
				$('#awx-express-checkout-wrapper').show();
				$('.awx-google-pay-btn').show();
				$('#awx-express-checkout-button-separator').show();
				$('.awx-express-checkout-error').html('').hide();
			});

			googlepay.on('click', (event) => {
				$('.awx-express-checkout-error').html('').hide();
			});

			googlepay.on('shippingAddressChange', async (event) => {
				const { callbackTrigger, shippingAddress } = event.detail.intermediatePaymentData;

				// add product to the cart which is required for shipping calculation
				if (callbackTrigger == 'INITIALIZE' && awxExpressCheckoutSettings.isProductPage) {
					await addToCart();
				}

				let paymentDataRequestUpdate = {};
				const response = await updateShippingOptions(shippingAddress);
				if (response && response.success) {
					awxShippingOptions = {
						shippingMethods: response.shipping.shippingMethods,
						shippingOptions: getGoogleFormattedShippingOptions(response.shipping.shippingOptions),
					};
					paymentDataRequestUpdate.shippingOptionParameters = {
						defaultSelectedOptionId: awxShippingOptions.shippingMethods[0],
						shippingOptions: awxShippingOptions.shippingOptions
					};
					paymentDataRequestUpdate = Object.assign(paymentDataRequestUpdate,  airwallexExpressCheckout.getGoogleTransactionInfo(response['cart']))
				} else {
					awxShippingOptions = [];
					paymentDataRequestUpdate.error = {
						reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
						message: response.message,
						intent: 'SHIPPING_ADDRESS'
					};
				}
				googlepay.update(paymentDataRequestUpdate);
			});

			googlepay.on('shippingMethodChange', async (event) => {
				const { shippingOptionData } = event.detail.intermediatePaymentData;

				let paymentDataRequestUpdate = {};
				const response = await updateShippingDetails(shippingOptionData.id, awxShippingOptions.shippingMethods);
				if (response && response.success) {
					paymentDataRequestUpdate = airwallexExpressCheckout.getGoogleTransactionInfo(response['cart']);
				} else {
					paymentDataRequestUpdate.error = {
						reason: 'SHIPPING_OPTION_INVALID',
						message: response.message,
						intent: 'SHIPPING_OPTION'
					};
				}

				googlepay.update(paymentDataRequestUpdate);
			});

			googlepay.on('authorized', async (event) => {
				if (awxExpressCheckoutSettings.isProductPage) await addToCart();
				const order = await createOrder(event.detail.paymentData, 'googlepay');
				airwallexExpressCheckout.processPayment(googlepay, order);
			});

			googlepay.on('error', (event) => {
				console.error('There was an error', event);
			});
		},

		getGooglePayRequestOptions: async function() {
			const cartDetails = awxExpressCheckoutSettings.isProductPage ? await getEstimatedCartDetails() : await getCartDetails();
			const { button, checkout, merchantInfo, transactionId } = awxExpressCheckoutSettings;

			if (!cartDetails.success) {
				console.warn(response.message);
				return [];
			}

			let paymentDataRequest = {
				mode: button.mode,
				buttonColor: button.theme,
				buttonType: button.buttonType,
				emailRequired: true,
				billingAddressRequired: true,
				billingAddressParameters: {
					format: 'FULL',
					phoneNumberRequired: checkout.requiresPhone
				},
				merchantInfo: {
					merchantName: merchantInfo.businessName,
				},
				autoCapture: checkout.autoCapture,
			};

			let callbackIntents = ['PAYMENT_AUTHORIZATION'];
			if (cartDetails.requiresShipping) {
				callbackIntents.push('SHIPPING_ADDRESS', 'SHIPPING_OPTION');
				paymentDataRequest.shippingAddressRequired = true;
				paymentDataRequest.shippingOptionRequired = true;
				paymentDataRequest.shippingAddressParameters = {
					phoneNumberRequired: checkout.requiresPhone,
				};
			}
			paymentDataRequest.callbackIntents = callbackIntents;
			const transactionInfo = airwallexExpressCheckout.getGoogleTransactionInfo(cartDetails);
			paymentDataRequest = Object.assign(paymentDataRequest, transactionInfo);

			return paymentDataRequest;
		},

		getGoogleTransactionInfo: function (cartDetails) {
			const { checkout, transactionId } = awxExpressCheckoutSettings;

			return {
				amount: {
					value: cartDetails.orderInfo.total.amount || 0,
					currency: cartDetails.currencyCode || checkout.currencyCode,
				},
				transactionId: transactionId,
				totalPriceLabel: checkout.totalPriceLabel,
				countryCode: cartDetails.countryCode || checkout.countryCode,
				displayItems: cartDetails.orderInfo.displayItems,
			};
		},

		initApplePayButton: () => {
			const { checkout } = awxExpressCheckoutSettings;
			const applePayRequestOptions = airwallexExpressCheckout.getApplePayRequestOptions(globalCartDetails);
			const applePay = Airwallex.createElement('applePayButton', applePayRequestOptions);
			applePay.mount('awx-ec-apple-pay-btn');

			applePay.on('ready', (event) => {
				if (deviceSupportApplePay()) {
					$('#awx-express-checkout-wrapper').show();
					$('.awx-apple-pay-btn').show();
					$('#awx-express-checkout-button-separator').show();
				}
			});

			applePay.on('click', (event) => {
				$('.awx-express-checkout-error').html('').hide();
			});

			applePay.on('validateMerchant', async (event) => {
				if (awxExpressCheckoutSettings.isProductPage) await addToCart();
				const merchantSession = await startPaymentSession(event?.detail?.validationURL);
				const { paymentSession, error } = merchantSession;

				if (paymentSession) {
					applePay.completeValidation(paymentSession);
				} else {
					applePay.fail(error);
				}
			});

			applePay.on('shippingAddressChange', async (event) => {
				const cartDetails = await getCartDetails();
				if ( cartDetails.success) {
					if (cartDetails.requiresShipping) {
						const response = await updateShippingOptions(event?.detail?.shippingAddress);
						if (response && response.success) {
							const { shipping, cart } = response;
							shippingMethods = shipping.shippingMethods;
							applePay.update({
								amount: {
									value: cart?.orderInfo?.total?.amount || 0,
								},
								lineItems: getAppleFormattedLineItems(cart.orderInfo.displayItems),
								shippingMethods: getAppleFormattedShippingOptions(shipping.shippingOptions),
								totalPriceLabel: checkout.totalPriceLabel,
							});
						} else {
							shippingMethods = [];
							console.warn(response?.message);
							applePay.fail({
								message: response?.message,
							});
						}
					} else {
						applePay.update({
							amount: {
								value: cartDetails?.orderInfo?.total?.amount || 0,
							},
							lineItems: getAppleFormattedLineItems(cartDetails.orderInfo.displayItems),
							totalPriceLabel: checkout.totalPriceLabel,
						});
					}
				} else {
					console.warn(cartDetails.message);
					applePay.fail({
						message: cartDetails.message,
					});
				}
			});

			applePay.on('shippingMethodChange', async (event) => {
				const response = await updateShippingDetails(event.detail.shippingMethod.identifier, shippingMethods);
				if (response && response.success) {
					const { cart } = response;
					applePay.update({
						amount: {
							value: cart?.orderInfo?.total?.amount || 0,
						},
						lineItems: getAppleFormattedLineItems(cart.orderInfo.displayItems),
						totalPriceLabel: checkout.totalPriceLabel,
					});
				} else {
					console.warn(response.message);
					applePay.fail({
						message: response?.message,
					});
				}
			});

			applePay.on('authorized', async (event) => {
				let payment = event?.detail?.paymentData || {};
				payment['shippingMethods'] = shippingMethods;
				const order = await createOrder(payment, 'applepay');

				airwallexExpressCheckout.processPayment(applePay, order);
			});

			applePay.on('error', (event) => {
				console.error('There was an error', event);
			});
		},

		getApplePayRequestOptions: (cartDetails) => {
			const {
				button,
				checkout,
			} = awxExpressCheckoutSettings;
			const {
				countryCode,
				currencyCode,
				orderInfo,
				requiresShipping
			} = cartDetails;

			return {
				mode: button.mode,
				buttonColor: button.theme,
				buttonType: button.buttonType,
				origin: window.location.origin,
				totalPriceLabel: checkout.totalPriceLabel,
				countryCode: countryCode ? countryCode : checkout.countryCode,
				requiredBillingContactFields: applePayRequiredBillingContactFields,
				requiredShippingContactFields: applePayRequiredShippingContactFields,
				amount: {
					value: orderInfo ? orderInfo.total.amount : checkout.subTotal,
					currency: currencyCode ? currencyCode : checkout.currencyCode,
				},
				lineItems: getAppleFormattedLineItems(orderInfo.displayItems),
				autoCapture: checkout.autoCapture,
			};
		},

		processPayment: (element, data) => {
			maskPageWhileLoading(50000);
			if (data.result === 'success') {
				const {
					createConsent,
					clientSecret,
					confirmationUrl,
				} = data.payload;

				if (createConsent) {
					element.createPaymentConsent({
						client_secret: clientSecret,
					}).then(() => {
						location.href = confirmationUrl;
					}).catch((error) => {
						removePageMask();
						$('.awx-express-checkout-error').html(error.message).show();
						console.warn(error.message);
					});
				} else {
					element.confirmIntent({
						client_secret: clientSecret,
					}).then(() => {
						location.href = confirmationUrl;
					}).catch((error) => {
						removePageMask();
						$('.awx-express-checkout-error').html(error.message).show();
						console.warn(error.message);
					});
				}
			} else {
				removePageMask();
				element.fail({
					message: data?.message,
				});
				$('.awx-express-checkout-error').html(data?.messages).show();
				console.warn(data);
			}
		},

		/**
		 * Change the height of the button according to settings
		 */
		setButtonHeight: function () {
			const { button } = awxExpressCheckoutSettings;
			const height     = button.height;
			$('.awx-apple-pay-btn apple-pay-button').css('--apple-pay-button-height', height);
			$('.awx-google-pay-btn button').css('height', height);
		},
	};

	// hide the express checkout gateway in the payment options
	$(document.body).on('updated_checkout', function () {
		$('.payment_method_airwallex_express_checkout').hide();
	});

	if ('awxExpressCheckoutSettings' in window && 'env' in awxExpressCheckoutSettings) {
		Airwallex.init({
			env: awxExpressCheckoutSettings.env,
			origin: window.location.origin,
			locale: awxExpressCheckoutSettings.locale,
		});

		airwallexExpressCheckout.init();

		// refresh payment data when total is updated.
		$( document.body ).on( 'updated_cart_totals', function() {
			airwallexExpressCheckout.init();
		} );

		// refresh payment data when total is updated.
		$( document.body ).on( 'updated_checkout', function() {
			airwallexExpressCheckout.init();
		} );

		$(document.body).on('change', '[name="quantity"]', function () {
			airwallexExpressCheckout.init();
		});
	}
});
