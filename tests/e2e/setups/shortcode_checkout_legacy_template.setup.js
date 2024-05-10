import { test, expect } from '@playwright/test';
import {
    addProductToCart,
    fillCustomerInCheckoutBlock,
    selectPaymentMethodInCheckout,
    placeOrderCheckoutBlock,
    verifyPaymentSuccess,
    refundOrder,
    changeStoreCurrency,
    changePaymentTemplate,
    useShortCodeCheckout,
} from '../Shared/wooUtils';
import {
    loginToAccount,
    logoutFromAccount
} from '../Shared/wpUtils';
import {
    verifyAirwallexPaymentStatus,
    fillInCardDetails,
} from '../Shared/airwallexUtils';
import { PAYMENT_FORM_TEMPLATE_LEGACY, PAYMENT_FORM_TEMPLATE_WP_PAGE, TEST_CARD, TEST_CARD_3DS_CHALLENGE } from '../Shared/constants';

test('Use Woo shortcode checkout and legacy payment template', async ({ page }) => {
    await useShortCodeCheckout(page);
    await changePaymentTemplate(page, PAYMENT_FORM_TEMPLATE_LEGACY);
});
