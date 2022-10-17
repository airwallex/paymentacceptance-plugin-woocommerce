<?php

namespace Airwallex;

class AdminClient extends AbstractClient
{
    public function __construct($clientId, $apiKey, $isSandbox)
    {
        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->isSandbox = $isSandbox;
    }

    public function getMerchantCountry()
    {
        $account = $this->getAccount();
        if (!empty($account['account_details']['business_details']['address']['country_code'])) {
            return $account['account_details']['business_details']['address']['country_code'];
        }

        return null;
    }
}
