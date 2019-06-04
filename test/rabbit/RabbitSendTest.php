<?php

namespace test\rabbit;

use app\Rabbit;
use PHPUnit\Framework\TestCase;

class RabbitSendTest extends TestCase
{
    function test()
    {
        Rabbit::send('test');
        Rabbit::send(json_encode(['hello world']));
        $this->assertTrue(true);
    }

    function testStockup()
    {
        Rabbit::send(json_encode([
            'task' => 'stockUp',
            'file' => '/tmp/stock_up_test.xlsx',
            'sku' => 'xy624,p8604a,p8406a',
        ]));
        $this->assertTrue(true);
    }
}
