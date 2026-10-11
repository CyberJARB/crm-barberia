<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wie war Ihr Besuch?</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#1a1a2e;padding:32px;text-align:center}
  .header h1{color:#f5c518;margin:0;font-size:22px;letter-spacing:1px}
  .body{padding:32px}
  .body p{color:#333;line-height:1.6;margin:0 0 16px}
  .cta{text-align:center;margin:32px 0}
  .cta a{background:#f5c518;color:#1a1a2e;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block}
  .footer{background:#f5f5f5;padding:20px 32px;font-size:12px;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ $cita->tenant->nombre }}</h1>
  </div>
  <div class="body">
    <p>Liebe/r <strong>{{ $cita->cliente->nombre }}</strong>,</p>
    <p>
      vielen Dank für Ihren heutigen Besuch bei uns!
      Wir hoffen, Sie waren mit unserem Service zufrieden und fühlen sich rundum wohl.
    </p>
    <p>
      Vergessen Sie nicht, Ihren nächsten Termin zu vereinbaren —
      so bleiben Sie immer gepflegt und gut aussehend.
    </p>

    <div class="cta">
      <a href="{{ $cita->tenant->slug ? url('/reservar/' . $cita->tenant->slug) : '#' }}">
        Nächsten Termin buchen
      </a>
    </div>

    <p>
      Wir würden uns sehr freuen, Sie bald wiederzusehen!
    </p>
    <p>Herzliche Grüße,<br><strong>{{ $cita->tenant->nombre }}</strong></p>
  </div>
  <div class="footer">
    Diese E-Mail wurde nach Ihrem Besuch automatisch generiert.<br>
    Gemäß DSGVO Art. 6 Abs. 1 lit. b – Vertragserfüllung. Sie können sich jederzeit abmelden.
  </div>
</div>
</body>
</html>
