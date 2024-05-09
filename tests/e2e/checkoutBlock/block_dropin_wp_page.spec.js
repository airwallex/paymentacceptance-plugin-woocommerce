import { test, expect } from '@playwright/test';
import {
    addProductToCart,
    fillCustomerInCheckoutBlock,
    selectPaymentMethodInCheckout,
    placeOrderCheckoutBlock,
    verifyPaymentSuccess,
    refundOrder,
    changeStoreCurrency,
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
import {
    WP_NORMAL_USER_EMAIL_FOR_DROP_IN,
    WP_NORMAL_USER_PASSWORD_FOR_DROP_IN,
} from '../Shared/constants';

test.describe('Drop in element - Block checkout page - WP page payment template', () => {
    test('Success transaction - Simple product - Card no 3DS', async ({ page }) => {
        await addProductToCart(page, 'simple_product');
        await page.goto('./checkout-block/');
        await fillCustomerInCheckoutBlock(page, 'DE');
        await selectPaymentMethodInCheckout(page, 'Pay with cards and more');
        await placeOrderCheckoutBlock(page, 'Place order');
        await fillInCardDetails(page, 'Airwallex dropIn element iframe', 'success');
        await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByRole('button', { name: 'Pay', exact: true }).click();
        await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
        const orderId = page.url().match(/order-received\/(\d+)/)[1];
        await verifyPaymentSuccess(page, orderId);
        await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
        await refundOrder(page, orderId);
        await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
    });

    test('Success transaction - Subscription product - Card 3DS Challenge - Renew subscription', async ({ page }) => {
        await loginToAccount(page, WP_NORMAL_USER_EMAIL_FOR_DROP_IN, WP_NORMAL_USER_PASSWORD_FOR_DROP_IN);
        await addProductToCart(page, 'subscription_product');
        await page.goto('./checkout-block/');
        await fillCustomerInCheckoutBlock(page, 'DE');
        await selectPaymentMethodInCheckout(page, 'Pay with cards and more');
        await placeOrderCheckoutBlock(page, 'Sign up now');
        await fillInCardDetails(page, 'Airwallex dropIn element iframe', '3ds_challenge');
        await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByRole('button', { name: 'Proceed', exact: true }).click();
        await threeDsChallenge(page);
        await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible();
        const orderId = page.url().match(/order-received\/(\d+)/)[1];
        await logoutFromAccount(page);
        await verifyPaymentSuccess(page, orderId, true);
        await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
    });

    test('Success transaction - Simple product - Klarna', async ({ page }) => {
        await changeStoreCurrency(page, 'USD');
        await addProductToCart(page, 'simple_product');
        await page.goto('./checkout-block/');
        await fillCustomerInCheckoutBlock(page, 'US');
        await selectPaymentMethodInCheckout(page, 'Pay with cards and more');
        await placeOrderCheckoutBlock(page, 'Place order');
        await expect(page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByText('Klarna')).toBeVisible();
        await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').locator('id=klarna').click();
        await page.frameLocator('iframe[name="Airwallex dropIn element iframe"]').getByRole('button', { name: 'Confirm' }).click();
        await expect(page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-field')).toBeVisible({ timeout: 50000 });
        await page.frameLocator('#klarna-apf-iframe').getByTestId('kaf-button').click();
        await page.frameLocator('#klarna-apf-iframe').getByLabel('Enter code').fill('123456');
        await page.frameLocator('#klarna-apf-iframe').getByTestId('select-payment-category').click();
        await page.frameLocator('#klarna-apf-iframe').getByTestId('confirm-and-pay').click();
        await expect(page.getByText('Thank you. Your order has been received.')).toBeVisible({ timeout: 50000 });
        const orderId = page.url().match(/order-received\/(\d+)/)[1];
        await verifyPaymentSuccess(page, orderId, false);
        await verifyAirwallexPaymentStatus(page, orderId, 'Succeeded');
        await refundOrder(page, orderId);
        await verifyAirwallexPaymentStatus(page, orderId, 'Refunded');
    });
});