<?php

namespace App\Http;

class AsynClient
{
    public static function postJson($url, $params, $headers = [])
    {
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $parts = parse_url($url);

        $handle = fsockopen(
            data_get($parts, 'host'),
            data_get($parts, 'port', data_get($parts, 'scheme') === 'https' ? 443 : 80),
            $errno,
            $errstr,
            5
        );

        $payload = implode("\r\n", array_filter([
            vsprintf('POST %s HTTP/1.1', [data_get($parts, 'path', '/')]),
            vsprintf('Host: %s', data_get($parts, 'host')),
            'Content-Type: application/json',
            collect($headers)->except('Content-Type')
                ->map(function ($value, $key) {
                    return vsprintf('%s: %s', [$key, $value]);
                })->implode("\r\n"),
            vsprintf('Content-Length: %s', [strlen($body)]),
            'Connection: Close',
            "\r\n" . $body,
        ]));
        
        fwrite($handle, $payload);
        fclose($handle);
    }

}
