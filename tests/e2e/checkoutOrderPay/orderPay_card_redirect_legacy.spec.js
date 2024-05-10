import { test, expect } from '@playwright/test';
import {
    selectPaymentMethodInCheckout,
    placeOrderPayPage,
    verifyPaymentSuccess,
    refundOrder,
    changeCardCheckoutForm,
    changePaymentTemplate,
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
import { CARD_CHECKOUT_FORM_REDIRECT, PAYMENT_FORM_TEMPLATE_LEGACY, PAYMENT_FORM_TEMPLATE_WP_PAGE, TEST_CARD, TEST_CARD_3DS_CHALLENGE } from '../Shared/constants';

test.describe('Redirect card element - Shortcode checkout page - Auto capture - Legacy payment template', () => {
  test('Change setting to use redirect card element', async ({ page }) => {
    await changeCardCheckoutForm(page, CARD_CHECKOUT_FORM_REDIRECT);
  });

  test('Change setting to use auto capture', async ({ page }) => {
    await useAutoCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', 'success');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
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
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', '3ds_challenge');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});

test.describe('Redirect card element - Shortcode checkout page - Manual capture - Legacy payment template', () => {
  test('Change setting to use manual capture', async ({ page }) => {
    await useManualCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    const paymentLink = await createManualOrder(page);
    await page.goto(paymentLink);
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', 'success');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
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
    await placeOrderPayPage(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', '3ds_challenge');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Authorized');
    await capturePayment(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});
