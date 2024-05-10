import { test, expect } from '@playwright/test';
import {
  addProductToCart,
  fillCustomerInCheckoutBlock,
  selectPaymentMethodInCheckout,
  placeOrderCheckoutBlock,
  verifyPaymentSuccess,
  refundOrder,
  changeStoreCurrency,
  useBlockCheckout,
} from '../Shared/wooUtils';
import { verifyAirwallexPaymentStatus } from '../Shared/airwallexUtils';

test.describe('Klarna - Block checkout page', () => {
  test('Success transaction - Simple Product - Klarna - USD', async ({ page }) => {
    await changeStoreCurrency(page, 'USD');
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout-block/');
    await fillCustomerInCheckoutBlock(page, 'US');
    await selectPaymentMethodInCheckout(page, 'Klarna');
    await placeOrderCheckoutBlock(page, 'Place order');
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

  test('Success transaction - Simple product - Klarna - Currency switcher HKD to USD', async ({ page }) => {
    await changeStoreCurrency(page, 'HKD');
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout-block/');
    await fillCustomerInCheckoutBlock(page, 'HK');
    await selectPaymentMethodInCheckout(page, 'Klarna');
    await expect(page.getByText(/Klarna is not available in your country.*/)).toBeVisible();
    await expect(page.locator('#payment-method').getByText('Klarna is not available in HKD for your billing country. We have converted your total to USD for you to complete your payment.')).toBeHidden();
    await expect(page.getByText('Klarna is not available in HKD for your billing country. Please use a different payment method to complete your purchase.')).toBeHidden();
    await placeOrderCheckoutBlock(page, 'Place order');
    await expect(page.locator('#payment-method').getByText('Please use a different payment method.')).toBeVisible();
    await fillCustomerInCheckoutBlock(page, 'US');
    await expect(page.getByText(/Klarna is not available in your country.*/)).toBeHidden();
    await expect(page.locator('#payment-method').getByText('Klarna is not available in HKD for your billing country. We have converted your total to USD for you to complete your payment.')).toBeVisible();
    await expect(page.getByText('Klarna is not available in HKD for your billing country. Please use a different payment method to complete your purchase.')).toBeHidden();
    await placeOrderCheckoutBlock(page, 'Place order');
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
