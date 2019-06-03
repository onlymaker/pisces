<?php

use app\Rabbit;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

define('ROOT', __DIR__);

require_once ROOT. '/vendor/autoload.php';

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

set_exception_handler(function (Throwable $t) {
    Rabbit::disconnect();
    logging($t);
});

$channel = Rabbit::getChannel();
$connection = Rabbit::getConnection();

function shutdown(AMQPChannel $channel, AbstractConnection $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

function messageHandler(AMQPMessage $message)
{
    $body = $message->body;
    $deliveryInfo = $message->delivery_info;
    $deliveryTag = $deliveryInfo['delivery_tag'];
    logging("receiving message $deliveryTag: $body");
    $data = json_decode($body);
    if (json_last_error()) {
        logging('json_decode error: ' . json_last_error_msg());
    } else {
        logging($data);
    }
    $deliveryInfo['channel']->basic_ack($deliveryTag);
}

Rabbit::consume('queue', 'tag', 'messageHandler');

logging("start and register consumer ok");

while (count($channel->callbacks)) {
    try {
        $channel->wait();
    } catch (ErrorException $e) {
        logging($e);
    }
}
