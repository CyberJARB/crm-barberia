# Liste der Subauftragsverarbeiter (Subprozessoren)

**gemäß Art. 28 Abs. 2 und 4 DSGVO**

| | |
|---|---|
| **Verantwortlicher / Auftragnehmer** | _[Firmenname eintragen]_ |
| **Stand** | 2026-06-14 · **Version** 1.0 |

> Jeder eingesetzte Subprozessor verarbeitet personenbezogene Daten ausschließlich auf Grundlage eines AVV nach Art. 28 DSGVO. Drittlandtransfers sind gesondert ausgewiesen und durch geeignete Garantien (Art. 46) abgesichert.

| Dienstleister | Zweck | Datenkategorien | Standort / Drittland | Garantie |
|---|---|---|---|---|
| **Hetzner Online GmbH** _[bestätigen]_ | Hosting/Infrastruktur | alle verarbeiteten Daten (verschlüsselt at rest/in transit) | Deutschland (EU) | AVV; ISO 27001 |
| **Stripe Payments Europe Ltd.** | Zahlungsabwicklung, Abo-Verwaltung (Cashier) | Zahlungs-/Abrechnungsdaten | Irland (EU); Konzern USA | AVV; EU-Standardvertragsklauseln (SCC); Data Privacy Framework |
| **E-Mail-Versand (SMTP)** _[Anbieter eintragen]_ | Transaktions-/Benachrichtigungs-E-Mails | Name, E-Mail, Termininfos | _[eintragen]_ | AVV; TLS-Transport |
| **Google (reCAPTCHA v3)** | Spam-/Bot-Schutz öffentlicher Formulare | IP-Adresse, Geräte-/Nutzungssignale | **USA (Drittland)** | SCC + Data Privacy Framework; **in Datenschutzerklärung ausweisen** |
| **n8n (Automatisierung)** _[self-hosted? bestätigen]_ | Workflow-Automatisierung | je nach Workflow | _[eintragen — bei Self-Hosting kein Drittübermittler]_ | _[AVV falls extern]_ |

---

## Hinweise

- **Google reCAPTCHA** ist ein Drittlandtransfer in die USA und muss in der **Datenschutzerklärung** der Mandanten-Websites transparent gemacht werden. Alternative bei strenger Auslegung: Self-hosted Captcha / Friendly Captcha (EU).
- Bei Wechsel oder Hinzunahme eines Subprozessors sind die Mandanten gem. AVV vorab zu informieren (Widerspruchsrecht).
- Diese Liste ist Bestandteil des AVV und aktuell zu halten.

_Platzhalter `[...]` vor produktivem Einsatz mit realen Anbieterdaten und unterzeichneten AVV ersetzen._
