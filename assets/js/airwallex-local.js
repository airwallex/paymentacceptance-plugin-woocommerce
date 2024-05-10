const AirwallexClient = {
	getCustomerInformation: function (fieldId, parameterName) {
		const $inputField = jQuery('#' + fieldId);
		if ($inputField.length) {
			return $inputField.val().toString().trim();
		} else if (typeof awxCommonData[parameterName] !== 'undefined') {
			return awxCommonData[parameterName].trim();
		} else {
			return '';
		}
	},
	getCardHolderName: function () {
		return String(AirwallexClient.getCustomerInformation('billing_first_name', 'billingFirstName') + ' ' + AirwallexClient.getCustomerInformation('billing_last_name', 'billingLastName')).trim();
	},
	getBillingInformation: function () {
		return {
			address: {
				city: AirwallexClient.getCustomerInformation('billing_city', 'billingCity'),
				country_code: AirwallexClient.getCustomerInformation('billing_country', 'billingCountry'),
				postcode: AirwallexClient.getCustomerInformation('billing_postcode', 'billingPostcode'),
				state: AirwallexClient.getCustomerInformation('billing_state', 'billingState'),
				street: String(AirwallexClient.getCustomerInformation('billing_address_1', 'billingAddress1') + ' ' + AirwallexClient.getCustomerInformation('billing_address_2', 'billingAddress2')).trim(),
			},
			first_name: AirwallexClient.getCustomerInformation('billing_first_name', 'billingFirstName'),
			last_name: AirwallexClient.getCustomerInformation('billing_last_name', 'billingLastName'),
			email: AirwallexClient.getCustomerInformation('billing_email', 'billingEmail'),
		}
	},
	ajaxGet: function (url, callback) {
		const xmlhttp              = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
				try {
					var data = JSON.parse(xmlhttp.responseText);
				} catch (err) {
					console.log(err.message + " in " + xmlhttp.responseText);
					return;
				}
				callback(data);
			}
		};
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	},
	displayCheckoutError: function (form, msg) {
		const checkout_form = jQuery(form);
		jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
		checkout_form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error"><li>' + msg + '</li></ul></div>');
		checkout_form.removeClass('processing').unblock();
		checkout_form.find('.input-text, select, input:checkbox').trigger('validate').blur();
		var scrollElement = jQuery('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');

		if (!scrollElement.length) {
			scrollElement = checkout_form;
		}
		if (typeof jQuery.scroll_to_notices === 'function') {
			jQuery.scroll_to_notices(scrollElement);
		}
	}
};

// hide the express checkout gateway in the payment options
jQuery(document.body).on('updated_checkout', function () {
	jQuery('.payment_method_airwallex_express_checkout').hide();
});
