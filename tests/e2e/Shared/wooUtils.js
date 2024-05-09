import { expect } from '@playwright/test';
import { loginAdmin, logoutAdmin } from './wpUtils';

const path = require('path');
const fs = require('fs');
const wooUrls = {
    settingsPaymentTab: '/wp-admin/admin.php?page=wc-settings&tab=checkout',
};
async function gotoWPPage(page, url) {
    await page.goto(url);
}
async function gotoWooPaymentTab(page) {
    await gotoWPPage(page, wooUrls.settingsPaymentTab);
}
/**
 * @param {import('@playwright/test').Page} page
 * @param                                   productSku
 */
const addProductToCart = async (page, productSku) => {
    await page.goto('/shop/');
    await expect(page.getByRole('heading', { name: 'Shop' })).toBeVisible();
    await page
        .locator('[data-product_sku="' + productSku + '"].button.add_to_cart_button')
        .click();
    await expect(page.locator('[data-product_sku="' + productSku + '"].button.add_to_cart_button.loading')).toHaveCount(0);
};

const emptyCart = async (page) => {
    await page.goto('/cart/');
    const canRemove = await page
        .getByRole('cell', { name: 'Remove this item' })
        .isVisible();
    if (canRemove) {
        await page.getByRole('cell', { name: 'Remove this item' }).click();
    }
};

/**
 * @param {import('@playwright/test').Page} page
 * @param                                   country
 */
const fillCustomerInCheckout = async (page, country = 'DE') => {
    await page.selectOption('select#billing_country', country);
    if (country === 'DE') {
        await page.locator('input[name="billing_city"]').fill('Berlin');
        await page.locator('input[name="billing_address_1"]').fill('Calle Drutal');
        await page.locator('input[name="billing_postcode"]').fill('22100');
    } else if (country === 'US') {
        await page.locator('select[name="billing_state"]').selectOption('CA');
        await page.locator('input[name="billing_city"]').fill('Mountain View');
        await page.locator('input[name="billing_address_1"]').fill('1600 Amphitheatre Parkway1');
        await page.locator('input[name="billing_postcode"]').fill('94043');
    } else if (country === 'HK') {
        await page.locator('select[name="billing_state"]').selectOption('HONG KONG');
        await page.locator('input[name="billing_city"]').fill('Causeway Bay');
        await page.locator('input[name="billing_address_1"]').fill('1 Matheson Street');
    }
    await page.locator('input[name="billing_first_name"]').fill('Julia');
    await page.locator('input[name="billing_last_name"]').fill('Callas');
    await page.locator('input[name="billing_phone"]').fill('+1 650-555-5555');
    await page.locator('input[name="billing_email"]').fill('test@test.com');
    const canFillCompany = await page
        .locator('input[name="billing_company"]')
        .isVisible();
    if (canFillCompany) {
        await page
            .locator('input[name="billing_company"]')
            .fill('Test company');
    }
    const canFillBirthDate = await page
        .locator('input[name="billing_birthdate"]')
        .isVisible();
    if (canFillBirthDate) {
        await page
            .locator('input[name="billing_birthdate"]')
            .fill('01-01-1990');
    }
};

/**
 * @param {import('@playwright/test').Page} page
 * @param                                   country
 */
const fillCustomerInCheckoutBlock = async (page, country = 'DE') => {
    const checkboxExists = await page.getByLabel('Use same address for billing').isVisible();
    if (checkboxExists) await page.getByLabel('Use same address for billing').check();
    const editLinkExists = await page.getByRole('button', { name: 'Edit address' }).isVisible();
    if (editLinkExists) await page.getByRole('button', { name: 'Edit address' }).click();
    await page.locator('#shipping-country').locator('input').fill(country);
    if (country === 'DE') {
        await page.locator('#shipping-city').fill('Berlin');
        await page.locator('#shipping-address_1').fill('Calle Drutal');
        await page.locator('#shipping-postcode').fill('22100');
    } else if (country === 'US') {
        await page.locator('#shipping-city').fill('Mountain View');
        await page.locator('#shipping-state').locator('input').fill('California');
        await page.locator('#shipping-address_1').fill('1600 Amphitheatre Parkway1');
        await page.locator('#shipping-postcode').fill('94043');
    } else if (country === 'HK') {
        await page.locator('#shipping-city').fill('Causeway Bay');
        await page.locator('#shipping-state').locator('input').fill('Hong Kong Island');
        await page.locator('#shipping-address_1').fill('1 Matheson Street');
    }
    await page.locator('#shipping-first_name').fill('Julia');
    await page.locator('#shipping-last_name').fill('Callas');
    await page.locator('#shipping-phone').fill('+1 650-555-5555');
    await page.locator('#email').fill('test_card@test.com');
    const canFillCompany = await page.getByLabel('Company').isVisible();
    if (canFillCompany) {
        await page.getByLabel('Company').fill('Test company');
    }
};

/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInBlockCheckout = async (page) => {
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Julia');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('Callas');
};

const selectPaymentMethodInCheckout = async (page, paymentMethod) => {
    await page.getByText(paymentMethod, { exact: true }).click();
};

const placeOrderCheckout = async (page, buttonText) => {
    // Click text=Place order
    await page.getByRole('button', { name: buttonText }).click();
};

const placeOrderCheckoutBlock = async (page, buttonText) => {
    // Click text=Place order
    await page.waitForTimeout(5000);
    await page.getByRole('button', { name: buttonText }).click();
};

const placeOrderPayPage = async (page) => {
    // Click text=Place order
    await page.getByRole('button', { name: 'Pay for order' }).click();
};

const captureTotalAmountCheckout = async (page) => {
    return await page.innerText('.order-total > td > strong > span > bdi');
};

const captureTotalAmountPayPage = async (page) => {
    return await page.innerText('.woocommerce-Price-amount.amount > bdi');
};

const captureTotalAmountBlockCheckout = async (page) => {
    const totalLine = await page
        .locator('div')
        .filter({ hasText: /^Total/ })
        .first();
    const totalAmount = await totalLine.innerText(
        '.woocommerce-Price-amount amount > bdi',
    );
    // totalAmount is "Total\n72,00 €" and we need to remove the "Total\n" part
    return totalAmount.substring(6, totalAmount.length);
};

const createManualOrder = async (page, productLabel = 'Simple') => {
    await loginAdmin(page);
    await page.goto('wp-admin/post-new.php?post_type=shop_order');
    await page.click('text=Add item(s)');
    await page.click('text=Add product(s)');
    await page
        .getByRole('combobox', { name: 'Search for a product…' })
        .locator('span')
        .nth(2)
        .click();
    await page.locator('span > .select2-search__field').fill(productLabel);
    await page.click('text=' + productLabel);
    await page.locator('#btn-ok').click();
    await page.waitForTimeout(2000);
    await page.getByRole('button', { name: 'Create' }).click();
    const paymentLink = await page.getByRole('link', {name: 'Customer payment page'}).first().getAttribute('href');
    await logoutAdmin(page);

    return paymentLink;
};

const useAutoCapture = async (page) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_card');
    await page.getByRole('group', { name: 'Capture immediately' }).locator('label').check('Capture immediately');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await logoutAdmin(page);
};

const useManualCapture = async (page) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_card');
    await page.getByRole('group', { name: 'Capture immediately' }).locator('label').uncheck('Capture immediately');
    await page.locator('select[name="airwallex-online-payments-gatewayairwallex_card_capture_trigger_order_status"]').selectOption('wc-completed');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await logoutAdmin(page);
};

const capturePayment = async (page, orderId) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-orders&action=edit&id=' + orderId);
    page.locator('select[name="order_status"]').selectOption('wc-completed');
    await page.locator('button[name="save"]').click();
    await expect(page.getByText('Airwallex payment capture success')).toBeVisible();
};

const useShortCodeCheckout = async (page) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=advanced');
    await page.locator('#select2-woocommerce_cart_page_id-container').click();
    await page.keyboard.type('cart');
    await page.getByText(/^cart \(ID: \d+\)$/i).click();
    await page.locator('#select2-woocommerce_checkout_page_id-container').click();
    await page.keyboard.type('checkout');
    await page.getByText(/^checkout \(ID: \d+\)$/i).click();
    await page.getByText('Save changes').click();
    await logoutAdmin(page);
};

const useBlockCheckout = async (page) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=advanced');
    await page.locator('#select2-woocommerce_cart_page_id-container').click();
    await page.keyboard.type('cart');
    await page.getByText(/^cart block \(ID: \d+\)$/i).click();
    await page.locator('#select2-woocommerce_checkout_page_id-container').click();
    await page.keyboard.type('checkout');
    await page.getByText(/^checkout block \(ID: \d+\)$/i).click();
    await page.getByText('Save changes').click();
    await logoutAdmin(page);
};

const gotoProductPage = async (page, productSku) => {
    await page.goto(`/product/${productSku}`);
};

const verifyPaymentSuccess = async (page, orderId, verifySubscription = false) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-orders&action=edit&id=' + orderId);
    await expect(page.locator('#order_status')).toHaveValue('wc-processing', );
    if (verifySubscription) {
        await verifySubscriptionSuccess(page);
    }
    await logoutAdmin(page);
}

const verifySubscriptionSuccess = async (page) => {
    await expect(page.getByText(/cst_.+/i)).toBeVisible();
    await expect(page.getByText(/cus_.+/i)).toBeVisible();
    await expect(page.locator('.woocommerce_subscriptions_related_orders')).toBeVisible();
    await page.locator('.woocommerce_subscriptions_related_orders').getByRole('link', { name: '#' }).click();
    await expect(page.locator('#order_status')).toHaveValue('wc-active', );
    await page.locator('select[name="wc_order_action"]').selectOption('wcs_process_renewal');
    page.on('dialog', dialog => dialog.accept());
    await page.locator('button[name="save"]').click();
    await expect(page.locator('#order_status')).toHaveValue('wc-active');
    await expect(page.locator('#subscription_renewal_orders').locator('.order-status').first()).toHaveText('Processing');
};

const refundOrder = async (page, orderId) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-orders&action=edit&id=' + orderId);
    await page.getByRole('button', { name: 'Refund' }).click();
    await page.locator('.refund_order_item_qty').first().fill('1');
    await page.locator('.refund_order_item_qty').first().blur();
    let totalAvailableAmount = await page.getByRole('row', { name: /Total available to refund:.*/ }).locator('bdi').innerText();
    totalAvailableAmount = totalAvailableAmount.substring(1, totalAvailableAmount.length);
    await page.evaluate(totalAvailableAmount => document.getElementById('refund_amount').value = totalAvailableAmount, totalAvailableAmount);
    page.on('dialog', dialog => dialog.accept());
    await page.locator('.do-api-refund').click();
    await expect(page.getByText(/Airwallex refund initiated: rfd_.+/i)).toBeVisible();
    await logoutAdmin(page);
};

const mockPayment = async (page, sandboxUrl) => {
    const mockId = sandboxUrl.split('/').pop();
    const baseUrl = sandboxUrl.replace(mockId, '');
    const mockPayUrl = `${baseUrl}mock_objects?id=${mockId}&action=pay`;
    const mockPayPage = await page.context().newPage();
    await mockPayPage.goto(mockPayUrl);
    await mockPayPage.close();
};

const changeStoreCurrency = async (page, currency) => {
    await loginAdmin(page);
    await page.goto('/wp-admin/admin.php?page=wc-settings&tab=general');
    await page.locator('select[name="woocommerce_currency"]').selectOption(currency);
    await page.locator('button[name="save"]').click();
    await logoutAdmin(page);
};

// type = PAYMENT_FORM_TEMPLATE_LEGACY | PAYMENT_FORM_TEMPLATE_WP_PAGE
const changePaymentTemplate = async (page, type) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_general');
    await page.locator('#airwallex-online-payments-gatewayairwallex_general_payment_page_template').selectOption(type);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await logoutAdmin(page);
};

// type = CARD_CHECKOUT_FORM_INLINE | CARD_CHECKOUT_FORM_REDIRECT
const changeCardCheckoutForm = async (page, type) => {
    await loginAdmin(page);
    await gotoWPPage(page, '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=airwallex_card');
    await page.locator('#airwallex-online-payments-gatewayairwallex_card_checkout_form_type').selectOption(type);
    await page.locator('button[name="save"]').click();
    await logoutAdmin(page);
}

module.exports = {
    addProductToCart,
    fillCustomerInCheckout,
    fillCustomerInBlockCheckout,
    fillCustomerInCheckoutBlock,
    gotoWooPaymentTab,
    placeOrderCheckout,
    emptyCart,
    placeOrderPayPage,
    placeOrderCheckoutBlock,
    selectPaymentMethodInCheckout,
    captureTotalAmountCheckout,
    captureTotalAmountBlockCheckout,
    captureTotalAmountPayPage,
    createManualOrder,
    useAutoCapture,
    useManualCapture,
    capturePayment,
    useShortCodeCheckout,
    useBlockCheckout,
    gotoProductPage,
    verifyPaymentSuccess,
    refundOrder,
    mockPayment,
    changeStoreCurrency,
    changeCardCheckoutForm,
    changePaymentTemplate,
};
