<?php

namespace Airwallex\Services;

class LogService{
    private $logDir;

    public function __construct(){
        if(defined('WC_LOG_DIR')){
            $this->logDir = WC_LOG_DIR;
        }else{
            $this->logDir = ABSPATH.'wc-content/uploads/';
        }
    }

    private function getLogFile($level){
        return $this->logDir . 'airwallex-'.$level.'-'.date('Y-m-d').'_'.md5(get_option('airwallex_api_key')).'.log';
    }

    public function log($message, $level = 'debug', $data = null){
        file_put_contents($this->getLogFile($level), '['.date('Y-m-d H:i:s').'] '.$message.' | '.serialize($data)."\n", 8);
    }

    public function debug($message, $data = null){
        $this->log($message, 'debug', $data);
    }

    public function warning($message, $data = null){
        $this->log('⚠ '.$message, 'debug', $data);
        $this->log($message, 'warning', $data);
    }
    public function error($message, $data = null){
        $this->log('💣 '.$message, 'debug', $data);
        $this->log($message, 'error', $data);
    }

}
