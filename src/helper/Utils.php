<?php

namespace helper;

class Utils extends \Prefab
{
    function headers(array $raw): array
    {
        $headers = [];
        list($protocol, $code) = explode(' ', array_shift($raw));
        $headers['protocol'] = $protocol;
        $headers['code'] = $code;
        foreach ($raw as $line) {
            list($name, $value) = explode(': ', $line);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}