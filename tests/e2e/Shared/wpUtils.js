import { expect } from '@playwright/test';
import {
    WP_ADMIN_USER_NAME,
    WP_ADMIN_PASSWORD,
} from './constants';

async function gotoWPPage(page, url) {
    await page.goto(url);
}

async function gotoWPLogin(page) {
    await gotoWPPage(page, './wp-login.php');
}

async function gotoWPPlugins(page) {
    await gotoWPPage(page, './wp-admin/plugins.php');
}

const loginAdmin = async (page) => {
    await gotoWPLogin(page);
    await page.locator('#user_login').fill(WP_ADMIN_USER_NAME);
    await page.locator('#user_pass').fill(WP_ADMIN_PASSWORD);
    await page.locator('input:has-text("Log In")').click();
    await expect(async () => {
        await page.goto('./wp-admin');
        await page.waitForURL(/\/wp-admin/, { timeout: 5000 });
    }).toPass();
};

const logoutAdmin = async (page) => {
    const logoutUrl = await page.locator('id=wp-admin-bar-logout').locator('a').getAttribute('href');
    await gotoWPPage(page, logoutUrl);
}

async function deactivateWPPlugin(page, pluginName) {
    await page
        .getByRole('link', { name: `Deactivate ${pluginName}`, exact: true })
        .click();
}

async function activateWPPlugin(page, pluginName) {
    await page
        .getByRole('cell', {
            name: `${pluginName} Activate ${pluginName} | Delete ${pluginName}`,
        })
        .getByRole('link', { name: `Activate ${pluginName}` })
        .click();
}

const enableCheckboxSetting = async (page, settingName, settingsTabUrl) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input[name="${settingName}"]`).check();
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click(),
    ]);
};

const disableCheckboxSetting = async (page, settingName, settingsTabUrl) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input[name="${settingName}"]`).uncheck();
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
};

async function saveSettings(page) {
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

const selectOptionSetting = async (
    page,
    settingName,
    settingsTabUrl,
    optionValue,
) => {
    await page.goto(settingsTabUrl);
    await page.selectOption(`select[name="${settingName}"]`, optionValue);
    await saveSettings(page);
};

const fillTextSettings = async (page, settingName, settingsTabUrl, value) => {
    await page.goto(settingsTabUrl);
    const field = await page.locator(`input[name="${settingName}"]`);
    await field.fill(value);
    await saveSettings(page);
};

const fillNumberSettings = async (page, settingName, settingsTabUrl, value) => {
    await page.goto(settingsTabUrl);
    await page.locator(`input#${settingName}`).fill('');
    await page.type(`input#${settingName}`, value.toString());
    await saveSettings(page);
};

const loginToAccount = async(page, username, password) => {
    await page.goto('./my-account');
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await page.locator('input[name="username"]').fill(username);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', {name: 'Log in'}).click();
};

const logoutFromAccount = async(page) => {
    await page.goto('./my-account');
    await page.locator('li').filter({ hasText: 'Log out' }).getByRole('link').click();
}

module.exports = {
    loginAdmin,
    logoutAdmin,
    deactivateWPPlugin,
    activateWPPlugin,
    gotoWPPlugins,
    enableCheckboxSetting,
    disableCheckboxSetting,
    selectOptionSetting,
    fillTextSettings,
    fillNumberSettings,
    loginToAccount,
    logoutFromAccount,
};
