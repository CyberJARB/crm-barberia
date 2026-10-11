# Datenschutz-Dokumentation

Formale DSGVO-/Versicherungsnachweise, abgeleitet aus dem realen Code dieses Projekts
(`SECURITY_HARDENING_REPORT.md`). Anlagen für AVV und Cyber-Versicherer.

| Dokument | Zweck | Rechtsgrundlage |
|---|---|---|
| [`TOMs.md`](TOMs.md) | Technische & organisatorische Maßnahmen | Art. 32 DSGVO |
| [`Verzeichnis-von-Verarbeitungstaetigkeiten.md`](Verzeichnis-von-Verarbeitungstaetigkeiten.md) | VVT — was wird wozu verarbeitet | Art. 30 DSGVO |
| [`Subprozessoren.md`](Subprozessoren.md) | Liste der Subauftragsverarbeiter | Art. 28 DSGVO |
| [`Incident-Response-Plan.md`](Incident-Response-Plan.md) | Notfall-/Meldeplan (72 h) | Art. 33/34 DSGVO |

## Vor produktivem Einsatz / vor Versicherungsabschluss
1. Alle Platzhalter `[...]` mit realen Firmen-/Anbieterdaten ausfüllen.
2. Unterschriebene AVV mit allen Subprozessoren einholen.
3. MFA für Admin/Super-Admin als **Pflicht** erzwingen.
4. **Restore-Test** durchführen und mit Datum dokumentieren.
5. Durch Datenschutzbeauftragten/Anwalt prüfen lassen (kein Ersatz für Rechtsberatung).

_Stand: 2026-06-14 · Version 1.0_
