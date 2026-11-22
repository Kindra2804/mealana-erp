# Umstellung: Laden + Kasse aufs ERP (mealana.at bleibt vorerst auf JTL)

Stand 22.11.2026 · für Jacky und Babsi · zum Abhaken

**Ziel:** Der Laden (Kasse, Lager) läuft im ERP, LS-POS fällt weg. Der Online-Shop mealana.at bleibt auf JTL, bis er selbst umzieht. JTL und ERP gleichen die Bestände über den **Lager → JTL-Abgleich** ab. Online-Bestellungen kommen per JTL-Import als Archiv-Aufträge ins ERP.

---

## A. Vorbereitung im Dev (vor dem Stichtag)

- [ ] **Babsi:** Vorschau *Artikel → ⬇ Import → JTL: neue Nummern* fertig durchsehen, „noch nicht geprüft“ = 0 (✔ seit 22.11.)
- [ ] **Hinweise klären**, die der Import sonst ablehnt (Vorschau → Hinweis-Chip anklicken):
  - [ ] „Nummer bei anderem Artikel“ (12, z.B. `G-723550`/`Gü-723550`, `PL-278500`/`PL 278500`, `WH30024`/`WJ30024`) → doppelten JTL-Artikel abhaken oder mit ✎ eigene Nummer
  - [ ] „Nummer doppelt“ (8 Väter, z.B. Lang MERINO 120, Kromski) → falsche Farbnummer: in JTL korrigieren oder ✎
  - [ ] „länger als 30 Zeichen“ (2 Workshop-Artikel) → ✎ kürzen
  - [ ] Vervaco: Kürzel beim Hersteller eintragen
- [ ] Gutscheine: **Liste** der JTL-/LS-POS-Gutscheine im Umlauf (Code, Betrag, Herkunft) – wie werden sie im Laden eingelöst? (siehe E)
- [ ] Steuerberatung: **Gegenkonto für „Bareingang zu JTL-Rechnung“** klären (Verrechnungskonto/Debitor JTL)

## B. Live einspielen (ein ruhiger Tag vor dem Stichtag)

- [ ] **Datenbank sichern** (macht der Update-Weg Pflicht)
- [ ] **Update-Paket** einspielen (Migrationen 258–263: Herstellerkürzel, Liste, Vorgaben, JTL-Bareingang)
- [ ] php.ini: `max_input_vars = 20000` (sonst schneidet PHP große Kontrolllisten still ab)
- [ ] **Babsis Liste übertragen:** im Dev `php scripts/datentransfer.php export --teile=jtl_liste` → Datei ins Live → `import --datei=… --probelauf` → passt? → ohne `--probelauf`
  - wiederholbar, falls Babsi im Dev noch etwas ändert – **nach dem Stichtag nicht mehr**, wenn im Live selbst geprüft wird
- [ ] Buchhaltung → Zahlungsart-Konten: bei **jtl_verrechnung** das Gegenkonto eintragen (sobald geklärt)
- [ ] **Artikel importieren, je JTL-Kategorie:** *Artikel → ⬇ Import → JTL Vater+Kind*
  - Stammdaten + Variationskombinationen + Kategorien-CSV der Kategorie
  - Häkchen **„Babsis Liste + neues Nummernschema anwenden“** (Standard)
  - **Feste Kategorie** = Zielkategorie (Sicherheitsnetz), JTL-Kategorien zuordnen, nicht mehr existierende = „– nicht zuordnen –“
  - Kontrollliste prüfen (neue Nummern, „wird umnummeriert“, Hinweise) → Import starten → Fehlerliste notieren
- [ ] **JTL-Bilder-Import** und **Merkmale** (`scripts/jtl_merkmale_import.php`) – finden die Artikel über die JTL-Nummer
- [ ] **Erstabgleich** *Lager → JTL-Abgleich*: JTL-Export „Lagerabgleich_ERP“ hochladen → setzt den Startbestand
- [ ] **Inventur im ERP** (mehrere Zähler, ohne Papier) → danach Export nach JTL (Inventur-Differenzen gehen mit)
- [ ] **Kasse einrichten:** Arbeitsplatz am Kassen-PC, BFR erreichbar, Bon-Drucker, Kundenanzeige, Kassenlade
  - [ ] Testbon + Storno am selben Tag, Nullbeleg/Monatsbeleg vorhanden
  - [ ] **Scan-Test:** neue Etiketten (Kassen-Liste, Mini-Anhänger) **und alte JTL-Etiketten** (werden über die JTL-Nummer gefunden)
  - [ ] Offline-Kasse (Sunmi) einmal vorbereiten und ein altes JTL-Etikett scannen
- [ ] Preisschilder/Kassen-Liste mit den neuen Nummern drucken, wo nötig

## C. Stichtag

- [ ] LS-POS: letzter **Tagesabschluss**, danach **keine Bons mehr** in LS-POS
- [ ] Morgens: **Abgleich** (1. ERP → JTL exportieren + in JTL einspielen + „In JTL eingespielt“, 2. JTL → ERP)
- [ ] Kasse öffnen: Kassenstand (Wechselgeld) erfassen
- [ ] Abends: **Z-Bon**, Abgleich

## D. Ablauf jeden Tag (Parallelbetrieb)

| Wann | Was | Wo |
|---|---|---|
| morgens + abends (bei viel Online-Betrieb öfter) | **Abgleich:** zuerst ERP → JTL (Ladenverkäufe), dann JTL → ERP (Online-Verkäufe) | ERP: Lager → JTL-Abgleich, JTL: Ameise |
| laufend | Verkauf im Laden | ERP-Kasse |
| Kunde holt **Online-Bestellung** ab | Ware **nicht** an der ERP-Kasse scannen (der Bestand geht über JTL + Abgleich ab). Ist sie unbezahlt: Kasse → ☰ → **🧾 JTL-Rechnung bezahlen** (Nummer + Betrag), danach **in JTL als bezahlt** markieren | ERP-Kasse + JTL |
| Kunde bringt **Online-Kauf zurück** | Geld: Kasse → ↩ **Freitext-Retour**. Ware: **in JTL** als Retoure einbuchen (kommt über den Abgleich ins ERP) | ERP-Kasse + JTL |
| neue Ware | **Wareneingang in JTL** (kommt über den Abgleich ins ERP) | JTL |
| neuer Artikel / Preisänderung | **in beiden** pflegen: JTL (für mealana.at) und ERP (Laden). Im ERP neuer Artikel nach Schema (VarKombi-Generator schlägt Nummer/Name vor) | JTL + ERP |
| Gutschein im Laden | ERP-Gutscheine an der Kasse wie gewohnt; alte JTL-/LS-POS-Gutscheine laut Liste (E) | ERP-Kasse |
| abends | **Z-Bon** (sonst macht ihn Jarvis um Mitternacht) | ERP-Kasse |

**Nie doppelt abziehen:** Jede Ware geht genau einmal ab – Ladenverkauf im ERP, Online-Verkauf in JTL. Der Abgleich bringt beides zusammen.

## E. Buchhaltung

- Laden **ab dem Stichtag nur aus dem ERP-Export** (Buchhaltung → Export). Im Umstellungsmonat nach Datum trennen: bis zum Vortag JTL/LS-POS, ab Stichtag ERP.
- Der JTL-Export darf ab dem Stichtag **keine Laden-Umsätze** mehr enthalten.
- „Bareingang zu JTL-Rechnung“: Kassa an Gegenkonto **jtl_verrechnung** – ohne eingetragenes Konto steht der Betrag als Hinweis „manuell buchen“ im Export. In JTL ist die Rechnung der Erlös.
- Online-Aufträge (Archiv aus JTL) sind **nicht** im ERP-Export.

## F. Offen / zu klären

- [ ] Gegenkonto **jtl_verrechnung** (Steuerberatung)
- [ ] **Alte Gutscheine:** Liste; im Laden einlösen – als „Freier Artikel“ negativ? oder nur noch online gültig (Newsletter)?
- [ ] Stichtag festlegen (Kassen evtl. schon im Jänner)
- [ ] Erste Woche: täglich kurz prüfen – Abgleich-Vorschau plausibel? Fehlbestände? Z-Bon da?

---

*Hintergrund:* Nummernschema und Vorschau siehe Handbuch Artikel → „JTL-Übernahme: neue Nummern“; Abgleich siehe Handbuch Lager → „JTL-Abgleich“; Kasse siehe Handbuch Kasse → „JTL-Rechnung bezahlen“.
