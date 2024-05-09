import { test, expect } from '@playwright/test';
import {
  addProductToCart,
  fillCustomerInCheckout,
  selectPaymentMethodInCheckout,
  placeOrderCheckout,
  verifyPaymentSuccess,
  refundOrder,
  mockPayment,
  changePaymentTemplate,
  useShortCodeCheckout,
} from '../Shared/wooUtils';
import { verifyAirwallexPaymentStatus } from '../Shared/airwallexUtils';
import { PAYMENT_FORM_TEMPLATE_LEGACY, PAYMENT_FORM_TEMPLATE_WP_PAGE } from '../Shared/constants';
import { before, beforeEach } from 'node:test';

test.describe('WeChat element - Shortcode checkout page - Legacy payment template', () => {
  test('Success transaction - Simple product - WeChat element', async ({ page }) => {
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout/');
    await fillCustomerInCheckout(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'WeChat Pay');
    await placeOrderCheckout(page, 'Place order');
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
