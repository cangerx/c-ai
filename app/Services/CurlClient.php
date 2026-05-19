<?php

namespace App\Services;

use RuntimeException;

class CurlClient
{
    public static function post(string $url, array $body, array $headers = [], int $timeout = 300, int $connectTimeout = 15): array
    {
        return static::request('POST', $url, $body, $headers, $timeout, $connectTimeout);
    }

    public static function get(string $url, array $headers = [], int $timeout = 30, int $connectTimeout = 10): array
    {
        return static::request('GET', $url, null, $headers, $timeout, $connectTimeout);
    }

    protected static function request(string $method, string $url, ?array $body, array $headers, int $timeout, int $connectTimeout): array
    {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException("cURL 请求失败: {$error}");
        }

        return [
            'status' => $httpCode,
            'body' => $responseBody,
            'json' => json_decode($responseBody, true) ?? [],
        ];
    }
}
