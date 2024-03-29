import $ from 'jquery';

/* global awxExpressCheckoutSettings, Airwallex */
/**
 * Get WC AJAX endpoint URL.
 *
 * @param  {String} endpoint Endpoint.
 * @return {String}
 */
const getAjaxURL = (endpoint) => {
	return awxEmbeddedLPMData.ajaxUrl
		.toString()
		.replace('%%endpoint%%', 'airwallex_' + endpoint);
};

export const getStoreCurrency = () => {
    return $.ajax({
        type: 'GET',
        data: {
            security: awxEmbeddedLPMData.nonce.getStoreCurrency,
        },
        url: getAjaxURL('get_store_currency'),
    });
};

export const createQuote = (originalCurrency, requiredCurrency) => {
    return $.ajax({
        type: 'POST',
        data: {
            payment_currency: originalCurrency,
            target_currency: requiredCurrency,
            security: awxEmbeddedLPMData.nonce.createQuoteCurrencySwitcher,
        },
        url: getAjaxURL('currency_switcher_create_quote'),
    });
};