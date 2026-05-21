@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 16px;font-size:20px;font-weight:700;color:#18181b;">邮件测试</h2>

<div style="background:#f0fdf4;border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;">
  <span style="font-size:24px;margin-right:12px;">✓</span>
  <div>
    <div style="font-size:14px;font-weight:600;color:#166534;">配置正确</div>
    <div style="font-size:12px;color:#4ade80;margin-top:2px;">SMTP 邮件服务已成功连通</div>
  </div>
</div>

<p style="margin:0;font-size:13px;color:#71717a;line-height:1.7;">
  收到此邮件说明你的 SMTP 配置已生效，可以正常发送验证码和通知。
</p>
@endsection
