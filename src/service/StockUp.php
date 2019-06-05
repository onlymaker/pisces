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

        foreach ($data as $item) {
            if ($item) {
                // write sku and thumb
                $thumb = $item['image'];
                unset($item['image']);
                $sku = array_keys($item)[0];
                $sheet->setCellValue('A' . $row, $sku)
                    ->getRowDimension($row)
                    ->setRowHeight(\PhpOffice\PhpSpreadsheet\Shared\Drawing::pixelsToPoints(60));
                $drawing = new Drawing();
                $drawing->setPath($this->getImage($thumb))
                    ->setWorksheet($sheet)
                    ->setCoordinates('B' . $row)
                    ->setOffsetX(2)
                    ->setOffsetY(2);
                $row++;
                // write data
                $stats = $item[$sku];
                foreach ($stats as $region) {
                    $prefix = '';
                    foreach ($region as $size => $sizeStats) {
                        $keys = array_keys($sizeStats);
                        usort($keys, ['service\StockUp', 'compare']);
                        if ($prefix != substr($size, 0, 2)) {
                            $prefix = substr($size, 0, 2);
                            $sheet->setCellValue('A' . ($row + 1), $size);
                            foreach ($keys as $k => $v) {
                                $column = chr(ord('A') + ($k + 1));
                                $sheet->setCellValue($column . $row, $v == 'stock' ? 'FBA Inventory' : $v);
                                $sheet->setCellValue($column . ($row + 1), $sizeStats[$v]);
                            }
                            $row += 2;
                        } else {
                            $sheet->setCellValue('A' . $row, $size);
                            foreach ($keys as $k => $v) {
                                $column = chr(ord('A') + ($k + 1));
                                $sheet->setCellValue($column . $row, $sizeStats[$v]);
                            }
                            $row++;
                        }
                    }
                }
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($file);
        chown($file, 'www-data');
        return true;
    }

    function calcSku($sku)
    {
        $prototype = new SqlMapper('prototype');
        $prototype->load(['model=?', $sku]);
        if (!$prototype->dry()) {
            $data = [
                $sku => [],
                'image' => explode(',', $prototype['images'])[0] . '?imageView2/0/w/50',
            ];
            $markets = ['US', 'DE', 'UK'];
            foreach ($markets as $market) {
                $this->calcSkuByMarket($sku, $market, $data);
            }
        } else {
            $data = [];
        }
        return $data;
    }

    function calcSkuByMarket($sku, $market, &$data)
    {
        $db = Mysql::instance()->get();
        $backwardDate = date('Y-m-d', strtotime('-90 days'));
        $forwardDate = date('Y-m-d', strtotime('60 day'));
        $query = $db->exec('select distinct arrive_date from volume_delivery where sku=? and destination=? and arrive_date between ? and ? order by arrive_date', [$sku, $market, $backwardDate, $forwardDate]);
        $dates = $query ? array_column($query, 'arrive_date') : [];

        $arrivalPoints = [];
        foreach ($dates as $date) {
            if (strtotime($date) < time()) {
                $arrivalPoints[] = $date;
            }
        }
        $futurePoints = array_diff($dates, $arrivalPoints);

        $jig = new Jig(ROOT . '/runtime/jig/');
        $cache = new Jig\Mapper($jig, 'report-' . date('Ymd'));
        $cache->load(['@sku=? and @market=?', $sku, $market]);
        if ($cache->dry()) {
            $options = [
                'method' => 'POST',
                'content' => http_build_query([
                    'sku' => $sku,
                    'date' => implode(',', $arrivalPoints),
                    'market' => $market,
                ]),
            ];
            $response = \Web::instance()->request('https://asin.onlymaker.com/Report', $options);
            $cache['sku'] = $sku;
            $cache['market'] = $market;
            $cache['data'] = $response['body'];
            $cache->save();
        }

        $key = $market == 'DE' ? 'EU' : $market;

        $data[$sku][$key] = json_decode($cache['data'], true)[$sku][$key];

        if ($futurePoints) {
            $filter = [];
            foreach ($futurePoints as $i => $date) {
                $filter[] = "'$date'";
            }
            $filter = "where sku='$sku' and destination='$market' and arrive_date in (" . implode(',', $filter) . ')';
            $query = $db->exec("select * from volume_delivery $filter");
            foreach ($query as $item) {
                if (isset($data[$sku][$key][$item['size']])) {
                    $data[$sku][$key][$item['size']][$item['arrive_date']] = $item['quantity'];
                } else {
                    $data[$sku][$key][$item['size']] = $this->paddingData($arrivalPoints, $item['arrive_date'], $item['quantity']);
                }
            }

            foreach ($data[$sku] as &$range) {
                foreach ($range as &$stats) {
                    foreach ($futurePoints as $date) {
                        if (!isset($stats[$date])) {
                            $stats[$date] = 0;
                        }
                    }
                }
            }
        }

        $futurePoints[] = $forwardDate;
        foreach ($data[$sku] as &$range) {
            foreach ($range as &$stats) {
                $requirement = $stats['requirement'] ?? ceil($stats[90] / 3);
                $delta = $requirement / 30;
                $balance = 0;
                $supply = $stats['stock'];
                $supplyDate = date('Y-m-d');
                foreach ($futurePoints as $date) {
                    $result = $delta * (strtotime($date) - strtotime($supplyDate)) / 86400 - $supply;
                    //ignore the gap (value > 0) between $supplyDate and $date
                    if ($result < 0) {
                        $balance += $result;
                    }
                    $supply = $stats[$date] ?? 0;
                    $supplyDate = $date;
                }
                $stats['requirement'] = max(0, $requirement + $balance);
            }
        }
    }

    function paddingData($remoteFields, $date, $quantity)
    {
        if (!$remoteFields) {
            $data = [
                7 => 0,
                30 => 0,
                60 => 0,
                90 => 0,
            ];
        } else {
            $data = array_flip($remoteFields);
            foreach ($data as &$item) {
                $item = 0;
            }
        }
        $data[$date] = $quantity;
        return $data;
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
