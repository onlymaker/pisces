<?php

namespace helper;

class Image
{
    function download($url, $width = 50, $renew = false)
    {
        $holder = __DIR__. '/holder.jpg';
        $info = pathinfo(parse_url($url, PHP_URL_PATH));
        if ($info['filename']) {
            $dir = ROOT . "/images/";
            if ($width) {
                $filename = $info['filename'] . '_' . $width;
            } else {
                $filename = $info['filename'];
            }
            $candidates = [
                $dir . $filename . '.jpg',
                $dir . $filename . '.jpeg',
                $dir . $filename . '.png',
            ];
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    if ($renew) {
                        unlink($candidate);
                    } else {
                        return $candidate;
                    }
                }
            }
            $url = $this->thumbnail($url, $width);
            //echo $url, PHP_EOL;
            $response = \Web::instance()->request($url);
            $headers = Utils::instance()->headers($response['headers']);
            if (substr($headers['code'], 0, 2) == 20) {
                $extension = explode('/', $headers['content-type'])[1];
                $file = $dir . $filename . '.' . $extension;
                file_put_contents($file, $response['body']);
                return $file;
            }
        }
        return $holder;
    }

    function thumbnail($url, $scale = 50)
    {
        if ($scale) {
            $suffix = 'imageView2/0/w/' . $scale;
        } else {
            $suffix = '';
        }
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            $matched = preg_match('/^.*imageView2\\/0\\/w\\/(?<scale>\\d+)$/', $url, $matches);
            if ($matched) {
                if ($matches['scale'] != $scale) {
                    $url = str_replace('imageView2/0/w/' . $matches['scale'], $suffix, $url);
                }
                return $url;
            } else {
                return $url . $suffix;
            }
        } else {
            return $url . '?' . $suffix;
        }
    }
}