<?php

namespace Airwallex\Services;

use Airwallex\LoggingClient;

class LogService
{
    const CARD_ELEMENT_TYPE = 'cardElement';
    const DROP_IN_ELEMENT_TYPE = 'dropInElement';
    const WECHAT_ELEMENT_TYPE = 'wechatElement';
    private $logDir;
    private $loggingClient;

    public function __construct()
    {
        if (defined('WC_LOG_DIR')) {
            $this->logDir = WC_LOG_DIR;
        } else {
            $this->logDir = ABSPATH . 'wc-content/uploads/';
        }
    }

    private function getLogFile($level)
    {
        return $this->logDir . 'airwallex-' . $level . '-' . date('Y-m-d') . '_' . md5(get_option('airwallex_api_key')) . '.log';
    }

    public function log($message, $level = 'debug', $data = null)
    {
        file_put_contents($this->getLogFile($level), '[' . date('Y-m-d H:i:s') . '] ' . $message . ' | ' . serialize($data) . "\n", 8);
    }

    public function debug($message, $data = null, $type = 'unknown')
    {
        $this->log($message, 'debug', $data);
        $this->getLoggingClient()->log(LoggingClient::LOG_SEVERITY_INFO, 'wp_info', $message, $data, $type);
    }

    public function warning($message, $data = null, $type = 'unknown')
    {
        $this->log('⚠ ' . $message, 'debug', $data);
        $this->log($message, 'warning', $data);
        $this->getLoggingClient()->log(LoggingClient::LOG_SEVERITY_WARNING, 'wp_warning', $message, $data, $type);
    }

    public function error($message, $data = null, $type = 'unknown')
    {
        $this->log('💣 ' . $message, 'debug', $data);
        $this->log($message, 'error', $data);
        $this->getLoggingClient()->log(LoggingClient::LOG_SEVERITY_ERROR, 'wp_error', $message, $data, $type);
    }

    protected function getLoggingClient(){
        if(!isset($this->loggingClient)){
            $this->loggingClient = new LoggingClient(get_option('airwallex_client_id'), get_option('airwallex_api_key'), in_array(get_option('airwallex_enable_sandbox'), [true, 'yes'], true));
        }
        return $this->loggingClient;
    }
}
