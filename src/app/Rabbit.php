<?php

namespace app;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Rabbit
{
    const type = 'topic';
    const exchange = 'oms';
    const routeKey = 'task';

    private static $connection;
    private static $channel;

    static function getConnection()
    {
        if (empty(self::$connection)) {
            $f3 = \Base::instance();
            self::$connection = new AMQPStreamConnection(
                $f3->get('RMQ_HOST'),
                $f3->get('RMQ_PORT'),
                $f3->get('RMQ_USER'),
                $f3->get('RMQ_PASS')
            );
        }
        return self::$connection;
    }

    static function getChannel()
    {
        if (empty(self::$channel)) {
            self::$channel = self::getConnection()->channel();
        }
        return self::$channel;
    }

    static function send(string $message)
    {
        // declare an exchange to publish messages
        $channel = self::getChannel();
        $channel->exchange_declare(Rabbit::exchange, self::type, false, false, false);
        $channel->basic_publish(new AMQPMessage($message), Rabbit::exchange, Rabbit::routeKey);
    }

    static function consume(callable $callback)
    {
        // declare a queue to consume from
        $channel = self::getChannel();
        $channel->exchange_declare(Rabbit::exchange, self::type, false, false, false);
        $channel->queue_declare('', false, false, true, false);
        $channel->queue_bind('', Rabbit::exchange, Rabbit::routeKey);
        $channel->basic_consume('', '', false, false, true, false, $callback);
    }

    static function disconnect()
    {
        if (self::$channel) {
            self::$channel->close();
        }
        if (self::$connection) {
            self::$connection->close();
        }
    }
}
