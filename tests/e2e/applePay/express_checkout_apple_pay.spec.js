import { test, expect } from '@playwright/test';
import {
    gotoProductPage,
    addProductToCart,
    useShortCodeCheckout,
    useBlockCheckout,
} from '../Shared/wooUtils';
import { loginAdmin, logoutAdmin } from '../Shared/wpUtils';

test.describe('Apple Pay express checkout', () => {
    test('Apple Pay button is visible', async ({ page }) => {
        await loginAdmin(page);
        await page.goto('./wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_express_checkout');
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['checkout', 'product_detail', 'cart']
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
        await logoutAdmin(page);
        await gotoProductPage(page, 'simple-product');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await useShortCodeCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await page.goto('./checkout/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await useBlockCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart-block/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awxApplePayButton').click();
        await page.goto('./checkout-block/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awxApplePayButton').click();

        await loginAdmin(page);
        await page.goto('./wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_express_checkout');
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['product_detail']
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
        await logoutAdmin(page);
        await gotoProductPage(page, 'simple-product');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await useShortCodeCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart/');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await page.goto('./checkout/');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await useBlockCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart-block/');
        await expect(page.locator('id=awxApplePayButton')).not.toBeAttached();
        await page.goto('./checkout-block/');
        await expect(page.locator('id=awxApplePayButton')).not.toBeAttached();

        await loginAdmin(page);
        await page.goto('./wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_express_checkout');
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['checkout']
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
        await logoutAdmin(page);
        await gotoProductPage(page, 'simple-product');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await useShortCodeCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart/');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await page.goto('./checkout/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await useBlockCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart-block/');
        await expect(page.locator('id=awxApplePayButton')).not.toBeAttached();
        await page.goto('./checkout-block/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awxApplePayButton').click();

        await loginAdmin(page);
        await page.goto('./wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_express_checkout');
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['cart']
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
        await logoutAdmin(page);
        await gotoProductPage(page, 'simple-product');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await useShortCodeCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awx-ec-apple-pay-btn').click();
        await page.goto('./checkout/');
        await expect(page.locator('id=awx-ec-apple-pay-btn')).not.toBeAttached();
        await useBlockCheckout(page);
        await addProductToCart(page, 'simple_product');
        await page.goto('./cart-block/');
        await expect(page.frameLocator('iframe[name="Airwallex apple pay button iframe"]').locator('data-test-id=applepaybutton_available')).toBeVisible();
        await page.locator('id=awxApplePayButton').click();
        await page.goto('./checkout-block/');
        await expect(page.locator('id=awxApplePayButton')).not.toBeAttached();

        await loginAdmin(page);
        await page.goto('./wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_express_checkout');
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['checkout', 'product_detail', 'cart']
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
        await logoutAdmin(page);
        await useShortCodeCheckout(page);
    });
});