<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ihr Update</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#1e1b4b 0%,#4f46e5 100%);padding:28px 32px}
  .header h1{color:#fff;margin:0;font-size:20px}
  .header p{color:#c7d2fe;margin:6px 0 0;font-size:13px}
  .body{padding:28px 32px}
  .kpis{width:100%;border-collapse:separate;border-spacing:8px;margin:0 -8px}
  .kpi{background:#f8f9ff;border:1px solid #e5e7f5;border-radius:8px;padding:14px;text-align:center;width:33%}
  .kpi .num{font-size:26px;font-weight:800;color:#1e1b4b;display:block}
  .kpi .lbl{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;display:block;margin-top:4px}
  h2{font-size:14px;color:#1e1b4b;margin:24px 0 10px;text-transform:uppercase;letter-spacing:.04em}
  .cita{padding:9px 12px;border-left:3px solid #4f46e5;background:#f8f9ff;border-radius:0 6px 6px 0;margin-bottom:6px;font-size:13px;color:#333}
  .cita strong{color:#1e1b4b}
  .nota{font-size:13px;color:#333;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 12px;margin-top:8px}
  .footer{background:#f5f5f5;padding:18px 32px;font-size:11px;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>📊 Ihr Update — {{ $tenant->nombre }}</h1>
    <p>{{ now()->locale('de')->isoFormat('dddd, DD.MM.YYYY') }} · {{ now()->format('H:i') }} Uhr</p>
  </div>
  <div class="body">

    <table class="kpis" role="presentation">
      <tr>
        <td class="kpi">
          <span class="num">{{ $stats['citasHoy'] }}</span>
          <span class="lbl">Termine heute</span>
        </td>
        <td class="kpi">
          <span class="num">{{ \App\Support\ReporteTexto::euro($stats['ingresosHoy']) }}</span>
          <span class="lbl">Umsatz heute</span>
        </td>
        <td class="kpi">
          <span class="num">{{ $stats['nuevosClientes24h'] }}</span>
          <span class="lbl">Neue Kunden (24h)</span>
        </td>
      </tr>
    </table>

    @if(count($stats['proximasCitas']) > 0)
      <h2>Nächste Termine</h2>
      @foreach($stats['proximasCitas'] as $cita)
        <div class="cita">
          <strong>{{ $cita['hora'] }}</strong> · {{ $cita['cliente'] }}
          <span style="color:#6b7280;">— {{ $cita['personal'] }}</span>
        </div>
      @endforeach
    @endif

    @if(count($stats['cumpleanosHoy']) > 0)
      <div class="nota">
        🎂 <strong>Geburtstag heute:</strong> {{ implode(', ', $stats['cumpleanosHoy']) }}
      </div>
    @endif

    @if($stats['wartelisteHoy'] > 0)
      <div class="nota">
        📋 <strong>{{ $stats['wartelisteHoy'] }} Kunde(n)</strong> stehen heute auf der Warteliste.
      </div>
    @endif

    <h2>Gestern</h2>
    <p style="font-size:13px;color:#333;margin:0;">
      Umsatz gestern: <strong>{{ \App\Support\ReporteTexto::euro($stats['ingresosAyer']) }}</strong>
    </p>

  </div>
  <div class="footer">
    Automatisches Update von Ihrem CRM. Frequenz änderbar unter
    Einstellungen → E-Mail Einstellungen.
  </div>
</div>
</body>
</html>
