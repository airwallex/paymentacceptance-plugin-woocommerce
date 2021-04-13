<?php

namespace Airwallex\Client;

use Exception;
use WP_Error;

class HttpClient
{
    private $lastCallInfo = null;



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
        $headers['Content-Type'] = 'application/json';
        $headers['x-api-version'] = '2020-04-30';

        if($method === 'POST'){
            $response = wp_remote_post( $url, array(
                    'method'      => 'POST',
                    'timeout'     => 10,
                    'redirection' => 5,
                    'headers'     => $headers,
                    'body'        => $data,
                    'cookies'     => []
                )
            );
        }else{
            $response = wp_remote_get(
                $url,
                [
                    'headers'=>$headers
                ]
            );

        }
        if(get_class($response) === WP_Error::class){
            throw new Exception($response->get_error_message(), $response->get_error_code());
        }
        $this->lastCallInfo = [
            'http_code'=>wp_remote_retrieve_response_code($response)
        ];
        return wp_remote_retrieve_body($response);
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
