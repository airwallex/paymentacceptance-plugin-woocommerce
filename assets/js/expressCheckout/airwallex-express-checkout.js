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
	startPaymentSession
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
} from './utils.js';

/* global awxExpressCheckoutSettings, Airwallex */
jQuery(function ($) {
	'use strict';

	const googlePayJSLib                  = 'https://pay.google.com/gp/p/js/pay.js';
	const applePayJSLib                   = 'https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-sdk.js';
	const AIRWALLEX_MERCHANT_ID           = 'BCR2DN4TWD5IRR2E';
	const awxGoogleBaseRequest            = {
		apiVersion: 2,
		apiVersionMinor: 0
	};
	const awxGoogleAllowedCardNetworks    = ["MASTERCARD", "VISA"];
	const awxGoogleAllowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];

	let awxGooglePaymentsClient = null;
	let awxShippingOptions      = [], shippingMethods = [];
	let globalCartDetails       = {};

	const airwallexExpressCheckout = {
		init: async function () {
			// if settings cannot be found, stop the process
			if (Object.keys(awxExpressCheckoutSettings).length === 0 && awxExpressCheckoutSettings.constructor === Object) {
				return;
			}

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
			
			// get cart details
			if (!awxExpressCheckoutSettings.isProductPage) {
				globalCartDetails = await getCartDetails();
			}

			const { button, checkout } = awxExpressCheckoutSettings;
			const mode                 = button.mode === 'recurring' ? 'recurring' : 'oneoff';
			
			// check the existence of apple session before init the button
			if (awxExpressCheckoutSettings.applePayEnabled
					&& mode in checkout.allowedCardNetworks.applepay
					&& checkout.allowedCardNetworks.applepay[mode].length > 0) {
					const appleScript  = document.createElement('script');
					appleScript.src    = applePayJSLib;
					appleScript.async  = true;
					appleScript.onload = () => {
						if (deviceSupportApplePay()) {
							airwallexExpressCheckout.onApplePayLoaded();
						}
				};
					document.body.appendChild(appleScript);
			}

			// check the existence of google before init the button
			if (awxExpressCheckoutSettings.googlePayEnabled
				&& mode in checkout.allowedCardNetworks.googlepay
				&& checkout.allowedCardNetworks.googlepay[mode].length > 0) {
				const googleScript  = document.createElement('script');
				googleScript.src    = googlePayJSLib;
				googleScript.async  = true;
				googleScript.onload = () => {
					airwallexExpressCheckout.onGooglePayLoaded();
				};
				document.body.appendChild(googleScript);
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

		onApplePayLoaded: function() {
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
					if (awxExpressCheckoutSettings.isProductPage) {
						const response = await addToCart();

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
					amount: isProductPage ? airwallexExpressCheckout.getEstimatedSubtotal(checkout.subTotal) : (orderInfo ? orderInfo.total.amount : checkout.subTotal),
				},
			};
		},

		getEstimatedSubtotal: function(price) {
			const quantity = $('.quantity .qty').val();
			const total    = quantity * 1 * price;

			return isNaN(total) ? 0 : total;
		},

		processApplePayPayment: async function(payment) {
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

		/**
		 * Remove and recreate the google pay button in the button container
		 */
		reloadGooglePayButton: function () {
			$('.awx-google-pay-btn').empty();
			airwallexExpressCheckout.addGooglePayButton();
		},

		/**
		 * Create google pay button on load, check whether google pay is supported in the current environment first 
		 */
		onGooglePayLoaded: function () {
			const client = airwallexExpressCheckout.getGooglePaymentsClient(awxExpressCheckoutSettings.checkout.requiresShipping);
			client.isReadyToPay(airwallexExpressCheckout.getGoogleIsReadyToPayRequest())
				.then(function (response) {
					if (response.result) {
						$('#awx-express-checkout-wrapper').show();
						$('.awx-google-pay-btn').show();
						$('#awx-express-checkout-button-separator').show();
						airwallexExpressCheckout.reloadGooglePayButton();
						airwallexExpressCheckout.setButtonHeight();
					}
				})
				.catch(function (err) {
					console.error(err);
				});
		},

		/**
		 * Get google isReadyToPayRequest object
		 * 
		 * @returns {Object} Google Pay API version, payment methods supported by the site
		 */
		getGoogleIsReadyToPayRequest: function () {
			return Object.assign(
				{},
				awxGoogleBaseRequest,
				{
					allowedPaymentMethods: airwallexExpressCheckout.getGoogleAllowedMethods(),
				}
			);
		},

		/**
		 * Return an active google PaymentsClient or initialize
		 * 
		 * @returns {google.payments.api.PaymentsClient} Google Pay API client
		 */
		getGooglePaymentsClient: function (requiresShipping = false) {
			const { merchantInfo } = awxExpressCheckoutSettings;

			if (awxGooglePaymentsClient === null) {
				let paymentOptions = {
					environment: awxExpressCheckoutSettings.env === 'prod' ? 'PRODUCTION' : "TEST",
					merchantInfo: {
						merchantName: merchantInfo.businessName,
						merchantId: merchantInfo.googleMerchantId ? merchantInfo.googleMerchantId : AIRWALLEX_MERCHANT_ID
					},
					paymentDataCallbacks: {
						onPaymentAuthorized: airwallexExpressCheckout.onGooglePaymentAuthorized,
					},
				};

				if (requiresShipping) {
					paymentOptions.paymentDataCallbacks['onPaymentDataChanged'] = airwallexExpressCheckout.onGooglePaymentDataChanged;
				};
				awxGooglePaymentsClient = new google.payments.api.PaymentsClient(paymentOptions);
			}

			return awxGooglePaymentsClient;

		},

		/**
		 * Add the google pay button
		 */
		addGooglePayButton: function () {
			const { checkout, button } = awxExpressCheckoutSettings;
			const client               = airwallexExpressCheckout.getGooglePaymentsClient(checkout.requiresShipping);
			const googleButton         = client.createButton({
				buttonColor: button.theme,
				buttonType: button.buttonType,
				buttonSizeMode: 'fill',
				onClick: airwallexExpressCheckout.onGooglePaymentButtonClicked,
			});
			$('.awx-google-pay-btn').append(googleButton);
		},

		/**
		 * Show Google Pay payment sheet when Google Pay payment button is clicked
		 */
		onGooglePaymentButtonClicked: async function () {
			$('.awx-express-checkout-error').html('').hide();
			// If login is required for checkout, display redirect confirmation dialog.
			if ( awxExpressCheckoutSettings.loginConfirmation ) {
				displayLoginConfirmation();
				return;
			}

			let response;
			if (awxExpressCheckoutSettings.isProductPage) {
				response = await addToCart();
			} else {
				response = await getCartDetails();
			}

			if (response.success) {
				const paymentDataRequest = airwallexExpressCheckout.getGooglePaymentDataRequest(response);
				const client             = airwallexExpressCheckout.getGooglePaymentsClient(response.requiresShipping);
				client.loadPaymentData(paymentDataRequest);
			} else {
				alter(awxExpressCheckoutSettings.errorMsg.cannotShowPaymentSheet);
				console.warn(response.message);
			}
		},

		/**
		 * Configure support for the Google Pay API
		 * 
		 * @param {Object} Cart details
		 * @returns {Object} PaymentDataRequest
		 */
		getGooglePaymentDataRequest: function (cartDetails) {
			const { merchantInfo, checkout } = awxExpressCheckoutSettings;

			const paymentDataRequest                 = Object.assign({}, awxGoogleBaseRequest);
			paymentDataRequest.emailRequired         = true;
			paymentDataRequest.allowedPaymentMethods = airwallexExpressCheckout.getGoogleAllowedMethods();
			paymentDataRequest.merchantInfo          = {
				merchantId: merchantInfo.googleMerchantId ? merchantInfo.googleMerchantId : AIRWALLEX_MERCHANT_ID,
				merchantName: merchantInfo.businessName,
			};
			paymentDataRequest.transactionInfo       = airwallexExpressCheckout.getGoogleTransactionInfo(cartDetails);
			paymentDataRequest.callbackIntents       = ["PAYMENT_AUTHORIZATION"];
			if (cartDetails.requiresShipping) {
				paymentDataRequest.callbackIntents.push("SHIPPING_ADDRESS", "SHIPPING_OPTION");
				paymentDataRequest.shippingAddressRequired   = true;
				paymentDataRequest.shippingAddressParameters = {
					phoneNumberRequired: checkout.requiresPhone,
				};
				paymentDataRequest.shippingOptionRequired    = true;
			}

			return paymentDataRequest;
		},

		/**
		 * Specifies support for one or more payment methods supported by the Google Pay API.
		 * 
		 * @returns {Object} Allowed payment methods 
		 */
		getGoogleAllowedMethods: function () {
			const { button, checkout, merchantInfo } = awxExpressCheckoutSettings;

			return [{
				type: 'CARD',
				parameters: {
					allowedAuthMethods: checkout.allowedAuthMethods || awxGoogleAllowedCardAuthMethods,
					allowedCardNetworks: airwallexExpressCheckout.getGooglePaySupportedNetworks(
						button.mode === 'recurring' ? checkout.allowedCardNetworks['googlepay']['recurring'] : checkout.allowedCardNetworks['googlepay']['oneoff']
					) || awxGoogleAllowedCardNetworks,
				allowPrepaidCards: true,
				allowCreditCards: true,
				assuranceDetailsRequired: false,
				billingAddressRequired: true,
				billingAddressParameters: {
					format: 'FULL',
					phoneNumberRequired: checkout.requiresPhone
					},
					cvcRequired: true,
				},
				tokenizationSpecification: {
					type: 'PAYMENT_GATEWAY',
					parameters: {
						gateway: 'airwallex',
						gatewayMerchantId: merchantInfo.accountId || '',
					},
				}
			}];
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
				transactionId: transactionId,
				totalPriceStatus: checkout.totalPriceStatus || 'FINAL',
				totalPriceLabel: checkout.totalPriceLabel,
				totalPrice: cartDetails.orderInfo.total.amount.toString() || '0.00',
				currencyCode: cartDetails.currencyCode || checkout.currencyCode,
				countryCode: cartDetails.countryCode || checkout.countryCode,
				displayItems: cartDetails.orderInfo.displayItems,
			};
		},

		/**
		 * Get google pay supported networks
		 * 
		 * @param {Array} supportNetworks 
		 * @returns {Object} Filtered support network
		 */
		getGooglePaySupportedNetworks: function (supportNetworks = []) {
			// Google pay don't support UNIONPAY
			// Google pay support MAESTRO, but country code must be BR, otherwise it will not be supported;
			const googlePayNetworks = supportNetworks
				.map((brand) => brand.toUpperCase())
				.filter((brand) => brand !== 'UNIONPAY' && brand !== 'MAESTRO' && brand !== 'DINERS');
			return googlePayNetworks;
		},

		/**
		 * Handles dynamic buy flow shipping address and shipping options callback intents.
		 * 
		 * @param {object} itermediatePaymentData response from Google Pay API a shipping address or shipping option is selected in the payment sheet.
		 */
		onGooglePaymentDataChanged: async function (intermediatePaymentData) {
			const { callbackTrigger, shippingAddress, shippingOptionData } = intermediatePaymentData;
			let paymentDataRequestUpdate                                   = {};

			if (callbackTrigger == "INITIALIZE" || callbackTrigger == "SHIPPING_ADDRESS") {
				const response = await updateShippingOptions(shippingAddress);

				if (response && response.success) {
					awxShippingOptions                                   = {
						shippingMethods: response.shipping.shippingMethods,
						shippingOptions: getGoogleFormattedShippingOptions(response.shipping.shippingOptions),
					};
					paymentDataRequestUpdate.newShippingOptionParameters = {
						defaultSelectedOptionId: awxShippingOptions.shippingMethods[0],
						shippingOptions: awxShippingOptions.shippingOptions
					};
					paymentDataRequestUpdate.newTransactionInfo          = airwallexExpressCheckout.getGoogleTransactionInfo(response['cart']);
				} else {
					awxShippingOptions             = [];
					paymentDataRequestUpdate.error = {
						reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
						message: response.message,
						intent: 'SHIPPING_ADDRESS'
					};
				}
			} else if (callbackTrigger == "SHIPPING_OPTION") {
				const response = await updateShippingDetails(shippingOptionData.id, awxShippingOptions.shippingMethods);

				if (response && response.success) {
					paymentDataRequestUpdate.newTransactionInfo = airwallexExpressCheckout.getGoogleTransactionInfo(response['cart']);
				} else {
					paymentDataRequestUpdate.error = {
						reason: 'SHIPPING_OPTION_INVALID',
						message: response.message,
						intent: 'SHIPPING_OPTION'
					};
				}
			}

			return new Promise(function (resolve, reject) {
				resolve(paymentDataRequestUpdate);
			});
		},

		/**
		 * Create and confirm payment intent on google payment authorized
		 */
		onGooglePaymentAuthorized: async function (paymentData) {
			// process payment here
			const orderResponse = await createOrder(paymentData, 'googlepay');

			if (orderResponse.result === 'success') {
				const commonPayload = orderResponse.payload;

				const paymentMethodObj = {
					type: 'googlepay',
					googlepay: {
						payment_data_type: 'encrypted_payment_token',
						encrypted_payment_token: paymentData.paymentMethodData.tokenizationData.token,
						billing: paymentData.paymentMethodData.info?.billingAddress ? airwallexExpressCheckout.buildGooglePayBilling(paymentData) : undefined,
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
						return new Promise(function (resolve, reject) {
							resolve({
								transactionState: 'ERROR',
								error: {
									reason: "OTHER_ERROR",
									message: error?.message,
									intent: "PAYMENT_AUTHORIZATION"
								}
							});
						});
					}
				} else {
					const confirmIntentPayload = getConfirmPayload(commonPayload, paymentMethodObj);
					confirmResponse            = await confirmPaymentIntent(commonPayload, confirmIntentPayload);
				}
				
				const { confirmation, error } = confirmResponse || {};
				if (confirmation) {
					return new Promise(function (resolve, reject) {
						resolve({ transactionState: 'SUCCESS' });
					});
				} else {
					return new Promise(function (resolve, reject) {
						resolve({
							transactionState: 'ERROR',
							error: {
								reason: "OTHER_ERROR",
								message: error?.message,
								intent: "PAYMENT_AUTHORIZATION"
							}
						});
					});
				}
			} else {
				return new Promise(function (resolve, reject) {
					resolve({
						transactionState: 'ERROR',
						error: {
							reason: "OTHER_ERROR",
							message: orderResponse?.messages,
							intent: "PAYMENT_AUTHORIZATION"
						}
					});
				});
			}
		},

		buildGooglePayBilling: function(paymentData) {
			const {
				name,
				locality,
				countryCode,
				postalCode,
				administrativeArea,
				address1,
				address2,
				address3,
				phoneNumber
			} = paymentData.paymentMethodData.info?.billingAddress || {};

			const formattedBilling = {
				first_name: name?.split(' ')[0],
				last_name: name?.split(' ')[1] || name?.split(' ')[0],
				email: paymentData.email,
				phone_number: phoneNumber,
			};

			if (countryCode) {
				formattedBilling.address = {
				  // some areas may not contain city info, such as Hong Kong, we default country as city.
					city: locality || countryCode,
					country_code: countryCode,
					postcode: postalCode,
					state: administrativeArea,
					street: `${address1 || ''} ${address2 || ''} ${address3 || ''}`.trim(),
				};
			}

			  return formattedBilling;
		},
	};

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
