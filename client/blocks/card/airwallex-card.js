import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import { InlineCard } from './elements.js';

const settings = getSetting('airwallex_card_data', {});

const title       = settings?.title ?? __('Credit Card', 'airwallex-online-payments-gateway');
const description = settings?.description ?? __('Pay with your credit card via Airwallex', 'airwallex-online-payments-gateway');
const logos       = settings?.icons ?? {};

const AirwallexLabelCard         = (props) => {
	const { PaymentMethodLabel } = props.components;

	return (
		<>
			<PaymentMethodLabel text ={title} />
			<span style              ={{ marginLeft: 'auto', display: 'flex', direction: 'rtl' }}>
				{Object.entries(logos).map(([name, src]) => {
					return (
						<img key     ={name} src={src} alt={title} className='airwallex-card-icon' />
					);
				})}
			</span>
		</>
	);
}

const AirwallexContentCard       = (props) => {
	return settings.checkout_form_type == 'inline' ? (
		<>
			<p>{description}</p>
			<InlineCard settings ={settings} props={props} />
		</>
	) : (
		<div>{description}</div>
	);
};

const canMakePayment = () => {
	return settings?.enabled ?? false;
}

export const airwallexCardOption = {
	name: settings?.name ?? 'airwallex_card',
	label: <AirwallexLabelCard />,
	content: <AirwallexContentCard />,
	edit: <AirwallexContentCard />,
	canMakePayment: canMakePayment,
	ariaLabel: title,
	supports: {
		features: settings?.supports ?? [],
	}
};
