import { test, expect } from '@playwright/test';
import {
  selectPaymentMethodInCheckout,
  placeOrderPayPage,
  verifyPaymentSuccess,
  refundOrder,
  changePaymentTemplate,
  useShortCodeCheckout,
  createManualOrder,
} from '../Shared/wooUtils';
import {
  verifyAirwallexPaymentStatus,
  fillInCardDetails,
  threeDsChallenge,
} from '../Shared/airwallexUtils';
import { PAYMENT_FORM_TEMPLATE_LEGACY, PAYMENT_FORM_TEMPLATE_WP_PAGE, TEST_CARD, TEST_CARD_3DS_CHALLENGE } from '../Shared/constants';
import { beforeEach } from 'node:test';

test.describe('Drop in element - Shortcode checkout page - Legacy payment template', () => {
  test('Success transaction - Simple product - Card no 3DS', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Pay with cards and more');
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex dropIn element iframe', 'success');
    await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });

  test('Success transaction - Simple product - Card 3DS challenge', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Pay with cards and more');
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex dropIn element iframe', '3ds_challenge');
    await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});
