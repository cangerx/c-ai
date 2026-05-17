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

        $appName = $path === '/v1/chat/completions' ? 'chat' : 'image-gen';
        $channels = AiChannel::where('status', 'active')
            ->where('app_name', $appName)
            ->orderBy('priority', 'desc')
            ->get();

        // fallback: 如果没有专用 chat 渠道，尝试 image-gen 渠道
        if ($channels->isEmpty() && $appName === 'chat') {
            $channels = AiChannel::where('status', 'active')
                ->where('app_name', 'image-gen')
                ->orderBy('priority', 'desc')
                ->get();
        }

        if ($channels->isEmpty()) {
            return response(json_encode(['error' => '暂无可用渠道']), 503)
                ->header('Content-Type', 'application/json');
        }

        $contentType = $request->header('Content-Type', '');
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

        // 尝试每个渠道，失败则 fallback 下一个
        foreach ($channels as $channel) {
            $isStream = ($channel->request_mode ?? 'sync') === 'stream'
                && $path !== '/v1/chat/completions';
            $isAsync = ($channel->request_mode ?? 'sync') === 'async';
            $targetUrl = rtrim($channel->base_url, '/') . $path;
            if ($isAsync) {
                $targetUrl .= '?async=true';
            }
            $authorization = 'Bearer ' . $channel->api_key;

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
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            $result = curl_exec($ch);

            if ($result === false) {
                curl_close($ch);
                continue; // 网络错误，尝试下一个渠道
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $responseBody = substr($result, $headerSize);
            curl_close($ch);

            // 5xx 或模型不可用，尝试下一个渠道
            if ($statusCode >= 500 || $statusCode === 404) {
                continue;
            }

            return response($responseBody, $statusCode)
                ->header('Content-Type', 'application/json');
        }

        // 所有渠道都失败
        return response(json_encode(['error' => '所有渠道暂时不可用，请稍后重试']), 502)
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
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
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
