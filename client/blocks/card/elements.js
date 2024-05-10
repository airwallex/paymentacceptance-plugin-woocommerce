import { useEffect, useState } from 'react';
import {
	loadAirwallex,
	createElement as createAirwallexElement,
	confirmPaymentIntent as confirmAirwallexPaymentIntent,
	createPaymentConsent as createAirwallexPaymentConsent,
	getElement as getAirwallexElement,
	init as initAirwallex,
} from 'airwallex-payment-elements';
import { __ } from '@wordpress/i18n';
import { getCardHolderName, getBillingInformation } from '../utils';

const confirmPayment      = ({
	settings,
	paymentDetails,
	billingData,
	successType,
	errorType,
	errorContext,
}) => {
	const confirmUrl      = settings.confirm_url + (settings.confirm_url.indexOf('?') !== -1 ? '&' : '?')
		+ 'order_id=' + paymentDetails.orderId + '&intent_id=' + paymentDetails.paymentIntent;
	const card            = getAirwallexElement('card');
	const paymentResponse = { type: successType };

	if (paymentDetails.createConsent) {
		return createAirwallexPaymentConsent({
			intent_id: paymentDetails.paymentIntent,
			customer_id: paymentDetails.customerId,
			client_secret: paymentDetails.clientSecret,
			currency: paymentDetails.currency,
			element: card,
			next_triggered_by: 'merchant',
			billing: getBillingInformation(billingData),
		}).then((response) => {
			paymentResponse.confirmUrl = confirmUrl;
			return paymentResponse;
		}).catch((error) => {
			paymentResponse.type           = errorType;
			paymentResponse.message        = error.message ?? JSON.stringify(error);
			paymentResponse.messageContext = errorContext;
			return paymentResponse;
		});
	} else {
		return confirmAirwallexPaymentIntent({
			element: card,
			id: paymentDetails.paymentIntent,
			client_secret: paymentDetails.clientSecret,
			payment_method: {
				card: {
					name: getCardHolderName(billingData),
				},
				billing: getBillingInformation(billingData),
			},
		}).then((response) => {
			paymentResponse.confirmUrl = confirmUrl;
			return paymentResponse;
		}).catch((error) => {
			paymentResponse.type           = errorType;
			paymentResponse.message        = error.message ?? JSON.stringify(error);
			paymentResponse.messageContext = errorContext;
			return paymentResponse;
		});
	}
}

export const InlineCard                             = ({
	settings: settings,
	props: props,
}) => {
	const [elementShow, setElementShow]             = useState(false);
	const [errorMessage, setErrorMessage]           = useState(false);
	const [isSubmitting, setIsSubmitting]           = useState(false);
	const [inputErrorMessage, setInputErrorMessage] = useState(false);

	const {
		emitResponse,
		billing,
	} = props;
	const {
		ValidationInputError,
		LoadingMask,
	} = props.components;
	const {
		onCheckoutSuccess,
		onPaymentSetup,
		onCheckoutFail,
		onCheckoutValidation,
	} = props.eventRegistration;

	useEffect(() => {
		loadAirwallex({
			env: settings.environment,
			origin: window.location.origin,
			locale: settings.locale,
		}).then(() => {
			initAirwallex({
				env: settings.environment,
			});
			
			const card = createAirwallexElement('card', {
				autoCapture: settings.capture_immediately,
			});
			card.mount('airwallex-card');
		});

		const onReady = (event) => {
			setElementShow(true);
			getAirwallexElement('card')?.focus();
			console.log('The Card element is ready.');
		};

		const onError       = (event) => {
			const { error } = event.detail;
			setErrorMessage(error.message);
			console.error('There was an error', error);
		};

		const onFocus = (_event) => {
			setInputErrorMessage('');
		};

		const onBlur        = (event) => {
			const { error } = event.detail;
			setInputErrorMessage(error?.message ?? JSON.stringify(error));
		};

		const domElement = document.getElementById('airwallex-card');
		domElement.addEventListener('onReady', onReady);
		domElement.addEventListener('onError', onError);
		domElement.addEventListener('onBlur', onBlur);
		domElement.addEventListener('onFocus', onFocus);
		return () => {
			domElement.removeEventListener('onReady', onReady);
			domElement.removeEventListener('onError', onError);
			domElement.removeEventListener('onFocus', onFocus);
			domElement.removeEventListener('onBlur', onBlur);
		};
	}, []);

	useEffect(() => {
		const onValidation = () => {
			if (inputErrorMessage) {
				return {
					errorMessage: __('An error has occurred. Please check your payment details.', 'airwallex-online-payments-gateway') + ` (${inputErrorMessage})`
				};
			}
			return true;
		};

		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		inputErrorMessage,
		onCheckoutValidation,
	]);

	useEffect(() => {
		const onSubmit = async () => {
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						'is-airwallex-card-block': true,
					}
				}
			};
		}

		const unsubscribeAfterProcessing = onPaymentSetup(onSubmit);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		settings,
		onPaymentSetup,
		emitResponse.responseTypes.SUCCESS,
	]);

	useEffect(() => {
		const onError = ({ processingResponse }) => {
			if (processingResponse?.paymentDetails?.errorMessage) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: processingResponse.paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
			return true;
		};

		const unsubscribeAfterProcessing = onCheckoutFail(onError);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		onCheckoutFail,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
	]);

	useEffect(() => {
		const onSuccess          = async ({ processingResponse }) => {
			setIsSubmitting(true);
			const paymentDetails = processingResponse.paymentDetails || {};

			const response = await confirmPayment({
				settings,
				paymentDetails,
				billingData: billing.billingData,
				successType: emitResponse.responseTypes.SUCCESS,
				errorType: emitResponse.responseTypes.ERROR,
				errorContext: emitResponse.noticeContexts.PAYMENTS,
			});

		if (response.type === emitResponse.responseTypes.SUCCESS) {
			location.href = response.confirmUrl;
		} else {
			setIsSubmitting(false);
			return response;
		}
		};

		const unsubscribeAfterProcessing = onCheckoutSuccess(onSuccess);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		onCheckoutSuccess,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
	]);

	return (
		<>
			<div className                     ='airwallex-checkout-loading-mask' style={{ display: isSubmitting ? 'block' : 'none' }}></div>
			<div id                            ="airwallex-card" style={{ display: elementShow ? 'block' : 'none' }}></div>
			<ValidationInputError errorMessage ={inputErrorMessage} />
		</>
	);
};
