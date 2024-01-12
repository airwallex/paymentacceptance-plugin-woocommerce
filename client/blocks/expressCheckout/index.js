import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { airwallexGooglePayOption } from './airwallex-google-pay.js';
import { airwallexApplePayOption } from './airwallex-apple-pay.js';
import { airTrackerCommonData } from './utils.js';

const settings = getSetting('airwallex_express_checkout_data', {});
// register the device fingerprint
const fingerprintScriptId = 'airwallex-fraud-api';
if (document.getElementById(fingerprintScriptId) === null) {
	const hostSuffix        = settings.env === 'prod' ? '' : '-demo';
	const fingerprintJsUrl  = `https://static${hostSuffix}.airwallex.com/webapp/fraud/device-fingerprint/index.js`;
	const fingerprintScript = document.createElement('script');
	fingerprintScript.defer = true;
	fingerprintScript.setAttribute('id', fingerprintScriptId);
	fingerprintScript.setAttribute('data-order-session-id', airTrackerCommonData.sessionId);
	fingerprintScript.src = fingerprintJsUrl;
	document.body.appendChild(fingerprintScript);
}

registerExpressPaymentMethod(airwallexApplePayOption);
registerExpressPaymentMethod(airwallexGooglePayOption);
