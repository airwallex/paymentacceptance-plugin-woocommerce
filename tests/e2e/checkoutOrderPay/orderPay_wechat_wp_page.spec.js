import { test, expect } from '@playwright/test';
import {
  selectPaymentMethodInCheckout,
  placeOrderPayPage,
  verifyPaymentSuccess,
  refundOrder,
  mockPayment,
  changePaymentTemplate,
  useShortCodeCheckout,
  createManualOrder,
} from '../Shared/wooUtils';
import { verifyAirwallexPaymentStatus } from '../Shared/airwallexUtils';
import { PAYMENT_FORM_TEMPLATE_LEGACY, PAYMENT_FORM_TEMPLATE_WP_PAGE } from '../Shared/constants';
import { before, beforeEach } from 'node:test';

test.describe('WeChat element - Shortcode checkout page - WP page payment template', () => {
  test('Success transaction - Simple product - WeChat element', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'WeChat Pay');
    await placeOrderPayPage(page, 'Place order');
    await expect(page.frameLocator('iframe[name="Airwallex wechat element iframe"]').getByTitle('QRCode')).toBeVisible();
    const sandboxUrl = await page.frameLocator('iframe[name="Airwallex wechat element iframe"]').getByTitle('QRCode').getAttribute('data-test');
    await mockPayment(page, sandboxUrl);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });
});
