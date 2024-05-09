/**
 * Get an unique ID
 * 
 * @returns {String} Unique ID
 */
export const generateUId = () => {
	const uniqueId       = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		const r          = (Math.random() * 16) | 0;
		const v          = c === 'x' ? r : (r & 0x3) | 0x8;
		return v.toString(16);
	});
	return uniqueId;
};

export let airTrackerCommonData = {
	sessionId: generateUId(),
};

export const getBrowserInfo = (sessionId) => {
	const { navigator, screen } = window || {};
	const { language, userAgent } = navigator || {};
	const { colorDepth, height, width } = screen || {};

	return {
		device_id: sessionId,
		screen_height: height,
		screen_width: width,
		screen_color_depth: colorDepth,
		language: language,
		timezone: new Date().getTimezoneOffset(),
		browser: {
			java_enabled: navigator?.javaEnabled(),
			javascript_enabled: true,
			user_agent: userAgent,
		},
	};
}

export const initAirwallex = (env, locale, callback) => {
	const initAirwallexInterval = setInterval(() => {
		if (window.Airwallex) {
			clearInterval(initAirwallexInterval);
			Airwallex.init({
				env,
				locale,
				origin: window.location.origin,
			});
			callback();
		}
	}, 1000);
};
