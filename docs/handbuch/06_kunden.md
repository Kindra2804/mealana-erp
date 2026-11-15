# 06 — Kundendatenbank

## Konzept

Die Kundendatenbank speichert alle Privat- und Geschäftskunden. Datenschutz-sensible Felder (Adressen, Kontaktdaten) sind verschlüsselt gespeichert (DSGVO-konform).

**Kundentypen:**
- **Privatkunde (B2C)** — normaler Endkunde
- **Geschäftskunde (B2B)** — mit Firmendaten und UID-Nummer
- **Laufkunde** — anonymer Käufer (kein Kundenkonto)

---

## Kunden suchen

**Navigation:** Kunden → Kundenliste

**Suche:** Name, E-Mail-Adresse, Telefon, Kundennummer

---

## Neuen Kunden anlegen

**Navigation:** Kunden → Neuer Kunde

1. **Typ** wählen: Privat oder Geschäft (B2B)
2. **Vorname + Nachname** eingeben (bei B2B: Firmenname)
3. **E-Mail** eingeben (für Auftragsbestätigungen und Mahnungen wichtig!)
4. **Adresse** eingeben (Rechnungsadresse)
5. Optional: Abweichende **Lieferadresse**
6. Bei B2B: **UID-Nummer** und Firmendaten
7. → Speichern

---

## Kunden bearbeiten

1. Kunden in der Liste anklicken
2. **Bearbeiten**-Button
3. Felder ändern
4. Speichern

> **Wichtig:** Wenn ein Auftrag bereits angelegt wurde, sind die Kundendaten darin **eingefroren** (Snapshot). Änderungen am Kundenstamm betreffen nur zukünftige Aufträge, nicht bestehende.

---

## Shop-Kunden (WooCommerce)

Kunden die über den Webshop bestellen werden automatisch importiert oder mit bestehenden Kunden verknüpft. Verknüpfung erfolgt über die E-Mail-Adresse.

---

## Datenschutz / DSGVO

Auf Kundenwunsch können alle persönlichen Daten gelöscht werden ("Recht auf Vergessenwerden"). Das System löscht die Daten kryptografisch — Bestellhistorie bleibt erhalten, Kundendaten werden anonymisiert.

> Für Löschung: Kunden-Detailseite → "Kunden löschen (DSGVO)"

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Doppelter Kunde | Suche nach E-Mail — Duplikate zusammenführen (Merge-Funktion) |
| Kunde aus WooCommerce nicht vorhanden | Wurde der Auftrag-Import durchgeführt? Shop-Sync prüfen |

## Newsletter-Empfänger (Kunden → 📧 Newsletter)

Empfänger werden **je Shop** geführt (eine Adresse kann in mehreren Shops stehen). E-Mail und Name sind verschlüsselt
gespeichert wie bei den Kunden. Status: **bestätigt** (darf Newsletter bekommen), **wartet auf Bestätigung**
(Double-Opt-in noch offen), **abgemeldet** (bleibt stehen, damit die Adresse nie wieder aktiv wird).

- **Nachweis:** je Empfänger alle Ereignisse mit Zeitpunkt und IP (angemeldet, bestätigt, abgemeldet, importiert) —
  der Einwilligungsnachweis nach DSGVO/TKG.
- **Abmelden von Hand** (z.B. Kunde ruft an): Knopf „Abmelden“.
- **Import aus dem JTL-Shop:** ⤓ Import aus JTL-Shop → dort steht die fertige Abfrage für phpMyAdmin (Shop-Datenbank,
  Reiter SQL). Ergebnis als CSV exportieren (Haken „Spaltennamen in die erste Zeile“), Shop wählen, **Probelauf**,
  dann **Jetzt importieren**. Übernommen werden bestätigte Abonnenten mit Opt-in-Datum und -IP aus JTL; Adressen der
  JTL-Blacklist kommen als „abgemeldet“ und setzen auch schon vorhandene auf abgemeldet. Doppelt importieren schadet
  nicht. Die CSV danach löschen (personenbezogene Daten).
- Recht: `newsletter.verwalten` (Admin, Assistent, Manager).

