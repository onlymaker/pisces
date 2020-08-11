<?php

use app\Rabbit;
use PhpAmqpLib\Message\AMQPMessage;
use service\StockUp;

define('ROOT', __DIR__);

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

function shutdown()
{
    writeLog('Script shutting down ...');
    Rabbit::disconnect();
    sleep(10);
}


$f3 = Base::instance();
$f3->mset([
    'AUTOLOAD' => ROOT . '/src/',
    'LOGS' => ROOT . '/runtime/logs/',
    'HALT' => false,
    'ONERROR' => 'writeLog',
    'UNLOAD' => 'shutdown',
]);
$f3->config(ROOT . '/cfg/system.ini,' . ROOT . '/cfg/debug.ini');

Rabbit::consume(function (AMQPMessage $message) {
    $body = $message->body;
    $deliveryInfo = $message->delivery_info;
    $deliveryTag = $deliveryInfo['delivery_tag'];
    writeLog("Receiving message $deliveryTag: $body");
    $data = json_decode($body, true);
    if (json_last_error()) {
        writeLog('json_decode error: ' . json_last_error_msg());
    } else {
        writeLog($data);
        if ($data['task'] == 'stockUp') {
            $stockUp = new StockUp();
            $stockUp->exec($data['name'], explode(',', $data['data']));
        } else if ($data['task'] == 'stockClear') {
            $file = ROOT . '/runtime/jig/report-' . date('Ymd');
            @unlink($file);
            writeLog("clear report cache $file");
        } else if ($data['task'] == 'testConnection') {
            $smtp = new \SMTP(
                'smtp.exmail.qq.com',
                465,
                'ssl',
                'service@onlymaker.com',
                \Base::instance()->get('EMAIL.SECRET')
            );
            $smtp->set('From', 'service@onlymaker.com');
            $smtp->set('To', '<jibo@onlymaker.com>');
            $smtp->set('Subject', 'Rabbit mq CN');
            writeLog('Send out result: ' . $smtp->send('Connection is OK.'));
        }
    }
    $deliveryInfo['channel']->basic_ack($deliveryTag);
});

writeLog("Start and register consumer ok");

$channel = Rabbit::getChannel();
while (count($channel->callbacks)) {
    $channel->wait();
}
