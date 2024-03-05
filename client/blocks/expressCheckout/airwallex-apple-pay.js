import {
	createOrder,
	startPaymentSession,
	updateShippingOptions,
	updateShippingDetails,
	getConfirmPayload,
	confirmPaymentIntent,
	paymentIntentCreateConsent,
	processOrderWithoutPayment,
} from './api';
import {
	getBrowserInfo,
	airTrackerCommonData,
	APPLE_PAY_VERSION,
	deviceSupportApplePay,
	getApplePaySupportedNetworks,
	getApplePayMerchantCapabilities,
	applePayRequiredBillingContactFields,
	applePayRequiredShippingContactFields,
	getAppleFormattedShippingOptions,
	getAppleFormattedLineItems,
	getFormattedValueFromBlockAmount,
} from './utils.js';

import { getSetting } from '@woocommerce/settings';

const settings = getSetting('airwallex_express_checkout_data', {});

const getApplePayPaymentRequest = (billing) => {
	const {
		button,
		checkout
	}                           = settings;
	const mode                  = button.mode === 'recurring' ? 'recurring' : 'oneoff';
	const supportedBrands       = checkout.allowedCardNetworks.applepay[mode];

	return {
		merchantCapabilities: getApplePayMerchantCapabilities(supportedBrands),
		supportedNetworks: getApplePaySupportedNetworks(supportedBrands),
		countryCode: checkout.countryCode,
		currencyCode: checkout.currencyCode,
		requiredBillingContactFields: applePayRequiredBillingContactFields,
		requiredShippingContactFields: applePayRequiredShippingContactFields,
		total: {
			label: checkout.totalPriceLabel,
			amount: billing?.cartTotal.value ? getFormattedValueFromBlockAmount(billing.cartTotal.value, billing.currency.minorUnit) : 0,
		},
	};
};

const processApplePayPayment = async (payment, shippingMethods) => {
	payment['shippingMethods'] = shippingMethods;
	const orderResponse      = await createOrder(payment, 'applepay');

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
				billing: payment.billingContact ? buildApplePayBilling(payment) : undefined,
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
};

const buildApplePayBilling = (paymentData) => {
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
	}                      = paymentData.billingContact || {};

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
}

const AWXApplePayButton = (props) => {
	const {
		locale,
		button,
	}                   = settings;
	const {
		shippingData,
		billing,
		setExpressPaymentError,
	}                   = props;

	const onApplePayClicked = () => {
		if (!ApplePaySession) {
			return;
		}

		let shippingMethods = [];
		const session       = new ApplePaySession(APPLE_PAY_VERSION, getApplePayPaymentRequest(billing));

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

		if (shippingData.needsShipping) {
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
			const response          = await processApplePayPayment(event.payment, shippingMethods);
			if (response.success) {
				session.completePayment({
					'status': ApplePaySession.STATUS_SUCCESS,
				});
			} else {
				session.completePayment({
					'status': ApplePaySession.STATUS_FAILURE,
				});
				console.warn(response.error);
				if (response.error) {
					setExpressPaymentError(response.error.replace('woocommerce-error', ''));
				}
			}
		};

		session.oncancel = (event) => {
			// Payment cancelled by WebKit
			console.log('cancel', event);
		};

		session.begin();
	};

	return (
		<div
			lang      ={locale}
			onClick   ={onApplePayClicked}
			className ='awx-block-ec-apple-pay-button'
			style     ={{
				'width': '100%',
				'height': button.height,
				'-apple-pay-button-style': button.theme || 'black',
				'-apple-pay-button-type': button.buttonType || '',
				'cursor': 'pointer',
				}}
		>
		</div>
	);
};

const AWXApplePayButtonPreview = (props) => {
	const {
		locale,
		button,
	}                          = settings;

	return (
		<div
			lang      ={locale}
			className ='awx-block-ec-apple-pay-button'
			style     ={{
				'width': '100%',
				'height': button.height,
				'-apple-pay-button-style': button.theme || 'black',
				'-apple-pay-button-type': button.buttonType || '',
				'cursor': 'pointer',
				}}
		>
		</div>
	);
};

const canMakePayment = ({
	cartTotals
}) => {
	const { button, checkout } = settings;
	const mode = button.mode === 'recurring' ? 'recurring' : 'oneoff';

	return (cartTotals.total_price != '0'
		&& settings.applePayEnabled
		&& mode in checkout.allowedCardNetworks.applepay
		&& checkout.allowedCardNetworks.applepay[mode].length > 0
		&& deviceSupportApplePay()) ?? false;
};

export const airwallexApplePayOption = {
	name: 'airwallex_express_checkout_apple_pay',
	content: <AWXApplePayButton />,
	edit: <AWXApplePayButtonPreview />,
	canMakePayment: canMakePayment,
	paymentMethodId: 'airwallex_express_checkout',
	supports: {
		features: settings?.supports ?? [],
	}
};
