import { useState } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import GooglePayButton from '@google-pay/button-react';
import { AIRWALLEX_MERCHANT_ID } from './constants.js';
import {
	createOrder,
	updateShippingOptions,
	updateShippingDetails,
	getConfirmPayload,
	confirmPaymentIntent,
	paymentIntentCreateConsent,
	processOrderWithoutPayment,
} from './api.js'
import {
	maskPageWhileLoading,
	removePageMask,
	displayLoginConfirmation,
	getFormattedValueFromBlockAmount,
	getGoogleFormattedShippingOptions,
} from './utils.js';

const settings = getSetting('airwallex_express_checkout_data', {});

const awxGoogleBaseRequest            = {
	apiVersion: 2,
	apiVersionMinor: 0
};
const awxGoogleAllowedCardNetworks    = ["MASTERCARD", "VISA"];
const awxGoogleAllowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];

const getGooglePaySupportedNetworks = (supportNetworks = []) => {
	// Google pay don't support UNIONPAY
	// Google pay support MAESTRO, but country code must be BR, otherwise it will not be supported;
	const googlePayNetworks = supportNetworks
		.map((brand) => brand.toUpperCase())
		.filter((brand) => brand !== 'UNIONPAY' && brand !== 'MAESTRO' && brand !== 'DINERS');
	return googlePayNetworks;
};

const getGoogleAllowedMethods                = () => {
	const { button, checkout, merchantInfo } = settings;

	return [{
		type: 'CARD',
		parameters: {
			allowedAuthMethods: checkout.allowedAuthMethods || awxGoogleAllowedCardAuthMethods,
			allowedCardNetworks: getGooglePaySupportedNetworks(
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
};

const getGoogleTransactionInfo        = (cartDetails) => {
	const { checkout, transactionId } = settings;

	return {
		transactionId: transactionId,
		totalPriceStatus: checkout.totalPriceStatus || 'FINAL',
		totalPriceLabel: checkout.totalPriceLabel,
		totalPrice: cartDetails.orderInfo.total.amount.toString() || '0.00',
		currencyCode: cartDetails.currencyCode || checkout.currencyCode,
		countryCode: cartDetails.countryCode || checkout.countryCode,
		displayItems: cartDetails.orderInfo.displayItems,
	};
};

const getGooglePaymentDataRequest    = (cartDetails) => {
	const { merchantInfo, checkout } = settings;

	const paymentDataRequest                 = Object.assign({}, awxGoogleBaseRequest);
	paymentDataRequest.emailRequired         = true;
	paymentDataRequest.allowedPaymentMethods = getGoogleAllowedMethods();
	paymentDataRequest.merchantInfo          = {
		merchantId: merchantInfo.googleMerchantId ? merchantInfo.googleMerchantId : AIRWALLEX_MERCHANT_ID,
		merchantName: merchantInfo.businessName,
	};
	if (cartDetails) {
		paymentDataRequest.transactionInfo = getGoogleTransactionInfo(cartDetails);
		if (cartDetails.requiresShipping) {
			paymentDataRequest.callbackIntents           = ["SHIPPING_ADDRESS", "SHIPPING_OPTION"];
			paymentDataRequest.shippingAddressRequired   = true;
			paymentDataRequest.shippingAddressParameters = {
				phoneNumberRequired: checkout.requiresPhone,
			};
			paymentDataRequest.shippingOptionRequired    = true;
		}
		
	}

	return paymentDataRequest;
};

const buildGooglePayBilling = (paymentData) => {
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
	}                       = paymentData.paymentMethodData.info?.billingAddress || {};

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
};

const getFormattedCartDetails = (billing) => {
	return {
		total: {
			amount: getFormattedValueFromBlockAmount(billing.cartTotal.value, billing.currency.minorUnit),
		},
		displayItems: [],
	};
};

const AWXGooglePayButton = (props) => {
	const {
		locale,
		env,
		button,
	}                    = settings;
	const {
		setExpressPaymentError,
		shippingData,
		billing,
	}                    = props;

	let awxShippingOptions = {};

	const basePaymentRequest = getGooglePaymentDataRequest({
		requiresShipping: shippingData.needsShipping,
		countryCode: '',
		currencyCode: billing.currency.code,
		orderInfo: getFormattedCartDetails(billing)
	});

	const onGooglePaymentButtonClicked = (event) => {
		// If login is required for checkout, display redirect confirmation dialog.
		if ( settings.loginConfirmation ) {
			event.preventDefault();
			displayLoginConfirmation();
			return;
		}
	};
	
	const onGooglePaymentDataChanged                                       = (intermediatePaymentData) => {
		return new Promise(async (resolve, reject) => {
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
					paymentDataRequestUpdate.newTransactionInfo          = getGoogleTransactionInfo(response['cart']);
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
					paymentDataRequestUpdate.newTransactionInfo = getGoogleTransactionInfo(response['cart']);
				} else {
					paymentDataRequestUpdate.error = {
						reason: 'SHIPPING_OPTION_INVALID',
						message: response.message,
						intent: 'SHIPPING_OPTION'
					};
				}
			}

			resolve(paymentDataRequestUpdate);
		});
	};
	
	const onGooglePaymentAuthorized = (paymentData) => {
		// process payment here
		return new Promise(async (resolve, reject) => {
			maskPageWhileLoading();
			const orderResponse = await createOrder(paymentData, 'googlepay');

			if (orderResponse.result === 'success') {
				const commonPayload = orderResponse.payload;
		
				const paymentMethodObj = {
					type: 'googlepay',
					googlepay: {
						payment_data_type: 'encrypted_payment_token',
						encrypted_payment_token: paymentData.paymentMethodData.tokenizationData.token,
						billing: paymentData.paymentMethodData.info?.billingAddress ? buildGooglePayBilling(paymentData) : undefined,
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
						removePageMask();
						resolve({
							transactionState: 'ERROR',
							error: {
								reason: "OTHER_ERROR",
								message: error?.message,
								intent: "PAYMENT_AUTHORIZATION"
							}
						});
						setExpressPaymentError(error?.message);
					}
				} else {
					const confirmIntentPayload = getConfirmPayload(commonPayload, paymentMethodObj);
					confirmResponse            = await confirmPaymentIntent(commonPayload, confirmIntentPayload);
				}
				
				const { confirmation, error } = confirmResponse || {};
				if (confirmation) {
					resolve({ transactionState: 'SUCCESS' });
				} else {
					removePageMask();
					resolve({
						transactionState: 'ERROR',
						error: {
							reason: "OTHER_ERROR",
							message: error?.message,
							intent: "PAYMENT_AUTHORIZATION"
						}
					});
					setExpressPaymentError(error?.message);
				}
			} else {
				removePageMask();
				resolve({
					transactionState: 'ERROR',
					error: {
						reason: "OTHER_ERROR",
						message: orderResponse?.messages,
						intent: "PAYMENT_AUTHORIZATION"
					}
				});
				setExpressPaymentError(orderResponse?.message);
			}
		});
	};

	let gPayBtnProps = {
		buttonLocale: locale,
		environment: env === 'prod' ? 'PRODUCTION' : 'TEST',
		buttonSizeMode: 'fill',
		buttonColor: button.theme,
		buttonType: button.buttonType,
		style: { 
			width: '100%',
			height: button.height
		},
		paymentRequest: basePaymentRequest,
		onCancel: (reason) => console.log(reason),
		onClick: onGooglePaymentButtonClicked,
		onError: (reason) => setExpressPaymentError(reason),
		onLoadPaymentData: (paymentData) => onGooglePaymentAuthorized(paymentData),
	};
	if (shippingData.needsShipping) {
		gPayBtnProps.onPaymentDataChanged = onGooglePaymentDataChanged;
	}

	return (
		<>
			<GooglePayButton
				{...gPayBtnProps}
			/>
		</>
	);
};

const AWXGooglePayButtonPreview = (props) => {
	const {
		checkout,
		locale,
		button,
		merchantInfo,
	}                           = settings;

	const paymentDataRequest                 = Object.assign(
		{},
		awxGoogleBaseRequest,
	);
	paymentDataRequest.allowedPaymentMethods = getGoogleAllowedMethods();
	paymentDataRequest.merchantInfo          = {
		merchantId: AIRWALLEX_MERCHANT_ID,
		merchantName: merchantInfo.businessName,
	};
	paymentDataRequest.transactionInfo       = {
		transactionId: 0,
		totalPriceStatus: 'FINAL',
		totalPriceLabel: checkout.totalPriceLabel,
		totalPrice: '0.00',
		currencyCode: checkout.currencyCode,
		countryCode: checkout.countryCode,
		displayItems: [],
	};

	let gPayBtnProps = {
		buttonLocale: locale,
		environment: 'TEST',
		buttonSizeMode: 'fill',
		buttonColor: button.theme,
		buttonType: button.buttonType,
		style: { 
			width: '100%',
			height: button.height
		},
		paymentRequest: paymentDataRequest,
		onClick: (e) => {e.preventDefault()},
	};
	return (
		<>
			<GooglePayButton
				{...gPayBtnProps}
			/>
		</>
	);
};

const canMakePayment           = () => {
	const { button, checkout } = settings;
	const mode                 = button.mode === 'recurring' ? 'recurring' : 'oneoff';

	return (settings?.googlePayEnabled
			&& mode in checkout.allowedCardNetworks.googlepay
			&& checkout.allowedCardNetworks.googlepay[mode].length > 0
		) ?? false;
};

export const airwallexGooglePayOption = {
	name: 'airwallex_express_checkout_google_pay',
	content: <AWXGooglePayButton />,
	edit: <AWXGooglePayButtonPreview />,
	canMakePayment: canMakePayment,
	paymentMethodId: 'airwallex_express_checkout',
	supports: {
		features: settings?.supports ?? [],
	}
};
