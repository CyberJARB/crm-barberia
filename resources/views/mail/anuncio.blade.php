<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $asunto }}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#1e1b4b 0%,#4f46e5 100%);padding:24px 32px}
  .header h1{color:#fff;margin:0;font-size:18px}
  .body{padding:28px 32px}
  .body p{color:#333;line-height:1.65;margin:0 0 14px;white-space:pre-line}
  .footer{background:#f5f5f5;padding:18px 32px;font-size:11px;color:#999;text-align:center}
  .footer a{color:#4f46e5;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ $asunto }}</h1>
  </div>
  <div class="body">
    <p>{{ $mensaje }}</p>
  </div>
  <div class="footer">
    {{ config('saas.producto') }} von {{ config('saas.empresa') }} ·
    <a href="mailto:{{ config('saas.soporte.email') }}">{{ config('saas.soporte.email') }}</a> ·
    WhatsApp {{ config('saas.soporte.whatsapp') }}
  </div>
</div>
</body>
</html>
