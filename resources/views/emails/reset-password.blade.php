@extends('emails.layout')

@section('content')
<p style="margin:0 0 6px;font-size:13px;color:#71717a;">你好</p>
<h2 style="margin:0 0 16px;font-size:20px;font-weight:700;color:#18181b;">重置密码</h2>

<p style="margin:0 0 24px;font-size:13px;color:#71717a;line-height:1.7;">
  我们收到了你的密码重置请求。点击下方按钮设置新密码：
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
<tr><td align="center">
  <a href="{{ $resetUrl }}" target="_blank"
     style="display:inline-block;background:#18181b;color:#ffffff;font-size:14px;font-weight:600;padding:12px 36px;border-radius:10px;text-decoration:none;letter-spacing:0.3px;">
    重置密码
  </a>
</td></tr>
</table>

<p style="margin:0 0 8px;font-size:12px;color:#a1a1aa;line-height:1.7;">
  链接 <span style="font-weight:600;">30 分钟</span>内有效。如果按钮无法点击，请复制以下地址到浏览器：
</p>
<p style="margin:0;font-size:11px;color:#a1a1aa;word-break:break-all;background:#fafafa;padding:10px 14px;border-radius:8px;line-height:1.5;">
  {{ $resetUrl }}
</p>
@endsection
