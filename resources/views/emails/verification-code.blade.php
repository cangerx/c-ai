@extends('emails.layout')

@section('content')
<p style="margin:0 0 6px;font-size:13px;color:#71717a;">{{ $greeting ?? '你好' }}</p>
<h2 style="margin:0 0 24px;font-size:20px;font-weight:700;color:#18181b;">{{ $heading }}</h2>

<div style="background:#fafafa;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px;">
  <div style="font-size:11px;color:#a1a1aa;margin-bottom:8px;letter-spacing:1px;text-transform:uppercase;">验证码</div>
  <div style="font-size:36px;font-weight:800;color:#18181b;letter-spacing:8px;font-family:'SF Mono',Monaco,'Cascadia Code',Consolas,monospace;">{{ $code }}</div>
</div>

<p style="margin:0;font-size:13px;color:#71717a;line-height:1.7;">
  验证码 <span style="font-weight:600;color:#18181b;">{{ $expiry ?? '10' }} 分钟</span>内有效。
</p>
@endsection
