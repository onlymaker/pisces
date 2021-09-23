<?php

namespace test\service;

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\TestCase;

class WordTest extends TestCase
{
    function testTemplate()
    {
        writeLog('test template');
        Settings::setCompatibility(false);
        $template = new TemplateProcessor(ROOT . '/resources/template.docx');
        $template->setValue('customer_name', 'Bo Ji');
        $template->saveAs(ROOT . '/runtime/result.docx');
        $this->assertTrue(true);
    }
}
