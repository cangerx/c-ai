<?php

namespace App\Apps\ImageGen\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProxyController extends Controller
{
    private const ALLOWED_PATHS = [
        '/v1/chat/completions',
        '/v1/images/generations',
        '/v1/images/edits',
    ];

    public function handle(Request $request): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = (string) $request->query('path', '');
        if (!in_array($path, self::ALLOWED_PATHS, true)) {
            return response(json_encode(['error' => 'Invalid proxy path']), 400)
                ->header('Content-Type', 'application/json');
        }

        $channel = AiChannel::where('status', 'active')
            ->where('app_name', 'image-gen')
            ->orderByRaw('priority DESC, RANDOM()')
            ->first();

        if (!$channel) {
            return response(json_encode(['error' => '暂无可用渠道']), 503)
                ->header('Content-Type', 'application/json');
        }

        $isStream = ($channel->request_mode ?? 'sync') === 'stream';
        $targetUrl = rtrim($channel->base_url, '/') . $path;
        $authorization = 'Bearer ' . $channel->api_key;

        $contentType = $request->header('Content-Type', '');
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

        $headers = ['Authorization: ' . $authorization];

        if ($isMultipart) {
            [$body, $mpContentType] = $this->rebuildMultipart($request);
            $headers[] = 'Content-Type: ' . $mpContentType;
        } else {
            $body = $request->getContent();
            if ($isStream && stripos($contentType, 'application/json') !== false) {
                $json = json_decode($body, true);
                if (is_array($json)) {
                    $json['stream'] = true;
                    $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            if ($contentType) {
                $headers[] = 'Content-Type: ' . $contentType;
            }
        }

        if ($isStream) {
            return $this->streamResponse($targetUrl, $headers, $body);
        }

        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return response(json_encode(['error' => 'Upstream error', 'detail' => $err]), 502)
                ->header('Content-Type', 'application/json');
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseBody = substr($result, $headerSize);
        curl_close($ch);

        return response($responseBody, $statusCode)
            ->header('Content-Type', 'application/json');
    }

    private function streamResponse(string $url, array $headers, string $body): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($url, $headers, $body) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                    echo $data;
                    if (ob_get_level() > 0) @ob_flush();
                    @flush();
                    return strlen($data);
                },
            ]);

            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function rebuildMultipart(Request $request): array
    {
        $boundary = '--------------------------' . bin2hex(random_bytes(12));
        $eol = "\r\n";
        $body = '';

        foreach ($request->except(['path']) as $key => $value) {
            if (is_string($value)) {
                $body .= '--' . $boundary . $eol;
                $body .= sprintf('Content-Disposition: form-data; name="%s"%s%s', $key, $eol, $eol);
                $body .= $value . $eol;
            }
        }

        foreach ($request->allFiles() as $fieldName => $files) {
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                $binary = file_get_contents($file->getRealPath());
                $body .= '--' . $boundary . $eol;
                $body .= sprintf(
                    'Content-Disposition: form-data; name="%s[]"; filename="%s"%s',
                    $fieldName,
                    $file->getClientOriginalName(),
                    $eol
                );
                $body .= 'Content-Type: ' . $file->getMimeType() . $eol . $eol;
                $body .= $binary . $eol;
            }
        }

        $body .= '--' . $boundary . '--' . $eol;
        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }
}
