/* global awxAdminSettings, awxAdminECSettings */
jQuery(function ($) {
	'use strict';

	const googlePayJSLib       = 'https://pay.google.com/gp/p/js/pay.js';
	const applePayJSLib        = 'https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-sdk.js';
	const awxGoogleBaseRequest = {
		apiVersion: 2,
		apiVersionMinor: 0
	};

	const awxGoogleAllowedCardNetworks    = ["MASTERCARD", "VISA"];
	const awxGoogleAllowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];
	const awxGoogleBaseCardPaymentMethod  = {
		type: 'CARD',
		parameters: {
			allowedAuthMethods: awxGoogleAllowedCardAuthMethods,
			allowedCardNetworks: awxGoogleAllowedCardNetworks,
		}
	};

	const airwallexExpressCheckoutSettings = {
		googlePaymentsClient: null,

		init: function () {
			if (!awxAdminECSettings) {
				return;
			}

			this.registerCustomizeEventListener();

			const appleScript  = document.createElement('script');
			appleScript.src    = applePayJSLib;
			appleScript.async  = true;
			appleScript.onload = () => {
				if (window.ApplePaySession) {
					$('.awx-apple-pay-btn').show();
				}
			};
			document.body.appendChild(appleScript);

			const googleScript  = document.createElement('script');
			googleScript.src    = googlePayJSLib;
			googleScript.async  = true;
			googleScript.onload = () => {
				airwallexExpressCheckoutSettings.onGooglePayLoaded();
				$('.awx-google-pay-btn').show();
			};
			document.body.appendChild(googleScript);
		},

		registerCustomizeEventListener: function() {
			$('#airwallex-online-payments-gatewayairwallex_express_checkout_call_to_action').change(function() {
				awxAdminECSettings.buttonType = this.value;
				airwallexExpressCheckoutSettings.reloadGooglePayButton();
				airwallexExpressCheckoutSettings.reloadApplePayButton();
				airwallexExpressCheckoutSettings.setButtonHeight();
			});

			$('#airwallex-online-payments-gatewayairwallex_express_checkout_appearance_size').change(function() {
				awxAdminECSettings.size = this.value;
				airwallexExpressCheckoutSettings.setButtonHeight();
			});

			$('#airwallex-online-payments-gatewayairwallex_express_checkout_appearance_theme').change(function() {
				awxAdminECSettings.theme = this.value;
				airwallexExpressCheckoutSettings.reloadGooglePayButton();
				airwallexExpressCheckoutSettings.reloadApplePayButton();
				airwallexExpressCheckoutSettings.setButtonHeight();
			});
		},

		setButtonHeight: function() {
			const height = awxAdminECSettings.sizeMap[awxAdminECSettings.size];
			$('.awx-apple-pay-btn apple-pay-button').css('--apple-pay-button-height', height);
			$('.awx-google-pay-btn button').css('height', height);
		},

		reloadApplePayButton: function() {
			$('.awx-apple-pay-btn').empty();
			$('.awx-apple-pay-btn').append(
				$('<apple-pay-button>').attr('locale', awxAdminECSettings.locale)
					.attr('buttonstyle', awxAdminECSettings.theme)
					.attr('type', awxAdminECSettings.buttonType)
			);
		},

		reloadGooglePayButton: function() {
			$('.awx-google-pay-btn').empty();
			this.addGooglePayButton();
		},

		addGooglePayButton: function() {
			const client = this.getGooglePaymentsClient();
			const button = client.createButton({
				buttonColor: awxAdminECSettings.theme,
				buttonType: awxAdminECSettings.buttonType,
				buttonSizeMode: 'fill',
				onClick: () => {},
			});
			$('.awx-google-pay-btn').append(button);
		},

		getGooglePaymentsClient: function() {
			if ( this.googlePaymentsClient === null ) {
				this.googlePaymentsClient = new google.payments.api.PaymentsClient({
					environment: "TEST",
				});
			}

			return this.googlePaymentsClient;
		
		},

		getGoogleIsReadyToPayRequest: function() {
			return Object.assign(
				{},
				awxGoogleBaseRequest,
				{
					allowedPaymentMethods: [awxGoogleBaseCardPaymentMethod]
				}
			);
		},

		onGooglePayLoaded: function() {
			const client = this.getGooglePaymentsClient();
			client.isReadyToPay(this.getGoogleIsReadyToPayRequest())
				.then(function(response) {
					if (response.result) {
						airwallexExpressCheckoutSettings.reloadGooglePayButton();
						airwallexExpressCheckoutSettings.setButtonHeight();
					}
				})
				.catch(function(err) {
					console.error(err);
				});
		},
	};

	airwallexExpressCheckoutSettings.init();

	$('.wc-airwallex-connection-test').on('click', function(e) {
		e.preventDefault();
		$.ajax({
			type: 'POST',
			data: {
				security: awxAdminSettings.apiSettings.nonce.connectionTest,
				client_id: $('#airwallex-online-payments-gatewayairwallex_general_client_id').val(),
				api_key: $('#airwallex-online-payments-gatewayairwallex_general_api_key').val(),
				is_sandbox: $('#airwallex-online-payments-gatewayairwallex_general_enable_sandbox').is(':checked') ? 'checked' : '',
			},
			url: awxAdminSettings.apiSettings.ajaxUrl.connectionTest,
		}).done(function (response) {
			window.alert(response.message);
            if (response.success) {
                $('.wc-airwallex-connection-test').closest('tr').hide();
                $('#awx-account-not-connected').hide();
				$('#awx-account-connected').show();
            } else {
                $('#awx-account-not-connected').show();
				$('#awx-account-connected').hide();
            }
		}).fail(function (error) {
			console.log(error);
			window.alert(error);
		});
	});

	$('.wc-airwallex-register-apple-pay-domain').on('click', function(e) {
		e.preventDefault();
		$.ajax({
			type: 'POST',
			data: {
				security: awxAdminECSettings.apiSettings.nonce.registerDomain,
				domainName: window.location.host,
			},
			url: awxAdminECSettings.apiSettings.ajaxUrl.registerDomain,
		}).done(function (response) {
			window.alert(response.message);
		}).fail(function (error) {
			console.log(error);
			window.alert(error.responseText);
		});
	});

    if (awxAdminSettings && awxAdminSettings.apiSettings.connected) {
        $('.wc-airwallex-connection-test').closest('tr').hide();
        $('#awx-account-not-connected').hide();
		$('#awx-account-connected').show();
    }

	$('.wc-airwallex-client-id, .wc-airwallex-api-key, .wc-airwallex-sandbox').on('change', function() {
        $('.wc-airwallex-connection-test').closest('tr').show();
		$('#awx-account-not-connected').hide();
		$('#awx-account-connected').hide();
	});
});
