<?php

namespace service;

use db\SqlMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Track
{
    function __construct()
    {
        \Base::instance()->set('CACHE', true);
        Settings::setCache(new SpreadSheetCache());
    }

    function exec(string $name, array $data)
    {
        $excel = new Spreadsheet();
        $sheet = $excel->getSheet(0);
        $mapper = new SqlMapper('order_item');
        foreach ($data as $i => $line) {
            writeLog("Trace id: $line");
            $row = $i + 1;
            $sheet->setCellValue('A' . $row, $line);
            $mapper->load(['trace_id=?', $line]);
            if ($mapper->dry()) {
                $sheet->fromArray([[$line]], "A$row");
            } else {
                $sheet->fromArray([[
                    $line,
                    $mapper['channel'],
                    $mapper['sku'],
                    $mapper['size'],
                ]], "A$row");
            }
        }
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $writer->save($name);
        chown($name, 'www-data');
        writeLog("Finish track task: $name");
    }
}
