import {
	maskPageWhileLoading,
} from './utils.js';

/**
 *
 * @param {Object} request 
 */
export function checkoutResponseFlow(confirmData, env, lang, confirmationUrl) {
	maskPageWhileLoading();
	window.location.href = confirmationUrl;
}
