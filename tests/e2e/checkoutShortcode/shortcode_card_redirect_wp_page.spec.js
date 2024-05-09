import { test, expect } from '@playwright/test';
import {
  addProductToCart,
  fillCustomerInCheckout,
  selectPaymentMethodInCheckout,
  placeOrderCheckout,
  verifyPaymentSuccess,
  refundOrder,
  changeCardCheckoutForm,
  changePaymentTemplate,
  useShortCodeCheckout,
  useAutoCapture,
  useManualCapture,
  capturePayment,
} from '../Shared/wooUtils';
import {
  loginToAccount,
  logoutFromAccount
} from '../Shared/wpUtils';
import {
  verifyAirwallexPaymentStatus,
  fillInCardDetails,
  threeDsChallenge,
} from '../Shared/airwallexUtils';
import { CARD_CHECKOUT_FORM_REDIRECT, WP_NORMAL_USER_EMAIL_FOR_CARD, WP_NORMAL_USER_PASSWORD_FOR_CARD, TEST_CARD, TEST_CARD_3DS_CHALLENGE } from '../Shared/constants';

test.describe('Redirect card element - Shortcode checkout page - Auto capture - WP page payment template', () => {
  test('Change setting to use redirect card element', async ({ page }) => {
    await changeCardCheckoutForm(page, CARD_CHECKOUT_FORM_REDIRECT);
  });

  test('Change setting to use auto capture', async ({ page }) => {
    await useAutoCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout/');
    await fillCustomerInCheckout(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderCheckout(page, 'Place order');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', 'success');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Pay', exact: true}).click();
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await verifyPaymentSuccess(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    await refundOrder(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
  });

  test('Success transaction - Subscription product - 3DS challenge - Renew subscription', async ({ page }) => {
    await loginToAccount(page, WP_NORMAL_USER_EMAIL_FOR_CARD, WP_NORMAL_USER_PASSWORD_FOR_CARD);
    await addProductToCart(page, 'subscription_product');
    await page.goto('./checkout/');
    await fillCustomerInCheckout(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderCheckout(page, 'Sign up now');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', '3ds_challenge');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Proceed', exact: true}).click();
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await logoutFromAccount(page);
    await verifyPaymentSuccess(page, orderId, true);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});

test.describe('Redirect card element - Shortcode checkout page - Manual capture - WP page payment template', () => {
  test('Change setting to use manual capture', async ({ page }) => {
    await useManualCapture(page);
  });

  test('Success transaction - Simple product - No 3DS', async ({ page }) => {
    await addProductToCart(page, 'simple_product');
    await page.goto('./checkout/');
    await fillCustomerInCheckout(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderCheckout(page, 'Place order');
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

  test('Success transaction - Subscription product - 3DS challenge - Renew subscription', async ({ page }) => {
    await loginToAccount(page, WP_NORMAL_USER_EMAIL_FOR_CARD, WP_NORMAL_USER_PASSWORD_FOR_CARD);
    await addProductToCart(page, 'subscription_product');
    await page.goto('./checkout/');
    await fillCustomerInCheckout(page, 'DE');
    await selectPaymentMethodInCheckout(page, 'Credit Card');
    await placeOrderCheckout(page, 'Sign up now');
    await fillInCardDetails(page, 'Airwallex full featured card element iframe', '3ds_challenge');
    await page.frameLocator('iframe[name="Airwallex full featured card element iframe"]').getByRole('button', {name: 'Proceed', exact: true}).click();
    await threeDsChallenge(page);
    await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
    const orderId = page.url().match(/order-received\/(\d+)/)[1];
    await logoutFromAccount(page);
    await verifyPaymentSuccess(page, orderId, true);
    await verifyAirwallexPaymentStatus(page, orderId, 'Authorized');
    await capturePayment(page, orderId);
    await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
  });
});
