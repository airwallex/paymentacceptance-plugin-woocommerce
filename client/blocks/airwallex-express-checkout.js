import { __ } from '@wordpress/i18n';

const title       = __('Express Checkout', 'airwallex-online-payments-gateway');
const description = '';

const AirwallexLabel             = (props) => {
	const { PaymentMethodLabel } = props.components;

	return <PaymentMethodLabel text ={title} />;
}

const AirwallexContent = (props) => {
	return <div>{description}</div>;
};

const canMakePayment = () => {
	return false;
}

export const airwallexExpressCheckoutOption = {
	name: 'airwallex_express_checkout',
	label: <AirwallexLabel />,
	content: <AirwallexContent />,
	edit: <AirwallexContent />,
	canMakePayment: canMakePayment,
	ariaLabel: title,
	supports: {
		features: [],
	}
};
