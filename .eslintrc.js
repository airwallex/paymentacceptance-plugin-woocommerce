module.exports = {
    extends: ['plugin:@woocommerce/eslint-plugin/recommended'],
    env: {
        browser: true,
        node: true,
        commonjs: true,
        jest: true,
    },
    rules: {
        '@wordpress/i18n-translator-comments': 'warn',
        '@wordpress/valid-sprintf': 'warn',
        'jsdoc/check-tag-names': [
            'error',
            { definedTags: ['jest-environment'] },
        ],
        'prettier/prettier': [
            'error',
            {
                useTabs: false,
                tabWidth: 4,
                singleQuote: true,
            },
        ],
        'prettier/prettier': 0,
        'no-console': 'off',
    },
    settings: {
        react: {
            version: 'detect',
        },
    },
};
