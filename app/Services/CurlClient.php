<?php

namespace App\Services;

use RuntimeException;

class CurlClient
{
    public static function post(string $url, array $body, array $headers = [], int $timeout = 300, int $connectTimeout = 15): array
    {
        return static::request('POST', $url, $body, $headers, $timeout, $connectTimeout);
    }

    public static function postMultipart(string $url, array $fields, array $headers = [], int $timeout = 300, int $connectTimeout = 15): array
    {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'content-type') continue;
            $curlHeaders[] = "{$key}: {$value}";
        }
        $curlHeaders[] = 'Expect:';

        $responseHeaders = [];
        $headerCallback = function ($ch, $header) use (&$responseHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        };

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => $headerCallback,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($responseBody === false || $errno !== 0) {
            throw new \RuntimeException("cURL 请求失败 ({$errno}): {$error}");
        }

        return [
            'status' => $httpCode,
            'body' => $responseBody,
            'headers' => $responseHeaders,
            'json' => json_decode($responseBody, true) ?? [],
        ];
    }

    public static function get(string $url, array $headers = [], int $timeout = 30, int $connectTimeout = 10): array
    {
        return static::request('GET', $url, null, $headers, $timeout, $connectTimeout);
    }

    public static function getRaw(string $url, array $headers = [], int $timeout = 120, int $connectTimeout = 15): array
    {
        return static::request('GET', $url, null, $headers, $timeout, $connectTimeout, true);
    }

    protected static function request(string $method, string $url, ?array $body, array $headers, int $timeout, int $connectTimeout, bool $raw = false): array
    {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        $responseHeaders = [];
        $headerCallback = function ($ch, $header) use (&$responseHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($header);
        };

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => $headerCallback,
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
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($responseBody === false || $errno !== 0) {
            throw new RuntimeException("cURL 请求失败 ({$errno}): {$error}");
        }

        $result = [
            'status' => $httpCode,
            'body' => $responseBody,
            'headers' => $responseHeaders,
        ];

        if (!$raw) {
            $result['json'] = json_decode($responseBody, true) ?? [];
        }

        return $result;
    }
}
