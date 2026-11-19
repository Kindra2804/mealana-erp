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

## JTL-Import Kunden + Aufträge (Kunden → ⬇ JTL-Import)

Holt Kunden und alte Aufträge aus JTL-Wawi ins ERP – beliebig oft wiederholbar, solange MeaLana-Shop und Laden noch auf JTL laufen. **Vorher eine Datenbank-Sicherung machen.**

1. **Kundendaten-Export** aus JTL hochladen → **🔍 Probelauf** (zeigt, was passieren würde) → **⬇ Import starten**. Schon importierte Kunden (JTL-Kundennummer) werden übersprungen, Kunden mit gleicher E-Mail (z.B. aus den Woo-Shops) nur verknüpft.
2. **Auftrags-Export** hochladen → Probelauf → Import. Die Aufträge kommen als **Archiv** (Kanal „Archiv“, Lieferstatus abgeschlossen) mit den Preisen von damals. Schon importierte werden übersprungen, Angebote ausgelassen. Fehlt ein Artikel im ERP, wird ein inaktiver Platzhalter `JTL-<Artikelnummer>` angelegt.
3. **Zahlung:** Aufträge ohne Zahlungseingang in JTL, die **älter als 1 Jahr** sind, gelten als bezahlt (meist an der Kasse bezahlt, in JTL nie nachgetragen). **Jüngere** stehen danach in der **Prüfliste** unten auf der Seite: alle sind als erledigt angehakt – nur die **wirklich noch offenen** abhaken und **✓ Speichern**. Angehakte werden bezahlt, die anderen bleiben „ausstehend“.

Hinweise:
- Die Datei wird in Stücken hochgeladen und abschnittsweise verarbeitet – auch der 90-MB-Voll-Export läuft ohne Einstellungen am Server. Bricht etwas ab: Seite neu laden → **fortsetzen**, nichts wird doppelt angelegt.
- Wird eine Datei im falschen Feld hochgeladen, meldet das die Seite sofort.
- Nach dem Import wird die hochgeladene Datei gelöscht (Kundendaten). Ein hochgeladener, noch nicht importierter Lauf lässt sich mit 🗑 verwerfen.
- Für Admins geht es weiterhin auch über die Kommandozeile: `php scripts/jtl_archiv_import_kunden.php <datei> [--dry-run]`, dann `jtl_archiv_import_auftraege.php`.

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
  Reiter SQL). Ergebnis exportieren — als CSV (Haken „Spaltennamen in die erste Zeile“) oder als SQL, Shop wählen, **Probelauf**,
  dann **Jetzt importieren**. Übernommen werden bestätigte Abonnenten mit Opt-in-Datum und -IP aus JTL; Adressen der
  JTL-Blacklist kommen als „abgemeldet“ und setzen auch schon vorhandene auf abgemeldet. Doppelt importieren schadet
  nicht. Ältere JTL-Abonnenten ohne Opt-in-Datum werden als bestätigt übernommen (Nachweis: „ohne Opt-in-Datum
  aus JTL“). Die CSV danach löschen (personenbezogene Daten).
- Recht: `newsletter.verwalten` (Admin, Assistent, Manager).

### Anmelden, bestätigen, abmelden (über die Belegseite)

- **Anmeldeformular:** Kunden → 📧 Newsletter → **🧩 Anmeldeformular** zeigt für den gewählten Shop den fertigen Code:
  für WooCommerce-Shops den Shortcode `[nahtlos_newsletter …]` (NAHTLOS Connector ab 1.8.0, als Shortcode-Block
  einfügen), für andere Shops (z.B. JTL, mealana.at) ein HTML-Stück zum Einfügen. Das Formular schickt an die
  Belegseite (Einstellungen → System → Paperless: Adresse + Schlüssel müssen eingetragen sein).
- **Ablauf:** Anmeldung → binnen etwa 15 Minuten kommt die **Bestätigungs-Mail** vom Postfach des Shops →
  Klick auf „Ja, Anmeldung bestätigen“ → Status „bestätigt“ (mit Zeitpunkt + IP im Nachweis). Wer nicht klickt,
  bekommt nie einen Newsletter. Abmelden über den Link in jedem Newsletter oder direkt im Mailprogramm.
- **Von Hand eintragen** (z.B. Kundin im Laden): ➕ Empfänger eintragen → E-Mail, Vorname, wie zugestimmt wurde →
  die Bestätigungs-Mail geht sofort raus.
- **Website + Hausfarbe** je Shop: Einstellungen → Kanäle (für Formular, Bestätigungs-Mail und die Seiten der Belegseite).

### Newsletter verfassen (Kunden → ✉️ Newsletter verfassen)

- **Neu:** Shop wählen (dahinter steht die Zahl der bestätigten Empfänger), internen Namen eingeben → ➕ Neuer Newsletter.
  Der Newsletter hat Logo, Hausfarbe, Website und Firmenadresse des Shops; ein Text-, ein Artikel- und ein Knopf-Baustein sind schon da.
- **Betreff + Vorschautext:** der Vorschautext steht im Postfach neben dem Betreff.
- **Bausteine:** 🖼 Kopfbild · 📝 Text (Überschrift + Text) · 🏞 Bild (mit Link + Bildunterschrift) · 🧶 Artikel ·
  🔘 Knopf · 🎁 Aktion/Gutschein (Kasten mit Code) · ➖ Trenner. Mit ↑ ↓ verschieben, ✕ entfernen.
- **Artikel:** Name oder Artikelnummer suchen und anklicken — Bild, aktueller Preis (Aktionspreis mit
  durchgestrichenem Normalpreis) und Link zum Shop kommen automatisch aus dem ERP, 1–3 Spalten.
- **Im Text:** `{vorname}` wird zum Vornamen (fehlt er, fällt es weg, z.B. „Hallo!“), `**fett**`, Leerzeile = neuer Absatz.
- **Bilder** werden beim Hochladen auf höchstens 1200 px verkleinert.
- **💾 Speichern + Vorschau** zeigt rechts genau die Mail. **✉ Testmail** schickt sie mit „[TEST]“ im Betreff an
  die eingetragene Adresse (vorbelegt: deine eigene) — über das Postfach des Shops.
- **Liste:** Kopieren (z.B. Vorlage für den nächsten Monat), Löschen nur bei Entwürfen. Verschickte Newsletter
  lassen sich nicht mehr ändern, nur kopieren.

### Newsletter verschicken

- **🚀 Versand starten** (im Editor) geht erst nach einer Testmail. Die Rückfrage nennt die Zahl der bestätigten
  Empfänger und die ungefähre Dauer. Ab dann ist der Newsletter gesperrt; Preise und Bilder werden mit dem Stand
  beim Start verschickt.
- Die Mails gehen **nach und nach** raus: höchstens **100 pro Stunde je Postfach** (Shops mit eigenem Postfach
  zählen getrennt), gleichmäßig verteilt — Cron „Newsletter-Versand“ alle 5 Minuten. Mailversand muss
  eingeschaltet sein (Einstellungen → Mail). Die Grenze steht in der Einstellung `newsletter_max_pro_stunde`.
- Wer sich während des Versands abmeldet, wird übersprungen. Jede Mail hat einen persönlichen Abmeldelink und die
  **Ein-Klick-Abmeldung** im Mailprogramm (Gmail/Outlook zeigen dafür „Abbestellen“ neben dem Absender).
- Bilder liegen auf der Belegseite (die Mail bleibt klein), Links laufen über die Belegseite und werden **gezählt —
  nur die Summe je Link, nicht wer geklickt hat** (keine Einwilligung nötig). Manche Mailprogramme prüfen Links
  automatisch; das kann ein paar Klicks dazuzählen.
- Im Editor oben: Fortschritt („2 von 3 verschickt“), Fehler, übersprungen, Klicks und „Klicks je Link“.
  **⏸ Anhalten / ▶ Fortsetzen**, **Versand beenden** (der Rest bekommt ihn dann nicht). Eine Mail, die nicht
  rausgeht, wird bis zu 3-mal versucht.

### Zielgruppen (Segmente) — vorbereitet, noch ausgeblendet

- Einschalten: Kunden → ✉️ Newsletter verfassen → Häkchen **🎯 Zielgruppen (Segmente) anbieten**. Dann hat jeder
  Entwurf oben die Karte **🎯 Empfänger**; ohne Auswahl bekommen ihn wie bisher alle bestätigten Empfänger.
- Bedingungen (alle gesetzten müssen zutreffen, leer = egal; Mehrfachauswahl mit Strg): **Kundenkonto**
  (nur Kunden / nur Nicht-Kunden), **Land**, **Kundengruppe**, **angemeldet seit**, **gekauft aus Kategorie**
  (inkl. Unterkategorien) bzw. **von Hersteller** in den letzten N Monaten, **letzter Kauf** innerhalb bzw.
  länger her als N Monate („Schläfer“).
- **Empfänger zählen** zeigt „X von Y bestätigten Empfängern“. Die Versand-Rückfrage nennt die Zielgruppe,
  die Liste markiert solche Newsletter mit 🎯.
- Kunde = gleiche E-Mail-Adresse wie im Kundenstamm (wird vor dem Zählen/Versand neu abgeglichen). Käufe aus allen
  Kanälen inkl. JTL-Archiv, ohne Stornos.

