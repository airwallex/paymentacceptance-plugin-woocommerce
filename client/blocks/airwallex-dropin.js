import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';

const settings = getSetting('airwallex_main_data', {});

const title              = settings?.title ?? __('Pay with cards and more', 'airwallex-online-payments-gateway');
const description        = settings?.description ?? '';
const logos              = settings?.icons ?? {};
const maxInlineLogoCount = 5;

const AirwallexLabelDropIn       = (props) => {
	const { PaymentMethodLabel } = props.components;

	return Object.keys(logos).length <= maxInlineLogoCount ? (
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
	) : (
		<PaymentMethodLabel text ={title} />
	);
}

const AirwallexContentDropIn     = (props) => {
	return Object.keys(logos).length > maxInlineLogoCount ? (
		<>
			<div className       ='airwallex-logo-list' style={{ display: 'flex' }}>
				{Object.entries(logos).map(([name, src]) => {
					return (
						<img key ={name} src={src} alt={title} className='airwallex-card-icon' />
					);
				})}
			</div>
			<div>{description}</div>
		</>
	) : (
		<>
			<div>{description}</div>
		</>
	);
};

const canMakePayment = () => {
	return settings?.enabled ?? false;
}

export const airwallexDropInOption = {
	name: settings?.name ?? 'airwallex_main',
	label: <AirwallexLabelDropIn />,
	content: <AirwallexContentDropIn />,
	edit: <AirwallexContentDropIn />,
	canMakePayment: canMakePayment,
	ariaLabel: title,
	supports: {
		features: settings?.supports ?? [],
	}
};
