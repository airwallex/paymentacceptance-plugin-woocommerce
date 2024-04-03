import { useEffect, useRef, useState } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import GooglePayButton from '@google-pay/button-react';
import { AIRWALLEX_MERCHANT_ID } from './constants.js';
import {
	createOrder,
	updateShippingOptions,
	updateShippingDetails,
} from './api.js'
import {
	maskPageWhileLoading,
	removePageMask,
	getFormattedValueFromBlockAmount,
	getGoogleFormattedShippingOptions,
} from './utils.js';
import {
	createElement as airwallexCreateElement,
	destroyElement,
} from 'airwallex-payment-elements';

const settings = getSetting('airwallex_express_checkout_data', {});

const getGoogleTransactionInfo = (cartDetails) => {
	const { checkout, transactionId } = settings;

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
};

const getFormattedCartDetails = (billing) => {
	const { checkout, transactionId } = settings;

	return {
		amount: {
			value: getFormattedValueFromBlockAmount(billing.cartTotal.value, billing.currency.minorUnit) || 0,
			currency: billing.currency.code || checkout.currencyCode,
		},
		transactionId: transactionId,
		totalPriceLabel: checkout.totalPriceLabel,
		countryCode: checkout.countryCode,
		displayItems: [],
	};
};

const getGooglePayRequestOptions = (billing, shippingData) => {
	const { button, checkout, merchantInfo } = settings;

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
	if (shippingData.needsShipping) {
		callbackIntents.push('SHIPPING_ADDRESS', 'SHIPPING_OPTION');
		paymentDataRequest.shippingAddressRequired = true;
		paymentDataRequest.shippingOptionRequired = true;
		paymentDataRequest.shippingAddressParameters = {
			phoneNumberRequired: checkout.requiresPhone,
		};
	}
	paymentDataRequest.callbackIntents = callbackIntents;
	const transactionInfo = getFormattedCartDetails(billing);
	paymentDataRequest = Object.assign(paymentDataRequest, transactionInfo);

	return paymentDataRequest;
};

const AWXGooglePayButton = (props) => {
	const {
		locale,
		env,
	} = settings;
	const {
		setExpressPaymentError,
		shippingData,
		billing,
		onError,
	} = props;

	let awxShippingOptions = {};
	const ELEMENT_TYPE = 'googlePayButton';
	const [element, setElement] = useState();
	const elementRef = useRef(null);

	const onShippingAddressChanged = async (event) => {

		const { shippingAddress } = event.detail.intermediatePaymentData;

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
			paymentDataRequestUpdate = Object.assign(paymentDataRequestUpdate, getGoogleTransactionInfo(response['cart']))
		} else {
			awxShippingOptions = [];
			paymentDataRequestUpdate.error = {
				reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
				message: response.message,
				intent: 'SHIPPING_ADDRESS'
			};
		}
		elementRef.current?.update(paymentDataRequestUpdate);
	};

	const onShippingMethodChanged = async (event) => {
		const { shippingOptionData } = event.detail.intermediatePaymentData;

		let paymentDataRequestUpdate = {};
		const response = await updateShippingDetails(shippingOptionData.id, awxShippingOptions.shippingMethods);
		if (response && response.success) {
			paymentDataRequestUpdate = getGoogleTransactionInfo(response['cart']);
		} else {
			paymentDataRequestUpdate.error = {
				reason: 'SHIPPING_OPTION_INVALID',
				message: response.message,
				intent: 'SHIPPING_OPTION'
			};
		}
		elementRef.current?.update(paymentDataRequestUpdate);
	};

	const onAuthorized = async (event) => {
		const orderResponse = await createOrder(event.detail.paymentData, 'googlepay');

		maskPageWhileLoading(50000);
		if (orderResponse.result === 'success') {
			const {
				createConsent,
				clientSecret,
				confirmationUrl,
			} = orderResponse.payload;

			if (createConsent) {
				elementRef.current?.createPaymentConsent({
					client_secret: clientSecret,
				}).then(() => {
					location.href = confirmationUrl;
				}).catch((error) => {
					removePageMask();
					onError(error.message);
					console.warn(error.message);
				});
			} else {
				elementRef.current?.confirmIntent({
					client_secret: clientSecret,
				}).then(() => {
					location.href = confirmationUrl;
				}).catch((error) => {
					removePageMask();
					onError(error.message);
					console.warn(error.message);
				});
			}
		} else {
			elementRef.current?.fail({
				message: order.messages,
			});
			onError(orderResponse.messages);
			console.warn(orderResponse.messages);
		}
	};

	const onAWXError = (event) => {
		const { error } = event.detail;
		onError(error.detail);
		console.warn('There was an error', error);
	}

	const createGooglePayButton = () => {
		const element = airwallexCreateElement(ELEMENT_TYPE, getGooglePayRequestOptions(billing, shippingData));
		const googlePayElement = element.mount('awxGooglePayButton');
		setElement(googlePayElement);
		elementRef.current = element;

		elementRef.current?.on('shippingAddressChange', (event) => {
			onShippingAddressChanged(event);
		});

		elementRef.current?.on('shippingMethodChange', (event) => {
			onShippingMethodChanged(event);
		});

		elementRef.current?.on('authorized', (event) => {
			onAuthorized(event);
		});

		elementRef.current?.on('error',(event) => {
			onAWXError(event);
		});
	};

	useEffect(() => {
		if ('Airwallex' in window) {
			createGooglePayButton();
		}
	}, []);

	useEffect(() => {
		if (!elementRef.current) return;

		destroyElement(ELEMENT_TYPE);
		createGooglePayButton();
	}, [billing.cartTotal]);

	return (
		<div id="awxGooglePayButton" />
	);
};

const AWXGooglePayButtonPreview = () => {
	const {
		checkout,
		locale,
		button,
		merchantInfo,
	} = settings;

	const paymentDataRequest = {
		apiVersion: 2,
		apiVersionMinor: 0,
		allowedPaymentMethods: {
			type: 'CARD',
			parameters: {
				allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
				allowedCardNetworks: ["MASTERCARD", "VISA"],
			},
			tokenizationSpecification: {
				type: 'PAYMENT_GATEWAY',
				parameters: {
					gateway: 'airwallex',
					gatewayMerchantId: merchantInfo.accountId || '',
				},
			}
		},
		merchantInfo: {
			merchantId: AIRWALLEX_MERCHANT_ID,
			merchantName: merchantInfo.businessName,
		},
		transactionInfo: {
			totalPriceStatus: 'FINAL',
			totalPriceLabel: checkout.totalPriceLabel,
			totalPrice: '0.00',
			currencyCode: checkout.currencyCode,
			countryCode: checkout.countryCode,
			displayItems: [],
		},
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
		onClick: (e) => { e.preventDefault() },
	};
	return (
		<>
			<GooglePayButton
				{...gPayBtnProps}
			/>
		</>
	);
};

const canMakePayment = ({
	cartTotals,
}) => {
	const { button, checkout } = settings;
	const mode = button.mode === 'recurring' ? 'recurring' : 'oneoff';

	return (cartTotals.total_price != '0'
		&& settings?.googlePayEnabled
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
