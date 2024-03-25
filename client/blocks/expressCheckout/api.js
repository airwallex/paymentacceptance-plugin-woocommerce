import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';

const settings = getSetting('airwallex_express_checkout_data', {});

const getAjaxURL = (endpoint) => {
	return settings.ajaxUrl
		.toString()
		.replace('%%endpoint%%', 'airwallex_' + endpoint);
};

export const startPaymentSession = (validationURL) => {
	const data                   = {
		security: settings.nonce.startPaymentSession,
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

export const getCartDetails = () => {
	const data              = {
		security: settings.nonce.payment
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

export const updateShippingOptions = (address) => {
	const data                     = {
		security: settings.nonce.shipping,
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

export const updateShippingDetails = (selectedShippingMethodId, shippingMethods) => {
	// for subscription product there could be multiple shipping methods
	// sync the selected shipping method id to all shipping methods
	for (let idx in shippingMethods) {
		shippingMethods[idx] = selectedShippingMethodId;
	}

	const data = {
		security: settings.nonce.updateShipping,
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
			_wpnonce: settings.nonce.checkout,
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
			_wpnonce: settings.nonce.checkout,
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
