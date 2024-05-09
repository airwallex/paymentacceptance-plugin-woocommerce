import { test, expect } from '@playwright/test';
import {
  addProductToCart,
  fillCustomerInCheckoutBlock,
  selectPaymentMethodInCheckout,
  placeOrderCheckoutBlock,
  verifyPaymentSuccess,
  refundOrder,
  mockPayment,
} from '../Shared/wooUtils';
import { verifyAirwallexPaymentStatus } from '../Shared/airwallexUtils';

test.describe('Wechat element - Block checkout page - Legacy payment template', () => {
  test('Success transaction - Simple product - WeChat element', async ({ page }) => {
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout-block/');
    await fillCustomerInCheckoutBlock(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'WeChat Pay');
    await placeOrderCheckoutBlock(page, 'Place order');
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
