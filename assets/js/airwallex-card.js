import { initAirwallex } from "./utils";

/** global awxCommonData, awxEmbeddedCardData */
jQuery(function ($) {
    const {
        env,
        locale,
        confirmationUrl,
        isOrderPayPage,
    } = awxCommonData;
    const awxCheckoutForm = isOrderPayPage ? '#order_review' : 'form.checkout';
    const {
        autoCapture,
        errorMessage,
        incompleteMessage,
    } = awxEmbeddedCardData;

    const initCardElement = () => {
        const airwallexSlimCard = Airwallex.createElement('card', {
            autoCapture: autoCapture,
        });
        let domElement = airwallexSlimCard.mount('airwallex-card');
        setInterval(function () {
            if (document.getElementById('airwallex-card') && !document.querySelector('#airwallex-card iframe')) {
                try {
                    domElement = airwallexSlimCard.mount('airwallex-card');
                } catch(e) {
                    console.warn(e);
                }
            }
        }, 1000);

        window.addEventListener('onError', (event) => {
            if (!event.detail) {
                return;
            }
            const { error } = event.detail;
            AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', error.message || ''));
        });

        $('form.checkout').on('checkout_place_order_success', function (ele, result, form) {
            confirmSlimCardPayment(result, airwallexSlimCard);
            return true;
        });

        $(document.body).on('click', '#place_order', function (event) {
            const selectedPaymentMethod = $('[name="payment_method"]:checked').val()
            if (awxCommonData.isOrderPayPage && 'airwallex_card' === selectedPaymentMethod) {
                airwallexCheckoutBlock('#order_review');
                event.preventDefault();
                $.ajax({
                    type: 'POST',
                    data: $("#order_review").serialize(),
                    url: awxCommonData.processOrderPayUrl,
                }).done((response) => {
                    if (response.result === 'success') {
                        if (response.redirect) {
                            location.href = response.redirect;
                        } else {
                            confirmSlimCardPayment(response, airwallexSlimCard);
                        }
                    } else {
                        console.warn(response.error);
                        $('#order_review').unblock();
                        AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', response.error || ''));
                    }
                }).fail((error) => {
                    console.warn(error);
                    $('#order_review').unblock();
                    AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', error.message || ''));
                });
            }
        });
    };

    const airwallexCheckoutBlock = (element) => {
        //timeout necessary because of event order in plugin CheckoutWC
        setTimeout(function () {
            $(element).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }, 50);
    }

    const confirmSlimCardPayment = (data, element) => {
        airwallexCheckoutBlock('form.checkout');

        if (!data || data.error) {
            AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', ''));
        }
        let finalConfirmationUrl = confirmationUrl;
        finalConfirmationUrl += finalConfirmationUrl.includes('?') ? '&' : '?';
        finalConfirmationUrl += 'order_id=' + data.orderId + '&intent_id=' + data.paymentIntent;
        if (data.createConsent) {
            Airwallex.createPaymentConsent({
                intent_id: data.paymentIntent,
                customer_id: data.customerId,
                client_secret: data.clientSecret,
                currency: data.currency,
                element: element,
                next_triggered_by: 'merchant',
                billing: AirwallexClient.getBillingInformation(),
            }).then((response) => {
                location.href = finalConfirmationUrl;
            }).catch(err => {
                console.warn(err);
                $('form.checkout').unblock();
                AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', err.message || ''));
            });
        } else {
            Airwallex.confirmPaymentIntent({
                element: element,
                intent_id: data.paymentIntent,
                client_secret: data.clientSecret,
                payment_method: {
                    card: {
                        name: AirwallexClient.getCardHolderName()
                    },
                    billing: AirwallexClient.getBillingInformation()
                },
            }).then((response) => {
                location.href = finalConfirmationUrl;
            }).catch(err => {
                console.warn(err);
                $('form.checkout').unblock();
                AirwallexClient.displayCheckoutError(awxCheckoutForm, String(errorMessage).replace('%s', err.message || ''));
            })
        }
    }

    if (awxCommonData) {
        initAirwallex(env, locale, initCardElement);
    }
});