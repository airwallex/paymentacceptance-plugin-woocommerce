import { expect, test } from '@playwright/test';
import { loginAdmin } from '../Shared/wpUtils';
import {
    AIRWALLEX_CLIENT_ID,
    AIRWALLEX_API_KEY,
    AIRWALLEX_WEBHOOK_SECRET_KEY
} from '../Shared/constants';

test.describe('Airwallex Settings Tab', () => {
    let page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await loginAdmin(page);
        await page.goto('wp-admin/plugins.php');
        await page.getByRole('link', { name: 'Airwallex API settings' }).click();
    });

    test.afterAll(async () => {
        await page.close();
    });

    test('Airwallex General Tab', async () => {
        await expect(page.getByLabel('Unique client ID')).toBeVisible();
        await expect(page.getByLabel('API key')).toBeVisible();
        await expect(page.getByLabel('Webhook secret key')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Enable sandbox' }).locator('label')).toBeVisible();
        await expect(page.getByLabel('Temporary order status after')).toBeVisible();
        await expect(page.getByLabel('Order state for pending')).toBeVisible();
        await expect(page.getByLabel('Order state for authorized')).toBeVisible();
        await expect(page.getByLabel('Cronjob interval')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Activate JS logging' }).locator('label')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Activate remote logging' }).locator('label')).toBeVisible();
        await expect(page.getByLabel('Payment form template')).toBeVisible();

        await page.getByLabel('Unique client ID').fill(AIRWALLEX_CLIENT_ID);
        await page.getByLabel('API key').fill(AIRWALLEX_API_KEY);
        await page.getByLabel('Webhook secret key').fill(AIRWALLEX_WEBHOOK_SECRET_KEY);
        await page.getByRole('group', { name: 'Enable sandbox' }).locator('label').check('Enable sandbox');
        await page.getByRole('group', { name: 'Activate remote logging' }).locator('label').check('Activate remote logging');
        await page.getByLabel('Payment form template').selectOption('wordpress_page');
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Connected', { exact: true })).toBeVisible();
    });

    test('Airwallex Card Tab', async () => {
        await page.getByRole('link', { name: 'Cards' }).click();
        await expect(page.getByText('Enable Airwallex Card Payments')).toBeVisible();
        await expect(page.getByLabel('Title')).toBeVisible();
        await expect(page.getByLabel('Description')).toBeVisible();
        await expect(page.getByLabel('Checkout form')).toBeVisible();
        await expect(page.getByLabel('Statement descriptor')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Capture immediately' })).toBeVisible();
        await expect(page.getByLabel('Capture status')).toBeVisible();

        await page.getByText('Enable Airwallex Card Payments').check();
        await page.getByLabel('Title').fill('Credit Card');
        await page.getByLabel('Description').fill('');
        await page.getByLabel('Checkout form').selectOption('inline');
        await page.getByLabel('Statement descriptor').fill('Your order %order%');
        await page.getByRole('group', { name: 'Capture immediately' }).locator('label').check('Capture immediately');
        await page.getByRole('button', { name: 'Save changes' }).click();
    });

    test('Airwallex Express Checkout Tab', async () => {
        await page.getByRole('link', { name: 'Express Checkout' }).click();
        await expect(page.getByRole('group', { name: 'Register domain file' })).toBeVisible();
        await expect(page.getByText('Enable Airwallex Express')).toBeVisible();
        await expect(page.getByRole('group', { name: 'Express Checkout' })).toBeVisible();
        await expect(page.getByRole('group', { name: 'Show Button On' })).toBeVisible();
        await expect(page.getByRole('group', { name: 'Call To Action' })).toBeVisible();
        await expect(page.getByRole('group', { name: 'Button Theme' })).toBeVisible();

        await page.getByLabel('Enable/Disable').check();
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_payment_methods').selectOption(
            ['apple_pay', 'google_pay']
        );
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_show_button_on').selectOption(
            ['checkout', 'product_detail', 'cart']
        );
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_call_to_action').selectOption(
            'buy'
        );
        await page.locator('#airwallex-online-payments-gatewayairwallex_express_checkout_appearance_theme').selectOption(
            'black'
        );
        await page.getByRole('button', { name: 'Save changes' }).click();
    });

    test('Airwallex WeChat Tab', async () => {
        await page.getByRole('link', { name: 'WeChat Pay' }).click();
        await expect(page.getByText('Enable Airwallex WeChat Pay')).toBeVisible();
        await expect(page.getByRole('cell', { name: 'Title' })).toBeVisible();
        await expect(page.getByRole('cell', { name: 'Description' })).toBeVisible();

        await page.getByLabel('Enable/Disable').check();
        await page.getByLabel('Title', { exact: true }).fill('WeChat Pay');
        await page.getByLabel('Description', { exact: true }).fill('');
        await page.getByRole('button', { name: 'Save changes' }).click();
    });

    test('Airwallex Klarna Tab', async () => {
        await page.getByRole('link', { name: 'Klarna', exact: true }).click();
        await expect(page.getByText('Enable Airwallex Klarna')).toBeVisible();
        await expect(page.getByLabel('Title', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Description', { exact: true })).toBeVisible();

        page.getByText('Enable Airwallex Klarna').check();
        page.getByLabel('Title', { exact: true }).fill('Klarna');
        page.getByLabel('Description', { exact: true }).fill('Pay later, or installments with Klarna');
        await page.getByRole('button', { name: 'Save changes' }).click();
    });

    test('Airwallex All Payment Methods Tab', async () => {
        await page.getByRole('link', { name: 'All Payment Methods' }).click();
        await expect(page.getByText('Enable Airwallex Payments')).toBeVisible();
        await expect(page.getByLabel('Title', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Description')).toBeVisible();
        await expect(page.getByRole('row', { name: 'Icons to display Choose which' }).getByRole('cell')).toBeVisible();
        await expect(page.getByRole('cell', { name: 'Shoppers with different' })).toBeVisible();
        await expect(page.getByRole('row', { name: 'Payment page template Select' }).getByRole('cell')).toBeVisible();

        await page.getByText('Enable Airwallex Payments').check();
        await page.getByLabel('Title', { exact: true }).fill('Pay with cards and more');
        await page.getByLabel('Description').fill('');
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="card_visa"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="card_mastercard"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="card_amex"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="card_jcb"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="card_unionpay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="applepay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="googlepay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_icons[]"][value="klarna"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="card"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="applepay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="googlepay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="klarna"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="pay_now"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="alipaycn"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="alipayhk"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_methods[]"][value="wechatpay"]').check();
        await page.locator('input[name="airwallex-online-payments-gatewayairwallex_main_template"][value="2col-1"]').check();
        await page.getByRole('button', { name: 'Save changes' }).click();
    });
});
