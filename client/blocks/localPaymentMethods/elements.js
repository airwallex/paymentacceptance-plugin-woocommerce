import { useEffect, useState } from 'react';
import { dispatch } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';
import { __ } from '@wordpress/i18n';
import {
	getReplacedText,
	getBrowserInfo,
	airTrackerCommonData,
} from '../utils';
import { createQuote } from '../api';

export const AirwallexLpmLabel = ({
	title,
	icon,
	...props
}) => {
	const { PaymentMethodLabel } = props.components;
    const { url, alt } = icon;

	return <>
        <PaymentMethodLabel text={title} />
        <img src={url} alt={alt} className='airwallex-lpm-icon' />
    </>;
}

export const AirwallexLpmContent = ({
	settings,
	description,
	...props
}) => {
	const { eventRegistration, emitResponse, billing, activePaymentMethod, shippingData } = props;
	const { LoadingMask } = props.components;
	const { onCheckoutValidation, onPaymentSetup, onCheckoutFail } = eventRegistration;
	const { currency } = billing;
	const { paymentMethodName, paymentMethodDocURL } = settings;
	const { currencyIneligibleCWOff, currencyIneligibleCWOn } = settings.textTemplate;
	const { criticalIcon, infoIcon } = settings.alterBoxIcons;
	const [showCountryIneligible, setShowCountryIneligible] = useState(false);
	const [showCurrencyIneligibleCWOff, setShowCurrencyIneligibleCWOff] = useState(false);
	const [showCurrencyIneligibleCWOn, setShowCurrencyIneligibleCWOn] = useState(false);
	const [isLoadingCurrencySwitching, setIsLoadingCurrencySwitching] = useState(true);
	const [convertCurrency, setConvertCurrency] = useState('');
	const [currentQuote, setCurrentQuote] = useState({});

	const updateCurrencySwitchingInfo = async (requiredCurrency) => {
		let showElement = false, conversionRate = '', convertedAmount = '';
		disableConfirmButton(true);
		await createQuote(currency.code, requiredCurrency, settings).then((response) => {
			const { quote } = response;
			if (quote) {
				setCurrentQuote(quote);
				showElement = true;
				const data = {
					'$$original_currency$$': quote.paymentCurrency,
					'$$converted_currency$$': quote.targetCurrency,
					'$$conversion_rate$$': quote.clientRate,
					'$$converted_amount$$': quote.targetAmount,
				};
				conversionRate = getReplacedText(settings?.textTemplate?.conversionRate, data);
				convertedAmount = getReplacedText(settings?.textTemplate?.convertedAmount, data);
				updateQuoteExpire({
					conversionRate,
					convertedAmount,
					requiredCurrency,
					baseAmount: quote.paymentAmount,
				});
				setShowCurrencyIneligibleCWOn(true);
				setShowCurrencyIneligibleCWOff(false);
				disableConfirmButton(false);
			} else {
				showElement = false;
				conversionRate = '';
				convertedAmount = '';
				setShowCurrencyIneligibleCWOn(false);
				setShowCurrencyIneligibleCWOff(true);
				disableConfirmButton(true);
			}
		}).catch((error) => {
			setShowCurrencyIneligibleCWOn(false);
			setShowCurrencyIneligibleCWOff(true);
			console.error(error);
		});
		setIsLoadingCurrencySwitching(false);
		disableConfirmButton(false);
		dispatch(CART_STORE_KEY).setCartData({
			extensions: {
				airwallex: {
					showElement,
					conversionRate,
					convertedAmount,
				},
			}
		});
	};

	const handleCurrencySwitching = (country, paymentMethod) => {
		setIsLoadingCurrencySwitching(true);
		if (paymentMethod in settings) {
			const { availableCurrencies } = settings;
			const { supportedCountryCurrency } = settings[paymentMethod];

			if (country in supportedCountryCurrency) {
				const requiredCurrency = supportedCountryCurrency[country];
				setConvertCurrency(requiredCurrency);

				if (currency.code === requiredCurrency) {
					setShowCountryIneligible(false);
					setShowCurrencyIneligibleCWOff(false);
					setShowCurrencyIneligibleCWOn(false);
					setIsLoadingCurrencySwitching(false);
				} else if (availableCurrencies && availableCurrencies.includes(requiredCurrency)) {
					updateCurrencySwitchingInfo(requiredCurrency);
					setShowCountryIneligible(false);
				} else {
					setShowCountryIneligible(false);
					setShowCurrencyIneligibleCWOff(true);
					setShowCurrencyIneligibleCWOn(false);
					setIsLoadingCurrencySwitching(false);
				}
			} else {
				setShowCountryIneligible(true);
				setShowCurrencyIneligibleCWOff(false);
				setShowCurrencyIneligibleCWOn(false);
				setIsLoadingCurrencySwitching(false);
			}
		}
	};

	const updateQuoteExpire = ({
		conversionRate,
		convertedAmount,
		requiredCurrency,
		baseAmount
	}) => {
		try {
			document.getElementById('wc-airwallex-quote-expire-convert-text').innerHTML =
				getReplacedText(currencyIneligibleCWOn, {'$$original_currency$$': currency.code, '$$converted_currency$$': requiredCurrency});
			document.getElementById('wc-airwallex-currency-switching-base-amount').innerHTML = baseAmount;
			document.getElementById('wc-airwallex-currency-switching-conversion-rate').innerHTML = conversionRate;
			document.getElementById('wc-airwallex-currency-switching-converted-amount').innerHTML = convertedAmount;
			document.getElementsByClassName('wc-block-components-checkout-place-order-button')[0].innerText
			document.getElementById('wc-airwallex-quote-expire-confirm').innerHTML = document.getElementsByClassName('wc-block-components-checkout-place-order-button')[0].innerText;
		} catch (error) {
			console.warn(error);	
		}
	};

	const showQuoteExpire = () => {
		try {
			document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire')[0].style.display = 'block';
			document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-mask')[0].style.display = 'block';
		} catch (error) {
			console.warn(error);
		}
	};

	const hideQuoteExpire = () => {
		try {
			document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire')[0].style.display = 'none';
			document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-mask')[0].style.display = 'none';
		} catch (error) {
			console.warn(error);
		}
	};

	const disableConfirmButton = function (disable) {
		try {
			if (disable) {
				document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-place-order-mask')[0].style.display = 'block';
			} else {
				document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-place-order-mask')[0].style.display = 'none';
			}
		} catch (error) {
			console.warn(error);
		}
    }

	const disablePlaceOrderButton = function (disable) {
		try {
			if (disable) {
				document.getElementsByClassName('wc-block-components-checkout-place-order-button')[0].setAttribute('disabled', 'disabled');
			} else {
				document.getElementsByClassName('wc-block-components-checkout-place-order-button')[0].removeAttribute('disabled');
			}
		} catch (error) {
			console.warn(error);
		}
    }
	
	useEffect( () => {
		handleCurrencySwitching(billing.billingAddress.country, activePaymentMethod);
	}, [
		billing.billingAddress.country,
		shippingData.selectedRates,
	] );

	useEffect(() => {
		const onValidation = () => {
			if (Object.keys(currentQuote).length === 0) {
				return {
					context: emitResponse.noticeContexts.PAYMENTS,
					errorMessage: __('Please use a different payment method.', 'airwallex-online-payments-gateway'),
				};
			} else if (currentQuote && currentQuote.refreshAt && new Date(currentQuote.refreshAt).getTime() >= new Date().getTime()) {
				return true;
			} else {
				updateCurrencySwitchingInfo(convertCurrency);
				showQuoteExpire();

				return new Promise((resolve, reject) => {
					const close = document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-close')[0];
					const back = document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-place-back')[0];
					const order = document.getElementsByClassName('wc-airwallex-currency-switching-quote-expire-place-order')[0];
	
					if (!close || !back || !order) {
						console.warn('Quote expire pop up modal not found.');
						resolve(false);
					}
	
					close.onclick = () => {
						hideQuoteExpire();
						resolve({
							context: emitResponse.noticeContexts.PAYMENTS,
							errorMessage: __('Payment aborted.', 'airwallex-online-payments-gateway'),
						});
					};
					back.onclick = () => {
						hideQuoteExpire();
						resolve({
							context: emitResponse.noticeContexts.PAYMENTS,
							errorMessage: __('Payment aborted.', 'airwallex-online-payments-gateway'),
						});
					};
					order.onclick = () => {
						hideQuoteExpire();
						resolve(true);
					};
				});
			}
		};

		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		currentQuote,
		convertCurrency,
		onCheckoutValidation,
	]);

	useEffect(() => {
		const onSubmit = async () => {
			const deviceData = getBrowserInfo(airTrackerCommonData.sessionId);
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						airwallex_device_data: JSON.stringify(deviceData),
						airwallex_target_currency: convertCurrency,
					},
				},
			};
		}

		const unsubscribeAfterProcessing = onPaymentSetup(onSubmit);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		settings,
		convertCurrency,
		onPaymentSetup,
		emitResponse.responseTypes.SUCCESS,
	]);

	useEffect(() => {
		const onError = ({ processingResponse }) => {
			if (processingResponse?.paymentDetails?.message) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: processingResponse.paymentDetails.message,
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

	return (
		<div>
			<LoadingMask
				isLoading={isLoadingCurrencySwitching}
				screenReaderLabel={ __(
					'Loading currency switching detail…',
					'airwallex-online-payments-gateway'
				) }
			>
				{description}
				<span style={{ display: isLoadingCurrencySwitching ? 'inline-block' : 'none' }} className="wc-airwallex-loader"></span>
				<CountryIneligibleAlert
					shouldDisplay={showCountryIneligible}
					methodName={paymentMethodName}
					docUrl={paymentMethodDocURL}
					icon={criticalIcon}
					test={isLoadingCurrencySwitching}
				/>
				<CurrencyIneligibleCWOffAlert
					shouldDisplay={showCurrencyIneligibleCWOff}
					text={getReplacedText(currencyIneligibleCWOff, {'$$original_currency$$': currency.code})}
					icon={criticalIcon}
				/>
				<CurrencyIneligibleCWOnAlert
					shouldDisplay={showCurrencyIneligibleCWOn}
					text={getReplacedText(currencyIneligibleCWOn, {'$$original_currency$$': currency.code, '$$converted_currency$$': convertCurrency})}
					icon={infoIcon}
				/>
			</LoadingMask>
		</div>
	);
};

export const AirwallexLpmContentAdmin = ({
	description,
	...props
}) => {
	return <div>{description}</div>;
};

const CountryIneligibleAlert = ({
	methodName,
	docUrl,
	icon,
	shouldDisplay,
	test
}) => {
	return (
		<div style={{ display: shouldDisplay ? 'flex' : 'none' }} className='wc-airwallex-alert-box wc-airwallex-error'>
			<img src={icon}></img>
			<div>
				{methodName}{__(' is not available in your country. Please change your billing address to a ', 'airwallex-online-payments-gateway')}
				<a href={docUrl} target='_blank'>{__('compatible country', 'airwallex-online-payments-gateway')}</a>
				{__(' or choose a different payment method.', 'airwallex-online-payments-gateway')}
			</div>
		</div>
	);
};

const CurrencyIneligibleCWOffAlert = ({
	text,
	icon,
	shouldDisplay
}) => {
	return (
		<div style={{ display: shouldDisplay ? 'flex' : 'none' }} className='wc-airwallex-alert-box wc-airwallex-error'>
			<img src={icon}></img>
			<div>{text}</div>
		</div>
	);
};

const CurrencyIneligibleCWOnAlert = ({
	text,
	icon,
	shouldDisplay
}) => {
	return (
		<div style={{ display: shouldDisplay ? 'flex' : 'none' }} className='wc-airwallex-alert-box wc-airwallex-info'>
			<img src={icon}></img>
			<div>{text}</div>
		</div>
	);
};
