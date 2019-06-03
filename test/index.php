<?php

define('ROOT', dirname(__DIR__));

require_once ROOT . '/vendor/autoload.php';

call_user_func(function (Base $f3) {
    $f3->mset([
        'AUTOLOAD' => ROOT . '/src/',
        'LOGS' => ROOT . '/runtime/logs/',
    ]);
    $f3->config(ROOT . '/cfg/system.ini,' . ROOT . '/cfg/debug.ini');
}, Base::instance());

function logging($log)
{
    if (is_scalar($log)) {
        echo $log, PHP_EOL;
        $logger = new Log(date('Y-m-d.\l\o\g'));
        $logger->write($log);
    } else {
        print_r($log);
    }
}
