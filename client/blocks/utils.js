export const getCardHolderName = (billingData) => {
	return billingData.first_name.concat(' ', billingData.last_name).trim();
}

export const getBillingInformation = (billingData) => {
	return {
		address: {
			city: billingData.city,
			country_code: billingData.country,
			postcode: billingData.postcode,
			state: billingData.state,
			street: billingData.address_1.concat(' ', billingData.address_2).trim(),
		},
		first_name: billingData.first_name,
		last_name: billingData.last_name,
		email: billingData.email,
	};
}

export const getReplacedText = function(template, values) {
	for (const key in values) {
		template = template.replace(key, values[key]);
	}

	return template;
}
