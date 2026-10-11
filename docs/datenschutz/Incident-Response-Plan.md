# Incident-Response-Plan / Notfallplan bei Datenschutzverletzungen

**Meldepflicht gemäß Art. 33 und 34 DSGVO**

| | |
|---|---|
| **Verantwortlicher** | _[Firmenname eintragen]_ |
| **Zuständige Aufsichtsbehörde** | _[z. B. Hamburgischer Beauftragter für Datenschutz und Informationsfreiheit (HmbBfDI)]_ |
| **Meldewege** | Aufsichtsbehörde (Online-Formular) · betroffene Personen · Cyber-Versicherer |
| **Stand** | 2026-06-14 · **Version** 1.0 |

> **Kernregel:** Bei einer meldepflichtigen Verletzung bleiben **72 Stunden** ab Kenntnisnahme zur Meldung an die Aufsichtsbehörde (Art. 33). Bei hohem Risiko zusätzlich unverzügliche Benachrichtigung der Betroffenen (Art. 34).

---

## Phase 0 — Vorbereitung (laufend)
- Kontaktliste aktuell halten: Aufsichtsbehörde, Cyber-Versicherer (24/7-Hotline), Hosting-Support, ggf. Anwalt/DSB.
- Logging aktiv (`AuditLog`, Sicherheits-Log) — Voraussetzung für die Analyse.
- Dieser Plan und die Kontakte offline verfügbar (nicht nur im betroffenen System).

## Phase 1 — Erkennung & Sofortmaßnahmen (Stunde 0)
1. Vorfall erfassen: Zeitpunkt der Kenntnisnahme **notieren** (startet die 72-h-Frist).
2. **Eindämmung:** betroffene Zugänge sperren, Sessions invalidieren, ggf. `php artisan down`, kompromittierte API-Keys (`Tenant.api_key_hash`) rotieren.
3. **Beweissicherung:** Logs sichern, **nichts vorschnell löschen oder neu aufsetzen**.
4. Cyber-Versicherer **früh** informieren (oft Voraussetzung für Kostenübernahme).

## Phase 2 — Bewertung (Stunde 0–24)
Prüfen und dokumentieren:
- **Was** ist passiert? (unbefugter Zugriff, Datenabfluss, Ransomware, Fehlkonfiguration …)
- **Welche Daten / wie viele Betroffene?** Besonders: betroffene **Art.-9-Daten (`alergias`)** → erhöht das Risiko.
- **Welche Mandanten** sind betroffen? (Mandantentrennung prüfen)
- **Risiko für die Betroffenen?** (Identitätsdiebstahl, finanzieller Schaden …)

**Entscheidungslogik:**
| Lage | Folge |
|---|---|
| Kein Risiko für Betroffene | Interne Dokumentation, keine Meldung |
| Risiko vorhanden | **Meldung an Aufsichtsbehörde (Art. 33) ≤ 72 h** |
| Hohes Risiko | **+ Benachrichtigung der Betroffenen (Art. 34)** |

## Phase 3 — Meldung (≤ 72 Stunden)
Meldung an die Aufsichtsbehörde mit dem Mindestinhalt nach Art. 33 Abs. 3:
1. Art der Verletzung, Kategorien und ungefähre Zahl der Betroffenen/Datensätze.
2. Kontaktstelle (Name, Erreichbarkeit).
3. Wahrscheinliche Folgen.
4. Ergriffene/geplante Maßnahmen.

> Sind nicht alle Infos in 72 h verfügbar: **fristgerecht melden** und Informationen schrittweise nachreichen (Art. 33 Abs. 4).

**Als Auftragsverarbeiter:** den betroffenen Mandanten (Verantwortlichen) **unverzüglich** informieren (Art. 33 Abs. 2) — dieser meldet an seine Aufsichtsbehörde.

## Phase 4 — Benachrichtigung der Betroffenen (bei hohem Risiko)
In klarer, einfacher Sprache: Art der Verletzung, Kontaktstelle, wahrscheinliche Folgen, Maßnahmen und Handlungsempfehlungen (z. B. Passwortwechsel).

## Phase 5 — Nachbearbeitung
- **Vollständige Dokumentation** des Vorfalls (Art. 33 Abs. 5 — Nachweispflicht, auch ohne Meldung).
- Ursachenanalyse (Root Cause), Schließen der Lücke, ggf. Backup-Restore (`BackupEncriptado`).
- TOM aktualisieren (`TOMs.md`), Lessons Learned, ggf. Test ergänzen.

---

## Schnell-Kontakte _[ausfüllen]_
| Rolle | Kontakt |
|---|---|
| Aufsichtsbehörde | _[HmbBfDI Online-Meldeformular]_ |
| Cyber-Versicherer (24/7) | _[Police-Nr. + Hotline]_ |
| Hosting-Notfall | _[Hetzner Support]_ |
| Anwalt / DSB | _[falls vorhanden]_ |

_Dieser Plan ist mindestens jährlich zu überprüfen und nach jedem Vorfall zu aktualisieren._
