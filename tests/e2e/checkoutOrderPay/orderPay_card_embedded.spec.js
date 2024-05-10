import { test, expect } from '@playwright/test';
import {
  selectPaymentMethodInCheckout,
  placeOrderPayPage,
  verifyPaymentSuccess,
  refundOrder,
  changeCardCheckoutForm,
  useAutoCapture,
  useManualCapture,
  capturePayment,
  useShortCodeCheckout,
  createManualOrder,
} from '../Shared/wooUtils';
import {
  verifyAirwallexPaymentStatus,
  fillInCardDetails,
  threeDsChallenge,
} from '../Shared/airwallexUtils';
import { CARD_CHECKOUT_FORM_INLINE, TEST_CARD, TEST_CARD_3DS_CHALLENGE } from '../Shared/constants';

test.describe('Embedded card element - Shortcode checkout page - Auto capture', () => {
  test('Change setting to use embedded card element', async ({ page }) => {
    await changeCardCheckoutForm(page, CARD_CHECKOUT_FORM_INLINE);
  });

  test('Change setting to use auto capture', async ({ page }) => {
    await useAutoCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await fillInCardDetails(page, 'Airwallex card element iframe', 'success');
    await placeOrderPayPage(page, 'Place order');
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });

  test('Success transaction - Simple product - 3DS challenge', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await fillInCardDetails(page, 'Airwallex card element iframe', '3ds_challenge');
    await placeOrderPayPage(page, 'Place order');
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});

test.describe('Embedded card element - Shortcode checkout page - Manual capture', () => {
  test('Change setting to use manual capture', async ({ page }) => {
    await useManualCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await fillInCardDetails(page, 'Airwallex card element iframe', 'success');
    await placeOrderPayPage(page, 'Place order');
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Authorized');
    await capturePayment(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });

  test('Success transaction - Simple product - 3DS challenge', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await fillInCardDetails(page, 'Airwallex card element iframe', '3ds_challenge');
    await placeOrderPayPage(page, 'Place order');
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Authorized');
    await capturePayment(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});
