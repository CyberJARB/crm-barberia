# Technische und Organisatorische Maßnahmen (TOM)

**gemäß Art. 32 DSGVO**

| | |
|---|---|
| **Verantwortlicher / Auftragnehmer** | _[Firmenname / Inhaber eintragen]_ |
| **Anschrift** | _[Anschrift eintragen]_ |
| **Produkt** | CRM-/Buchungsplattform (Mandantenfähiges SaaS), Stack: Laravel 13, PHP 8.5, Filament 5, MySQL |
| **Hosting** | _[Hetzner Online GmbH, Rechenzentrum Deutschland — bestätigen]_ |
| **Stand** | 2026-06-14 |
| **Version** | 1.0 |

> **Hinweis:** Dieses Dokument beschreibt die zum Stichtag implementierten Maßnahmen und dient als Anlage zum Auftragsverarbeitungsvertrag (AVV) sowie als Nachweis gegenüber Cyber-Versicherern. Es ersetzt keine Rechtsberatung; vor produktivem Einsatz durch Datenschutzbeauftragten/Anwalt prüfen lassen. Quellenbelege verweisen auf den realen Code (`SECURITY_HARDENING_REPORT.md`).

---

## 1. Vertraulichkeit (Art. 32 Abs. 1 lit. b DSGVO)

### 1.1 Zutrittskontrolle (physischer Zugang)
Der physische Zugang zu den verarbeitenden Systemen liegt beim Hosting-Dienstleister (_Hetzner, ISO 27001-zertifiziert, RZ Deutschland_). Maßnahmen des Rechenzentrums: Zutritt nur für autorisiertes Personal, Videoüberwachung, Vereinzelungsanlagen. Nachweis über die ISO-27001-Zertifizierung und den AVV des Hosters (siehe `Subprozessoren.md`).

### 1.2 Zugangskontrolle (Zugang zu den Systemen)
| Maßnahme | Umsetzung im System |
|---|---|
| Passwortrichtlinie | Mindestlänge 12 Zeichen, Abgleich gegen geleakte Passwörter (HaveIBeenPwned), Passwort-Historie 10 (`config/security.php`, `PasswordHistory`) |
| Account-Lockout | Sperrung nach definierten Fehlversuchen (`User`-Model, `config/security.php`, OWASP A07) |
| Mehr-Faktor-Authentisierung (MFA) | Für Admin-/Super-Admin-Panel (Filament) — _vor Go-Live als Pflicht erzwingen_ |
| Brute-Force-Schutz | Rate-Limiting auf Login (`bootstrap/app.php`), reCAPTCHA v3 + Honeypot auf öffentlichen Formularen |
| Sichere Sessions | `session.encrypt=true`, `secure=true` (Produktion), `HttpOnly`, kurze Lebensdauer (`config/session.php`) |
| Transportverschlüsselung | TLS-Erzwingung (`URL::forceScheme('https')`, HSTS, `upgrade-insecure-requests`) |

### 1.3 Zugriffskontrolle (Zugriff auf Daten)
| Maßnahme | Umsetzung |
|---|---|
| Rollenbasierte Rechte (RBAC) | Laravel Policies je Resource; Berechtigungsprüfung auch bei Direktaufruf per URL (`AuditLogResource::canViewAny`) |
| Minimalprinzip | Dedizierter MySQL-Benutzer mit `SELECT/INSERT/UPDATE/DELETE` (kein `DROP/GRANT/FILE`) |
| Unveränderliche Finanzdaten | `PagoResource`: Löschen deaktiviert (GoBD / Art. 5 Abs. 1 lit. f) |
| Schutz vor IDOR | Globaler Mandanten-Scope (siehe 1.5) verhindert objektbezogenen Fremdzugriff (OWASP A01/BOLA) |
| Eingabevalidierung | Längen-/Bereichs-/Regex-Validierung in allen Filament-Resources (CWE-20/1284) |

### 1.4 Pseudonymisierung & Verschlüsselung (Art. 32 Abs. 1 lit. a)
| Maßnahme | Umsetzung |
|---|---|
| Verschlüsselung besonders schützenswerter Felder | `Cliente`: `telefono`, **`alergias` (Gesundheitsdaten, Art. 9)**, `notas` — feldweise verschlüsselt (`HasEncryptedFields`, AES via APP_KEY) |
| Verschlüsselte Lead-Daten | `Lead`: feldweise Verschlüsselung sensibler Felder |
| Passwörter | gehasht (bcrypt/Argon2), nie im Klartext |
| Queue-Payloads | E-Mail-Jobs `ShouldBeEncrypted` — Klartext in der `jobs`-Tabelle ausgeschlossen (CWE-312) |
| Backups | AES-256-CBC, PBKDF2 100.000 Iterationen (`BackupEncriptado`) |
| Anonymisierung | Recht auf Vergessenwerden umgesetzt (`Cliente::anonymisieren`, Art. 17) |

### 1.5 Trennungskontrolle (Mandantentrennung) — **kritisch**
| Maßnahme | Umsetzung |
|---|---|
| Automatische Datentrennung | Globaler Eloquent-Scope `BelongsToTenant` filtert **alle** Queries nach `tenant_id` |
| Kontextbindung | `EnsureTenantScope` (Web) und `AutenticarTenantApi` (API, SHA-256-gehashter Key) binden den Mandantenkontext je Request |
| Automatisierte Regressionstests | `tests/Feature/TenantIsolationTest.php` — belegt, dass ein Nutzer keine Fremddaten sieht |
| **Restrisiko / Maßnahme** | Der Scope ist „fail-open" (ohne gebundenen Kontext keine Filterung). Empfehlung: zusätzliche Isolationstests für API-/Job-/Konsolenpfade; mittelfristig „fail-closed" für mandantengebundene Modelle in Endnutzer-Kontexten. |

---

## 2. Integrität (Art. 32 Abs. 1 lit. b)

| Maßnahme | Umsetzung |
|---|---|
| Weitergabekontrolle | TLS im Transport; SMTP nur mit TLS (`MAIL_SCHEME=tls`); DB nur auf localhost/privatem Netz |
| Eingabekontrolle / Protokollierung | Revisionssicheres Audit-Log (`AuditLog`): wer, was, wann; Super-Admin-Sicht (`AuditLogGlobalResource`) |
| Schutz vor Injection | Eloquent/Prepared Statements; Output-Escaping in Blade (kein `{!! !!}` in Views — verifiziert) |
| Sicherheits-Header | CSP, COOP, CORP, `X-Frame-Options`, Permissions-Policy (`SecurityHeaders`-Middleware) |
| Supply-Chain | Feste Versions-Pins (`composer.json`), `composer audit` + OWASP ZAP + SBOM in CI |

---

## 3. Verfügbarkeit und Belastbarkeit (Art. 32 Abs. 1 lit. b und c)

| Maßnahme | Umsetzung |
|---|---|
| Datensicherung | Verschlüsseltes Backup, täglich 02:00 Uhr geplant (`BackupEncriptado`) |
| Wiederherstellbarkeit | **Restore-Test verpflichtend** (Deploy-Checkliste) — Durchführung dokumentieren, Ziel: RTO/RPO definieren |
| Schutz vor Überlast (DoS) | Rate-Limiting global (Lesen 300/min, Schreiben 180/min), API-Throttle, Job-Timeouts |
| Health Checks | Integrierte Health-/Status-Prüfung (`SaludCheck`) |
| Schutz destruktiver Befehle | `DB::prohibitDestructiveCommands()` in Produktion |

---

## 4. Verfahren zur regelmäßigen Überprüfung (Art. 32 Abs. 1 lit. d)

| Maßnahme | Umsetzung |
|---|---|
| Automatisierte Tests | 52+ Tests (Feature/Unit), inkl. RBAC, Mandantentrennung, GDPR-Anonymisierung |
| Statische Analyse | PHPStan/Larastan (`phpstan.neon`) |
| Dynamische Sicherheitsprüfung | OWASP ZAP in CI (`.zap/`) |
| Abhängigkeits-Audit | `composer audit --locked` als Pipeline-Gate |
| Log-Aufbewahrung | Sicherheits-Log 90 Tage, Audit-Log 365 Tage (`config/security.php`, Art. 5 Abs. 1 lit. e) |
| Überprüfungsintervall der TOM | Mindestens jährlich sowie nach wesentlichen Änderungen |

---

## 5. Auftragskontrolle (Subunternehmer)
Einsatz von Subauftragsverarbeitern nur mit AVV nach Art. 28 DSGVO. Liste und Rechtsgrundlagen siehe **`Subprozessoren.md`**. Drittlandtransfer (z. B. Google reCAPTCHA) ist dort gesondert ausgewiesen.

---

_Erstellt als Nachweis nach Art. 32 DSGVO. Änderungen sind versioniert zu führen._
