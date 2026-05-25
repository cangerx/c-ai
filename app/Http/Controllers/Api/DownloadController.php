<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * 流式代理远程文件，强制以 attachment 形式响应。
     * 用于绕过 OSS/COS 跨域 + download attribute 失效 + CORS 等问题。
     * GET /api/download?url=<encoded>&filename=<optional>
     */
    public function proxy(Request $request): StreamedResponse
    {
        $url = trim((string) $request->query('url', ''));
        $this->assertAllowed($url);

        $filename = $this->normalizeFilename((string) $request->query('filename', ''), $url);

        // 先用 HEAD 拿到 Content-Type / Content-Length，避免响应阶段才发现 4xx/5xx
        $head = $this->curlHead($url);
        if ($head['status'] < 200 || $head['status'] >= 400) {
            Log::warning('download.proxy upstream not ok', ['url' => $url, 'status' => $head['status']]);
            abort(502, "Upstream HTTP {$head['status']}");
        }

        $contentType = $head['content_type'] ?: 'application/octet-stream';
        $contentLength = $head['content_length'];

        $headers = array_filter([
            'Content-Type' => $contentType,
            'Content-Length' => $contentLength > 0 ? (string) $contentLength : null,
            'Content-Disposition' => $this->dispositionHeader($filename),
            'Cache-Control' => 'public, max-age=86400',
            'X-Accel-Buffering' => 'no',
        ]);

        return response()->stream(function () use ($url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => function ($ch, string $chunk): int {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();
                    return strlen($chunk);
                },
                CURLOPT_USERAGENT => 'Cang-AI-Download/1.0',
            ]);
            curl_exec($ch);
            if (curl_errno($ch)) {
                Log::warning('download.proxy curl error', ['url' => $url, 'error' => curl_error($ch)]);
            }
            curl_close($ch);
        }, 200, $headers);
    }

    /**
     * 返回带 response-content-disposition 的直连 URL，浏览器拿到该 URL 直下即可（零回源流量）。
     * 适用于支持该参数的标准 S3/OSS/COS 公共读 bucket。
     * GET /api/download-url?url=<encoded>&filename=<optional>
     */
    public function presign(Request $request)
    {
        $url = trim((string) $request->query('url', ''));
        $this->assertAllowed($url);

        $filename = $this->normalizeFilename((string) $request->query('filename', ''), $url);

        $disposition = 'attachment;filename=' . rawurlencode($filename)
            . ";filename*=UTF-8''" . rawurlencode($filename);

        $sep = str_contains($url, '?') ? '&' : '?';
        $downloadUrl = $url . $sep . 'response-content-disposition=' . rawurlencode($disposition);

        return response()->json([
            'url' => $downloadUrl,
            'filename' => $filename,
        ]);
    }

    /** 校验 URL：scheme/host/IP/白名单 */
    protected function assertAllowed(string $url): void
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            abort(400, 'Invalid URL');
        }

        // SSRF 防护：禁止解析到私有/保留 IP
        $ip = @gethostbyname($host);
        if (! $ip || $ip === $host) {
            Log::warning('download: DNS resolve failed', ['host' => $host]);
            abort(400, 'Host resolve failed');
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            abort(403, 'Forbidden IP');
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: '';
        $allowed = array_filter(array_merge(
            [$appHost],
            app(StorageProfileService::class)->allowedHosts(['default', 'temp', 'backup'])
        ));

        $cloudPatterns = [
            '/\.(cos\.[a-z0-9\-]+\.myqcloud\.com)$/i',
            '/\.(oss-[a-z0-9\-]+\.aliyuncs\.com)$/i',
            '/\.r2\.cloudflarestorage\.com$/i',
            '/^(cdn\d*\.dmiapi\.com|cdn\d*\.duomiapi\.com)$/i',
        ];

        $isAllowed = in_array($host, $allowed, true);
        if (! $isAllowed) {
            foreach ($cloudPatterns as $pat) {
                if (preg_match($pat, $host)) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (! $isAllowed) {
            Log::warning('download: host not in whitelist', ['host' => $host, 'allowed' => $allowed]);
            abort(403, 'Host not allowed');
        }
    }

    /** 文件名清洗：保留中文+字母数字+常见符号，去掉路径分隔与控制字符 */
    protected function normalizeFilename(string $filename, string $url): string
    {
        if ($filename !== '') {
            $name = preg_replace('#[\\\\/?*|"<>:\x00-\x1f]+#u', '_', $filename);
            $name = trim($name, '. ');
            if ($name !== '') {
                return $name;
            }
        }

        $base = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'file';
        $base = preg_replace('/[^\w.\-]/', '_', $base);

        return $base !== '' ? $base : 'file';
    }

    /** RFC 5987 兼容的 Content-Disposition 头 */
    protected function dispositionHeader(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $filename);

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            addslashes($ascii ?: 'file'),
            rawurlencode($filename)
        );
    }

    /** 小工具：HEAD 上游获取 Content-Type / Content-Length */
    protected function curlHead(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Cang-AI-Download/1.0',
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $contentLength = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        return [
            'status' => $status,
            'content_type' => $contentType,
            'content_length' => $contentLength,
        ];
    }
}
