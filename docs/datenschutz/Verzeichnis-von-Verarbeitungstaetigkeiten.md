# Verzeichnis von Verarbeitungstätigkeiten (VVT)

**gemäß Art. 30 DSGVO**

| | |
|---|---|
| **Verantwortlicher** | _[Firmenname / Inhaber, Anschrift, Kontakt eintragen]_ |
| **Datenschutzbeauftragter** | _[falls benannt — für Einzelunternehmer i. d. R. nicht erforderlich]_ |
| **Rolle** | Auftragsverarbeiter ggü. den Mandanten (Salons/Betriebe); Verantwortlicher für eigene Nutzer-/Vertragsdaten |
| **Stand** | 2026-06-14 · **Version** 1.0 |

> Hinweis: Als SaaS-Anbieter sind Sie für die Endkundendaten Ihrer Mandanten **Auftragsverarbeiter** (der Salon ist Verantwortlicher). Für Ihre eigenen Geschäftsbeziehungen (Mandanten-Accounts, Abrechnung) sind Sie **Verantwortlicher**. Dieses Verzeichnis deckt beide Rollen ab.

---

## V1 — Kundenverwaltung der Mandanten (CRM)

| Feld | Angabe |
|---|---|
| **Zweck** | Verwaltung von Endkunden des Mandanten (Termine, Historie, Kommunikation) |
| **Betroffene** | Endkunden der Salons/Betriebe |
| **Datenkategorien** | Name, E-Mail, Telefon (verschl.), Geburtsdatum, Besuchshistorie, Notizen (verschl.) |
| **Besondere Kategorien (Art. 9)** | **Gesundheitsdaten: Feld `alergias`/Allergien (verschlüsselt)** — Rechtsgrundlage und Erforderlichkeit beim Mandanten zu dokumentieren |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. b (Vertrag); für Art. 9: Art. 9 Abs. 2 lit. a (Einwilligung) bzw. lit. h |
| **Empfänger** | Mandant (Verantwortlicher); Subprozessoren lt. `Subprozessoren.md` |
| **Löschfrist** | Anonymisierung auf Anforderung (Art. 17, `Cliente::anonymisieren`); im Übrigen nach Vorgabe des Mandanten |
| **TOM** | siehe `TOMs.md` |

## V2 — Terminbuchung / öffentliche Reservierungs-API

| Feld | Angabe |
|---|---|
| **Zweck** | Online-Terminbuchung über die Website des Mandanten (API v1) |
| **Betroffene** | Buchende Endkunden |
| **Datenkategorien** | Name, Kontakt, gewünschter Service, Zeitpunkt |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. b |
| **Besonderheit** | API-Authentifizierung per mandantenbezogenem, SHA-256-gehashtem Schlüssel; Throttling gegen Missbrauch |
| **Löschfrist** | gem. Mandantenvorgabe |

## V3 — Lead-/Interessentenverwaltung

| Feld | Angabe |
|---|---|
| **Zweck** | Erfassung und Pipeline-Verwaltung von Interessenten |
| **Datenkategorien** | Name, Telefon (verschl.), E-Mail, Nachricht, UTM-Quelldaten, Opt-in |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. a (Einwilligung/Opt-in) bzw. lit. f |
| **Löschfrist** | nach Zweckfortfall / Widerruf |

## V4 — Zahlungs-/Abrechnungsdaten

| Feld | Angabe |
|---|---|
| **Zweck** | Abrechnung von Leistungen, Abo-Verwaltung |
| **Datenkategorien** | Betrag, Referenz, Abo-Status (Zahlungsdaten via Stripe) |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. b und lit. c (steuerl. Aufbewahrung) |
| **Empfänger** | Stripe Payments Europe Ltd. (AVV) |
| **Löschfrist** | gesetzliche Aufbewahrung (GoBD/§ 147 AO, i. d. R. 10 Jahre); Datensätze unveränderlich |

## V5 — Mitarbeiter-/Personalverwaltung (des Mandanten)

| Feld | Angabe |
|---|---|
| **Zweck** | Dienst-/Schichtplanung, Zuordnung von Terminen |
| **Datenkategorien** | Name, Telefon, Arbeitszeiten/Abwesenheiten |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. b; § 26 BDSG (Beschäftigtenkontext) |

## V6 — Nutzer-/Account-Verwaltung (eigene Verantwortung)

| Feld | Angabe |
|---|---|
| **Zweck** | Authentifizierung, Zugriffssteuerung der Plattform-Nutzer |
| **Datenkategorien** | Name, E-Mail, gehashtes Passwort, MFA, Passwort-Historie, Audit-Log |
| **Rechtsgrundlage** | Art. 6 Abs. 1 lit. b und lit. f (IT-Sicherheit) |
| **Löschfrist** | Audit-Log 365 Tage, Sicherheits-Log 90 Tage (`config/security.php`) |

---

_Das Verzeichnis ist aktuell zu halten und auf Verlangen der Aufsichtsbehörde vorzulegen (Art. 30 Abs. 4)._
