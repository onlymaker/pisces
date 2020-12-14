<?php

namespace test\service;

use PHPUnit\Framework\TestCase;
use service\SkuImage;

class SkuImageTest extends TestCase
{
    function testSkuImage()
    {
        $target = new SkuImage();
        $target->exec('/tmp/', ['SKU','H161204', 'H161204 8cm']);
        $this->assertTrue(true);
    }
}