import {
	maskPageWhileLoading,
	removePageMask
} from './utils';

/**
 *
 * @param {Object} request 
 */
export function checkoutResponseFlow(confirmData, env, lang, confirmationUrl) {
	maskPageWhileLoading();
	window.location.href = confirmationUrl;
}
