<?php

namespace Airwallex\Client;

class AdminClient extends AbstractClient {
	public function getMerchantCountry() {
		$account = $this->getAccount();
		if ( ! empty( $account['account_details']['business_details']['address']['country_code'] ) ) {
			return $account['account_details']['business_details']['address']['country_code'];
		}

		return null;
	}
}
