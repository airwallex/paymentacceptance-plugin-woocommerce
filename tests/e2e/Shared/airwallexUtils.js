import { expect } from '@playwright/test';
import {
    AIRWALLEX_USER_EMAIL,
    AIRWALLEX_USER_PASSWORD,
    CARD_MAP,
} from './constants';
import 'dotenv/config';

const ENV_HOST = {
    dev: 'dev.airwallex.com',
    staging: 'staging.airwallex.com',
    demo: 'demo.airwallex.com',
    prod: 'www.airwallex.com',
}

export const loginAirwallex = async (page) => {
    await page.goto(`https://${ENV_HOST[process.env.ENVIRONMENT] || 'demo.airwallex.com'}/app/login`);
    await page.locator('[name="email"]').fill(AIRWALLEX_USER_EMAIL);
    await page.locator('[name="password"]').fill(AIRWALLEX_USER_PASSWORD);
    await page.getByRole('button', { name: 'Log in' }).click();
};

export const verifyAirwallexPaymentStatus = async (page, orderId, paymentStatus) => {
    await page.goto(`https://${ENV_HOST[process.env.ENVIRONMENT] || 'demo.airwallex.com'}/app/login`);
    await page.locator('[name="email"]').fill(AIRWALLEX_USER_EMAIL);
    await page.locator('[name="password"]').fill(AIRWALLEX_USER_PASSWORD);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/app\/dashboard/);
    await page.goto(`https://${ENV_HOST[process.env.ENVIRONMENT] || 'demo.airwallex.com'}/app/acquiring/list`);
    await expect(page.getByLabel('Clear Date')).toBeVisible();
    await page.getByLabel('Clear Date').click();
    await page.getByRole('cell', { name: orderId, exact: true }).click();
    await expect(page.locator('div').filter({ hasText: new RegExp(`^Payment status${paymentStatus}$`) })).toBeVisible();
    await page.locator('[data-test="page_controls"]').click();
    await page.locator('[data-test="content_wrapper"] [data-test="page_control_logout"]').click();
    await page.waitForURL(/\/app\/login/);
};

export const fillInCardDetails = async (page, iframeName, type) => {
    await expect(page.frameLocator(`iframe[name="${iframeName}"]`).locator('input[autocomplete="cc-number"]')).toBeVisible();
    await page.waitForTimeout(1000);
    await page.frameLocator(`iframe[name="${iframeName}"]`).locator('input[autocomplete="cc-number"]').fill(CARD_MAP[type]);
    await page.frameLocator(`iframe[name="${iframeName}"]`).locator('input[autocomplete="cc-exp"]').fill('12/34');
    await page.frameLocator(`iframe[name="${iframeName}"]`).locator('input[autocomplete="cc-csc"]').fill('123 ');
};

export const threeDsChallenge = async (page) => {
    await expect(page.locator('#threeDs')).toBeVisible();
    await expect(page.frameLocator('iframe[name="Airwallex 3DS wrapper iframe"]').frameLocator('iframe[name="Airwallex 3DS iframe"]').getByRole('button', { name: 'Submit' })).toBeVisible();
    await page.waitForTimeout(1000);
    await page.frameLocator('iframe[name="Airwallex 3DS wrapper iframe"]').frameLocator('iframe[name="Airwallex 3DS iframe"]').locator('input[name="challengeDataEntry"]').type('1234');
    await expect(page.frameLocator('iframe[name="Airwallex 3DS wrapper iframe"]').frameLocator('iframe[name="Airwallex 3DS iframe"]').getByRole('button', { name: 'Submit' })).toBeEnabled();
    await page.waitForTimeout(1000);
    await page.frameLocator('iframe[name="Airwallex 3DS wrapper iframe"]').frameLocator('iframe[name="Airwallex 3DS iframe"]').getByRole('button', { name: 'Submit' }).click();
};