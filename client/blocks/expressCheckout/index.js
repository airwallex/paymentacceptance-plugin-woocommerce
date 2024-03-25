import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { airwallexGooglePayOption } from './airwallex-google-pay.js';
import { airwallexApplePayOption } from './airwallex-apple-pay.js';
import { loadAirwallex } from 'airwallex-payment-elements';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting('airwallex_express_checkout_data', {});
const {
    locale,
    env,
} = settings;
loadAirwallex({
    env,
    locale,
    origin: window.location.origin,
});

registerExpressPaymentMethod(airwallexApplePayOption);
registerExpressPaymentMethod(airwallexGooglePayOption);

