<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Alles Gute zum Geburtstag!</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#1a1a2e 0%,#3d2c8d 100%);padding:40px 32px;text-align:center}
  .header .emoji{font-size:48px;display:block;margin-bottom:8px}
  .header h1{color:#f5c518;margin:0;font-size:24px;letter-spacing:1px}
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
    <span class="emoji">🎂</span>
    <h1>Alles Gute zum Geburtstag!</h1>
  </div>
  <div class="body">
    <p>Liebe/r <strong>{{ $cliente->nombre }}</strong>,</p>
    <p>
      das gesamte Team von <strong>{{ $cliente->tenant->nombre }}</strong> wünscht Ihnen
      von Herzen alles Gute zu Ihrem Geburtstag! Wir hoffen, Sie haben einen
      wunderschönen Tag voller schöner Momente.
    </p>
    <p>
      Gönnen Sie sich etwas — wie wäre es mit einem frischen Look zum neuen Lebensjahr?
    </p>

    <div class="cta">
      <a href="{{ $cliente->tenant->slug ? url('/reservar/' . $cliente->tenant->slug) : '#' }}">
        Geburtstags-Termin buchen
      </a>
    </div>

    <p>Herzliche Grüße,<br><strong>{{ $cliente->tenant->nombre }}</strong></p>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch zu Ihrem Geburtstag versendet.<br>
    DSGVO Art. 6 Abs. 1 lit. f – berechtigtes Interesse. Sie können sich jederzeit abmelden.
  </div>
</div>
</body>
</html>
