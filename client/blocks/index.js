import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { airwallexDropInOption } from './airwallex-dropin.js';
import { airwallexCardOption } from './card/airwallex-card.js';
import { airwallexWeChatInOption } from './airwallex-wechat.js';
import { airwallexExpressCheckoutOption } from './airwallex-express-checkout.js';

registerPaymentMethod(airwallexDropInOption);
registerPaymentMethod(airwallexCardOption);
registerPaymentMethod(airwallexWeChatInOption);
registerPaymentMethod(airwallexExpressCheckoutOption);
