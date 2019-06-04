<?php

namespace test\service;

use PHPUnit\Framework\TestCase;
use service\StockUp;

class StockUpTest extends TestCase
{
    function testCalcSku()
    {
        $stockUp = new StockUp();
        $stockUp->save('/tmp/stockup.xlsx', [
            $stockUp->calcSku('xy624'),
            $stockUp->calcSku('p8604a'),
            $stockUp->calcSku('p8406a')
        ]);
        $this->assertTrue(true);
    }
}
