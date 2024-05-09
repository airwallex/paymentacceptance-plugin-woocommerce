import { test } from '@playwright/test';
import { STORAGE_STATE } from '../../playwright.config';
import { loginAirwallex } from './Shared/airwallexUtils';

test('Login to airwallex', async ({ page }) => {
    await loginAirwallex(page);
    await page.context().storageState({ path: STORAGE_STATE });
});
