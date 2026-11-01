# 15 Dropshipping (Streckengeschäft)

Beim Dropshipping schickt der **Lieferant die Ware direkt an den Kunden**. Bei uns gibt es dafür keinen Packplatz und keine Lagerbuchung. Rechnung und Versandmail an den Kunden kommen trotzdem wie gewohnt vom ERP.

Derzeit nur mit **Lang Yarns** (Lieferant „Lang Garn & Wolle GmbH“, Korschenbroich DE).

---

## Grundregel

**Strecke nur, wenn der GANZE Auftrag per Dropshipping geht.** Ist auch nur ein Artikel nicht dabei, wird ganz normal bestellt (Wareneingang) und gemeinsam verschickt. Gemischte Aufträge gibt es nicht.

Außerdem nicht möglich bei: Abholung, Nachnahme, Kassen-Aufträgen, Gutscheinen im Auftrag, oder wenn schon etwas ausgeliefert wurde.

---

## Einrichten (einmalig)

1. **Lieferanten** → Lang öffnen → Bearbeiten → Karte **Dropshipping**
   - Haken **Dropshipping möglich**, damit gilt es für alle Artikel dieses Lieferanten
   - **Pauschale pro Lieferung** (€ netto, derzeit 11,00), das ist Langs Bearbeitungs- und Versandanteil pro Paket
2. Einzelne Artikel ausnehmen: Artikel öffnen → Reiter Lieferanten → ✏️ → **Dropshipping (Strecke)**
   - *wie beim Lieferanten* (Standard) · *ja* · *nein – ausgenommen*
   - Die Spalte **Strecke** in der Lieferanten-Tabelle zeigt, was gilt

---

## Ablauf im Alltag

1. **Aufträge finden:** Auftragsliste → Filter Lieferung → **Dropshipping: Strecke bereit**
   (= geht per Strecke, noch nicht bestellt, bezahlt *oder* Zahlungsart Rechnung)
2. **Auftrag öffnen:** Die Karte **Dropshipping** zeigt „Strecke möglich“ → Pauschale prüfen → **Per Dropshipping bestellen**
   - Ist der Auftrag noch nicht bezahlt (Vorkasse), fragt das ERP nach. Normalerweise wird erst nach Zahlungseingang bestellt
   - Das ERP legt eine Bestellung (Typ Strecke) an und gibt die Lager-Reservierungen frei
3. **Im Lang-Händlerportal bestellen** (Menüpunkt Dropshipping): Lieferadresse und Artikel aus der Karte übernehmen, jeder Wert hat einen ⧉-Knopf zum Kopieren
   - Als E-Mail im Portal **unsere eigene Adresse** angeben. Dann kommt Langs Versandmail mit dem Tracking zu uns
4. **Versandmail von Lang kommt:** Auftrag öffnen (Filter **Dropshipping: bestellt, Tracking offen**) → Trackingnummer + Versanddienstleister → **Versendet + Tracking**
   - Auftrag wird „Versendet“, ohne Lagerbuchung
   - Rechnung wird erstellt und mit der Versandmail an den Kunden geschickt (ohne E-Mail-Adresse: PDF zum Ausdrucken)
   - Der Shop bekommt den Status mit dem nächsten Abgleich

---

## Lang kann nicht liefern?

Auftrag öffnen → Karte Dropshipping → **Dropship-Bestellung stornieren**. Die Reservierungen leben wieder auf und der Auftrag läuft normal über Bestellung, Wareneingang und Packplatz.

---

## Lieferanten-Rechnung von Lang

- Kommt aus Deutschland, **IGL** (innergemeinschaftlich, Reverse Charge), **10 Tage netto**
- Inhalt: Artikel zum **normalen EK** + **eine Pauschale pro Lieferung**
- In der Dropship-Bestellung steht die erwartete Summe (EK + Pauschale). Die Rechnung dort erfassen, wie bei jeder Bestellung (Einkauf → Bestellungen → Strecke-Bestellung öffnen → Rechnung)

---

## Retouren

Retouren kommen **zu uns**, also der normale Retouren-Ablauf am Packplatz (die Ware wird bei uns eingelagert).

---

## Häufige Fragen

| Frage | Antwort |
|-------|---------|
| Karte „Dropshipping“ erscheint nicht | Mindestens ein Artikel ist nicht dropship-fähig, oder Abholung/Nachnahme/schon teilgeliefert. Dann normal bestellen |
| Keine Lang-Artikelnummer in der Karte | Bei vielen Artikeln fehlt sie noch in der Lieferanten-Zuordnung. Ersatzweise unsere Nummer + EAN verwenden. Kommt später mit dem Lang-Importer |
| Auftrag taucht nicht in der Pickliste auf | Richtig so: bei Lang **bestellte** Strecken-Aufträge werden in Pickliste und Packplatz ausgeblendet |
| Badge „Strecke möglich“ in der Pickliste | Nur ein Hinweis: der ganze Auftrag könnte per Dropshipping gehen. Lagernde Ware einfach normal packen; für Exoten auf das Badge klicken → Auftrag → bestellen |
| Dropship-Bestellung im Wareneingang? | Erscheint dort absichtlich nicht, die Ware kommt nie zu uns |
