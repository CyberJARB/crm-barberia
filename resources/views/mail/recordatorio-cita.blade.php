<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Erinnerung: Ihr Termin</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#1a1a2e;padding:32px;text-align:center}
  .header h1{color:#f5c518;margin:0;font-size:22px;letter-spacing:1px}
  .header p{color:#aaa;margin:8px 0 0;font-size:14px}
  .body{padding:32px}
  .body p{color:#333;line-height:1.6;margin:0 0 16px}
  .box{background:#f8f8f8;border-left:4px solid #f5c518;border-radius:4px;padding:20px;margin:24px 0}
  .box table{width:100%;border-collapse:collapse}
  .box td{padding:6px 0;color:#333;font-size:15px}
  .box td:first-child{font-weight:bold;width:40%;color:#555}
  .notice{background:#fff8e1;border:1px solid #f5c518;border-radius:4px;padding:16px;margin:20px 0;font-size:14px;color:#555}
  .footer{background:#f5f5f5;padding:20px 32px;font-size:12px;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ $cita->tenant->nombre }}</h1>
    <p>Erinnerung an Ihren Termin morgen</p>
  </div>
  <div class="body">
    <p>Liebe/r <strong>{{ $cita->cliente->nombre }}</strong>,</p>
    <p>wir möchten Sie an Ihren morgigen Termin erinnern:</p>

    <div class="box">
      <table>
        <tr>
          <td>Datum:</td>
          <td>{{ $cita->fecha->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</td>
        </tr>
        <tr>
          <td>Uhrzeit:</td>
          <td>{{ \Carbon\Carbon::parse($cita->hora_inicio)->format('H:i') }} Uhr</td>
        </tr>
        <tr>
          <td>Dienstleistung:</td>
          <td>{{ $cita->servicios->pluck('nombre')->join(', ') ?: '–' }}</td>
        </tr>
        <tr>
          <td>Mitarbeiter:</td>
          <td>{{ $cita->personal->nombre ?? '–' }}</td>
        </tr>
      </table>
    </div>

    <div class="notice">
      ⏰ Bitte sagen Sie Ihren Termin mindestens <strong>24 Stunden vorher</strong> ab,
      falls Sie nicht erscheinen können.
    </div>

    <p>Bis morgen – wir freuen uns auf Sie!</p>
    <p>Mit freundlichen Grüßen,<br><strong>{{ $cita->tenant->nombre }}</strong></p>
  </div>
  <div class="footer">
    Diese Erinnerung wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.<br>
    Gemäß DSGVO Art. 6 Abs. 1 lit. b wird diese E-Mail zur Vertragserfüllung versendet.
  </div>
</div>
</body>
</html>
