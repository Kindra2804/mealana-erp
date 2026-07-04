# Offline-Kasse (Messe) — Anleitung & bekannte Grenzen

Stand: 2026-07-04 (überarbeitet nach Praxis-Feedback). Betrifft den Messe-Workflow:
`messe_vorbereiten.php` → `bon_offline.php` → `messe_rueckkehr.php`.

## Voraussetzungen (einmalig einrichten)

1. **Messe-Lager anlegen** (falls noch nicht vorhanden) — Typ `messe`, z.B. "Messestand".
2. **Offline-Kasse anlegen**: Einstellungen → Kassen → Neue Kasse
   - Modus: `offline`
   - Lager: das Messe-Lager
   - `bfr_url`: die Adresse des BFR-Dienstes am Messe-Laptop (üblicherweise `http://127.0.0.1:8787`, wenn der BFR direkt am selben Rechner hängt)
   - RKSV-Kassen-ID: wie bei Kasse 1, über die Kassen-Registrierungsseite (Einstellungen → Kassen → Registrierung)
3. Das BFR-Gerät (Signaturkarte + Kartenleser) muss am Messe-Laptop selbst angeschlossen sein, nicht am Hauptserver.

## Ablauf am Messetag

**Vorabend, noch im Firmennetz (WLAN/LAN):**
1. `Kasse → 🎪 Messe → Zur Messe vorbereiten` — Ziel-Kasse + Ziel-Lager wählen, Artikel scannen. Bei chargenpflichtigen Artikeln öffnet sich eine Chargen-Auswahl — es kann nur aus tatsächlich vorhandenen Chargen im Hauptlager gewählt werden.
2. "Umbuchung durchführen" — bucht die gewählten Artikel/Chargen ins Messe-Lager um
3. Unten erscheint das neue Sync-Paket → "Offline-Kasse laden →" klicken
4. Auf der Offline-Kasse-Seite: **"📥 Sync-Daten laden"** klicken — lädt Artikel/Chargen/Preise/Kassenkonfiguration in die lokale Browser-Datenbank (IndexedDB) und registriert den Service Worker (cached die Seite selbst für den Offline-Fall)
5. Status-Punkt oben rechts sollte auf **grün / "Bereit (offline-fähig)"** wechseln
6. Ab jetzt: Laptop darf vom Netz getrennt und heruntergefahren werden.

**Am Messestand (ohne Netzverbindung zum Server, ggf. mehrere Tage):**
- Verkäufe wie gewohnt tätigen — Scannen **oder Artikelname eingeben zum Suchen** (ab 2 Zeichen), Zahlart wählen, abschließen
- Chargenpflichtige Artikel: Dropdown zeigt nur die tatsächlich mitgenommenen Chargen mit noch verfügbarer Menge — kein Verkauf über den mitgenommenen Bestand hinaus möglich
- Fehlt ein Artikel im Sortiment (vergessen zu scannen o.ä.): **"➕ Freier Artikel"** — Bezeichnung, Preis, Steuersatz frei eintragen
- Jeder Verkauf wird direkt gegen das BFR-Gerät signiert (kein Server nötig, Browser spricht das Gerät direkt an)
- Ist BFR kurz nicht erreichbar: Verkauf läuft trotzdem durch, Beleg zeigt "Sicherheitseinrichtung ausgefallen" und wird später automatisch nachsigniert
- **Browser stürzt ab / Laptop wird neu gestartet:** Seite kann jetzt auch ohne jede Serververbindung neu geöffnet werden (Service Worker liefert die App-Hülle aus dem Cache), IndexedDB-Daten (Artikel, Chargen, noch nicht hochgeladene Bons) bleiben unverändert erhalten

**Zurück im Firmennetz:**
1. Browser-Tab öffnen (falls geschlossen: über die letzte bekannte URL, z.B. Lesezeichen) → **"⤴ Bons hochladen"** klicken
2. `Kasse → 🎪 Messe → Von Messe zurück` — Sync-Paket wählen, pro **Charge** (nicht pro Artikel) "Zurück" und "Schwund" eintragen, "Rückkehr abschließen"
3. Bei mehrtägiger Messe: für jeden einzelnen Tag unter `Kasse → Kassenstand` einen Z-Bon nachträglich erzeugen (Datumsfeld im Z-Bon-Formular, leer = heute)

## Browser-Absturz / Neuladen ohne Server — jetzt gelöst

Ursprünglich musste der Browser-Tab durchgehend geöffnet bleiben (`bon_offline.php` war eine normale, vom Server ausgelieferte Seite — ohne Server kein Neuladen möglich). Das ist für einen mehrtägigen Messe-Einsatz nicht praktikabel und wurde behoben:

- Die Seite ist jetzt eine **rein statische Hülle** (keine serverseitig berechneten Werte mehr außer beim allerersten Laden) — ein **Service Worker** (`sw_bon_offline.js`) cached sie beim ersten Öffnen und liefert sie danach auch ganz ohne Netzwerkverbindung aus.
- IndexedDB-Daten lagen ohnehin schon unabhängig vom Tab auf der Festplatte — jetzt kommt man auch ohne Server wieder an die Seite selbst heran.
- **Wichtig:** Das Sync-Daten-Laden (Schritt 4 oben) muss weiterhin **einmal online** passieren — danach ist alles lokal.
- **Noch nicht live im Browser getestet** — nur die Logik verifiziert. Vor dem echten Einsatz einmal bewusst testen: Seite laden → Browser-DevTools → Netzwerk auf "Offline" stellen → Seite neu laden → sollte trotzdem funktionieren.

## On-/Offline-Umschaltung

Es gibt **keine automatische Umschaltung**. `bon.php` (Online-Kasse) und `bon_offline.php` (Offline-Kasse) sind zwei getrennte Seiten. Der `modus`-Wert einer Kasse ist aktuell nur eine Kennzeichnung (zeigt z.B. das „MESSEBETRIEB"-Badge), kein Router. Personal muss wissen: Offline-Kasse → über „🎪 Messe" gehen.

## Was geht offline (noch) nicht, das online schon geht

| Funktion | Online (bon.php) | Offline (bon_offline.php) |
|---|---|---|
| Kundensuche/-zuordnung | ✅ | ❌ (immer Laufkunde — Kundendaten-Verschlüsselung läuft serverseitig, technisch nicht möglich) |
| Gutschein als Zahlart | ✅ (Zahlungsart-Stub) | ❌ (Gutschein-Modul existiert generell noch nicht) |
| Storno | ✅ | ❌ (nur Verkauf, kein Rückgabe-/Storno-Flow) |
| Schnellwahl-Buttons | ✅ (9 Slots) | ❌ — Daten werden beim Pre-Sync mitgeliefert, aber noch keine UI dafür gebaut |
| Freier Artikel (Platzhalter 99-9999) | ✅ | ✅ **behoben** — "➕ Freier Artikel"-Button |
| Textsuche nach Artikelname | ✅ | ✅ **behoben** — ab 2 Zeichen |
| Charge wählen | ✅ (Dropdown mit Bestands-Chargen) | ✅ **behoben** — Dropdown der tatsächlich mitgenommenen Chargen, lokal mengenbegrenzt |
| Browser-Neuladen ohne Server | — (Server ja immer da) | ✅ **behoben** — Service Worker cached die App-Hülle |
| Z-Bon für vergangene Tage | ✅ (immer "heute") | ✅ **behoben** — Datumsfeld im Z-Bon-Formular, für jeden Messetag einzeln nach Rückkehr |
| Bon parken | ✅ (DB-Warteschlange) | ❌ — architektonisch einfach möglich, aber noch nicht umgesetzt |
| Kombi-Zahlung (Bar+Karte gemischt) | ✅ | ❌ (nur Bar ODER Karte) |
| Rabatt auf Position | ✅ | ❌ (Datenfeld vorhanden, aber keine Eingabe-UI) |
| Reservierungs-Überverkauf-Warnung | ✅ | ❌ |
| X-Bon, Kassenbuch | ✅ | ❌ (nicht Teil der Messe-Kasse; Z-Bon siehe oben) |
| Kassenlade ansteuern | ✅ (ESC/POS über Server) | ❌ (keine Server-Verbindung für den Ansteuerungs-Befehl) |

**Bewusste, dauerhafte Design-Entscheidungen** (nicht "fehlt noch", sondern so gewollt): Kundendaten, Gutschein (solange Modul nicht existiert), reduzierter Funktionsumfang generell — die Messe-Kasse ist absichtlich ein schlankes Werkzeug, kein 1:1-Ersatz der Ladenkasse.

**Verbleibende MVP-Lücken** (könnten bei Bedarf nachgezogen werden): Schnellwahl-UI, Bon parken, Kombi-Zahlung, Rabatt-UI.

## Bekannte Lücken im Gesamtsystem (nicht offline-spezifisch, aber hier aufgefallen)

- **Keine Lager-Verwaltungs-UI**: neue Lager anlegen/bearbeiten geht aktuell nur per SQL. Wird spätestens für Lagerplätze gebraucht. Sollte dabei auch ein Flag "für Offline-Kassen auswählbar" bekommen, statt sich (wie aktuell in `messe_vorbereiten.php`) auf `lager.typ = 'messe'` zu verlassen.
- **Keine Nummernkreis-Verwaltung**: weder die Kassenbon-Nummern (pro Kasse) noch die Dokumenten-Nummern (`dokument_nummern`-Tabelle, global pro Typ+Jahr) sind irgendwo einsehbar oder konfigurierbar.
- **Divers-Platzhalter-Artikel (99-9999) fehlte in der Dev-Datenbank**: Migration 078 war als "angewendet" markiert, hatte aber wegen `INSERT IGNORE` beim ersten Lauf still nichts eingefügt. Manuell nachgetragen — bei anderen Installationen im Hinterkopf behalten, falls "Freier Artikel" (online wie offline) nicht funktioniert.
