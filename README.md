# CRM für Barbershops & Friseursalons

Mandantenfähiges SaaS-CRM für Barbershops und Friseursalons — Terminverwaltung, Kundenkartei, Kasse, Online-Buchung und Lead-Pipeline. Entwickelt mit **Security by Design** und DSGVO-Konformität als Grundprinzip.

**Stack:** Laravel 13 · PHP 8.3+ · Filament 5 · Livewire · MySQL · Stripe (Cashier)

---

## Funktionen

- **Termine & Kalender** — Kalenderansicht, Mitarbeiter-Arbeitszeiten, Abwesenheiten, Warteliste, automatische Erinnerungen
- **Kundenverwaltung** — Kundenkartei mit Historie, DSGVO-Export und Anonymisierung
- **Kasse & Zahlungen** — Kassenansicht, Gutscheine, Produkte; Zahlungsdatensätze sind unveränderlich (GoBD)
- **Online-Buchung** — öffentliche Buchungsseite pro Salon (`/reservar/{slug}`) und REST-API für externe Websites
- **Lead-Pipeline** — Kanban-Pipeline für Interessenten, Erfassung über API
- **Berichte & Dashboard** — Umsatz, beliebteste Leistungen, Umsatz pro Mitarbeiter, PDF-/Excel-Export
- **Abonnements** — Tarife (Basis / Professional / Premium) über Stripe Checkout
- **Mandantenfähigkeit** — strikte Datentrennung zwischen Salons (Tenant Isolation)

## Sicherheit

Ausgerichtet an **OWASP Top 10 (A01–A10)**, **ISO 27001**, **NIST CSF** und **DSGVO Art. 32**.

| Bereich | Umsetzung |
|---|---|
| Authentifizierung | 2FA (TOTP), Kontosperre nach Fehlversuchen, Passwort-Historie, starke Passwortregeln, Step-up-Authentifizierung für sensible Aktionen |
| Autorisierung | RBAC mit Policies, Tenant Isolation auf Modellebene |
| Datenschutz | Verschlüsselte Queue-Jobs, DSGVO-Export & Anonymisierung, TOMs, Verarbeitungsverzeichnis, Incident-Response-Plan |
| HTTP-Härtung | Strikte CSP, HSTS, COOP/CORP, Permissions-Policy, erzwungenes HTTPS |
| Missbrauchsschutz | Rate Limiting (Login, API, Livewire-Mutationen), reCAPTCHA v3, SSRF-Guard |
| Monitoring | Audit-Log, Security-Logger, Anomalieerkennung, Sicherheits-Widgets im Dashboard |
| CI/CD | GitHub Actions: `composer audit` (CVE-Scan), PHPStan Level 6, Security-Testsuite |

Details: [`SECURITY_HARDENING_REPORT.md`](SECURITY_HARDENING_REPORT.md) · [`docs/datenschutz/`](docs/datenschutz/) · [`docs/seguridad/`](docs/seguridad/)

## Installation (lokal)

Voraussetzungen: PHP 8.3+, Composer, Node.js, MySQL

```bash
git clone https://github.com/CyberJARB/crm-barberia.git
cd crm-barberia

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
# Datenbank-Zugangsdaten und ADMIN_SEED_PASSWORD in .env eintragen

php artisan migrate --seed
php artisan serve
```

Admin-Panel: `http://localhost:8000/admin` — Login `admin@crm-barberia.de` mit dem Passwort aus `ADMIN_SEED_PASSWORD` (leer lassen → ein zufälliges Passwort wird beim Seeding angezeigt).

## Tests

```bash
php artisan test
```

209 Tests, u. a. Tenant Isolation, Kontosperre, Passwort-Historie, DSGVO-Anonymisierung, Step-up-Auth und öffentliche Buchungs-API.

## Deployment

Referenzarchitektur und Checkliste für den Produktivbetrieb: [`DEPLOY.md`](DEPLOY.md)

## Autor

**Jose Andres Ruiz** — [CyberJARB](https://cyberjarb.de) · Webentwicklung mit Spezialisierung auf Cybersicherheit, Hamburg

## Lizenz

© 2026 Jose Andres Ruiz (CyberJARB). Alle Rechte vorbehalten. Der Quellcode ist zur Ansicht veröffentlicht; Nutzung, Vervielfältigung oder kommerzieller Einsatz nur mit schriftlicher Genehmigung.
