<?php

namespace Airwallex;

use Exception;

class LoggingClient extends AbstractClient
{
    const LOG_SEVERITY_INFO = 'info';
    const LOG_SEVERITY_WARNING = 'warn';
    const LOG_SEVERITY_ERROR = 'error';

    public function __construct($clientId, $apiKey, $isSandbox)
    {
        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->isSandbox = $isSandbox;
    }

    protected function getSessionId()
    {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    public function log($severity, $eventName, $message, $details = [])
    {
        $data = [
            'commonData' => [
                'appName' => 'pa_plugin',
                'source' => 'woo_commerce',
                'deviceId' => 'unknown',
                'sessionId' => $this->getSessionId(),
                'appVersion' => AIRWALLEX_VERSION,
                'platform' => $this->getClientPlatform(),
                'env' => $this->isSandbox ? 'demo' : 'prod',
            ],
            'data' => [
                [
                    'severity' => $severity,
                    'eventName' => $eventName,
                    'message' => $message,
                    'details' => json_encode($details),
                    'trace' => json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)),
                ],
            ],
        ];
        try {
            $client = $this->getHttpClient();
            $client->call(
                'POST',
                $this->getLogUrl('papluginlogs/logs'),
                json_encode($data),
                [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                ],
                null,
                true
            );
        }catch (Exception $e){
            //silent
        }
    }
    protected function getClientPlatform(){
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($userAgent, 'Linux') !== false) {
            return 'linux';
        }else if (strpos($userAgent, 'Android') !== false) {
            return 'android';
        }else if (strpos($userAgent, 'Windows') !== false) {
            return 'windows';
        }elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            return 'ios';
        }elseif (strpos($userAgent, 'Macintosh') !== false || strpos($userAgent, 'Mac OS X') !== false) {
            return 'macos';
        }else {
            return 'other';
        }
    }
}
