<?php

namespace service;

use db\SqlMapper;
use helper\Image;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class SkuImage
{
    function __construct()
    {
        \Base::instance()->set('CACHE', true);
        Settings::setCache(new SpreadSheetCache());
    }

    function exec(string $name, array $skus)
    {
        $imgWidth = 50;
        $imgPadding = 5;
        $helper = new Image();
        $excel = new Spreadsheet();
        $sheet = $excel->getSheet(0);
        $product = new SqlMapper('prototype');
        foreach ($skus as $i => $sku) {
            writeLog("Get image for: $sku");
            $row = $i + 1;
            $sheet->setCellValue('A' . $row, $sku);
            $product->load(['model=?', $sku]);
            $images = explode(',', $product['images']);
            $drawing = new Drawing();
            $drawing->setPath($helper->download($images[0], $imgWidth))
                ->setWorksheet($sheet)
                ->setCoordinates('B' . $row)
                ->setOffsetX($imgPadding)
                ->setOffsetY($imgPadding);
            $sheet->getRowDimension($row)
                ->setRowHeight(($imgWidth + 2 * $imgPadding) * 0.75);
        }
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setWidth(SharedDrawing::pixelsToCellDimension($imgWidth + 2 * $imgPadding, new Font()));
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $writer->save($name);
        chown($name, 'www-data');
    }
}
