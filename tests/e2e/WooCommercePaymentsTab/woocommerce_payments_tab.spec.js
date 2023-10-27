const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const { gotoWooPaymentTab } = require('../Shared/wooUtils');
const { getMethodNames } = require('../Shared/gateways');

test.describe(' - WooCommerce Payments Tab', () => {
    test.beforeEach(async ({ page }) => {
        await gotoWooPaymentTab(page);
    });

    test('[T1] Validate that all payment methods are displayed per UI design', async ({
        page,
    }) => {
        const methodNames = getMethodNames();
        const locator = page.locator('a.wc-payment-gateway-method-title');
        const allMethodsPresent = await locator.evaluateAll(
            (elements, names) => {
                const displayedMethods = elements.map((element) => {
                    return element.textContent.trim();
                });
                const foundMethods = names.map((name) => {
                    return displayedMethods.includes(name);
                });
                return foundMethods.every((found) => found === true);
            },
            methodNames,
        );
        expect(allMethodsPresent).toBe(true);
    });
});
