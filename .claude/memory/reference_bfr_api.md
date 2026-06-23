---
name: reference-bfr-api
description: "BFR BONit Fiscal Recorder – RKSV-Signatur-API (lokale Signaturkarte, AT, offline-tauglich)"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 7c2206d0-2966-4b33-8077-f725a9bdff96
---

## BFR BONit Fiscal Recorder — RKSV Schnittstelle

Lokaler Dienst am PC (mit Signaturkarte), spricht XML über HTTP.
Kein Cloud-Aufruf nötig → 100% offline-tauglich → ideal für Messe-Kasse.

**Basis-URL:** `http://127.0.0.1:8787` (oder IP des BFR-PCs im lokalen Netz)

---

## Startup-Check

`GET /state` → prüfen ob BFR läuft UND ob `<RN>` mit der konfigurierten Kassen-ID übereinstimmt (Pflicht laut Doku).

```xml
<State>
  <RN>BFR0226</RN>           <!-- Kassen-ID — muss mit unserer Kasse übereinstimmen -->
  <SigCount>152</SigCount>   <!-- Bisherige Signaturen gesamt -->
  <SC>ATU65033000:AT1:5619064c</SC>
  <Recorder>LocalCard</Recorder>
  <Online>BFR</Online>
  <Company>ATU65033000</Company>
</State>
```

Wenn `<Link>` bei einer Transaktion "Sicherheitseinrichtung ausgefallen" zurückgibt → Ausdruck auf Beleg Pflicht.

---

## Transaktion signieren

`POST /register` mit XML-Body:

```xml
<Tra>
  <ESR D='2026-02-17T11:52:55' TN='23' T='9.80'>
    <TaxA>
      <Tax TaxG='A' Amt='9.80'/>
    </TaxA>
  </ESR>
</Tra>
```

| Attribut | Bedeutung |
|---|---|
| `D` | Datum+Uhrzeit ISO 8601 mit T-Trenner (`2026-02-17T11:52:55`) |
| `TN` | Belegnummer — aufsteigend, einmalig, numerisch oder alphanumerisch |
| `T` | Belegsumme brutto (Summe aller Tax-Gruppen) |
| `TaxG` | Steuergruppe: A/B/C/D/E |
| `Amt` | Bruttobetrag für diese Steuergruppe |

**Steuergruppen (Stand Feb 2026, AT):**

| TaxG | Satz | Typische Verwendung |
|---|---|---|
| A | 20% | Standard (Wolle, Zubehör, ...) |
| B | 10% | Bücher, Lebensmittel |
| C | 13% | Kultur, Pflanzen |
| D | 0% | Steuerfreie Umsätze, Export |
| E | 19% / 4,9% | Spezial |

Bei Multi-Tax alle 5 TaxG-Einträge senden, ungenutzte mit `Amt='0.00'`.

**Antwort:**

```xml
<TraC SQ="1">
  <Result RC="OK"/>
  <Fis>
    <Code>_R1-AT1_BFR0226_23_...</Code>   <!-- QR-Code Inhalt → auf Beleg drucken -->
    <Link>ATU65033000</Link>               <!-- Steuerkennung ODER "Sicherheitseinrichtung ausgefallen" -->
  </Fis>
</TraC>
```

Nur `<Code>` (QR-Code) und `<Link>` sind relevant. Beide auf Beleg drucken.

---

## Nullbeleg

```xml
<Tra>
  <ESR D='2026-02-17T12:41:46' TT='BFR0226' TN='Nullbeleg260217124146'></ESR>
</Tra>
```

Kein `<TaxA>` nötig. Eigener Belegnummernkreis erlaubt (darf sich nicht mit normalen Belegen überschneiden).

---

## Was wir in der Kassendatenbank speichern müssen

Pro Beleg:
- `beleg_nr` (TN — unsere fortlaufende Kassennummer)
- `beleg_datum` (D)
- `betrag_brutto` (T)
- `steuer_a` / `steuer_b` / `steuer_c` / `steuer_d` / `steuer_e` (Amt je Gruppe)
- `qr_code` (Code aus Response — unveränderlich!)
- `link_text` (Link aus Response)
- `kassen_id` (RN aus /state)

## Kassen-Implementation Hinweise

- Belegnummer je Kasse getrennt führen (BFR0226 für Laden, anderer Name für Messe)
- Startbeleg (Nullbeleg) bei Tagesöffnung
- Jahresbeleg (Nullbeleg) am 31.12.
- `/state` beim App-Start aufrufen und RN validieren
- BFR-Erreichbarkeit: `http://127.0.0.1:8787` (lokal) oder IP im LAN (z.B. Messe-PC greift auf Server-PC zu)
- Für Messe offline: BFR-Dienst + Karte direkt am Messe-Laptop installiert → kein Netz nötig
