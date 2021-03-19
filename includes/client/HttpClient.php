<?php

namespace Airwallex\Client;

use Exception;

class HttpClient
{
    private $lastCallInfo = null;

    /**
     * @param $url
     * @return resource
     */
    private function getCurlResource($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        return $ch;
    }

    /**
     * @param $method
     * @param $url
     * @param $data
     * @param $headers
     * @return bool|string
     * @throws Exception
     */
    private function httpSend($method, $url, $data, $headers)
    {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'x-api-version: 2020-04-30';
        $ch        = $this->getCurlResource($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $this->execute($ch);
    }

    /**
     * @param $ch
     * @return bool|string
     * @throws Exception
     */
    private function execute($ch)
    {
        $response = curl_exec($ch);
        if ($response === false) {
            $errorMsg = "CURL error: " . curl_error($ch);
            curl_close($ch);
            throw new Exception($errorMsg);
        } else {
            $this->lastCallInfo = curl_getinfo($ch);
        }
        curl_close($ch);
        return $response;
    }

    /**
     * @param $method
     * @param $url
     * @param $data
     * @param $headers
     * @return Response
     * @throws Exception
     */
    public function call($method, $url, $data, $headers)
    {
        $startTime = microtime(true);

        $rawResponse = $this->httpSend($method, $url, $data, $headers);
        if (!($responseData = json_decode($rawResponse, true))) {
            throw new Exception('API response invalid');
        }
        $response              = new Response();
        $response->data        = $responseData;
        $response->status      = $this->lastCallInfo["http_code"];
        $response->time        = round(microtime(true) - $startTime, 3);
        $response->requestData = $data;
        $response->requestUrl  = $url;

        return $response;
    }
}
