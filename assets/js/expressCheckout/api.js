import {
	getDeviceInfo,
	getBrowserInfo,
	airTrackerCommonData,
	deviceData,
} from './utils.js';
import VersionData from '../../../version.json';
import { checkoutResponseFlow } from './threeDs.js';
import $ from 'jquery';

/* global awxExpressCheckoutSettings, Airwallex */
/**
 * Get WC AJAX endpoint URL.
 *
 * @param  {String} endpoint Endpoint.
 * @return {String}
 */
const getAjaxURL = (endpoint) => {
	return awxExpressCheckoutSettings.ajaxUrl
		.toString()
		.replace('%%endpoint%%', 'airwallex_' + endpoint);
};

export const startPaymentSession = (validationURL) => {
	const data                   = {
		security: awxExpressCheckoutSettings.nonce.startPaymentSession,
		validationURL: validationURL,
		origin: window.location.host,
	}

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('start_payment_session'),
	}).done((response) => {
		return response;
	}).fail((error) => {
		return {
			success: false,
			message: error,
		}
	});
};

/**
 * Get shipping options based on address.
 *
 * @param {PaymentAddress} address Shipping address.
 */
export const updateShippingOptions = (address) => {
	const data                     = {
		security: awxExpressCheckoutSettings.nonce.shipping,
		country: address.countryCode ?? '',
		state: address.administrativeArea ?? '',
		postcode: address.postalCode ?? '',
		city: address.locality ?? '',
	};

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('get_shipping_options')
	}).done(function (response) {
		return response;
	}).fail(function (error) {
		return {
			success: false,
			message: error,
		};
	});;
};

/**
 * Updates the shipping price and the total based on the shipping option.
 *
 * @param {String}   selectedShippingMethodId        Selected shipping method id.
 * @param {Array}   shippingMethods All the shipping methods.
 */
export const updateShippingDetails = (selectedShippingMethodId, shippingMethods) => {
	// for subscription product there could be multiple shipping methods
	// sync the selected shipping method id to all shipping methods
	for (let idx in shippingMethods) {
		shippingMethods[idx] = selectedShippingMethodId;
	}

	const data = {
		security: awxExpressCheckoutSettings.nonce.updateShipping,
		shippingMethods: shippingMethods,
	};

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('update_shipping_method')
	}).done(function (response) {
		return response;
	}).fail(function (error) {
		return {
			success: false,
			message: error,
		};
	});
};

/**
 * Get estimated cart details for product
 */
export const getEstimatedCartDetails = () => {
	let product_id = $('.single_add_to_cart_button').val();

	// Check if product is a variable product.
	if ($('.single_variation_wrap').length) {
		product_id = $('.single_variation_wrap').find('input[name="product_id"]').val();
	}

	let data = {
		security: awxExpressCheckoutSettings.nonce.estimateCart,
		product_id: product_id,
		qty: $('.quantity .qty').val(),
		attributes: $('.variations_form').length ? getAttributes().data : []
	};

	// add addons data to the POST body
	let formData = $('form.cart').serializeArray();
	$.each(formData, function (i, field) {
		if (/^addon-/.test(field.name)) {
			if (/\[\]$/.test(field.name)) {
				let fieldName = field.name.substring(0, field.name.length - 2);
				if (data[fieldName]) {
					data[fieldName].push(field.value);
				} else {
					data[fieldName] = [field.value];
				}
			} else {
				data[field.name] = field.value;
			}
		}
	});

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('get_estimated_cart_details')
	}).done(function(response) {
		return response;
	}).fail(function(error) {
		return {
			success: false,
			message: error,
		}
	});
};

/**
 * Adds the current item on the page to the cart and return the cart details
 *
 * @returns 
 */
export const addToCart = () => {
	let product_id     = $('.single_add_to_cart_button').val();

	// Check if product is a variable product.
	if ($('.single_variation_wrap').length) {
		product_id = $('.single_variation_wrap').find('input[name="product_id"]').val();
	}

	let data = {
		security: awxExpressCheckoutSettings.nonce.addToCart,
		product_id: product_id,
		qty: $('.quantity .qty').val(),
		attributes: $('.variations_form').length ? getAttributes().data : []
	};

	// add addons data to the POST body
	let formData = $('form.cart').serializeArray();
	$.each(formData, function (i, field) {
		if (/^addon-/.test(field.name)) {
			if (/\[\]$/.test(field.name)) {
				let fieldName = field.name.substring(0, field.name.length - 2);
				if (data[fieldName]) {
					data[fieldName].push(field.value);
				} else {
					data[fieldName] = [field.value];
				}
			} else {
				data[field.name] = field.value;
			}
		}
	});

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('add_to_cart')
	}).done(function(response) {
		return response;
	}).fail(function(error) {
		return {
			success: false,
			message: error,
		}
	});
};

/**
 * Get cart details
 * 
 * @returns {Object} Cart details
 */
export const getCartDetails = () => {
	const data              = {
		security: awxExpressCheckoutSettings.nonce.payment
	};

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('get_cart_details'),
	}).done(function (response) {
		return response;
	}).fail(function (error) {
		return {
			success: false,
			message: error,
		}
	});
};

/**
 * Create new order and process the payment
 * 
 * @return {Object} Payment intent
 */
export const createOrder = (paymentData, paymentMethodType) => {
	const data           = 'googlepay' === paymentMethodType ? getOrderDataForGooglePay(paymentData) : getOrderDataForApplePay(paymentData);

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('create_order'),
	}).done(function (response) {
		return response;
	}).fail(function (error) {
		return error;
	});
};

export const confirmPaymentIntent = (commonPayload, confirmPayload) => {
	const data                    = {
		security: awxExpressCheckoutSettings.nonce.payment,
		confirmPayload: confirmPayload,
		commonPayload: commonPayload,
		origin: window.location.origin,
	};

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('confirm_payment_intent'),
	}).done(function (response) {
		const { confirmation, error } = response;
		if (confirmation) {
			checkoutResponseFlow(confirmation, awxExpressCheckoutSettings.env, awxExpressCheckoutSettings.locale, commonPayload.confirmationUrl);
			
			return { confirmation };
		} else {
			return {
				error: {
					message: error?.message,
				}
			};
		}
	}).fail(function (err) {
		return {error: err};
	});
};

export const paymentIntentCreateConsent = (commonPayload, paymentMethodObj) => {
	const data                          = {
		security: awxExpressCheckoutSettings.nonce.payment,
		commonPayload: commonPayload,
		paymentMethodObj: paymentMethodObj,
	}

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('create_payment_consent'),
	}).done(function(response) {
		return response;
	}).fail(function(error) {
		return {
			success: false,
			error: error,
		}
	});
};

export const processOrderWithoutPayment = (redirectUrl, paymentMethodObj) => {
	const data                          = {
		security: awxExpressCheckoutSettings.nonce.payment,
		redirectUrl: redirectUrl,
		paymentMethodObj: paymentMethodObj,
		deviceData: getBrowserInfo(airTrackerCommonData.sessionId),
		origin: window.location.origin,
	}

	return $.ajax({
		type: 'POST',
		data: data,
		url: getAjaxURL('create_consent_without_payment'),
	}).done(function(response) {
		const { confirmation, error, confirmationUrl } = response;
		if (confirmation) {
			checkoutResponseFlow(confirmation, awxExpressCheckoutSettings.env, awxExpressCheckoutSettings.locale, confirmationUrl);
			
			return { confirmation };
		} else {
			return {
				error: {
					message: error?.message,
				}
			};
		}
	}).fail(function(error) {
		return {
			success: false,
			error: error,
		}
	});
};

export const getConfirmPayload = (commonPayload, paymentMethodObj, paymentConsentId = null) => {
	let confirmPayload         = {
		device_data: getBrowserInfo(airTrackerCommonData.sessionId),
		payment_method: paymentMethodObj,
		payment_method_options: {
			card: {
				auto_capture: commonPayload.autoCapture,
			},
		},
	};

	if (paymentConsentId) {
		confirmPayload['payment_consent_reference'] = {
			id: paymentConsentId,
		}
	}

	return confirmPayload;
};

const getAttributes = () => {
	let select      = $( '.variations_form' ).find( '.variations select' ),
		data        = {},
		count       = 0,
		chosen      = 0;

	select.each( function() {
		let attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
		let value          = $( this ).val() || '';

		if ( value.length > 0 ) {
			chosen ++;
		}

		count ++;
		data[ attribute_name ] = value;
	});

	return {
		'count'      : count,
		'chosenCount': chosen,
		'data'       : data
	};
};

const getOrderDataForGooglePay = (paymentData) => {
	const billing              = paymentData.paymentMethodData.info.billingAddress;
	const shipping             = paymentData.shippingAddress;
	const formattedBilling     = {
		billing_first_name:        billing ? billing.name.split( ' ' ).slice( 0, 1 ).join( ' ' ) : '',
		billing_last_name:         billing ? billing.name.split( ' ' ).slice( 1 ).join( ' ' ) : '',
		billing_company:           '',
		billing_email:             paymentData.email ? paymentData.email : '',
		billing_phone:             billing ? billing.phoneNumber : '',
		billing_country:           billing ? billing.countryCode : '',
		billing_address_1:         billing ? billing.address1 : '',
		billing_address_2:         billing ? billing.address2.concat(' ', billing.address3).trim() : '',
		billing_city:              billing ? billing.locality : '',
		billing_state:             billing ? billing.administrativeArea : '',
		billing_postcode:          billing ? billing.postalCode : '',
	}
	const formattedShipping    = {
		shipping_first_name:       shipping ? shipping.name.split( ' ' ).slice( 0, 1 ).join( ' ' ) : '',
		shipping_last_name:        shipping ? shipping.name.split( ' ' ).slice( 1 ).join( ' ' ) : '',
		shipping_company:          shipping && shipping.organization ? shipping.organization : '',
		shipping_country:          shipping ? shipping.countryCode : '',
		shipping_address_1:        shipping ? shipping.address1 : '',
		shipping_address_2:        shipping ? shipping.address2.concat(' ', shipping.address3).trim() : '',
		shipping_city:             shipping ? shipping.locality : '',
		shipping_state:            shipping ? shipping.administrativeArea : '',
		shipping_postcode:         shipping ? shipping.postalCode : '',
		shipping_method:           [ paymentData.shippingOptionData ? paymentData.shippingOptionData.id : null ],
	};

	const data = Object.assign(
		{
			_wpnonce: awxExpressCheckoutSettings.nonce.checkout,
			order_comments:            '',
			payment_method:            'airwallex_express_checkout',
			ship_to_different_address: 1,
			terms:                     1,
			payment_method_type:      'googlepay'
		},
		formattedBilling,
		formattedShipping,
	);

	return data;
}

const getOrderDataForApplePay = (paymentData) => {
	let billing               = paymentData.billingContact;
	const shipping            = paymentData.shippingContact;
	const formattedBilling    = {
		billing_first_name:        billing ? billing.givenName : '',
		billing_last_name:         billing ? billing.familyName : '',
		billing_company:           '',
		billing_email:             billing && billing.emailAddress ? billing.emailAddress : (shipping ? shipping.emailAddress : ''),
		billing_phone:             billing && billing.phoneNumber ? billing.phoneNumber : (shipping ? shipping.phoneNumber : ''),
		billing_country:           billing ? billing.countryCode : '',
		billing_address_1:         billing && billing.addressLines.length > 0 ? billing.addressLines.shift() : '',
		billing_address_2:         billing ? billing.addressLines.join(' ') : '',
		billing_city:              billing ? (billing.locality ? billing.locality : billing.administrativeArea) : '',
		billing_state:             billing ? billing.administrativeArea : '',
		billing_postcode:          billing ? billing.postalCode : '',
	};
	const formattedShipping   = {
		shipping_first_name:       shipping ? shipping.givenName : '',
		shipping_last_name:        shipping ? shipping.familyName : '',
		shipping_company:          '',
		shipping_country:          shipping ? shipping.countryCode : '',
		shipping_address_1:        shipping && shipping.addressLines.length > 0 ? shipping.addressLines.shift() : '',
		shipping_address_2:        shipping ? shipping.addressLines.join(' ') : '',
		shipping_city:             shipping ? (shipping.locality ? shipping.locality : shipping.administrativeArea ) : '',
		shipping_state:            shipping ? shipping.administrativeArea : '',
		shipping_postcode:         shipping ? shipping.postalCode : '',
		shipping_method:           paymentData.shippingMethods ? paymentData.shippingMethods : [ null ],
	};

	const data = Object.assign(
		{
			_wpnonce: awxExpressCheckoutSettings.nonce.checkout,
			order_comments:            '',
			payment_method:            'airwallex_express_checkout',
			ship_to_different_address: 1,
			terms:                     1,
			payment_method_type:      'applepay'
		},
		formattedBilling,
		formattedShipping,
	);

	return data;
}
