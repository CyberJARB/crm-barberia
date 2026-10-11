<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Test-E-Mail</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#1a1a2e;padding:32px;text-align:center}
  .header h1{color:#f5c518;margin:0;font-size:22px}
  .body{padding:32px}
  .body p{color:#333;line-height:1.6;margin:0 0 16px}
  .badge{display:inline-block;background:#e8f5e9;color:#2e7d32;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:bold}
  .footer{background:#f5f5f5;padding:20px 32px;font-size:12px;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ $tenant->nombre }}</h1>
  </div>
  <div class="body">
    <p><span class="badge">✓ Test erfolgreich</span></p>
    <p>Diese E-Mail bestätigt, dass Ihre SMTP-Konfiguration korrekt eingestellt ist.</p>
    <p>
      <strong>SMTP-Host:</strong> {{ $tenant->smtp_host }}<br>
      <strong>Port:</strong> {{ $tenant->smtp_port }}<br>
      <strong>Verschlüsselung:</strong> {{ strtoupper($tenant->smtp_encryption ?? 'TLS') }}<br>
      <strong>Von:</strong> {{ $tenant->mailFromAddress() }}
    </p>
    <p>Alles ist bereit. Ihre Kunden erhalten ab sofort automatische E-Mails.</p>
  </div>
  <div class="footer">
    CRM Barberías – E-Mail-Einstellungen Test
  </div>
</div>
</body>
</html>
