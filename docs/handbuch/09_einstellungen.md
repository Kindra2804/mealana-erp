# 09 — Einstellungen

## Navigation

Oben rechts: ⚙ Einstellungen

Es gibt vier Bereiche:

| Tab | Inhalt |
|-----|--------|
| **Firma** | Firmenname, Adresse, Logo — erscheinen auf PDFs |
| **Kanäle** | WooCommerce-Shops verwalten |
| **Mail/SMTP** | E-Mail-Versand konfigurieren |
| **System** | Preisanzeige, Kleinunternehmer-Modus, PLC-Ordner |

---

## Firmendaten (Tab: Firma)

Diese Daten erscheinen auf allen Dokumenten (Rechnung, Lieferschein, Auftragsbestätigung):

- Firmenname
- Adresse (Straße, PLZ, Ort, Land)
- Telefon, Fax, E-Mail, Website
- UID-Nummer, Steuernummer
- Bankverbindung (Name, IBAN, BIC)
- **Logo:** Bild hochladen (wird oben links auf PDFs angezeigt)

> **Wichtig:** Diese Felder müssen ausgefüllt sein bevor die ersten Rechnungen erstellt werden!

---

## Kanäle / Shops (Tab: Kanäle)

Hier werden WooCommerce-Shops verwaltet:

- **Shop hinzufügen:** Name, URL, API-Schlüssel
- **Logo je Shop** hochladen
- Shops können aktiv/inaktiv geschaltet werden

---

## Mail / SMTP (Tab: Mail/SMTP)

Damit das System E-Mails senden kann (Auftragsbestätigungen, Mahnungen, Versandbenachrichtigungen):

| Feld | Beispiel |
|------|---------|
| SMTP-Host | `mail.gmx.net` oder `smtp.gmail.com` |
| SMTP-Port | `587` (Standard für TLS) |
| Benutzer | E-Mail-Adresse |
| Passwort | E-Mail-Passwort |
| Verschlüsselung | `tls` (empfohlen) |
| Absender-Name | `MEALANA KG` |
| Absender-Adresse | `shop@mealana.at` |
| **Mail aktiv** | Muss auf "Ja" stehen damit wirklich gesendet wird! |

### Test-Mail senden:

1. Alle Felder ausfüllen und **speichern**
2. E-Mail-Adresse für den Test eingeben
3. → **Test-Mail senden**
4. Postfach prüfen — kam die Mail an?

> Wenn keine Mail ankommt: SMTP-Daten mit dem E-Mail-Anbieter prüfen. Bei Gmail: App-Passwort verwenden (kein normales Passwort).

> **Mail aktiv = Nein:** Das System protokolliert intern dass eine Mail gesendet worden wäre, sendet aber nichts. Nützlich zum Testen ohne echte Mails zu senden.

---

## System-Einstellungen (Tab: System)

| Einstellung | Bedeutung |
|-------------|-----------|
| **Preisanzeige in Aufträgen** | Brutto oder Netto als Hauptanzeige |
| **Kleinunternehmer-Modus** | Kein Steuerausweis auf Rechnungen (§ 6 Abs. 1 Z 27 UStG) |
| **PLC-Polling-Ordner** | Pfad zum Ordner den der Paketdrucker (PLC) überwacht |

### PLC-Ordner konfigurieren:

1. Auf dem Computer nachschauen wo der PLC seinen Einlese-Ordner hat (z.B. `C:\PLC\polling\`)
2. Diesen Pfad hier eintragen
3. Speichern
4. Test: Einen Auftrag am Packplatz versenden — es sollte eine XML-Datei im Ordner erscheinen

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Firmendaten erscheinen nicht auf PDF | Gespeichert? Tab "Firma" → alle Felder ausfüllen → Speichern |
| Test-Mail kommt nicht an | SMTP-Einstellungen prüfen · "Mail aktiv" auf Ja? · Spam-Ordner prüfen |
| PLC druckt kein Etikett | PLC-Ordner-Pfad korrekt? Ordner existiert? PLC-Software läuft? |
