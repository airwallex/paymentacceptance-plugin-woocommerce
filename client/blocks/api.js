import $ from 'jquery';

const getAjaxURL = (endpoint, settings) => {
	return settings.ajaxUrl
		.toString()
		.replace('%%endpoint%%', 'airwallex_' + endpoint);
};

export const getStoreCurrency = (settings) => {
    return $.ajax({
        type: 'GET',
        data: {
            security: settings?.nonce?.getStoreCurrency,
        },
        url: getAjaxURL('get_store_currency', settings),
    });
};

export const createQuote = (originalCurrency, requiredCurrency, settings) => {
    return $.ajax({
        type: 'POST',
        data: {
            payment_currency: originalCurrency,
            target_currency: requiredCurrency,
            security: settings?.nonce?.createQuoteCurrencySwitcher,
        },
        url: getAjaxURL('currency_switcher_create_quote', settings),
    });
};
