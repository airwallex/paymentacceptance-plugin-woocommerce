import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import {
    AirwallexLpmLabel,
    AirwallexLpmContent,
    AirwallexLpmContentAdmin,
} from './elements';

const settings = getSetting('airwallex_klarna_data', {});
const icon = settings.icon ?? {};

const title       = settings?.title ?? __('Klarna', 'airwallex-online-payments-gateway');
const description = settings?.description ?? '';

const canMakePayment = () => {
	return settings?.enabled ?? false;
}

export const airwallexKlarnaOption = {
	name: settings?.name ?? 'airwallex_klarna',
	label: <AirwallexLpmLabel
        title={title}
        icon={icon}
    />,
	content: <AirwallexLpmContent
        settings={settings}
        description={description}
    />,
	edit: <AirwallexLpmContentAdmin
        description={description}
    />,
	canMakePayment: canMakePayment,
	ariaLabel: title,
	supports: {
		features: settings?.supports ?? [],
	}
};
