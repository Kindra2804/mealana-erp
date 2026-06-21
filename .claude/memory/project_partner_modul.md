---
name: project-partner-modul
description: "Partner-Modul Design + Implementierungsstand (Mietfächer, Kommission, Spende)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1d6af759-efaf-424e-9b61-6578c5cf2dd1
---

# Partner-Modul (Stand 2026-06-20)

## Konzept

Externe Partner in zwei Richtungen:
- **Kommission** (inbound): Mietfach-Inhaber — ihre Ware bei MeaLana, wir verkaufen, rechnen ab
- **Spende** (inbound): Yarnpride e.V. — Artikel gegen Mindestspende, Geld geht 1:1 weiter (Steuerberater klärt RKSV)
- **Händler** (outbound): Befreundete Betriebe verkaufen MeaLana-Ware → abgebildet als **externes Lager** (lager.typ='extern_haendler'), NICHT im Partner-Modul → kommt nach Kern-Verkauf

## Datenbank (Migrationen 049–052, eingespielt 2026-06-20)

- `049_partner_stamm.sql` → partner + mietfaecher
- `050_partner_belege.sql` → dokument_nummern (alle Rechnungskreise) + miet_rechnungen + kommissions_abrechnungen
- `051_spenden_log.sql` → spenden_log (Yarnpride-Dokumentation, weitergeleitet-Flag)
- `052_artikel_partner.sql` → ALTER artikel: partner_id + partner_modus ENUM(eigen|kommission|spende)

## Abrechnungs-Modi (pro Partner konfigurierbar)

| Modus | Belege |
|-------|--------|
| Getrennt | Mietrechnung + Kommissions-Abrechnung separat |
| Gegenverrechnung | Ein Dokument: Erlöse - Miete = Saldo |

| Belegtyp | Wer erstellt |
|----------|-------------|
| Gutschrift | MeaLana erstellt im Namen des Partners |
| Fremdrechnung | Partner stellt eigene Rechnung, wir buchen sie ein |
| Info-Abrechnung | Formloser Beleg (für KU-Partner ohne Steuerbeleg) |

## Fachmieter mit Fremdrechnung (Klärung 2026-06-21)

Viele Fachmieter sind **Fremdrechnung**: MeaLana hat Rechnungsblöcke des Fachieters auf Lager,
verkauft in deren Namen und auf deren Rechnung. Kein MeaLana-Ausgangsbeleg.

**Lagerverwaltung:** Identisch zu Yarnpride/Händler-Konsignation — eigenes "Lager" (extern_partner),
Lagerbewegungen werden vollständig gebucht (Eingang, Verkauf, Rücklauf).

**DB-Erweiterung (noch nicht migriert):**
```sql
ALTER TABLE partner
  ADD COLUMN abrechnung_typ VARCHAR(30) NOT NULL DEFAULT 'eigen_rechnung';
  -- Werte: 'eigen_rechnung' | 'fremd_rechnung' | 'info_abrechnung'
```

`fremd_rechnung` → kein MeaLana-Beleg, nur Lagerbewegung + optionale Notiz (welcher Rechnungsblock-Nr.)
`eigen_rechnung` → MeaLana erstellt Gutschrift (bisheriger Modus)
`info_abrechnung` → KU-Partner, formloser Beleg

**Timing:** Migration + Feld im Partner-Formular einbauen WENN public/partner/ gebaut wird.

## PHP-Schicht (Stand 2026-06-20)

- `src/modules/partner/PartnerRepository.php` ✅
  - findAll() mit Filter (typ, aktiv) + LEFT JOIN anzahl_faecher
  - findById(), insert(), update(), setAktiv()
  - findMietfaecherByPartner(), insertMietfach(), updateMietfach()
- `src/modules/partner/PartnerService.php` ✅
  - save(), aktualisiere(), setAktiv()
  - saveMietfach(), aktualisiereMietfach()
  - Validierung: Name/Typ Pflicht, E-Mail-Format, Provision ≥ 0, Mietende > Mietbeginn

## Status: ✅ VOLLSTÄNDIG (2026-06-21)

### Gebaut
- `public/partner/liste.php` — Partner-Liste, Neu+Bearbeiten Modal, Typ-Chips, Auto-Beleg-Typ
- `public/partner/mietfaecher.php` — Physische Fächer-Übersicht, Status Frei/Belegt, Vermieten/Kündigen
- `public/partner/speichern.php`, `aktualisieren.php`, `status_setzen.php`
- `public/partner/fach_speichern.php`, `fach_aktualisieren.php`
- `public/partner/vertrag_speichern.php`, `vertrag_beenden.php`
- `src/modules/partner/MietfachRepository.php` + `MietfachService.php`
- shell_top.php: Partner + Mietfächer in Sidebar + Top-Nav

### Noch offen
- `public/partner/abrechnung/` — Monatsabschluss + PDF (braucht Verkauf/Kasse als Datenquelle)

**Why:** Abrechnung erst sinnvoll wenn Verkaufsmodul Daten liefert (welche Artikel wurden pro Partner verkauft).

**How to apply:** Nächster Partner-Schritt = Abrechnung, nach Auftragsmodul/Kasse.
