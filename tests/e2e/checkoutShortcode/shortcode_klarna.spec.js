import { test, expect } from '@playwright/test';
import {
  addProductToCart,
  fillCustomerInCheckout,
  selectPaymentMethodInCheckout,
  placeOrderCheckout,
  verifyPaymentSuccess,
  refundOrder,
  changeStoreCurrency,
  useShortCodeCheckout,
} from '../Shared/wooUtils';
import { verifyAirwallexPaymentStatus } from '../Shared/airwallexUtils';
import { beforeEach } from 'node:test';

test.describe('Klarna - Shortcode checkout page', () => {
  beforeEach(async ({ page }) => {
    await useShortCodeCheckout(page);
  });

  test('Success transaction - Klarna - USD', async ({ page }) => {
    await changeStoreCurrency(page, 'USD');
    await addProductToCart(page, 'simple_product');
    await page.goto('/checkout/');
    await fillCustomerInCheckout(page, 'US');
    await selectPaymentMethodInCheckout(page, 'Klarna');
    await placeOrderCheckout(page, 'Place order');
    await expect(page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-field')).toBeVisible({timeout: 50000});
    await page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-button').click();
    await page.frameLocator('#klarna-apf-iframe').getByLabel('Enter code').fill('123456');
    await page.frameLocator('#klarna-apf-iframe').getByTestId('select-payment-category').click();
    await page.frameLocator('#klarna-apf-iframe').getByTestId('confirm-and-pay').click();
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible({timeout: 50000});
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });

  test('Success transaction - Klarna - Currency switcher HKD to USD', async ({ page }) => {
    await changeStoreCurrency(page, 'HKD');
    await addProductToCart(page, 'simple_product');
    await page.goto('/checkout/');
    await fillCustomerInCheckout(page, 'HK');
    await selectPaymentMethodInCheckout(page, 'Klarna');
    await expect(page.locator('.wc-airwallex-lpm-country-ineligible')).toBeVisible();
    await expect(page.locator('.wc-airwallex-lpm-currency-ineligible-switcher-on')).toBeHidden();
    await expect(page.locator('.wc-airwallex-lpm-currency-ineligible-switcher-off')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Place order' })).toBeDisabled();
    await fillCustomerInCheckout(page, 'US');
    await expect(page.locator('.wc-airwallex-lpm-country-ineligible')).toBeHidden();
    await expect(page.locator('.wc-airwallex-lpm-currency-ineligible-switcher-on')).toBeVisible();
    await expect(page.locator('.wc-airwallex-lpm-currency-ineligible-switcher-off')).toBeHidden();
    await placeOrderCheckout(page, 'Place order');
    await expect(page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-field')).toBeVisible({timeout: 50000});
    await page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-button').click();
    await page.frameLocator('#klarna-apf-iframe').getByLabel('Enter code').fill('123456');
    await page.frameLocator('#klarna-apf-iframe').getByTestId('select-payment-category').click();
    await page.frameLocator('#klarna-apf-iframe').getByTestId('confirm-and-pay').click();
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible({timeout: 50000});
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
    await changeStoreCurrency(page, 'USD');
  });
});
