<?php

define('TESTS_ROOT_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(TESTS_ROOT_DIR));

define('ABSPATH', ROOT_DIR);
define('AIRWALLEX_PLUGIN_PATH', ROOT_DIR . '/');
define('AIRWALLEX_PLUGIN_NAME', 'airwallex-online-payments-gateway');
define('AUTH_KEY', 'k|eFIw~Ng+9@Yu(4{&D7I?YVu r[Rd1K3n;jB9WeQwNNRR7ha+COU]eH*7Wd;bH;');

require_once ROOT_DIR . '/vendor/autoload.php';
require_once TESTS_ROOT_DIR . '/phpunit/Stubs/WC_Payment_Gateway.php';
