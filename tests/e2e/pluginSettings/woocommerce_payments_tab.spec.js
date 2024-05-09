import { expect, test } from '@playwright/test';
import { gotoWooPaymentTab } from '../Shared/wooUtils';
import { loginAdmin } from '../Shared/wpUtils';
import { getMethodNames } from '../Shared/gateways';

test.describe('WooCommerce Payments Tab', () => {
    test('All payment methods are displayed per UI design', async ({ page }) => {
        await loginAdmin(page);
        await gotoWooPaymentTab(page);
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
