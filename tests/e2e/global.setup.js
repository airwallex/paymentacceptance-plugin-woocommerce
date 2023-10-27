const { test } = require('@playwright/test');
const { loginAdmin } = require('./Shared/wpUtils');
const { STORAGE_STATE } = require('../../playwright.config');

test('do login', async ({ page }) => {
    await loginAdmin(page);
    await page.context().storageState({ path: STORAGE_STATE });
});
