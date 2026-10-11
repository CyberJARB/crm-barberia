<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Willkommen bei CRM Barberías</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 0; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .header { background: #4f46e5; padding: 32px 40px; text-align: center; }
  .header h1 { color: #fff; font-size: 22px; font-weight: 700; margin: 0; }
  .header p { color: #c7d2fe; font-size: 14px; margin: 6px 0 0; }
  .body { padding: 32px 40px; }
  .body h2 { font-size: 18px; color: #111827; margin: 0 0 8px; }
  .body p { font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 16px; }
  .card { background: #f5f3ff; border: 1px solid #e0e7ff; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .card .row { display: flex; justify-content: space-between; font-size: 14px; color: #4b5563; padding: 4px 0; }
  .card .row span:last-child { font-weight: 600; color: #111827; }
  .trial-badge { display: inline-block; background: #dcfce7; color: #16a34a; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 100px; margin-bottom: 20px; }
  .btn { display: block; width: fit-content; margin: 24px auto 0; background: #4f46e5; color: #fff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 12px 32px; border-radius: 10px; text-align: center; }
  .steps { counter-reset: step; margin: 24px 0; }
  .step { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
  .step-num { flex-shrink: 0; width: 24px; height: 24px; background: #4f46e5; color: #fff; border-radius: 50%; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
  .step-text { font-size: 14px; color: #374151; line-height: 1.5; }
  .footer { padding: 20px 40px; border-top: 1px solid #f3f4f6; text-align: center; }
  .footer p { font-size: 11px; color: #9ca3af; line-height: 1.6; margin: 0; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1>✂️ CRM Barberías</h1>
    <p>Ihr professionelles Verwaltungssystem</p>
  </div>

  <div class="body">
    <h2>Willkommen, {{ $user->name }}!</h2>
    <p>Ihr Konto für <strong>{{ $tenant->nombre }}</strong> wurde erfolgreich erstellt. Sie können ab sofort mit Ihrem 14-tägigen kostenlosen Test beginnen.</p>

    <div class="trial-badge">✓ 14 Tage kostenlos – keine Kreditkarte erforderlich</div>

    <div class="card">
      <div class="row"><span>Geschäft</span><span>{{ $tenant->nombre }}</span></div>
      <div class="row"><span>Stadt</span><span>{{ $tenant->ciudad ?: '—' }}</span></div>
      <div class="row"><span>Ihr Konto</span><span>{{ $user->email }}</span></div>
      <div class="row"><span>Testphase endet</span><span>{{ $tenant->trial_ends_at?->locale('de')->isoFormat('D. MMMM YYYY') }}</span></div>
    </div>

    <p><strong>So starten Sie in 3 Schritten:</strong></p>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-text"><strong>Mitarbeiter anlegen</strong> — Fügen Sie Ihre Friseure unter „Personal" hinzu.</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-text"><strong>Dienstleistungen einrichten</strong> — Definieren Sie Ihre Serviceangebote mit Preisen und Dauer.</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text"><strong>Ersten Termin buchen</strong> — Tragen Sie Ihren ersten Kunden und Termin ein.</div>
      </div>
    </div>

    <a href="{{ config('app.url') }}/admin" class="btn">Zum Dashboard →</a>
  </div>

  <div class="footer">
    <p>
      Diese E-Mail wurde an {{ $user->email }} gesendet, weil Sie sich bei CRM Barberías registriert haben.<br>
      Rechtsgrundlage: DSGVO Art.&nbsp;6(1)(b) — Vertragserfüllung.<br>
      Bei Fragen: support@crm-barberias.de
    </p>
  </div>

</div>
</body>
</html>
