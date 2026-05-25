<?php

/**
 * async-oo provider 配置
 *
 * callback_base_url:
 *   生成 callback_url 时使用的基址。留空则用 config('app.url')。
 *   场景：生产环境 APP_URL=https://api.foo.com，希望上游回调到
 *   https://callback.foo.com 时可单独配置。
 *
 * timeout_minutes:
 *   image_async_jobs.expires_at 偏移分钟。超时由 schedule 任务清理。
 */
return [
    'callback_base_url' => env('ASYNC_OO_CALLBACK_BASE_URL'),
    'timeout_minutes' => (int) env('ASYNC_OO_TIMEOUT_MINUTES', 30),
];
