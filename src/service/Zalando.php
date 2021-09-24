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
            if (is_file($input)) {
                writeLog('read file: ' . $input);

                $data = [];
                $headers = [];
                $results = [];

                $reader = new \SpreadsheetReader($input);
                $reader->ChangeSheet(0);
                while ($reader->valid()) {
                    $line = $reader->current();
                    if ($headers) {
                        $orderNumber = $line[$headers['order_number']];
                        $table = [
                            'product_sku' => $line[$headers['product_sku']],
                            'product_name' => $line[$headers['product_name']],
                            'quantity' => $line[$headers['quantity']],
                            'unit_price' => $line[$headers['unit_price']],
                            'line_price' => $line[$headers['line_price']],
                        ];
                        if (isset($data[$orderNumber])) {
                            $data[$orderNumber]['table'][] = $table;
                        } else {
                            $line['table'] = [$table];
                            $data[$orderNumber] = $line;
                        }
                    } else {
                        $headers = array_flip($line);
                    }
                    $reader->next();
                }

                unset($headers['product_sku']);
                unset($headers['product_name']);
                unset($headers['quantity']);
                unset($headers['unit_price']);
                unset($headers['line_price']);

                Settings::setCompatibility(false);
                foreach ($data as $k => $v) {
                    $template = new TemplateProcessor(ROOT . '/resources/template.docx');
                    $template->cloneRowAndSetValues('product_sku', $v['table']);
                    unset($v['table']);
                    foreach ($headers as $name => $idx) {
                        $template->setValue($name, $v[$idx]);
                    }
                    $result = '/tmp/' . $k . '_' . bin2hex(random_bytes(2)) . '_invoice.docx';
                    writeLog('Saving ' . $result);
                    $template->saveAs($result);
                    $results[] = $result;
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
