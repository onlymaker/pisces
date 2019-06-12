<?php

namespace service;

use DB\Jig;
use db\Mysql;
use db\SqlMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class StockUp
{
    function exec(string $file, array $skus)
    {
        $data = [];
        $skus = array_unique($skus);
        foreach ($skus as $sku) {
            $data[] = $this->calcSku($sku);
        }
        if ($this->save($file, $data)) {
            writeLog("Finish: $file");
        } else {
            writeLog("Error: $file");
            unlink($file);
            writeLog($skus);
        }
    }

    function save(string $file, array $data)
    {
        $dir = pathinfo($file, PATHINFO_DIRNAME);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        \Base::instance()->set('CACHE', true);
        Settings::setCache(new SpreadSheetCache());

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('Bo Ji')
            ->setLastModifiedBy('Bo Ji')
            ->setTitle('Good luck for stock up')
            ->setSubject('Office 2007 XLSX Document')
            ->setDescription('Document for Office 2007 XLSX, generated using PHP classes.')
            ->setKeywords('stock up')
            ->setCategory('Stats');

        $sheet = $spreadsheet->setActiveSheetIndex(0)->setTitle(date('Y-m-d'));
        $row = 1;
        $sheet->setCellValue('B' . $row, 'order');
        $sheet->setCellValue('C' . $row, 'latest');
        $sheet->setCellValue('D' . $row, 'inventory');
        $sheet->setCellValue('E' . $row, 'inbound');
        $sheet->setCellValue('F' . $row, 'factory');
        $sheet->setCellValue('G' . $row, 'requirement');
        $row++;

        foreach ($data as $item) {
            if ($item) {
                $thumb = $item['image'];
                unset($item['image']);
                $drawing = new Drawing();
                $drawing->setPath($this->getImage($thumb))
                    ->setWorksheet($sheet)
                    ->setCoordinates('A' . $row)
                    ->setOffsetX(2)
                    ->setOffsetY(2);
                $sku = array_keys($item)[0];
                $sheet->setCellValue('B' . $row, $sku)
                    ->getRowDimension($row)
                    ->setRowHeight(\PhpOffice\PhpSpreadsheet\Shared\Drawing::pixelsToPoints(60));
                $row++;
                $stats = $item[$sku];
                foreach ($stats as $region) {
                    foreach ($region as $size => $sizeStats) {
                        $sheet->setCellValue('A' . $row, $size);
                        // order
                        $sheet->setCellValue('B' . $row, $sizeStats['order']['quantity']);
                        // latest
                        $sheet->setCellValue('C' . $row, $sizeStats['latest']);
                        // inventory
                        $sheet->setCellValue('D' . $row, $content = $sizeStats['inventory']);
                        // inbound
                        $content = '';
                        foreach ($sizeStats['inbound'] as $inbound) {
                            $content .= "{$inbound['quantity']} ({$inbound['name']})\n";
                        }
                        $sheet->setCellValue('E' . $row, $content);
                        $sheet->getCell('E' . $row)->getStyle()->getAlignment()->setWrapText(true);
                        // factory
                        $content = '';
                        foreach ($sizeStats['factory'] as $factory) {
                            $content .= "{$factory['quantity']} ({$factory['name']})\n";
                        }
                        $sheet->setCellValue('F' . $row, $content);
                        $sheet->getCell('F' . $row)->getStyle()->getAlignment()->setWrapText(true);
                        // requirement
                        $sheet->setCellValue('G' . $row, $sizeStats['requirement']);
                        $row++;
                    }
                }
            }
        }
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($file);
        chown($file, 'www-data');
        return true;
    }

    function calcSku($sku)
    {
        $data = [];
        $prototype = new SqlMapper('prototype');
        $prototype->load(['model=?', $sku]);
        if (!$prototype->dry()) {
            $data = $this->getAmazonStats($sku);
            $data['image'] = explode(',', $prototype['images'])[0] . '?imageView2/0/w/50';
            $sql = <<<SQL
select volume_serial, volume_type,
  us_size, us_quantity,
  eu_size, de_quantity,
  uk_size, uk_quantity,
  factory, create_time
from volume_order
where sku = '$sku'
  and status = 0
order by create_time
SQL;
            $query = Mysql::instance()->get()->exec($sql);
            foreach ($query as $item) {
                $factory = $item['factory'];
                $serial = $item['volume_serial'];
                $type = $item['volume_type'];
                if ($item['us_quantity'] > 0) {
                    $this->setFactoryStats($sku, $item['us_size'], $item['us_quantity'], $factory, $serial, $type, $data);
                }
                if ($item['uk_quantity'] > 0) {
                    $this->setFactoryStats($sku, $item['eu_size'], $item['de_quantity'], $factory, $serial, $type, $data);
                }
                if ($item['uk_quantity'] > 0) {
                    $this->setFactoryStats($sku, $item['uk_size'], $item['uk_quantity'], $factory, $serial, $type, $data);
                }
            }
            foreach ($data[$sku] as &$region) {
                foreach ($region as &$stats) {
                    if (!isset($stats['factory'])) {
                        $stats['factory'] = [];
                    }
                    $requirement = $stats['requirement'];
                    foreach ($stats['factory'] as $factoryStats) {
                        $requirement -= $factoryStats['quantity'];
                    }
                    if ($requirement < 0) {
                        $requirement = 0;
                    }
                    $stats['requirement'] = $requirement;
                }
            }
            writeLog("sku [$sku] calc success");
        } else {
            writeLog("sku [$sku] is not found");
        }
        return $data;
    }

    function getAmazonStats($sku)
    {
        $jig = new Jig(ROOT . '/runtime/jig/');
        $cache = new Jig\Mapper($jig, 'report-' . date('Ymd'));
        $cache->load(['@sku=?', $sku]);
        if ($cache->dry()) {
            $options = [
                'method' => 'POST',
                'content' => http_build_query([
                    'sku' => $sku,
                ]),
            ];
            $response = \Web::instance()->request('https://asin.onlymaker.com/Report', $options);
            if ($response['headers'][0] == 'HTTP/1.1 200 OK') {
                $cache['sku'] = $sku;
                $cache['data'] = $response['body'];
                $cache->save();
            }
        }
        return $cache['data'] ? json_decode($cache['data'], true) : [$sku => []];
    }

    function setFactoryStats($sku, $size, $quantity, $factory, $serial, $type, &$data)
    {
        $value = [
            'name' => $serial,
            'factory' => $factory,
            'type' => $type,
            'quantity' => $quantity
        ];
        $prefix = strtoupper(substr($size, 0, 2));
        if (isset($data[$sku][$prefix])) {
            if (isset($data[$sku][$prefix][$size])) {
                if (isset($data[$sku][$prefix][$size]['factory'])) {
                    $data[$sku][$prefix][$size]['factory'][] = $value;
                } else {
                    $data[$sku][$prefix][$size]['factory'] = [$value];
                }
            } else {
                $data[$sku][$prefix][$size] = [
                    'order' => ['name' => 'average', 'quantity' => 0],
                    'inventory' => 0,
                    'inbound' => [],
                    'factory' => [$value],
                ];
            }
        } else {
            writeLog("Warning: $sku with unknown size $size");
        }
    }

    function getImage($imageUrl)
    {
        $meta = parse_url($imageUrl);
        if (empty($meta['path'])) {
            $imageUrl = 'http://qiniu.syncxplus.com/meta/holder.jpg?imageView2/0/w/100';
            $image = '/tmp/holder.jpg';
        } else {
            $image = '/tmp' . $meta['path'];
        }
        if (!is_file($image)) {
            file_put_contents($image, file_get_contents($imageUrl));
        }
        return $image;
    }

    function compare($a, $b)
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $b - $a;
        } else {
            if (strlen($a) == strlen($b)) {
                return strcasecmp($a, $b);
            } else {
                $time = time();
                $timeA = strtotime($a);
                $timeB = strtotime($b);
                if ($timeA) {
                    return $timeA - $time;
                } else if ($timeB) {
                    return $time - strtotime($b);
                } else {
                    return strlen($a) - strlen($b);
                }
            }
        }
    }
}
