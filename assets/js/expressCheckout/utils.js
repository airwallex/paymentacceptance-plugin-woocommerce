const APPLE_PAY_VERSION = 4;

export const maskPageWhileLoading = function (timeout = 5000) {
	jQuery.blockUI({
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6
		}
	});
	setTimeout(function () {
		jQuery.unblockUI();
	}, timeout);
};

export const removePageMask = function () {
	jQuery.unblockUI();
};

export const deviceSupportApplePay = () => {
	try {
		return (
			'ApplePaySession' in window &&
			ApplePaySession?.supportsVersion(APPLE_PAY_VERSION) &&
			ApplePaySession?.canMakePayments()
		);
	} catch {
		console.error('ApplePaySession is not supported on this device.');
	}
};

export const getApplePaySupportedNetworks = (supportBrands) => {
	const brands                          = (supportBrands || [])
		.map((brand) => {
			if (brand === 'unionpay') {
				return 'chinaUnionPay';
			}
			return brand;
		})
		.filter((brand) => brand !== 'diners');
if (brands.includes('mastercard') && !brands.includes('maestro')) {
	return [...brands, 'maestro'];
}
	return brands;
};

export const getApplePayMerchantCapabilities = (supportBrands) => {
	if (supportBrands?.includes('unionpay')) {
		return ['supports3DS', 'supportsDebit', 'supportsCredit', 'supportsEMV'];
	} else {
		return ['supports3DS', 'supportsDebit', 'supportsCredit'];
	}
};

export const applePayRequiredBillingContactFields = [
	'email',
	'name',
	'phone',
	'postalAddress',
];

export const applePayRequiredShippingContactFields = [
	'email',
	'name',
	'phone',
	'postalAddress',
];

export const getGoogleFormattedShippingOptions = (shippingOptions) => {
	return shippingOptions.map((shippingOption) => {
		return {
			id: shippingOption.id,
			label: shippingOption.label,
			description: shippingOption.description,
		};
	});
};

export const getAppleFormattedShippingOptions = (shippingOptions) => {
	return shippingOptions.map((shippingOption) => {
		return {
			identifier: shippingOption.id,
			label: shippingOption.label,
			detail: shippingOption.description,
			amount: shippingOption.amount,
		};
	});
};

export const getAppleFormattedLineItems = (lineItems) => {
	return lineItems.map((lineItem) => {
		return {
			label: lineItem.label,
			amount: lineItem.price,
		};
	});
};

export const displayLoginConfirmation = (loginConfirmation = null) => {
	if (!loginConfirmation) {
		return;
	}

	let message = loginConfirmation.message;

	// Remove asterisks from string.
	message = message.replace(/\*\*/g, '');

	if (confirm(message)) {
		// Redirect to my account page.
		window.location.href = loginConfirmation.redirect_url;
	}
};
