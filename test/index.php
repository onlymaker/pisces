<?php

define('ROOT', dirname(__DIR__));

require_once ROOT . '/vendor/autoload.php';

function writeLog($log)
{
    if (is_scalar($log)) {
        echo $log, PHP_EOL;
        $logger = new Log(date('Y-m-d.\l\o\g'));
        $logger->write($log);
    } else {
        print_r($log);
    }
}

$f3 = Base::instance();
$f3->mset([
    'AUTOLOAD' => ROOT . '/src/',
    'LOGS' => ROOT . '/runtime/logs/',
    'ONERROR' => 'writeLog',
]);
$f3->config(ROOT . '/cfg/system.ini,' . ROOT . '/cfg/debug.ini');
