<?php

namespace service;

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class Zalando
{
    function franceReceipt(string $save, string $input)
    {
        try {
            $results = [];
            if (is_file($input)) {
                writeLog('read file: ' . $input);
                $reader = new \SpreadsheetReader($input);
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

                $archive = new ZipArchive();
                $archive->open($save, ZipArchive::CREATE);
                foreach ($results as $result) {
                    $archive->addFile($result);
                    $archive->renameName($result, pathinfo($result, PATHINFO_BASENAME));
                }
                $archive->close();
            } else {
                writeLog('file not found: ' . $input);
            }
        } catch (\Exception $e) {
            writeLog($e->getCode());
            writeLog($e->getMessage());
            writeLog($e->getTraceAsString());
        }
    }
}
