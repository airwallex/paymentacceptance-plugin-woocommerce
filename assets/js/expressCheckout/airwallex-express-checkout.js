import {
	addToCart,
	getCartDetails,
	updateShippingOptions,
	updateShippingDetails,
	createOrder,
	confirmPaymentIntent,
	paymentIntentCreateConsent,
	processOrderWithoutPayment,
	getConfirmPayload,
	startPaymentSession,
	getEstimatedCartDetails,
} from './api.js';
import {
	airTrackerCommonData,
	APPLE_PAY_VERSION,
	deviceSupportApplePay,
	getApplePaySupportedNetworks,
	getApplePayMerchantCapabilities,
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

	const applePayJSLib                   = 'https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-sdk.js';

	let awxShippingOptions      = [], shippingMethods = [];
	let globalCartDetails       = {};

	const airwallexExpressCheckout = {
		init: async function () {
			// if settings cannot be found, stop the process
			if (Object.keys(awxExpressCheckoutSettings).length === 0 && awxExpressCheckoutSettings.constructor === Object) {
				return;
			}

			// get cart details
			globalCartDetails = awxExpressCheckoutSettings.isProductPage ? await getEstimatedCartDetails() : await getCartDetails();
			// if the order/product does not require initial payment, do not proceed
			if (!globalCartDetails?.orderInfo?.total?.amount) return;

			// register the device fingerprint
			const fingerprintScriptId = 'airwallex-fraud-api';
			if (document.getElementById(fingerprintScriptId) === null) {
				const hostSuffix        = awxExpressCheckoutSettings.env === 'prod' ? '' : '-demo';
				const fingerprintJsUrl  = `https://static${hostSuffix}.airwallex.com/webapp/fraud/device-fingerprint/index.js`;
				const fingerprintScript = document.createElement('script');
				fingerprintScript.defer = true;
				fingerprintScript.setAttribute('id', fingerprintScriptId);
				fingerprintScript.setAttribute('data-order-session-id', airTrackerCommonData.sessionId);
				fingerprintScript.src = fingerprintJsUrl;
				document.body.appendChild(fingerprintScript);
			}

			const { button, checkout } = awxExpressCheckoutSettings;
			const mode                 = button.mode === 'recurring' ? 'recurring' : 'oneoff';
			
			// check the existence of apple session before init the button
			const applePayScriptId = 'airwallex-apple-pay-js';
			if (awxExpressCheckoutSettings.applePayEnabled
				&& mode in checkout.allowedCardNetworks.applepay
				&& checkout.allowedCardNetworks.applepay[mode].length > 0) {
				if (document.getElementById(applePayScriptId) === null) {
					const appleScript  = document.createElement('script');
					appleScript.src    = applePayJSLib;
					appleScript.async  = true;
					appleScript.setAttribute('id', applePayScriptId);
					appleScript.onload = () => {
						if (deviceSupportApplePay()) {
							airwallexExpressCheckout.onApplePayLoaded();
						}
					};
					document.body.appendChild(appleScript);
				}
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
				const orderResponse = await createOrder(event.detail.paymentData, 'googlepay');

				maskPageWhileLoading(50000);
				if (orderResponse.result === 'success') {
					const {
						createConsent,
						paymentIntentId,
						clientSecret,
						autoCapture,
						confirmationUrl,
					} = orderResponse.payload;

					if (createConsent) {
						googlepay.createPaymentConsent({
							intent_id: paymentIntentId,
							client_secret: clientSecret,
							autoCapture:  autoCapture,
						}).then(() => {
							location.href = confirmationUrl;
						}).catch((error) => {
							removePageMask();
							$('.awx-express-checkout-error').html(error.message).show();
							console.warn(error.message);
						});
					} else {
						googlepay.confirmIntent({
							intent_id: paymentIntentId,
							client_secret: clientSecret,
							autoCapture:  autoCapture,
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
					googlepay.confirmIntent({});
					$('.awx-express-checkout-error').html(orderResponse?.messages).show();
					console.warn(orderResponse);
				}
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

		/**
		 * Provide Google Pay API with a payment amount, currency, and amount status
		 * 
		 * @param {Object} Cart details
		 * @returns {Object}
		 */
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

		/**
		 * Change the height of the button according to settings
		 */
		setButtonHeight: function () {
			const { button } = awxExpressCheckoutSettings;
			const height     = button.height;
			$('.awx-apple-pay-btn apple-pay-button').css('--apple-pay-button-height', height);
			$('.awx-google-pay-btn button').css('height', height);
		},

		onApplePayLoaded: async function() {
			$('#awx-express-checkout-wrapper').show();
			$('.awx-apple-pay-btn').show();
			$('#awx-express-checkout-button-separator').show();

			$('apple-pay-button').on('click', function() {
				$('.awx-express-checkout-error').html('').hide();
				// If login is required for checkout, display redirect confirmation dialog.
				if ( awxExpressCheckoutSettings.loginConfirmation ) {
					displayLoginConfirmation();
					return;
				}

				const session = new ApplePaySession(APPLE_PAY_VERSION, airwallexExpressCheckout.getApplePayPaymentRequest(globalCartDetails));

				session.onvalidatemerchant          = async (event) => {
					const merchantSession           = await startPaymentSession(event.validationURL);
					const { paymentSession, error } = merchantSession;

					if (paymentSession) {
						session.completeMerchantValidation(paymentSession);
					} else {
						console.warn(error);
						session.abort();
					}
				};

				session.onpaymentmethodselected     = async (event) => {
					const response = awxExpressCheckoutSettings.isProductPage ? await addToCart() : await getCartDetails();

					if (response.success) {
						const { orderInfo } = response;

						const paymentMethodUpdate = {
							newTotal: orderInfo.total,
							newLineItems: getAppleFormattedLineItems(orderInfo.displayItems),
						}
						session.completePaymentMethodSelection(paymentMethodUpdate);
					} else {
						console.warn('Failed to add the product to the cart.');
						session.abort();
					}
				};

				if (awxExpressCheckoutSettings.checkout.requiresShipping) {
					session.onshippingmethodselected = async (event) => {
						const response               = await updateShippingDetails(event.shippingMethod.identifier, shippingMethods);
	
						if (response && response.success) {
							const { cart }             = response;
							const shippingMethodUpdate = {
								newTotal: cart.orderInfo.total,
								newLineItems: getAppleFormattedLineItems(cart.orderInfo.displayItems),
							};
							session.completeShippingMethodSelection(shippingMethodUpdate);
						} else {
							console.warn(response.message);
							session.abort();
						}
					};
	
					session.onshippingcontactselected = async (event) => {
						const response                = await updateShippingOptions(event.shippingContact);
	
						if (response && response.success) {
							const { shipping, cart }    = response;
							shippingMethods             = shipping.shippingMethods;
							const shippingContactUpdate = {
								newShippingMethods: getAppleFormattedShippingOptions(shipping.shippingOptions),
								newTotal: cart.orderInfo.total,
								newLineItems: getAppleFormattedLineItems(cart.orderInfo.displayItems),
							};
							session.completeShippingContactSelection(shippingContactUpdate);
						} else {
							console.warn(response.message);
							session.completeShippingContactSelection({
								errors: [
									new ApplePayError('addressUnserviceable'),
								],
							});
						}
					}
				}

				session.onpaymentauthorized = async (event) => {
					const response          = await airwallexExpressCheckout.processApplePayPayment(event.payment);
					if (response.success) {
						session.completePayment({
							'status': ApplePaySession.STATUS_SUCCESS,
						});
					} else {
						session.completePayment({
							'status': ApplePaySession.STATUS_FAILURE,
						});
						$('.awx-express-checkout-error').html(response.error).show();
						console.warn(response.error);
					}
				};

				session.oncancel = (event) => {
					// Payment cancelled by WebKit
					console.log('cancel', event);
				};

				session.begin();
			});
		},

		getApplePayPaymentRequest: function(cartDetails) {
			const {
				isProductPage,
				button,
				checkout,
			}                     = awxExpressCheckoutSettings;
			const {
				countryCode,
				currencyCode,
				orderInfo,
			}                     = cartDetails;
			const mode            = button.mode === 'recurring' ? 'recurring' : 'oneoff';
			const supportedBrands = checkout.allowedCardNetworks.applepay[mode];

			return {
				merchantCapabilities: getApplePayMerchantCapabilities(supportedBrands),
				supportedNetworks: getApplePaySupportedNetworks(supportedBrands),
				countryCode: countryCode ? countryCode : checkout.countryCode,
				currencyCode: currencyCode ? currencyCode : checkout.currencyCode,
				requiredBillingContactFields: applePayRequiredBillingContactFields,
				requiredShippingContactFields: applePayRequiredShippingContactFields,
				total: {
					label: orderInfo ? orderInfo.total.label : checkout.totalPriceLabel,
					amount: orderInfo ? orderInfo.total.amount : checkout.subTotal,
				},
			};
		},

		getEstimatedSubtotal: function(price) {
			const quantity = $('.quantity .qty').val();
			const total    = quantity * 1 * price;

			return isNaN(total) ? 0 : total;
		},

		processApplePayPayment: async function(payment) {
			payment['shippingMethods'] = shippingMethods;
			const orderResponse = await createOrder(payment, 'applepay');
			if (orderResponse.result === 'success') {
				const commonPayload                  = orderResponse.payload;
				const { paymentMethod, paymentData } = payment.token;
				const paymentMethodObj               = {
					type: 'applepay',
					applepay: {
						card_brand: paymentMethod?.network?.toLowerCase(),
						card_type: paymentMethod?.type,
						data: paymentData?.data,
						ephemeral_public_key: paymentData?.header?.ephemeralPublicKey,
						public_key_hash: paymentData?.header?.publicKeyHash,
						transaction_id: paymentData?.header?.transactionId,
						signature: paymentData?.signature,
						version: paymentData?.version,
						billing: payment.billingContact ? airwallexExpressCheckout.buildApplePayBilling(payment) : undefined,
					},
				};

				let confirmResponse;
				if (orderResponse.redirect) {
					// if the order does not require payment, a redirect url will be returned,
					// try to create consent if the order contains subscription product
					confirmResponse = await processOrderWithoutPayment(orderResponse.redirect, paymentMethodObj);
				} else if (orderResponse.payload.createConsent) {
					const createConsentResponse       = await paymentIntentCreateConsent(commonPayload, paymentMethodObj);
					const { paymentConsentId, error } = createConsentResponse;

					if (paymentConsentId) {
						const confirmIntentPayload = getConfirmPayload(commonPayload, paymentMethodObj, paymentConsentId);
						confirmResponse            = await confirmPaymentIntent(commonPayload, confirmIntentPayload);
					} else {
						return {
							success: false,
							error: error?.messages,
						}
					}
				} else {
					const confirmIntentPayload = getConfirmPayload(commonPayload, paymentMethodObj);
					confirmResponse            = await confirmPaymentIntent(commonPayload, confirmIntentPayload);
				}
				
				const { confirmation, error } = confirmResponse || {};
				if (confirmation) {
					return {
						success: true,
					};
				} else {
					return {
						success: false,
						error: error?.message,
					};
				}
			} else {
				return {
					success: false,
					error: orderResponse?.messages,
				};
			}
		},

		buildApplePayBilling: function(paymentData) {
			const {
				givenName,
				familyName,
				emailAddress,
				locality,
				country,
				countryCode,
				postalCode,
				administrativeArea,
				addressLines,
				phoneNumber
			} = paymentData.billingContact || {};

			let formattedBilling = {
				first_name: givenName,
				last_name: familyName,
				email: emailAddress,
				phone_number: phoneNumber,
			};

			if (countryCode) {
				formattedBilling.address = {
				  // some areas may not contain city info, such as Hong Kong, we default country as city.
					city: locality || country || countryCode,
					country_code: countryCode,
					postcode: postalCode,
					state: administrativeArea,
					street: addressLines?.join(','),
				};
			}

			  return formattedBilling;
		},
	};

	Airwallex.init({
		env: awxExpressCheckoutSettings.env,
		origin: window.location.origin,
		locale: awxExpressCheckoutSettings.locale,
	});

	// hide the express checkout gateway in the payment options
	$(document.body).on('updated_checkout', function () {
		$('.payment_method_airwallex_express_checkout').hide();
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
});
