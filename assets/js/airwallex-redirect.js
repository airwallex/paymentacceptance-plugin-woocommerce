import { initAirwallex } from "./utils";

/** global awxCommonData, awxRedirectElData */
jQuery(function ($) {
    [].forEach.call(document.querySelectorAll('.elementor-menu-cart__container'), function (el) {
        el.style.visibility = 'hidden';
    });
    
    const createElement = () => {
        const {
            elementType,
            elementOptions,
            containerId,
            orderId,
            paymentIntentId,
        } = awxRedirectElData;
        const { confirmationUrl } = awxCommonData;
        const element = Airwallex.createElement(elementType, elementOptions);
        let domElement = element.mount(containerId);
        const waitElementInterval = setInterval(function () {
            if (document.getElementById(containerId) && !document.querySelector(`#${containerId} iframe`)) {
                try {
                    domElement = element.mount(containerId);
                } catch(e) {
                    console.warn(e);
                }
            } else if (document.getElementById(containerId) && document.querySelector(`#${containerId} iframe`)) {
                clearInterval(waitElementInterval);
            }
        }, 3000);
        window.addEventListener('onSuccess', (event) => {
            if (event.target.id !== containerId) {
                return;
            }
            document.getElementById(containerId).style.display = 'none';
            document.getElementById('airwallex-error-message').style.display = 'none';
            var successCheck = document.getElementById('success-check');
            if (successCheck) {
                successCheck.style.display = 'inline-block';
            }
            var successMessage = document.getElementById('success-message');
            if (successMessage) {
                successMessage.style.display = 'block';
            }
            location.href = `${confirmationUrl}order_id=${orderId}&intent_id=${paymentIntentId}`;
        });

        window.addEventListener('onError', (event) => {
            document.getElementById('airwallex-error-message').style.display = 'block';
            console.warn(event.detail);
        });
    };
    
    if (awxCommonData) {
        const { env, locale } = awxCommonData;
        initAirwallex(env, locale, createElement);
    }
});
