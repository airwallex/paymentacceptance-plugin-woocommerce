import VersionData from '../../../version.json';
import { detect } from 'detect-browser';

export const APPLE_PAY_VERSION = 4;

export const getBrowserInfo             = (sessionId) => {
	const { navigator, screen }         = window || {};
	const { language, userAgent }       = navigator || {};
	const { colorDepth, height, width } = screen || {};

	return {
		device_id: sessionId,
		screen_height: height,
		screen_width: width,
		screen_color_depth: colorDepth,
		language: language,
		timezone: new Date().getTimezoneOffset(),
		browser: {
			java_enabled: navigator?.javaEnabled(),
			javascript_enabled: true,
			user_agent: userAgent,
		},
	};
}

export const getDeviceInfo = ({ sessionId, origin }) => {
	const browser          = detect() || {};
	return {
		browser_info: `${browser?.name}/${browser?.version} ${browser?.os}`,
		device_id: sessionId,
		http_browser_type: browser?.name,
		cookies_accepted: true,
		host_name: origin,
	};
};

const getOS         = () => {
	const userAgent = window.navigator.userAgent;
	// @ts-ignore
	const platform         = window.navigator?.userAgentData?.platform || window.navigator.platform;
	const macosPlatforms   = ['Macintosh', 'MacIntel', 'MacPPC', 'Mac68K'];
	const windowsPlatforms = ['Win32', 'Win64', 'Windows', 'WinCE'];
	const iosPlatforms     = ['iPhone', 'iPad', 'iPod'];
	let os                 = 'other';

	if (macosPlatforms.indexOf(platform) !== -1) {
		os = 'macos';
	} else if (iosPlatforms.indexOf(platform) !== -1) {
		os = 'ios';
	} else if (windowsPlatforms.indexOf(platform) !== -1) {
		os = 'windows';
	} else if (/Android/.test(userAgent)) {
		os = 'android';
	} else if (/Linux/.test(platform)) {
		os = 'linux';
	}

	return os;
};

const DEVICE_ID_STORAGE_KEY = 'AIR_ANALYTICS_DEVICE_ID';

export const generateUId = () => {
	const uniqueId       = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		const r          = (Math.random() * 16) | 0;
		const v          = c === 'x' ? r : (r & 0x3) | 0x8;
		return v.toString(16);
	});
	return uniqueId;
};

const getDeviceId = () => {
	try {
		let deviceId = window.localStorage.getItem(DEVICE_ID_STORAGE_KEY);
		if (!deviceId) {
			deviceId = generateUId();
			window.localStorage.setItem(DEVICE_ID_STORAGE_KEY, deviceId);
		}
		return deviceId;
	} catch (e) {
		return 'deviceId';
	}
};

export let airTrackerCommonData = {
	appName: 'pa_plugin_woocommerce',
	deviceId: getDeviceId(),
	sessionId: generateUId(),
	appVersion: VersionData?.version,
	platform: getOS(),
};

export const ENV_HOST = {
	prod: 'checkout.airwallex.com',
	demo: 'checkout-demo.airwallex.com',
	staging: 'checkout-staging.airwallex.com',
};

export const getGatewayUrl = (env) => `https://${ENV_HOST[env] || 'checkout.airwallex.com'}`;

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
		console.error('ApplePaySession is not supported in iframe');
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

export const getFormattedValueFromBlockAmount = (amount, currencyMinorUnit) => {
	// google pay only allow 2 digits
	return (parseInt( amount, 10 ) / 10 ** currencyMinorUnit);
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
