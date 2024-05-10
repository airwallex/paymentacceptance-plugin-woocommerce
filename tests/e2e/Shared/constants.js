import 'dotenv/config';

export const CARD_CHECKOUT_FORM_INLINE = 'inline';
export const CARD_CHECKOUT_FORM_REDIRECT = 'redirect';
export const PAYMENT_FORM_TEMPLATE_LEGACY = 'default';
export const PAYMENT_FORM_TEMPLATE_WP_PAGE = 'wordpress_page';
export const TEST_CARD = '4035501000000008';
export const TEST_CARD_3DS_CHALLENGE = '4012000300000088';
export const CARD_MAP = {
    'success': '4035501000000008',
    '3ds_challenge': '4012000300000088',
};
export const WP_ADMIN_USER_NAME = process.env.WP_ADMIN_USER_NAME;
export const WP_ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD;
export const WP_NORMAL_USER_EMAIL_FOR_CARD = process.env.WP_NORMAL_USER_EMAIL_FOR_CARD;
export const WP_NORMAL_USER_PASSWORD_FOR_CARD = process.env.WP_NORMAL_USER_PASSWORD_FOR_CARD;
export const WP_NORMAL_USER_EMAIL_FOR_DROP_IN = process.env.WP_NORMAL_USER_EMAIL_FOR_DROP_IN;
export const WP_NORMAL_USER_PASSWORD_FOR_DROP_IN = process.env.WP_NORMAL_USER_PASSWORD_FOR_DROP_IN;
export const AIRWALLEX_USER_EMAIL = process.env.AIRWALLEX_USER_EMAIL;
export const AIRWALLEX_USER_PASSWORD = process.env.AIRWALLEX_USER_PASSWORD;
export const AIRWALLEX_CLIENT_ID = process.env.AIRWALLEX_CLIENT_ID;
export const AIRWALLEX_API_KEY = process.env.AIRWALLEX_API_KEY;
export const AIRWALLEX_WEBHOOK_SECRET_KEY = process.env.AIRWALLEX_WEBHOOK_SECRET_KEY;