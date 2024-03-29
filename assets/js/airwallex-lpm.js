import {
    getBrowserInfo,
    airTrackerCommonData,
    injectDeviceFingerprintJS,
} from "./expressCheckout/utils";
import {
    getStoreCurrency,
    createQuote,
} from "./api";

/** global awxEmbeddedLPMData */
jQuery(function ($) {
    let originalCurrency = '', requiredCurrency = '';
    let currentQuote = {};
    let isLoading = false;

    const addCustomDataToCheckoutForm = function (form) {
        $('<input>').prop({
            type: 'hidden',
            id: 'airwallex_device_data',
            name: 'airwallex_device_data',
            value: JSON.stringify(getBrowserInfo(airTrackerCommonData.sessionId)),
        }).appendTo(form);
        $('<input>').prop({
            type: 'hidden',
            id: 'airwallex_origin',
            name: 'airwallex_origin',
            value: window.location.origin,
        }).appendTo(form);
        $('<input>').prop({
            type: 'hidden',
            id: 'airwallex_target_currency',
            name: 'airwallex_target_currency',
            value: requiredCurrency,
        }).appendTo(form);
    }

    const handleCurrencySwitching = function () {
        let canMakePayment = true;
        const selectedCountry = getSelectedCountry();
        const selectedPaymentMethod = getSelectedPaymentMethod();

        console.log('selected country', selectedCountry);

        if (selectedPaymentMethod in awxEmbeddedLPMData) {
            const { availableCurrencies } = awxEmbeddedLPMData;
            const { supportedCountryCurrency } = awxEmbeddedLPMData[selectedPaymentMethod];
            const { currencyIneligibleCWOff } = awxEmbeddedLPMData.textTemplate;
            
            console.log('original currency', originalCurrency);
            console.log('supported currency', supportedCountryCurrency);

            if (selectedCountry in supportedCountryCurrency) {
                requiredCurrency = supportedCountryCurrency[selectedCountry];
                const values = {
                    '$$original_currency$$': originalCurrency,
                    '$$converted_currency$$': requiredCurrency,
                };

                console.log('required currency', requiredCurrency);

                if (originalCurrency === requiredCurrency) {
                    // no action here
                } else if (availableCurrencies && availableCurrencies.includes(requiredCurrency)) {
                    displayCurrencySwitchingInfo(originalCurrency, requiredCurrency);
                } else {
                    canMakePayment = false;
                    $('.wc-airwallex-lpm-currency-ineligible-switcher-off div').html(getReplacedText(currencyIneligibleCWOff, values));
                    $('.wc-airwallex-lpm-currency-ineligible-switcher-off').show();
                }
            } else {
                canMakePayment = false;
                $('.wc-airwallex-lpm-country-ineligible').show();
            }
        }

        return canMakePayment;
    }

    const handleQuoteExpire = function() {
        console.log('checkout place order');
        console.log(getSelectedPaymentMethod());
        console.log('start checking...');
        if (currentQuote && currentQuote.refreshAt && new Date(currentQuote.refreshAt).getTime() >= new Date().getTime()) {
            return Promise.resolve(true);
        } else {
            displayCurrencySwitchingInfo(originalCurrency, requiredCurrency);
            showQuoteExpire();
            $('.wc-airwallex-currency-switching-quote-expire-close').off('click');
            $('.wc-airwallex-currency-switching-quote-expire-place-back').off('click');
            $('.wc-airwallex-currency-switching-quote-expire-place-order').off('click');

            return new Promise(function(resolve, reject) {
                $('.wc-airwallex-currency-switching-quote-expire-close, .wc-airwallex-currency-switching-quote-expire-place-back').on('click', function() {
                    console.log('close');
                    reject(false);
                });
                $('.wc-airwallex-currency-switching-quote-expire-place-order').on('click', function() {
                    console.log('place order');
                    resolve(true);
                });
            });
        }
    }

    const displayCurrencySwitchingInfo = function(originalCurrency, requiredCurrency) {
        isLoading = true;
        disablePlaceOrderButton(true);
        disableConfirmButton(true);
        showLoading();
        createQuote(originalCurrency, requiredCurrency).done(function(response) {
            console.log(response);
            const { quote } = response;
            const { currencyIneligibleCWOff, currencyIneligibleCWOn, conversionRate, convertedAmount } = awxEmbeddedLPMData.textTemplate;
            if (quote) {
                currentQuote = quote;
                const values = {
                    '$$original_currency$$': originalCurrency,
                    '$$conversion_rate$$': quote.clientRate,
                    '$$converted_currency$$': quote.targetCurrency,
                    '$$converted_amount$$': quote.targetAmount,
                };
                $('.wc-airwallex-currency-switching-base-amount').html(quote.paymentAmount);
                $('.wc-airwallex-currency-switching-convert-text').html('').append(getReplacedText(conversionRate, values));
                $('.wc-airwallex-currency-switching-converted-amount').html('').append(getReplacedText(convertedAmount, values));
                $('.wc-airwallex-currency-switching-quote-expire-convert-text').html('').append(getReplacedText(currencyIneligibleCWOn, values));
                $('.wc-airwallex-lpm-currency-ineligible-switcher-on div').html(getReplacedText(currencyIneligibleCWOn, values));
                $('.wc-airwallex-lpm-currency-ineligible-switcher-on').show();
                showCurrencySwitchingInfo();
                disablePlaceOrderButton(false);
                disableConfirmButton(false);
            } else {
                hideCurrencySwitchingInfo();
                const values = {
                    '$$original_currency$$': originalCurrency,
                    '$$converted_currency$$': requiredCurrency,
                };
                $('.woocommerce-checkout .wc-airwallex-alert-box').hide();
                $('.wc-airwallex-lpm-currency-ineligible-switcher-off div').html(getReplacedText(currencyIneligibleCWOff, values));
                $('.wc-airwallex-lpm-currency-ineligible-switcher-off').show();
                disablePlaceOrderButton(true);
            }
        }).fail(function(error) {
            hideCurrencySwitchingInfo();
            console.log(error);
        }).always(function() {
            isLoading = false;
            hideLoading();
        });
    }

    const showLoading = function() {
        $('.wc-airwallex-loader').show();
    }

    const hideLoading = function() {
        $('.wc-airwallex-loader').hide();
    }

    const showCurrencySwitchingInfo = function() {
        $('.wc-airwallex-currency-switching').show();
    }

    const hideCurrencySwitchingInfo = function() {
        $('.wc-airwallex-currency-switching').hide();
    }

    const showQuoteExpire = function() {
        $('.wc-airwallex-currency-switching-quote-expire').show();
        $('.wc-airwallex-currency-switching-quote-expire-mask').show();
    }

    const hideQuoteExpire = function() {
        $('.wc-airwallex-currency-switching-quote-expire').hide();
        $('.wc-airwallex-currency-switching-quote-expire-mask').hide();
    }

    const getReplacedText = function(template, values) {
        for (const key in values) {
            template = template.replace(key, values[key]);
        }

        return template;
    }

    const getSelectedCountry = function () {
        return $('#billing_country').val();
    }

    const getSelectedPaymentMethod = function () {
        const method = $('.woocommerce-checkout input[name="payment_method"]:checked').attr('id');

        return method ? method.replace('payment_method_', '') : '';
    }

    const disablePlaceOrderButton = function (disable) {
        console.log('disable place order ' + disable);
        $('.woocommerce-checkout #place_order').prop('disabled', disable);
    }

    const disableConfirmButton = function (disable) {
        if (disable) {
            $('.wc-airwallex-currency-switching-quote-expire-place-order-mask').show();
        } else {
            $('.wc-airwallex-currency-switching-quote-expire-place-order-mask').hide();
        }
    }
    
    const registerEventListener = function () {
        $(document.body).on('country_to_state_changed payment_method_selected updated_checkout', function () {
            hideCurrencySwitchingInfo();
            $('.woocommerce-checkout .wc-airwallex-alert-box').hide();
            const canMakePayment = handleCurrencySwitching();
            console.log('can male payment ' + canMakePayment);
            disablePlaceOrderButton(!canMakePayment || isLoading);
        });

        $(document.body).on('click', '#place_order', function (event) {
            console.log('place order');
            if (getSelectedPaymentMethod() in awxEmbeddedLPMData) {
                event.preventDefault();
                handleQuoteExpire().then(function(result) {
                    console.log('result: ' + result);
                    $('form.checkout').trigger( 'submit' );
                }).catch(function(error) {
                    console.warn(error);
                }).finally(function() {
                    hideQuoteExpire();
                });
            }
        });

        $('form.woocommerce-checkout').on('checkout_place_order', function (event, wcCheckoutForm) {
            if (getSelectedPaymentMethod() in awxEmbeddedLPMData) {
                addCustomDataToCheckoutForm(wcCheckoutForm.$checkout_form);
            }
            
            return true;
        });
    }

    if (awxEmbeddedLPMData) {
        console.log(awxEmbeddedLPMData);

        injectDeviceFingerprintJS(awxEmbeddedLPMData.env, airTrackerCommonData.sessionId);

        getStoreCurrency().done(function(response) {
            originalCurrency = response.currency;
            registerEventListener();
        });

        $('#wc-airwallex-quote-expire-confirm').text($('#place_order').text());
    }
});