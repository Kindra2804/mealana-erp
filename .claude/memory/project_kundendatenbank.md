---
name: project-kundendatenbank
description: "Kundendatenbank Design — B2B/B2C, DSGVO, Verschlüsselung, Shop-Sync, Laufkunde, Merge (Stand 2026-06-19)"
metadata: 
  node_type: memory
  type: project
  originSessionId: b4bbae66-fc1a-4aeb-b217-8ae73d4fe662
---

## Entscheidungen (2026-06-19)

### B2B gleich einbauen
Alle B2B-Felder (UID-Nummer, Kreditlimit, Zahlungsbedingungen) kommen von Anfang an rein.
**Why:** Nachträglicher Einbau bei vielen Feldern ist teuer. Besser einmal richtig.

### Multi-Shop Matching
Ein Kunde = ein ERP-Datensatz, auch wenn er sich in mehreren Shops registriert.
Match-Logik: primär via E-Mail-Hash (exakter Match), sekundär manuell.
**Manueller Merge-Bereich geplant:** Eigener Admin-Screen für Datensätze mit mehreren Übereinstimmungen (gleicher Name + Adresse aber andere Mail o.ä.) → manuelle Zusammenführung.
**Why:** Datenkonsistenz — kein Kunde soll doppelt in der DB stehen.

### Laufkunde
EIN globaler "Laufkunde"-Datensatz (kein echter Kunde).
- Kanal wird gespeichert (Kasse / Messe / etc.)
- Abrechnung anonym (kein Kundenkopf auf Rechnung)
- Kasse: Standard ist Laufkunde; Stammkunde vorher raussuchen + aktiv zuweisen
- Keine E-Mail, kein Login, kein Shop-Account
**How to apply:** laufkunde = 1 Flag auf kunden-Tabelle ODER eigener Datensatz mit id=1 als Systemkonstante.

### Shops-Referenz
- WooCommerce ist der Erstanlauf
- Eigener Shop ist das finale Langzeit-Ziel (komplett selbst gebaut, für maximale Kontrolle)
- "Freunde" (andere Instanzen) nutzen ggf. dauerhaft WooCommerce
- kunden_shops-Tabelle muss beide Szenarien unterstützen

---

## Datenbank-Struktur

### Tabelle: kunden
```sql
-- Operativ (unverschlüsselt)
id, kundennummer, status (aktiv/gesperrt/geloescht),
kundengruppe_id FK, zahlungsbedingung_id FK NULL,
standardzahlungsart VARCHAR,
kreditlimit DECIMAL(10,2) NULL,          -- B2B
sprache VARCHAR(5) DEFAULT 'de',
kundenherkunft ENUM(shop, messe, empfehlung, walkin, kasse),
ist_laufkunde TINYINT(1) DEFAULT 0,
ist_firma TINYINT(1) DEFAULT 0,
erstellt_am TIMESTAMP, aktualisiert_am TIMESTAMP

-- Verschlüsselt (AES-256-GCM, PHP-seitig)
vorname_enc, nachname_enc,
firmenname_enc NULL,
email_enc, email_hash,      -- email_hash = HMAC-SHA256 für Suche
telefon_enc NULL, mobil_enc NULL,
geburtsdatum_enc NULL,
uid_nummer_enc NULL,        -- B2B: UID/VAT-Nummer
notiz_enc NULL
```

### Tabelle: kunden_adressen
```sql
id, kunde_id FK,
adresstyp ENUM(haupt, rechnung, lieferung),
ist_standard TINYINT(1) DEFAULT 0,
-- verschlüsselt:
firma_enc NULL, vorname_enc, nachname_enc,
strasse_enc, hausnummer_enc,
plz_enc, ort_enc,
land VARCHAR(2) DEFAULT 'AT',  -- ISO, NICHT verschlüsselt (für Logik)
zusatz_enc NULL
```

### Tabelle: kunden_shops (Sync-Verknüpfung)
```sql
id, kunde_id FK, shop_id FK,
external_id VARCHAR(255) NULL,   -- WooCommerce user_id
sync_status ENUM(pending, synced, error) DEFAULT 'pending',
synced_at TIMESTAMP NULL,
fehler_meldung TEXT NULL
UNIQUE KEY uq_kunde_shop (kunde_id, shop_id)
```

### Tabelle: kunden_dsgvo_consent
```sql
id, kunde_id FK,
consent_typ ENUM(newsletter, marketing, profiling),
eingewilligt TINYINT(1),
eingewilligt_am TIMESTAMP,
quelle ENUM(shop, messe, kasse, erp_manuell, telefon),
ip_adresse VARCHAR(45) NULL,    -- bei Online-Consent
widerrufen_am TIMESTAMP NULL,
kommentar VARCHAR(255) NULL
```

### Tabelle: kunden_merge_queue (Merge-Bereich)
```sql
id,
kunde_a_id FK, kunde_b_id FK,
erkannt_am TIMESTAMP,
erkennungsgrund VARCHAR(255),   -- "gleiche E-Mail in Shop 2" etc.
status ENUM(offen, gemerged, abgelehnt),
bearbeitet_von INT NULL FK → benutzer,
bearbeitet_am TIMESTAMP NULL
```

---

## Verschlüsselung

**Methode: AES-256-GCM** — PHP-seitig, NICHT MySQL AES_ENCRYPT()
**Key:** in `.env` als `ENCRYPTION_MASTER_KEY` (256-bit hex), NICHT in DB
**Pro Record:** zufälliger IV (16 Byte), wird als Präfix des verschlüsselten Blobs gespeichert
**E-Mail-Suche:** `email_hash = HMAC-SHA256(strtolower(email), ENCRYPTION_SEARCH_KEY)`
**Suchbarkeit Name:** Daten in PHP laden, dort filtern (für 10k Kunden OK)

### Warum NICHT Blowfish
- 64-bit Blocksize (Birthday-Attack bei großen Datenmengen)
- bcrypt = Einweg-Hashing, kein Entschlüsseln möglich
- Nicht für symmetrische Datenverschlüsselung geeignet

### Warum NICHT MySQL AES_ENCRYPT()
- Key erscheint in SQL-Query → landet in MySQL-Query-Log, Monitoring, Crash-Dumps

### DSGVO Crypto-Shredding
Bei Löschantrag (Art. 17 DSGVO): nicht Daten löschen, sondern **Kunden-Key wegwerfen**.
Daten bleiben als unlesbarer Ciphertext → rechtlich als gelöscht anerkannt.
Transaktionsbelege (Rechnungen) bleiben erhalten (gesetzliche Aufbewahrungspflicht 7 Jahre),
sind aber nicht mehr dem Menschen zuordenbar.

**How to apply:** PHP-Klasse `Encryption` kapselt alles. Alle Module benutzen nur die Klasse, nie direkt openssl_encrypt().

---

## Shop-Sync Szenarien

| Szenario | Richtung | Aktion |
|---|---|---|
| Kunde registriert sich im Shop | Shop → ERP | Neu anlegen ODER matchen via email_hash; DSGVO-Consent speichern |
| Kunde ändert Adresse im Shop | Shop → ERP | Adresse aktualisieren |
| Kunde im ERP angelegt (Messe) | ERP → Shop | WooCommerce-Account anlegen via REST API (wenn E-Mail vorhanden); WooCommerce schickt Passwort-Reset selbst |
| DSGVO-Löschung | ERP beides | ERP anonymisieren + WooCommerce User löschen via API |

**How to apply:** Webhook-Handler + REST-API-Client für WooCommerce. Webhook-Endpoint im ERP empfängt customer.created / customer.updated Events.

---

## Was noch offen ist
- Zahlungsbedingungen-Tabelle (analog Lieferanten, wird geteilt)
- Ansprechpartner bei Firmenkunden (kunden_ansprechpartner, analog lieferanten_vertreter)
- Kreditlimit-Prüfung: wo greift sie? (Auftragsanlage + Kasse)
- Treuepunkte / Stammkunden-Badge: erst wenn Verkaufshistorie steht
