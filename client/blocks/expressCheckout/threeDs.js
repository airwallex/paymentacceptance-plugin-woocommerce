import {
	getGatewayUrl,
	maskPageWhileLoading,
	removePageMask
} from './utils.js';

const threeDsFrictionlessType = 'threeDsFrictionless';
const threeDsChallengeType    = 'threeDsChallenge';
const loadingType             = 'threeDsLoading';
const popupBgStyle            = `
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 0%;
  z-index: 10000;
  overflow: hidden;
  background: rgba(84, 84, 94, 0.3);
`;

let frictionlessIframe, challengeIframe;
let challengeDiv  = null,loadingDiv = null;
let customizeLang = '';

/**
 *
 * @param {Object} nextActionData 
 * @returns {boolean}
 */
export const is3dsFlow = (nextActionData) => {
	return ['redirect', 'redirect_form'].includes(nextActionData?.type || '');
};

/**
 *
 * @param {Object} request 
 */
export function checkoutResponseFlow(confirmData, env, lang, confirmationUrl) {
	if (confirmData?.nextAction && is3dsFlow(confirmData?.nextAction)) {
		const gatewayUrl = getGatewayUrl(env);
		customizeLang = lang;
		showLoading(gatewayUrl);
		add3DSEventHandler(confirmationUrl);

		switch (confirmData.nextAction.stage) {
			case "WAITING_DEVICE_DATA_COLLECTION": {
				createIframe(confirmData, threeDsFrictionlessType);
				break;
			}
			case "WAITING_USER_INFO_INPUT": {
				createIframe(confirmData, threeDsChallengeType);
				break;
			}
		}
	} else {
		maskPageWhileLoading();
		window.location.href = confirmationUrl;
	}
}

const setPopupStyle = (iframe) => {
    iframe.setAttribute(
        'style',
        `
      background: white;
      width: 100%;
      height: 100%;
      position: 'absolute';
      top: '50%';
      left: '50%';
      transform: 'translate(-50%, -50%)'};
      border-radius: '4px';
      `,
    );
};

const showLoading = (gatewayUrl) => {
    setTimeout(() => {
        if (loadingDiv) return;
        const loadingIframe = document.createElement('iframe');
        loadingIframe.setAttribute('src', `${gatewayUrl}/#/elements/loading?lang=${customizeLang}`);
        loadingIframe.setAttribute('frameborder', '0');
		setPopupStyle(loadingIframe);
        loadingDiv = document.createElement('div');
        loadingDiv.setAttribute('id', loadingType);
        loadingDiv.setAttribute('style', popupBgStyle);
        loadingDiv.style.height = '100%';
        loadingDiv.appendChild(loadingIframe);
        document.body.appendChild(loadingDiv);
    }, 4);
};

const hideLoading = () => {
    if (loadingDiv) {
        loadingDiv.remove();
        loadingDiv = null;
    }
};

const simulateHiddenFormSubmit = (document, data, url) => {
	const formEle = document.createElement('form');
	formEle.setAttribute('method', 'post');
	formEle.setAttribute('style', 'height: 0; overflow: hidden;');
	formEle.action = url;
	Object.keys(data).forEach((key) => {
		const inputEle = document.createElement('input');
		inputEle.type = 'hidden';
		inputEle.name = key;
		inputEle.value = data[key];
		formEle.appendChild(inputEle);
	});
	const submitBtn = document.createElement('input');
	submitBtn.type = 'submit';
	formEle.appendChild(submitBtn);
	document.body.appendChild(formEle);
	submitBtn.click();
};

const createIframe = (confirmData, type) => {
	let iframe = frictionlessIframe || challengeIframe;
	let html = '';
	if (type === threeDsFrictionlessType) {
		frictionlessIframe = document.createElement('iframe');
		iframe = frictionlessIframe;
		html = '<body>Frictionless loading...</body>';
		iframe.setAttribute('name', 'frictionless iframe');
		document.body.appendChild(iframe);
	} else if (type === threeDsChallengeType) {
		challengeIframe = document.createElement('iframe');
		iframe = challengeIframe;
		html = '<body>Challenge loading...</body>';
		iframe.setAttribute('name', 'challenge iframe');

        challengeDiv = document.createElement('div');
        challengeDiv.setAttribute('id', threeDsChallengeType);
        challengeDiv.setAttribute('style', popupBgStyle);
        challengeDiv.style.height = '100%';
        challengeDiv.appendChild(iframe);
        document.body.appendChild(challengeDiv);
	}
	iframe.contentWindow.document.open();
	iframe.contentWindow.document.write(html);
	setPopupStyle(iframe);

	simulateHiddenFormSubmit(iframe.contentWindow.document, confirmData.nextAction.data, confirmData.nextAction.url);
}

const add3DSEventHandler = (confirmationUrl) => {
	window.addEventListener('message', (message) => {
		switch (message.data.type) {
			case '3dsSuccess': {
				frictionlessIframe?.parentNode?.removeChild(frictionlessIframe);
				challengeIframe?.parentNode?.removeChild(challengeIframe);
				hideLoading();
				maskPageWhileLoading();
				setTimeout(function () {
					//Unblock UI
					removePageMask();
				}, 5000);
				window.location.href = confirmationUrl;
				break;
			}
			case '3dsFailure': {
				frictionlessIframe?.parentNode?.removeChild(frictionlessIframe);
				challengeIframe?.parentNode?.removeChild(challengeIframe);
				hideLoading();
				break;
			}
			case '3dsChallenge': {
				createIframe(message.data.data, threeDsChallengeType);
				break;
			}
		}
	});
}
