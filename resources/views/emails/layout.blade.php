<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject ?? '' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
<tr><td align="center">

{{-- Card --}}
<table width="420" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">

{{-- Header --}}
<tr><td style="padding:32px 36px 0 36px;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td style="font-size:18px;font-weight:700;color:#18181b;letter-spacing:-0.3px;">{{ $siteName ?? 'CANG-AI' }}</td>
      <td align="right" style="font-size:11px;color:#a1a1aa;letter-spacing:0.5px;">{{ $tagline ?? '' }}</td>
    </tr>
  </table>
  <div style="height:1px;background:linear-gradient(90deg,#e4e4e7 0%,transparent 100%);margin-top:20px;"></div>
</td></tr>

{{-- Body --}}
<tr><td style="padding:28px 36px 32px 36px;">
  @yield('content')
</td></tr>

{{-- Footer --}}
<tr><td style="padding:0 36px 28px 36px;">
  <div style="height:1px;background:#f4f4f5;margin-bottom:16px;"></div>
  <p style="margin:0;font-size:11px;color:#a1a1aa;line-height:1.6;">
    此邮件由 <span style="color:#71717a;">{{ $siteName ?? 'CANG-AI' }}</span> 系统自动发送，请勿回复。<br>
    如非本人操作，请忽略此邮件。
  </p>
</td></tr>

</table>

{{-- Sub-footer --}}
<table width="420" cellpadding="0" cellspacing="0">
<tr><td style="padding:16px 0;text-align:center;">
  <span style="font-size:10px;color:#c4c4c8;">© {{ date('Y') }} {{ $siteName ?? 'CANG-AI' }}</span>
</td></tr>
</table>

</td></tr>
</table>
</body>
</html>
