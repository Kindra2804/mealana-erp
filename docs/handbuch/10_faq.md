# 10 — Häufige Fragen (FAQ)

## Allgemein

**Ich habe etwas falsch eingegeben und gespeichert — kann ich das rückgängig machen?**  
Meistens ja: einfach nochmal öffnen und korrigieren. Beim Lager ist das leider nicht möglich — dort wird jede Buchung protokolliert und kann nicht gelöscht werden. Ein Fehler beim Wareneingang wird durch eine manuelle Gegenbuchung korrigiert.

---

**Das System lädt sehr langsam oder gar nicht.**  
Zuerst prüfen ob der Server (Laragon / XAMPP) läuft. Dann Browser-Cache leeren (Strg+F5). Wenn es nur bestimmte Seiten betrifft: JavaScript-Fehler in der Browser-Konsole prüfen (F12 → Console).

---

**Ich sehe einen roten Fehler-Banner — was tun?**  
Den Text im roten Banner lesen — er beschreibt meist genau was fehlt (Pflichtfeld leer, Wert schon vorhanden, etc.). Wenn unklar: Screenshot machen und nachfragen.

---

## Artikel

**Warum kann ich die Artikelnummer nicht ändern?**  
Artikelnummern sind nach dem Anlegen gesperrt — sie werden als eindeutige Kennung in Aufträgen, Lagerbewegungen und Dokumenten verwendet. Bei einem Fehler: Artikel kopieren (mit neuer Nummer), alten deaktivieren.

---

**Ein Kind-Artikel hat einen anderen Preis als der Vater — warum?**  
Kind-Artikel können eigene Preise haben (z.B. größere Menge = anderer Preis). Wenn kein eigener Preis gesetzt ist, gilt der Vater-Preis. Prüfen unter: Kind-Artikel → Tab Preise.

---

**Warum erscheint der SALE-Preis nicht im Shop?**  
WooCommerce-Sync läuft noch nicht vollautomatisch. Nach einer Preisänderung muss der Sync manuell angestoßen werden (oder der nächste automatische Sync läuft durch).

---

## Lager

**Der Bestand zeigt 5 an, aber wir haben nur 3 physisch auf Lager.**  
Im Lagerbewegungsprotokoll nachschauen wann der letzte Wareneingang war. Eventuell wurde eine falsche Menge eingebucht. Korrektur: Neuen Wareneingang mit der Differenz als Abgang buchen (negative Menge — Absprache mit Jacky nötig).

---

**Was ist der Unterschied zwischen "reserviert" und "verfügbar"?**  
- **Ist:** Physisch vorhanden
- **Reserviert:** Für offene Aufträge vorgemerkt (noch nicht versendet)
- **Verfügbar:** Ist minus Reserviert — das kann noch verkauft werden

---

## Aufträge

**Ein Kunde hat bezahlt aber der Status ist noch "ausstehend".**  
Zahlung muss manuell gebucht werden: Auftrag öffnen → "Zahlung buchen". Bei WooCommerce-Aufträgen: Status-Sync prüfen.

---

**Ich habe einen Auftrag storniert aber der Lagerstand wurde nicht zurückgebucht.**  
Das passiert automatisch — aber nur wenn der Lagerabgang vorher auch gebucht war. Bei Vorkasse-Aufträgen wird der Abgang erst bei Zahlungseingang gebucht. Wenn vorher storniert: kein Abgang → keine Rückbuchung nötig.

---

**Die Mahnung wurde nicht gesendet.**  
Prüfen: 1) Einstellungen → Mail/SMTP → "Mail aktiv" auf Ja? 2) Hat der Kunde eine E-Mail-Adresse? 3) Mahnungs-Tabelle prüfen (technisch: `mahnungen`-Tabelle).

---

## Packplatz

**Der "Verpacken"-Button ist grau und lässt sich nicht klicken.**  
Noch nicht alle Positionen grün. Entweder wurden noch nicht alle Artikel gescannt, oder eine Position wurde zu oft gescannt (rot markiert). Rote Zeilen prüfen.

---

**Scanner piept aber nichts passiert.**  
Cursor muss im Scan-Feld sein (blaues Eingabefeld oben). Einmal ins Feld klicken, dann scannen.

---

**Das EasyPak-Etikett wird nicht gedruckt.**  
1. PLC-Software gestartet? 2. PLC-Ordner in den Einstellungen korrekt eingetragen? 3. Besteht eine Verbindung zur Österreichischen Post (Internet)?

---

## Mahnwesen

**Ich möchte eine Mahnung für einen bestimmten Auftrag manuell senden.**  
Derzeit nur über den Cronjob automatisch. Manuelle Mahnung: E-Mail direkt aus dem E-Mail-Programm senden und im Auftrags-Notizfeld dokumentieren.

---

## Einstellungen

**Ich habe das SMTP-Passwort geändert aber Mails kommen nicht mehr an.**  
Einstellungen → Mail/SMTP → Passwort neu eingeben (wird aus Sicherheit nicht angezeigt) → Speichern → Test-Mail senden.

---

## Ich finde etwas nicht / Funktion fehlt

Prüfen ob das Modul schon fertig ist: [Modul-Übersicht](../workflows/modul_uebersicht.md).  
Wenn die Funktion noch als "TODO" markiert ist: sie wird in einer zukünftigen Version kommen.
