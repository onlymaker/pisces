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
        $reader = new \SpreadsheetReader(ROOT . '/resources/template.xlsx');
        $reader->ChangeSheet(0);
        $headers = $reader->current();

        Settings::setCompatibility(false);

        $reader->next();
        while ($reader->valid()) {
            $template = new TemplateProcessor(ROOT . '/resources/template.docx');
            $data = $reader->current();
            foreach ($headers as $k => $v) {
                $template->setValue($v, $data[$k]);
            }
            $result = '/tmp/zalando_france_' . $reader->key() . '.docx';
            writeLog('Saving ' . $result);
            $template->saveAs($result);
            $results[] = $result;
            $reader->next();
        }
        $this->assertTrue(true);
    }
}
