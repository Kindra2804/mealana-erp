<?php
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle        = 'Bedienungsanleitung';
$activeModule     = '';
$actionBarContent = '<span style="font-size:13px;color:var(--color-text-muted)">Bedienungsanleitung — MeaLana ERP</span>';
require_once __DIR__ . '/includes/shell_top.php';
?>

<style>
    .ba-layout {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 24px;
        align-items: start;
        max-width: 1100px;
        margin: 0 auto;
    }

    .ba-toc {
        position: sticky;
        top: 12px;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: 6px;
        padding: 16px;
        font-size: 13px;
    }

    .ba-toc h3 {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--color-text-muted);
        margin: 0 0 10px;
    }

    .ba-toc a {
        display: block;
        padding: 4px 6px;
        color: var(--color-text);
        text-decoration: none;
        border-radius: 4px;
        line-height: 1.4;
    }

    .ba-toc a:hover { background: var(--color-bg); }

    .ba-toc a.sub {
        padding-left: 18px;
        font-size: 12px;
        color: var(--color-text-muted);
    }

    .ba-content h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--color-nav);
        margin: 36px 0 8px;
        padding-top: 12px;
        border-top: 2px solid var(--color-border);
    }

    .ba-content h2:first-child { margin-top: 0; border-top: none; }

    .ba-content h3 {
        font-size: 14px;
        font-weight: 600;
        margin: 20px 0 6px;
        color: var(--color-text);
    }

    .ba-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 2px 7px;
        border-radius: 10px;
        vertical-align: middle;
        margin-left: 8px;
    }

    .ba-badge-fertig  { background: #dcfce7; color: #166534; }
    .ba-badge-arbeit  { background: #fef9c3; color: #854d0e; }
    .ba-badge-geplant { background: #f1f5f9; color: #64748b; }

    .ba-content p {
        font-size: 13px;
        line-height: 1.7;
        margin: 6px 0 12px;
        color: var(--color-text);
    }

    .ba-content ul, .ba-content ol {
        font-size: 13px;
        line-height: 1.8;
        padding-left: 20px;
        margin: 6px 0 12px;
        color: var(--color-text);
    }

    .ba-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin: 10px 0 16px;
    }

    .ba-table th {
        text-align: left;
        padding: 6px 10px;
        background: var(--color-bg);
        border-bottom: 2px solid var(--color-border);
        font-weight: 600;
        font-size: 12px;
    }

    .ba-table td {
        padding: 6px 10px;
        border-bottom: 1px solid var(--color-border);
        vertical-align: top;
    }

    .ba-hint {
        background: #eff6ff;
        border-left: 3px solid #3b82f6;
        border-radius: 0 4px 4px 0;
        padding: 10px 14px;
        font-size: 13px;
        margin: 10px 0 16px;
        color: #1e40af;
    }

    .ba-warn {
        background: #fff7ed;
        border-left: 3px solid #f97316;
        border-radius: 0 4px 4px 0;
        padding: 10px 14px;
        font-size: 13px;
        margin: 10px 0 16px;
        color: #9a3412;
    }

    .ba-step {
        display: flex;
        gap: 10px;
        margin: 4px 0;
        font-size: 13px;
        line-height: 1.6;
    }

    .ba-step-nr {
        flex-shrink: 0;
        width: 22px;
        height: 22px;
        background: var(--color-nav);
        color: #fff;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 2px;
    }

    .ba-placeholder {
        background: var(--color-bg);
        border: 1px dashed var(--color-border);
        border-radius: 6px;
        padding: 16px 20px;
        color: var(--color-text-muted);
        font-size: 13px;
        margin: 8px 0 16px;
    }
</style>

<div class="card" style="padding:24px">
    <div class="ba-layout">

        <!-- ── Inhaltsverzeichnis ──────────────────────────────────────────── -->
        <nav class="ba-toc">
            <h3>Inhalt</h3>
            <a href="#einleitung">Einleitung</a>
            <a href="#navigation">Navigation</a>
            <a href="#artikel">Artikel</a>
            <a href="#artikel-varianten" class="sub">↳ Varianten & Achsen</a>
            <a href="#artikel-bilder" class="sub">↳ Bilder</a>
            <a href="#artikel-merkmale" class="sub">↳ Merkmale</a>
            <a href="#artikel-preise" class="sub">↳ Preise & Aktionen</a>
            <a href="#lager">Lager</a>
            <a href="#lager-lagerplaetze" class="sub">↳ Lagerplätze</a>
            <a href="#lager-lagerwert" class="sub">↳ Lagerwert</a>
            <a href="#lager-wareneingang" class="sub">↳ Wareneingang</a>
            <a href="#packplatz">Packplatz</a>
            <a href="#packplatz-scan" class="sub">↳ Artikel scannen</a>
            <a href="#packplatz-versenden" class="sub">↳ Versenden</a>
            <a href="#packplatz-rueck" class="sub">↳ Rücklagerungen</a>
            <a href="#einkauf">Einkauf & Bestellungen</a>
            <a href="#verkauf">Aufträge & Verkauf</a>
            <a href="#mahnwesen" class="sub">↳ Mahnwesen</a>
            <a href="#haendler" class="sub">↳ Händler-Außenlager</a>
            <a href="#kunden">Kunden</a>
            <a href="#partner">Partner & Mietfächer</a>
            <a href="#partner-lager" class="sub">↳ Partner-Lager & Partnerware</a>
            <a href="#buchhaltung">Buchhaltung</a>
            <a href="#buchhaltung-export" class="sub">↳ DATEV/CSV-Export + Zahlungs-Kontrolle</a>
            <a href="#inventur">Inventur</a>
            <a href="#einstellungen">Einstellungen</a>
            <a href="#einstellungen-mail" class="sub">↳ Mail / SMTP</a>
            <a href="#lieferanten">Lieferanten</a>
            <a href="#hersteller">Hersteller</a>
            <a href="#benutzer">Benutzer & Rechte</a>
            <a href="#kasse">Kasse</a>
            <a href="#kasse-bon" class="sub">↳ Bon erstellen</a>
            <a href="#kasse-abholbereit" class="sub">↳ Abholbereit / Aufträge</a>
            <a href="#kasse-sammelabholung" class="sub">↳ Sammelabholung</a>
            <a href="#kasse-rechnung-bezahlen" class="sub">↳ Rechnung bezahlen (Zahlbeleg)</a>
            <a href="#kasse-freitext-retour" class="sub">↳ Freitext-Retour</a>
            <a href="#kasse-kassensturz" class="sub">↳ Kassensturz / Z-Bon</a>
            <a href="#gutscheine">Gutscheine</a>
            <a href="#gutscheine-verkaufen" class="sub">↳ An der Kasse verkaufen</a>
            <a href="#gutscheine-bezahlen" class="sub">↳ Mit Gutschein bezahlen</a>
            <a href="#gutscheine-storno" class="sub">↳ Storno</a>
            <a href="#gutscheine-shop" class="sub">↳ Online-Shop</a>
            <a href="#inventur">Inventur</a>
        </nav>

        <!-- ── Kapitel ────────────────────────────────────────────────────── -->
        <div class="ba-content">

            <!-- EINLEITUNG -->
            <h2 id="einleitung">Einleitung <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>MeaLana ERP ist das Warenwirtschaftssystem der Wollboutique MeaLana. Es umfasst Artikelverwaltung, Lager, Einkauf, Aufträge, Packplatz und die Anbindung an WooCommerce-Shops.</p>
            <p>Das System läuft lokal auf einem Windows-PC. Geöffnet wird es im Browser unter <strong>http://localhost<?= BASE_PATH ?>/</strong> — oder über VPN von unterwegs.</p>
            <p>Der <strong>Packplatz</strong> hat eine eigene dunkle Oberfläche: <strong>http://localhost<?= BASE_PATH ?>/packplatz/</strong></p>

            <!-- NAVIGATION -->
            <h2 id="navigation">Navigation <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Die <strong>Hauptnavigation</strong> befindet sich oben: Artikel · Lager · Aufträge · Einstellungen. Ausgegraut = noch nicht fertig.</p>
            <p>Links befindet sich die <strong>Sidebar</strong> mit Unterseiten des aktiven Moduls.</p>
            <p>Nach jeder Aktion erscheint kurz ein farbiger Balken oben:</p>
            <table class="ba-table">
                <tr><th>Farbe</th><th>Bedeutung</th></tr>
                <tr><td>Grün</td><td>Aktion erfolgreich (verschwindet nach ~3 Sek.)</td></tr>
                <tr><td>Rot</td><td>Fehler — bitte lesen was drinsteht</td></tr>
                <tr><td>Gelb (!)</td><td>Hinweis — Aktion wurde trotzdem gespeichert</td></tr>
            </table>

            <!-- ARTIKEL -->
            <h2 id="artikel">Artikel <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Unter <em>Artikel → Liste</em> sieht man alle Artikel. Filter: aktiv/inaktiv, Typ, Kategorie, fehlende EAN, fehlende Bilder. Spalten können über den Spalten-Picker (Zahnrad rechts oben) angepasst werden.</p>

            <h3>Neuen Artikel anlegen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>Artikelnummer</strong> eingeben — muss eindeutig sein (z.B. DROPS-LIMA-50)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>Name</strong> eingeben — erscheint im Shop und auf Dokumenten</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div><strong>Artikeltyp</strong> wählen: Standard oder Varianten-Vater (bei Farben/Größen)</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Hersteller, Einheit, Steuerklasse auswählen</div></div>
            <div class="ba-step"><div class="ba-step-nr">5</div><div><strong>EAN/GTIN</strong> eingeben — wichtig für den Packplatz-Scanner!</div></div>
            <div class="ba-step"><div class="ba-step-nr">6</div><div>Brutto-VK eingeben, Kategorien zuweisen → Speichern</div></div>
            <div class="ba-hint">💡 Ohne EAN kann der Barcode-Scanner am Packplatz den Artikel nicht erkennen. EAN immer eintragen!</div>

            <h3 id="artikel-varianten">Varianten & Achsen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Varianten-Artikel bestehen aus einem <strong>Vater-Artikel</strong> (z.B. "DROPS Lima") und mehreren <strong>Kind-Artikeln</strong> (je eine Farbe/Größe).</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Vater-Artikel öffnen → Tab <strong>Varianten</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>Achsen zuweisen</strong> (z.B. "Farbe") und Werte eingeben (z.B. "Rot", "Blau")</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Tab Varianten → <strong>Kombinationen erstellen</strong> → Artikelnummern vergeben → Erstellen</div></div>
            <p>Stammdaten werden automatisch vom Vater an alle Kinder weitergegeben — beim Erstellen der Kinder, und bei jeder Änderung am Vater-Artikel.</p>

            <h4>Was wird vom Vater an die Kinder weitergegeben?</h4>
            <table class="ba-table">
                <thead><tr><th>Feld</th><th>Vererbt?</th></tr></thead>
                <tbody>
                    <tr><td>Hersteller</td><td>✅ ja</td></tr>
                    <tr><td>Steuerklasse (MwSt.)</td><td>✅ ja</td></tr>
                    <tr><td>Artikeltyp</td><td>✅ ja</td></tr>
                    <tr><td>Kurzbeschreibung</td><td>✅ ja</td></tr>
                    <tr><td>Beschreibung (Langtext)</td><td>✅ ja</td></tr>
                    <tr><td>Technische Details</td><td>✅ ja</td></tr>
                    <tr><td>Interne Beschreibung</td><td>✅ ja</td></tr>
                    <tr><td>Meta-Titel / Meta-Description</td><td>✅ ja</td></tr>
                    <tr><td>Einheit</td><td>✅ ja</td></tr>
                    <tr><td>Inhaltsmenge / Inhalt-Einheit</td><td>✅ ja</td></tr>
                    <tr><td>Gewicht (Artikel + Versand)</td><td>✅ ja</td></tr>
                    <tr><td>Abmessungen (L × B × H)</td><td>✅ ja</td></tr>
                    <tr><td>Herkunftsland / TARIC-Code</td><td>✅ ja</td></tr>
                    <tr><td>Grundpreis-Bezugsmenge / Anzeigen</td><td>✅ ja</td></tr>
                    <tr><td>Charge-Pflicht</td><td>✅ ja</td></tr>
                    <tr><td>Überverkauf erlaubt</td><td>✅ ja</td></tr>
                    <tr><td>Kategorien</td><td>✅ ja (beim Kategorien-Speichern)</td></tr>
                    <tr><td>Artikelnummer</td><td>❌ nein — jedes Kind hat eigene</td></tr>
                    <tr><td>Name</td><td>❌ nein — jedes Kind hat eigenen</td></tr>
                    <tr><td>Preis</td><td>❌ nein — Kinder können eigene Preise haben</td></tr>
                    <tr><td>Aktiv / Inaktiv</td><td>❌ nein — Kind kann separat deaktiviert sein</td></tr>
                    <tr><td>Auslaufartikel-Flag</td><td>❌ nein — Kind kann separat Auslauf sein</td></tr>
                    <tr><td>EAN</td><td>❌ nein — jedes Kind hat eigene EAN</td></tr>
                    <tr><td>Bilder</td><td>❌ nein — jedes Kind hat eigene Bilder</td></tr>
                </tbody>
            </table>

            <h3 id="artikel-bilder">Bilder <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Artikel-Detail → Tab <strong>Bilder</strong>: Bild per Drag &amp; Drop hochladen. Das System verkleinert automatisch auf max. 1920px (JPG 85%).</p>
            <ul>
                <li><strong>Hauptbild:</strong> Stern ☆ klicken</li>
                <li><strong>Reihenfolge:</strong> Pfeile ↑ / ↓</li>
                <li><strong>Alt-Text:</strong> Bildbeschreibung für Barrierefreiheit und Google</li>
            </ul>
            <div class="ba-hint">💡 Erlaubte Formate: JPG, PNG, GIF, WebP. Das Original wird vor der Verkleinerung gespeichert.</div>

            <h3 id="artikel-merkmale">Merkmale <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Merkmale sind technische Eigenschaften (Nadelstärke, Material …). Sie erscheinen im Shop als Produktattribute.</p>
            <p>Artikel-Detail → Tab <strong>Merkmale</strong> → Merkmal wählen → Wert eingeben oder auswählen → Speichern.</p>

            <h3 id="artikel-preise">Preise & Aktionen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Preis-Priorität (höchste gewinnt):</p>
            <table class="ba-table">
                <tr><th>Priorität</th><th>Typ</th><th>Wo setzen</th></tr>
                <tr><td>1 — höchste</td><td>SALE-Override</td><td>Artikel → Preise → SALE-Override</td></tr>
                <tr><td>2</td><td>Aktionspreis</td><td>Artikel → Aktionen</td></tr>
                <tr><td>3</td><td>Kundengruppen-Preis</td><td>Artikel → Preise → Zeile je KG</td></tr>
                <tr><td>4 — niedrigste</td><td>Standard-Preis</td><td>Artikel → Preise → Standard-KG</td></tr>
            </table>
            <p><strong>Aktionen</strong> gelten für ganze Kategorien und laufen zeitlich begrenzt. Sie werden täglich um 0:00 Uhr automatisch aktiviert/deaktiviert (Cronjob).</p>

            <!-- LAGER -->
            <h2 id="lager">Lager <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Das Lager-Modul verwaltet Bestände und Bewegungen. Zwei Lager: <strong>Standardlager</strong> und <strong>Lager Messe</strong>.</p>
            <p>Bestand = <strong>Ist</strong> (physisch vorhanden) − <strong>Reserviert</strong> (für offene Aufträge) = <strong>Verfügbar</strong> (kann noch verkauft werden).</p>
            <div class="ba-warn">⚠ Bestände nie direkt in der Datenbank ändern! Immer über Wareneingang oder Storno. Direkte Änderungen zerstören das Bewegungsprotokoll.</div>
            <h3 id="lager-lagerplaetze">Lagerplätze <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Lager → Lagerplätze. Kürzel aus Bereich (optional), Regal und Fach: Regal 3 / Fach 12 → <code>R3-F12</code>, Bereich K / Regal 1 / Fach 4 → <code>K-R1-F4</code>. Sortierung nach Laufweg.</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>+ Regal mit Fächern</strong> legt ein ganzes Regal an (z.B. Fach 1–20), <strong>+ Neuer Lagerplatz</strong> ein einzelnes Fach</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>🏷 Etiketten drucken</strong>: A4, 3 × 8 Etiketten (70 × 37 mm) mit QR-Code — der Code öffnet die Zählung des Fachs</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Artikeln <strong>Stammplatz</strong> (Verkaufsfach) und optional <strong>Nachfüllplatz</strong> geben: Artikel → Lager-Reiter, Massenaktion „Lagerplatz zuweisen“ in der Artikelliste oder „+ Platz“ im Wareneingang. Beim Vater gilt der Platz für alle Varianten.</div></div>
            <p>Angezeigt auf Pickliste und Packplatz (nach Laufweg sortiert) und im Wareneingang („Gehört in …“). Mehrere Chargen dürfen im selben Fach liegen. Bestand wird weiter je Lager geführt, nicht je Fach.</p>

            <h3 id="lager-lagerwert">Lagerwert <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Lager → Lagerwert (Recht „Buchhaltung anzeigen“). Wert des Bestands zu Einkaufspreisen netto, getrennt nach <strong>eigenen Lagern</strong> und <strong>bei Händlern</strong>. Partnerware zählt nie mit.</p>
            <p>EK-Reihenfolge: letzter Wareneingang → Standardlieferant → günstigster Lieferant → Vater-/Originalartikel → sonst 0 €.</p>
            <div class="ba-hint">💡 Kachel „Artikel ohne EK“ → Link zur Artikelliste mit Filter „Kein EK (mit Bestand)“ zum Nachpflegen.</div>
            <p>Automatisch festgehalten am <strong>Monatsende</strong> (Cronjob <code>cron/lagerwert.php</code>) sowie bei <strong>Start und Abschluss jeder Inventur</strong>. Je Stichtag gibt es eine CSV mit der bewerteten Bestandsliste.</p>

            <h3 id="lager-wareneingang">Wareneingang <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Lager → Wareneingang</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Artikel suchen oder EAN scannen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Menge, EK-Preis, Lager und optional Charge eingeben</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Optional: Lieferschein-Nr. des Lieferanten eintragen</div></div>
            <div class="ba-step"><div class="ba-step-nr">5</div><div>→ Einbuchen</div></div>
            <div class="ba-hint">💡 War ein Auslaufartikel auf Bestand 0 und bekommt jetzt Ware? Das System reaktiviert ihn automatisch.</div>

            <!-- PACKPLATZ -->
            <h2 id="packplatz">Packplatz <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Eigene Oberfläche, optimiert für Touchscreen und Barcode-Scanner. Dunkles Design.</p>
            <p>Adresse: <strong>http://localhost<?= BASE_PATH ?>/packplatz/</strong></p>
            <p>Hauptmenü: Warenausgang (fertig) · Wareneingang (fertig) · Intern (fertig) · Retoure (fertig) · Rücklagerungen (fertig)</p>

            <h3 id="packplatz-scan">Artikel scannen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Packplatz → Warenausgang</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Auftrag wählen: Pickliste scannen/anklicken <em>oder</em> Auftragsnummer direkt eingeben</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Jeden Artikel scannen — Zeile wird <span style="color:#22c55e;font-weight:600">grün</span> wenn Menge vollständig, <span style="color:#ef4444;font-weight:600">rot</span> wenn zu viel</div></div>
            <div class="ba-hint">💡 Vorwahl: Zahl eingeben und dann einmal scannen → bucht mehrere auf einmal. Bild des Artikels erscheint rechts.</div>
            <div class="ba-hint">💡 Artikel ohne EAN? Doppelklick auf die EAN-Zelle (oder "⚠ Kein EAN — nachtragen") öffnet ein Eingabefeld direkt auf dem Scan-Bildschirm — speichert sofort und kann direkt weitergescannt werden.</div>

            <h3 id="packplatz-versenden">Versenden <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Wenn alle Zeilen grün: Button <strong>Verpacken</strong> wird aktiv</div></div>
            <div class="ba-step"><div class="ba-step-nr">5</div><div>Overlay: <strong>Gewicht</strong> prüfen/korrigieren (aus Artikelgewichten vorausgefüllt)</div></div>
            <div class="ba-step"><div class="ba-step-nr">6</div><div><strong>Trackingnummer</strong> vom aufgedruckten Label abscannen</div></div>
            <div class="ba-step"><div class="ba-step-nr">7</div><div>→ Abschließen: Status wird auf "versendet" gesetzt, Versandmail (mit Lieferschein) an den Kunden, <strong>(Teil-)Rechnung</strong> über genau diese Lieferung wird automatisch erstellt und per eigener Mail geschickt</div></div>
            <p>Für <strong>Teillieferung</strong> (nicht alle Artikel lieferbar): Button "Teillieferung" statt "Verpacken" → gleicher Overlay-Flow. Die Teilrechnung zeigt nicht Geliefertes als „Noch ausständig“.</p>
            <div class="ba-hint">💡 Kunde ohne E-Mail-Adresse: nach dem Abschließen erscheint <strong>🖨 Rechnung drucken</strong> — ausdrucken und ins Paket legen. Schon an der Kasse bezahlte Ware kommt nicht nochmal auf die Rechnung.</div>
            <div class="ba-hint">💡 <strong>Restbetrag offen</strong> (z.B. nach Umstellung Abholung → Versand): rechts sichtbar, beim Verpacken kommt „Wirklich versenden?“ — Abbrechen lässt den Auftrag bis zur Zahlung liegen.</div>

            <h3 id="packplatz-rueck">Rücklagerungen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Nach einer Kassen-Retoure (bar oder als Gutschein, zu einem Auftrag oder als Freitext-Retour) oder einer ERP-Rechnungskorrektur mit <strong>"Ware zur Prüfung an den Packplatz"</strong> ist nur das Geld erledigt — die Ware ist noch nicht im Lagerbestand. Bei Gutschriften sind Charge und Lager aus dem ursprünglichen Verkauf vorbefüllt (eine Zeile pro verkaufter Charge).</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Packplatz → <strong>Rücklagerungen</strong> (Badge zeigt Anzahl offener Einträge)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Zeile suchen → <strong>Einbuchen</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Ziel-Lager + <strong>Zustand</strong> wählen (siehe Tabelle)</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Bei chargenpflichtigen Artikeln: Charge prüfen/eintragen (Pflicht, außer bei "Defekt")</div></div>
            <table class="ba-table">
                <tr><th>Zustand</th><th>Wohin wird gebucht</th></tr>
                <tr><td><strong>Neu</strong></td><td>Originalartikel</td></tr>
                <tr><td><strong>Retour / Gebraucht / Beschädigt</strong></td><td>Zustandsartikel mit Anhang (<code>D-101071-RET</code>, <code>-GEB</code>, <code>-BSC</code>) — wird bei Bedarf automatisch angelegt, <strong>zählt nie für den Onlineshop</strong>. Zu finden über Artikelliste → Status / Qualität → Zustand (B-Ware).</td></tr>
                <tr><td><strong>Defekt</strong></td><td>nicht in den Bestand — wird als Retoure-Eingang und sofort als <strong>Schwund</strong> ausgebucht, steht also in der Lagerverfolgung (Bewegungslog) des Artikels</td></tr>
            </table>
            <p><strong>Welche Aufträge?</strong> Online-/manuelle Aufträge, auch an der Kasse bezahlte Abholungen (Rechnungskorrektur bezieht sich dann auf den Kassenbon, Rückzahlung über „Rückerstattung buchen“ oder bar an der Kasse). Reine Kassenverkäufe (K1-…) nur an der Kasse.</p>
            <p><strong>Retoure (Rücksendung per Post):</strong> Packplatz → Retoure → pro Position Menge · Charge · Zustand (Charge aus dem Verkauf vorbefüllt, <strong>＋ Charge</strong> für mehrere Chargen/Zustände). Möglich ist nur die Menge, die noch nicht zurückgekommen bzw. gutgeschrieben ist — schützt vor Doppelbuchung mit der Kasse.</p>

            <!-- EINKAUF -->
            <h2 id="einkauf">Einkauf & Bestellungen <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Hier werden Bestellungen bei Lieferanten verwaltet (Einkauf = wir bestellen bei Lieferanten, nicht: Kunden bestellen bei uns). Lieferanten selbst siehe <a href="#lieferanten">Lieferanten</a>.</p>
            <h3>Neue Bestellung anlegen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Bestellungen → Neue Bestellung → Lieferant auswählen</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Artikel + Menge + EK-Preis eintragen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Speichern → Bestellnummer wird generiert</div></div>
            <h3>Bestellung als PDF erstellen & an Lieferant senden</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Bestellung öffnen → Bereich "Bestellung an Lieferant" → <strong>PDF erstellen</strong> (öffnet automatisch zum Ansehen/Drucken)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Nur falls per Mail nötig: <strong>Per Mail senden</strong> → Vorschau mit Empfänger/Betreff/Text prüfen/anpassen → Mail senden</div></div>
            <div class="ba-hint">💡 Kann mehrfach neu erstellt werden (z.B. nach Mengenänderung). Bei Lieferanten mit eigenem B2B-Bestellportal reicht meist das PDF ohne Mail-Versand.</div>
            <h3>Wareneingang zur Bestellung buchen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Bestellung öffnen → "Wareneingang buchen"</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Tatsächlich gelieferte Mengen + EK-Preis bestätigen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Optional: Charge eingeben → Einbuchen</div></div>
            <p>Bei Teillieferungen: nur die gelieferten Mengen eingeben → Status "Teilweise geliefert". Beim nächsten Eingang erneut buchen.</p>

            <!-- AUFTRÄGE -->
            <h2 id="verkauf">Aufträge & Verkauf <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Jeder Auftrag hat zwei getrennte Status:</p>
            <table class="ba-table">
                <tr><th>Status</th><th>Werte</th></tr>
                <tr><td><strong>Zahlungsstatus</strong></td><td>ausstehend · bezahlt · teilbezahlt · storniert</td></tr>
                <tr><td><strong>Lieferstatus</strong></td><td>neu · in Bearbeitung · versandbereit · versendet · teilgeliefert · abgeschlossen · Retoure offen · storniert</td></tr>
            </table>

            <h3 id="abgeschlossen">Wann ist ein Auftrag „Abgeschlossen“?</h3>
            <p>Automatisch — nur wenn <strong>alles ausgeliefert</strong>, <strong>alles auf einem Beleg</strong> (Rechnung oder Kassenbon), <strong>keine Retoure offen</strong> und der <strong>Saldo aus den Belegen 0</strong> ist (Rechnungen/Bons − Korrekturen − Zahlungen). Sonst bleibt er „versendet“ bzw. „teilgeliefert“.</p>
            <p><strong>Retoure offen:</strong> Kommt Ware zurück, öffnet sich der Auftrag automatisch wieder — bis die Ware am Packplatz eingelagert und gutgeschrieben/erstattet ist. Danach schließt er sich von selbst. Ist dem Kunden nach einer Rechnungskorrektur noch Geld zurückzuzahlen, steht im Auftrag „Rückerstattung offen“ mit <strong>↩ Rückerstattung buchen</strong> (bar: an der Kasse den Auftrag laden).</p>

            <h3 id="belege">Belege: Rechnung, Teilrechnung, Bon</h3>
            <p>Jede Ware, die das Haus verlässt, steht auf <strong>genau einem</strong> Beleg — nie auf Rechnung UND Bon.</p>
            <table class="ba-table">
                <tr><th>Fall</th><th>Beleg</th></tr>
                <tr><td>Versand (auch Teillieferung)</td><td>Teilrechnung über genau diese Lieferung — automatisch am Packplatz</td></tr>
                <tr><td>An der Kasse bezahlt</td><td>Kassenbon (= Rechnung), A4 an der Abholmail</td></tr>
                <tr><td>Vorab bezahlt + abgeholt</td><td>Rechnung, an der Abholmail</td></tr>
                <tr><td>Gutschein-Bestellung (Shop)</td><td>Rechnung mit 0 %</td></tr>
                <tr><td>Retoure / Erstattung</td><td>Rechnungskorrektur bzw. Stornorechnung oder Bon mit Minus-Zeilen</td></tr>
            </table>
            <p>Teilrechnung: nur Geliefertes, darunter „Noch ausständig“ ohne Preise; Versandkosten auf der ersten Rechnung. Jede Rechnung zeigt die <strong>Zahlungen zum Auftrag</strong> und den offenen Betrag. Im Auftrag: 🖨 neben jeder Rechnung = Nachdruck mit aktueller Zahlungsinfo. „(Teil-)Rechnung erstellen“ erscheint nur, wenn es ausgelieferte, noch nicht verrechnete Ware gibt.</p>
            <div class="ba-hint">💡 Mengen, die schon auf Rechnung/Bon stehen, lassen sich beim Bearbeiten nicht mehr verringern — dafür eine Rechnungskorrektur.</div>

            <h3 id="rechnungskorrektur">Rechnungskorrektur / Stornorechnung (statt „Gutschrift“)</h3>
            <p>Eine „Gutschrift“ stellt nur der Leistungsempfänger aus — wir korrigieren mit einer <strong>Rechnungskorrektur</strong> (einzelne Positionen) oder <strong>Stornorechnung</strong> (alles Offene). Nummernkreis bleibt GS-….</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Auftrag → Dokumente → <strong>Rechnungskorrektur / Storno</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Art wählen, Positionen/Mengen angeben (nur Verrechnetes, noch nicht Korrigiertes), Grund eintragen (steht auf dem Beleg)</div></div>
            <div class="ba-hint">💡 Der Beleg nennt die Originalrechnung(en) mit Nummer und Datum; Mengen, Entgelte und Steuer stehen mit Minus. Versandkosten: bei Stornorechnung automatisch, bei Korrektur per Häkchen. Das PDF öffnet sich im neuen Tab.</div>

            <h3 id="belege-spalte">Belege-Spalte + offene Werte</h3>
            <p>In der Auftragsliste zeigt die Spalte <strong>Belege</strong> je Auftrag: AB · LS · AZ · RG · RK (Rechnungskorrektur) · Bon — farbig = vorhanden, grau = noch keiner, Zahl = mehrere. Klick öffnet das PDF (bei mehreren klappt eine Liste auf). <strong>Blaues !</strong> = ausgeliefert, aber noch kein Beleg → Rechnung erstellen.</p>
            <p>Die Kacheln <strong>Auftragsbestand · Geliefert, nicht verrechnet · Offene Rechnungen · Retoure offen</strong> stehen oben auf Buchhaltung → Zahlungs-Kontrolle und im Dashboard; Klick öffnet die gefilterte Auftragsliste. Der Auftragsbestand ist nur „erwarteter Umsatz“ — er zählt nicht für Steuer/Buchhaltung.</p>

            <h3>Auftrag manuell anlegen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Aufträge → Neuer Auftrag → Kunden suchen oder neu anlegen (Pflicht — jeder Auftrag braucht ein Kundenkonto)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Zahlungsart und Lieferart wählen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Artikel hinzufügen (EAN scannen oder Artikelnummer eingeben), Mengen anpassen</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Notizen eintragen → Speichern</div></div>
            <div class="ba-hint">💡 Auftragsnummer wird automatisch generiert (A-2026-00001).</div>

            <h3 id="versandart">Versandart ändern (Abholung ↔ Versand)</h3>
            <p>Auftrag → neben dem Lieferstatus <strong>⇄ Versandart ändern</strong>. Versand: Versandklasse wählen (Kosten vorgeschlagen, änderbar); Abholung: Versandkosten fallen weg.</p>
            <table class="ba-table"><tr><th></th><th>Abholung → Versand</th><th>Versand → Abholung</th></tr>
                <tr><td>Gepackte Ware</td><td>aus dem Abholfach zurück ins Lager (gleiche Charge), Auftrag „in Bearbeitung“ → normal über den Packplatz</td><td>schon Verschicktes gilt als übergeben</td></tr>
                <tr><td>Schon bezahlt</td><td><strong>teilbezahlt</strong> + Mail mit Restbetrag; Packplatz fragt „wirklich versenden?“</td><td><strong>Guthaben</strong> — Kasse zahlt bei Abholung bar oder als Gutschein aus</td></tr>
            </table>
            <div class="ba-hint">💡 Gesperrt, wenn schon ganz verschickt/abgeholt oder die Versandkosten schon auf einer Rechnung stehen (dann Rechnungskorrektur). Der Shop wird nicht automatisch umgestellt. „Abgeschlossen“/„Retoure offen“ lassen sich nicht von Hand setzen.</div>

            <h3>Zahlungseingang buchen</h3>
            <p>Auftrag öffnen → <strong>Zahlung buchen</strong> → Betrag, Datum und <strong>Zahlungsweg</strong> (Überweisung/PayPal/Nachnahme/Sonstige — steht dann in der Zahlungsinfo der Rechnung) → Zahlungsstatus wechselt auf "bezahlt" bzw. "teilbezahlt".</p>
            <div class="ba-warn">⚠ Bar- oder Kartenzahlung nicht hier buchen, sondern an der Kasse: Menü → <strong>💶 Rechnung bezahlen</strong> (RKSV-Zahlbeleg).</div>

            <h3 id="mahnwesen">Mahnwesen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Der Cronjob läuft täglich.</p>
            <table class="ba-table">
                <tr><th>Zahlungsart</th><th>Wann</th><th>Aktion</th></tr>
                <tr><td><strong>Vorkasse</strong></td><td>14 Tage nach Bestellung</td><td>Zahlungserinnerung per Mail</td></tr>
                <tr><td><strong>Vorkasse</strong></td><td>30 Tage nach Bestellung</td><td>Automatische Stornierung (Reservierungen frei)</td></tr>
                <tr><td><strong>Rechnung</strong></td><td>7 Tage nach Fälligkeit</td><td>Zahlungserinnerung per Mail (automatisch)</td></tr>
                <tr><td><strong>Rechnung</strong></td><td>14 Tage nach Erinnerung</td><td>1. Mahnung (5 €) — Vorschlag zur Freigabe</td></tr>
                <tr><td><strong>Rechnung</strong></td><td>14 Tage nach 1. Mahnung</td><td>2. Mahnung (10 €) — Vorschlag zur Freigabe</td></tr>
            </table>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Verkauf → <strong>Mahnwesen</strong> (Zahl im Menü = offene Vorschläge)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>Freigeben</strong> → PDF + Mail an den Kunden, Gebühr wird fällig. Ohne E-Mail-Adresse: PDF ausdrucken (Verlauf → PDF)</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Kulanz: <strong>Gebühr erlassen</strong>. Nach abgelaufener 2. Mahnung steht der Auftrag unter „Manuell klären“</div></div>
            <div class="ba-hint">💡 Fälligkeit = Zahlungsbedingung von Auftrag oder Kunde (sonst 14 Tage). Tage und Gebühren: Einstellungen → System → Mahnwesen. Die Mahngebühr zählt zum offenen Betrag — erst wenn auch sie bezahlt oder erlassen ist, steht der Auftrag auf „bezahlt“.</div>
            <div class="ba-warn">⚠ Bei Rechnung gibt es nie einen automatischen Storno — die Ware ist meist schon beim Kunden.</div>

            <!-- KUNDEN -->
            <h3 id="haendler">Händler-Außenlager (Kommission) <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Ware liegt beim Händler, bleibt aber euer Bestand, bis er den Verkauf meldet. Einrichten: Kunde öffnen → <strong>🏬 Als Händler einrichten</strong>. Übersicht: Verkauf → <strong>Händler</strong> (dort auch der Standard-Händlerrabatt in % auf den Endkunden-VK; ein eigener Händlerpreis am Artikel hat Vorrang).</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>🚚 Ware liefern</strong> → Lieferschein (HL) mit Händlerpreis netto + empfohlenem VK. Der Preis gilt ab Lieferung.</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>🧾 Verkauf melden</strong> — „was verkauft wurde“ oder „was noch da ist“; Preise korrigierbar, Schwund-Spalte wird nicht verrechnet → Auftrag + Rechnung (netto + USt), erst dann Lagerabbuchung.</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div><strong>↩ Rücknahme</strong> (HR) zurück ins eigene Lager, <strong>⚠ Schwund</strong> (HS, Grund Pflicht).</div></div>
            <p>Verschiedene Lieferpreise: die älteste Lieferung wird zuerst verrechnet. Zahlung, Mahnung und Export laufen über den normalen Auftrag.</p>

            <h2 id="kunden">Kunden <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Privat- und Geschäftskunden verwalten. Datenschutz-sensible Felder sind AES-256-verschlüsselt gespeichert (DSGVO-konform).</p>
            <p><strong>Neuen Kunden anlegen:</strong> Kunden → Neuer Kunde → Typ wählen (Privat / B2B) → Felder ausfüllen → Speichern.</p>
            <div class="ba-hint">💡 Wichtig: E-Mail-Adresse eintragen — sie wird für Auftragsbestätigungen und Mahnungen benötigt.</div>
            <p>Shop-Kunden aus WooCommerce werden automatisch importiert und per E-Mail-Adresse mit bestehenden Kunden verknüpft.</p>
            <p><strong>DSGVO-Löschung:</strong> Kunden-Detail → "Kunden löschen (DSGVO)" — persönliche Daten werden kryptografisch gelöscht, Bestellhistorie bleibt anonymisiert erhalten.</p>

            <!-- PARTNER -->
            <h2 id="partner">Partner & Mietfächer <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <table class="ba-table">
                <tr><th>Typ</th><th>Was ist das?</th></tr>
                <tr><td><strong>Mietfach</strong></td><td>Physischer Platz im Geschäft — Partner verkauft eigene Ware</td></tr>
                <tr><td><strong>Kommission</strong></td><td>Ware des Partners wird bei uns verkauft — Partner bekommt Anteil</td></tr>
                <tr><td><strong>Spende</strong></td><td>Überschussware gespendet, Gegenwert protokolliert</td></tr>
            </table>
            <p><strong>Mietfach zuweisen:</strong> Partner öffnen → Tab Mietfächer → "Mietfach zuweisen" → Nummer, Mietbeginn, Monatsbetrag → Speichern. Vertragshistorie bleibt immer erhalten.</p>

            <h3 id="partner-lager">Partner-Lager und Partnerware <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Partner → Name anklicken → <strong>Partner-Lager anlegen</strong>. Die gemieteten Fächer werden Lagerplätze im Lager des Partners; neue Mietverträge kommen automatisch dazu.</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Reiter <strong>Artikel</strong>: Partner-Artikel anlegen — Nummer immer <code>XP</code> + Partner-ID (z.B. <code>XP01-SEIFE-LAV</code>), Preis, MwSt, EAN, Fach. Nicht in der normalen Artikelliste, nie im Onlineshop.</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Reiter <strong>Bestand</strong> → <strong>📥 Ware übernehmen</strong> (Mengen eintragen oder scannen) → Übernahmeschein (US-Nr.) zum Unterschreiben. <strong>📤 Rückgabe</strong> → Rückgabeschein (RS-Nr.).</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Die <strong>Kasse</strong> bucht Partner-Artikel automatisch aus dem Partner-Lager ab (Storno zurück dorthin).</div></div>
            <p>Reiter <strong>Bewegungen</strong> zeigt jede Übernahme, jeden Verkauf, jedes Storno und jede Rückgabe; Reiter <strong>Belege &amp; Verkaufsliste</strong> alle Scheine und eine Verkaufsliste als PDF für einen frei wählbaren Zeitraum.</p>
            <p><em>Noch nicht gebaut:</em> Partner-Abrechnung (Gutschrift/Fremdrechnung/Info) und „im Namen und auf Rechnung von“ samt RKSV-Trennung — wird gesondert geplant.</p>

            <!-- BUCHHALTUNG -->
            <h2 id="buchhaltung">Buchhaltung <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>MeaLana führt keine eigene Fibu — nur Kontenzuordnung + Export für den Steuerberater.</p>
            <table class="ba-table">
                <tr><th>Seite</th><th>Wofür?</th></tr>
                <tr><td><strong>Artikelgruppen</strong></td><td>Erlöskonto je Warengruppe (Wolle, Nadeln, ...)</td></tr>
                <tr><td><strong>Kontenplan</strong></td><td>Zentrale Kontenliste — neu anlegen/bearbeiten, nie löschen (nur deaktivieren)</td></tr>
                <tr><td><strong>Kreditoren</strong></td><td>Kreditorenkonto je Lieferant, manuell zugewiesen</td></tr>
                <tr><td><strong>Lieferantenrechnungen</strong></td><td>Kreditoren-Übersicht: offene/bezahlte Einkaufsrechnungen, Fälligkeit + Skonto-Frist aus den Lieferanten-Stammdaten berechnet</td></tr>
                <tr><td><strong>Zahlungsart-/Steuer-Konten</strong></td><td>Welches Konto für bar/Bank/PayPal/... bzw. 20%/10%/13%/0%</td></tr>
            </table>
            <p><strong>Debitorennummer (Kunde):</strong> wird bei Neuanlage automatisch vorgeschlagen, ist aber überschreibbar — auf der Kunde-Detailseite draufklicken zum Ändern (wichtig bei Bestandskunden mit vorhandener Nummer aus der bisherigen Buchhaltung).</p>
            <p><strong>Lieferantenrechnungen:</strong> Rechnungsdaten (Nummer/Betrag/Datum) werden weiterhin auf der Bestellung selbst erfasst (Bestellungen → Detail) — die neue Übersicht sammelt sie nur zentral und rechnet Fälligkeit/Skonto aus. Zahlungen (Überweisung oder Guthaben-Verrechnung) werden im Zahlungsverlauf auf der Bestellung gebucht; der Status (offen/teilbezahlt/bezahlt) ergibt sich automatisch aus der Summe.</p>
            <p><strong>Lieferanten-Guthaben (DROPS-Modell):</strong> Wird eine Bestellung im Wareneingang mit "Rest streichen" abgeschlossen und dabei ein Gutschriftbetrag eingetragen, landet der als Guthaben-Zugang auf dem Lieferanten-Konto (sichtbar auf der Lieferanten-Detailseite). Bei der nächsten Bestellung kann dieses Guthaben als eigene Zahlungsart "Guthaben-Verrechnung" genutzt werden — begrenzt auf den tatsächlich verfügbaren Saldo.</p>

            <h3 id="buchhaltung-export">DATEV/CSV-Export</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Einmalig <strong>DATEV-Einstellungen</strong> eintragen (Berater-Nr., Mandanten-Nr. — vom Steuerberater erfragen)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>Zeitraum</strong> wählen (Von/Bis oder Schnellwahl Monat/Quartal/Jahr)</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Gelbe <strong>Hinweise</strong> prüfen — diese Positionen wurden NICHT automatisch gebucht und müssen von Hand nachgetragen werden</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div><strong>CSV</strong> (funktioniert überall) oder <strong>DATEV</strong> herunterladen und an den Steuerberater übergeben</div></div>
            <p><strong>Umsatz kommt nur aus Belegen</strong> — Kassenbon, Rechnung, Rechnungskorrektur/Stornorechnung. Aufträge allein sind nur „erwarteter Umsatz“. MEALANA ist eine KG (Soll-Versteuerung): Rechnungen und Gutschriften werden zum <strong>Rechnungsdatum</strong> gegen das Kundenkonto gebucht, jeder Zahlungseingang (Überweisung, PayPal, …) zum Buchungsdatum als Bank an Kundenkonto. Zahlbelege an der Kasse: Kassa an Kundenkonto, 0 % (USt steht schon auf der Rechnung).</p>
            <p><strong>Gutscheine</strong> laufen über das Anzahlungskonto 3230 (Verkauf = Anzahlung ohne USt, Einlösung = Zahlung von 3230). <strong>Gemischte Kassenzahlungen</strong> (Bar + Karte, Gutschein + Rest) werden anteilig auf Kassa, Bank und 3230 aufgeteilt. Versandkosten werden als eigener Erlös mit dem Steuersatz der überwiegenden Leistung gebucht.</p>
            <p><strong>Zahlungs-Kontrolle</strong> (Buchhaltung → Zahlungs-Kontrolle): listet Kassenbons und Aufträge mit ihren Zahlungen und der Konten-Aufteilung des Exports. Standard: nur gemischte Zahlungen, Gutscheine und Differenzen (orange) — Haken „alle Belege zeigen" für die ganze Liste. Unten die Summe je Konto.</p>
            <p style="color:#c2410c"><strong>Wichtig:</strong> Vor dem ersten echten DATEV-Import unbedingt eine Testdatei mit dem Steuerberater abstimmen.</p>

            <!-- INVENTUR -->
            <h2 id="inventur">Inventur <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Ein Inventur-Lauf mit frei wählbarem Scope: ganzes Lager, ein Lagerplatz, eine Kategorie, ein einzelner Artikel oder ein Mietfach — statt getrennter Module für große und kleine Zählungen.</p>
            <p><strong>Lagerplätze</strong> (Lager → Lagerplätze): Regal/Fach-Struktur unterhalb eines Lagers, siehe <a href="#lager-lagerplaetze">Lagerplätze</a>.</p>
            <p><strong>Fach-QR-Code scannen</strong> (Handy-Kamera oder ins Suchfeld der Zählseite): läuft eine Inventur für das Fach oder das ganze Lager → direkt zur Zählung (Fach vorgewählt), sonst „Zwischenzählung starten“ nur für dieses Fach.</p>
            <p><strong>Artikel am falschen Platz:</strong> beim Zählen in einem fremden Fach erscheint „Gezählt – gehört aber in R3-F12 … Bitte zurückräumen“ (mit der Menge, die am richtigen Platz schon gezählt wurde). Die Zählung gilt trotzdem.</p>
            <p><strong>Inventur starten</strong> (Lager → Inventur): Scope wählen, "Blind zählen" (Soll-Bestand für den Zähler ausblenden) ist standardmäßig aktiv. Ein laufender Lauf kann pausiert und später fortgesetzt werden (Zwischenstand bleibt erhalten) oder endgültig abgebrochen werden.</p>
            <p><strong>Zählen</strong> (Button bei einem laufenden Lauf): zeigt die Soll-Liste passend zum Scope, pro Zeile Ist-Menge + Notiz eintragen und speichern (kein Seiten-Neuladen nötig). Oben kann jederzeit ein Artikel frei erfasst werden, der nicht auf der Liste steht (neue Charge, unerwarteter Fund).</p>
            <p><strong>Mehrere Zähler:</strong> bei Scope "Ganzes Lager" oben einen Lagerplatz als aktuellen Arbeitsbereich wählen — informativ, warnt nur wenn eine andere Person denselben Platz schon zählt.</p>
            <p><strong>Buchungssperre:</strong> läuft für ein Lager eine Voll-Lager-Inventur, sind Kasse und Wareneingang für genau dieses Lager gesperrt, bis die Inventur abgeschlossen/abgebrochen wird.</p>
            <p><strong>Abschluss</strong> ("Prüfen …"-Link): zeigt zuerst eine Vorschau — die Differenzliste stützt sich auf den Gesamtbestand pro Artikel/Lager (Summe alt vs. neu). Nicht gezählte Artikel werden einfach übersprungen (kein Blocker). Erst nach "Jetzt buchen &amp; abschließen" wird wirklich korrigiert: passt alles genau, wird nur das Inventurdatum gesetzt; hat sich nur die Chargen-/Lagerplatzverteilung geändert, wird umgebucht ohne Lagerbewegung; bei echter Mengenabweichung zusätzlich eine Lagerbewegung (Typ "inventur"/"schwund"). Fehlbestand ohne Notiz wird komplett verweigert. Alternativ: "Ohne Buchung pausieren" oder "Verwerfen ohne Buchung".</p>
            <p><strong>Scope "Lagerplatz":</strong> Vorher/Nachher bezieht sich hier nur auf diesen einen Platz, nicht den Gesamtbestand des Lagers — ein an einen anderen Platz umgelagerter Artikel ist am alten Platz eine lokale Unterschreitung (Notiz Pflicht), der Rest an anderen Plätzen bleibt unangetastet. Ein anderer, dort neu gefundener Artikel lässt sich ganz normal buchen.</p>
            <p><strong>Letzte Inventur:</strong> wird beim Abschluss für jeden gezählten Artikel gesetzt, sichtbar im Tab "Lager" der Artikel-Detailseite und als optionale Spalte in der Artikelliste.</p>
            <p><strong>Fortschritts-Anzeige:</strong> in der Inventur-Übersicht zeigt jeder laufende/pausierte Lauf einen Balken + Prozent, wie viele Soll-Positionen schon gezählt sind. Bei Scope "Lagerplatz" ohne bisherige Zuordnung gibt es stattdessen nur eine Anzahl erfasster Funde (kein Prozentwert möglich).</p>
            <p><strong>Manager-Auslauf-Shortcut:</strong> ab Manager-Rang gibt es in der Zählliste pro Zeile einen 🏁-Button, um den Artikel direkt (mit Sicherheitsabfrage) als Auslaufartikel zu markieren.</p>
            <p><strong>Druckversion:</strong> "🖨 Druckversion"-Button auf der Zählseite — druckoptimierte Seite (Browser-Druck/PDF-Export). Bei Scope "Ganzes Lager" wählbar: gesamte Liste, Suche nach bestimmtem Artikel, oder eine Blanko-Liste für einen einzelnen Lagerplatz zum handschriftlichen Ausfüllen.</p>

            <!-- EINSTELLUNGEN -->
            <h2 id="einstellungen">Einstellungen <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Navigation: oben rechts ⚙ Einstellungen. Vier Tabs: <strong>Firma · Kanäle · Mail/SMTP · System</strong>.</p>
            <p><strong>Firma:</strong> Firmenname, Adresse, Logo — erscheinen auf allen Rechnungen und Dokumenten. Vor dem ersten Druck ausfüllen!</p>
            <p><strong>Kanäle:</strong> WooCommerce-Shops verwalten (Name, URL, API-Schlüssel, Logo).</p>
            <p><strong>System:</strong> Preisanzeige in Aufträgen (Brutto/Netto), Kleinunternehmer-Modus, PLC-Polling-Ordner.</p>

            <h3 id="einstellungen-mail">Mail / SMTP <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <table class="ba-table">
                <tr><th>Feld</th><th>Beispiel</th></tr>
                <tr><td>SMTP-Host</td><td>mail.gmx.net</td></tr>
                <tr><td>SMTP-Port</td><td>587</td></tr>
                <tr><td>Verschlüsselung</td><td>tls</td></tr>
                <tr><td>Absender-Name</td><td>MEALANA KG</td></tr>
                <tr><td><strong>Mail aktiv</strong></td><td>Muss auf "Ja" stehen!</td></tr>
            </table>
            <p><strong>Test-Mail:</strong> E-Mail-Adresse eingeben → "Test-Mail senden" → Postfach prüfen.</p>
            <div class="ba-hint">💡 "Mail aktiv = Nein": System protokolliert intern, sendet aber nichts. Gut zum Testen.</div>

            <!-- LIEFERANTEN -->
            <h2 id="lieferanten">Lieferanten <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Lieferanten sind ein <strong>eigenständiges Modul</strong> unter Einkauf → Lieferanten (nicht mehr Teil der Artikel-Stammdaten).</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Stammdaten: Name/Firma, Land (Dropdown), UStID, Steuerregel, Adresse, Kontaktdaten</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Konditionen: Zahlungsziel, Skonto, Mindestbestellwert, Standard-Lieferzeit/-kosten</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Bankverbindung (IBAN/BIC) — nur bei Bedarf sichtbar</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Tab <strong>Vertreter</strong>: beliebig viele Ansprechpartner je Lieferant</div></div>
            <div class="ba-step"><div class="ba-step-nr">5</div><div>Tab <strong>Zugänge</strong>: Login-Daten für Händlerportale (Passwort per Klick einblendbar)</div></div>
            <div class="ba-warn">⚠ Zugangs-Passwörter werden unverschlüsselt gespeichert — nur für unkritische Portal-Logins verwenden.</div>
            <p>Tabs <strong>Artikel</strong> und <strong>Bestellungen</strong> zeigen alle Verknüpfungen zu diesem Lieferanten. Siehe auch <a href="#einkauf">Einkauf & Bestellungen</a>.</p>

            <!-- HERSTELLER -->
            <h2 id="hersteller">Hersteller <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Hersteller anlegen: Artikel → Hersteller → Neu. Felder: Name, Logo, Website, GPSR-Kontakt (EU-Produktsicherheitsverordnung). Hersteller werden dann bei jedem Artikel zugeordnet.</p>

            <!-- BENUTZER -->
            <h2 id="benutzer">Benutzer & Rechte <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Rollen: <strong>superadmin</strong> (alles), <strong>admin</strong> (alles außer System), <strong>mitarbeiter</strong> (eingeschränkt).</p>
            <p>Benutzer verwalten: Einstellungen → Benutzer (im Aufbau — aktuell über Datenbankzugriff).</p>

            <!-- KASSE -->
            <h2 id="kasse">Kasse <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Das Point-of-Sale-System für das Ladengeschäft. Eigene Oberfläche, optimiert für Touchscreen und Barcode-Scanner.</p>
            <p><strong>Adresse:</strong> <code>http://localhost<?= BASE_PATH ?>/kasse/</code></p>

            <h3 id="kasse-bon">Bon erstellen — Normaler Verkauf <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>EAN scannen</strong> — Barcode-Scanner auf Ware richten → Artikel erscheint sofort im Warenkorb<br><small>Oder: Lupe-Symbol → Namenssuche (Name/Artikelnummer eintippen)</small></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Bei <strong>Varianten-Artikeln</strong> (z.B. DROPS Lima mit Farben) öffnet sich automatisch ein Auswahlfeld → Variante wählen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Bei <strong>Garnen mit Chargen-Pflicht</strong> öffnet sich der Chargen-Dialog → Partie/Charge wählen (FIFO — älteste zuerst)</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div><strong>Zahlart wählen:</strong> Bar (Rückgeld wird berechnet) / Karte extern / Gutschein</div></div>
            <div class="ba-step"><div class="ba-step-nr">5</div><div>→ <strong>Bon erstellen</strong> — Bon wird gedruckt, Lagerabgang automatisch gebucht</div></div>
            <div class="ba-hint">💡 Mehrfachmengen: Zahl eintippen, dann einmal scannen → Menge sofort eingetragen. Rabatt: Position antippen → % eingeben.</div>
            <table class="ba-table">
                <tr><th>Zahlart</th><th>Was passiert</th></tr>
                <tr><td><strong>Bar</strong></td><td>Gegeben-Betrag eingeben → Rückgeld wird angezeigt</td></tr>
                <tr><td><strong>Karte extern</strong></td><td>SumUp/Bankomat — Betrag extern bestätigen, hier nur dokumentiert</td></tr>
                <tr><td><strong>Gutschein</strong></td><td>Code eingeben → Prüfen → Guthaben wird angezeigt; reicht es nicht, Rest bar oder mit Karte (siehe <a href="#gutscheine">Gutscheine</a>)</td></tr>
                <tr><td><strong>Divers</strong></td><td>Freie Position ohne Stammdaten — Beschreibung + Betrag eingeben</td></tr>
            </table>

            <h3 id="kasse-abholbereit">Abholbereit+bezahlt — Aufträge übergeben <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Wenn ein ERP-Auftrag auf "Abholbereit" gesetzt und bezahlt ist, erscheint er in der Kasse unter <strong>Offene Auswahl</strong>.</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Kasse → <strong>Offene Auswahl</strong> → Auftrag wählen</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Tatsächlich mitgenommene Mengen eingeben (kann vom Auftrag abweichen)</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>System erkennt automatisch den Fall:</div></div>
            <table class="ba-table">
                <tr><th>Fall</th><th>Was passiert</th></tr>
                <tr><td><strong>Exakt</strong> — Mengen stimmen</td><td>Kein Bon nötig, Auftrag direkt abgeschlossen</td></tr>
                <tr><td><strong>Retour</strong> — Kunde nimmt weniger</td><td>Retour-Bon wird erstellt, Differenz in Bar zurückgezahlt</td></tr>
                <tr><td><strong>Extra</strong> — Kunde nimmt mehr</td><td>Extra-Bon für die Zugaben, Zusatzbetrag einzahlen</td></tr>
                <tr><td><strong>Mix</strong> — teils retour, teils extra</td><td>Retour-Bon + Extra-Bon werden erstellt</td></tr>
            </table>
            <div class="ba-hint">💡 Belege: hier kassierte Ware → Bon (= Rechnung, A4 an der Abholmail). Vorab bezahlte Ware → automatisch eine <strong>Rechnung</strong> an der Abholmail. „Will er nicht“ bei bezahltem Auftrag → Bon-Zeile „Rückzahlung (nicht abgeholt)“ mit 0 % (Anzahlung zurück, kein Umsatz).</div>
            <div class="ba-hint">💡 <strong>Guthaben</strong> (mehr bezahlt als der Auftrag jetzt kostet, z.B. Versand auf Abholung umgestellt): beim Bezahlen fragt die Kasse „Bar auszahlen“ oder „Als Gutschein ausstellen“ — Bon-Zeile „Rückzahlung Guthaben zu Auftrag …“ mit 0 %.</div>

            <h3 id="kasse-sammelabholung">Sammelabholung — mehrere Bestellungen eines Kunden <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Hat ein Kunde mehrere Bestellungen zur Abholung, holt er sie mit <strong>einem</strong> Bon ab.</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>📦 Auftrag</strong> → eine der Bestellungen suchen und anklicken</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Die Kasse bietet weitere offene Abholungen desselben Kunden an — abholbereite sind angehakt, noch nicht gepackte nicht</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div><strong>Ausgewählte mitladen</strong> — jeder Auftrag erscheint als eigener Block mit Nummer und „bezahlt/unbezahlt“</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Nicht Mitgenommenes per <strong>−</strong> auf 0 setzen, Regal-Artikel einfach dazuscannen, dann <strong>Bezahlen</strong></div></div>
            <table class="ba-table">
                <tr><th>Situation</th><th>Was passiert</th></tr>
                <tr><td>Auftrag schon online bezahlt</td><td>Steht nicht im Kassenbetrag, wird nur abgeschlossen</td></tr>
                <tr><td>Auftrag unbezahlt</td><td>Wird mit dem Bon bezahlt (nur die mitgenommenen Mengen)</td></tr>
                <tr><td>Teilabholung</td><td>Kasse fragt je Zeile: „holt er später“ (Rest bleibt im Abholfach, teilgeliefert, kommt beim nächsten Laden wieder) oder „will er nicht“ (Rücklagerung am Packplatz, bezahlt → Geld zurück)</td></tr>
                <tr><td>Von einem Auftrag nichts mitgenommen</td><td>Auftrag bleibt unverändert liegen (✕ nimmt ihn ganz vom Bon)</td></tr>
                <tr><td>Alles bezahlt, alles mitgenommen</td><td>Kein Bon nötig, alle Aufträge werden direkt abgeschlossen</td></tr>
            </table>
            <p>Nur Aufträge <strong>desselben Kunden</strong> lassen sich zusammenfassen. Auf dem Bon steht jeder Auftrag mit seiner Nummer, bereits bezahlte als „Abgeholt, bereits bezahlt: …“. Eine Retoure aus einem älteren Auftrag braucht weiterhin einen eigenen Bon.</p>

            <h3 id="kasse-freitext-retour">Freitext-Retour <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Für Rückgaben ohne Auftrag im ERP (z.B. alte JTL-Verkäufe von vor der Umstellung).</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>⚙ Menü → <strong>↩ Freitext-Retour</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Artikel suchen, Menge + Rückerstattungs-Preis eintragen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>Bei chargenpflichtigen Artikeln: Charge eintragen <em>oder</em> "Charge unbekannt" anhaken (Pflicht)</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>→ <strong>Zurücknehmen</strong> — rote Zeile mit ↩ im Warenkorb, dann normal bezahlen (Auszahlung bei reiner Retoure)</div></div>
            <div class="ba-hint">💡 Ware danach am Packplatz unter "Rücklagerungen" einbuchen — die Kasse bucht nur das Geld, nicht das Lager.</div>

            <h3 id="kasse-rechnung-bezahlen">Rechnung bezahlen (Zahlbeleg) <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p>Kunde zahlt eine <strong>offene Rechnung</strong> bar oder mit Karte im Geschäft.</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>⚙ Menü → <strong>💶 Rechnung bezahlen</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Rechnungs-/Auftragsnummer (auch Teil) oder Kundenname eintippen — Treffer anklicken (offener Betrag vorausgefüllt, Teilzahlung möglich). Abholung ohne Rechnung → wird normal als Auftrag geladen</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div><strong>+ Hinzufügen</strong> → Zeile „Zahlung zu Auftrag …“ mit 0 % → normal bezahlen</div></div>
            <div class="ba-hint">💡 RKSV-signiert, zählt in Kassenbuch und Tagesabschluss, aber kein neuer Umsatz. Die Zahlung steht beim Auftrag und erscheint im Rechnungs-Nachdruck. Storno des Bons nimmt die Zahlung automatisch zurück. Nicht zusammen mit Abholung/Retoure auf einem Bon.</div>

            <h3 id="kasse-kassensturz">Kassensturz / Tagesabschluss <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <p><strong>X-Bon</strong> (Zwischenbericht): Zeigt den aktuellen Stand ohne Abschluss — gut für Zwischenkontrollen.</p>
            <p><strong>Z-Bon</strong> (Tagesabschluss):</p>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>Kasse → <strong>Kassensturz</strong></div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div><strong>Zählhilfe:</strong> Scheine und Münzen einzeln eingeben → Summe wird berechnet</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div>→ <strong>Z-Bon erstellen</strong> — echter Tagesabschluss, Z-Bon wird gedruckt</div></div>
            <div class="ba-hint">💡 Bon stornieren: Kasse → Bon-Journal → Bon suchen → Stornieren. Lagerabgang wird automatisch rückgebucht.</div>
            <div class="ba-hint">💡 Druckerkonfiguration: 80mm Thermodrucker als Windows-Standarddrucker setzen. Im Browser: Rand "Keine", Kopfzeile "Aus".</div>
            <p>RKSV-Signatur läuft über den BFR BONit Fiscal Recorder — jeder Bon wird automatisch signiert. Ist BFR kurz nicht erreichbar, verkauft die Kasse trotzdem weiter (Bon zeigt "Sicherheitseinrichtung ausgefallen"), Details unter Kasse → 🔏 RKSV.</p>
            <div class="ba-hint">💡 Bon parken: ⏸ Parken-Button — mehrere Bons können gleichzeitig geparkt und später wieder abgerufen werden.</div>

            <!-- GUTSCHEINE -->
            <h2 id="gutscheine">Gutscheine <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Jeder Gutschein hat einen eigenen Code (<code>MEA-XXXX-XXXX-XXXX</code>), ist standardmäßig 10 Jahre gültig und beim Verkauf <strong>steuerfrei</strong> (Mehrzweckgutschein — die Umsatzsteuer fällt erst beim Einkauf damit an). Bei einer <strong>Teileinlösung</strong> wird der alte Code ungültig und der Kunde bekommt für den Rest einen neuen Code. Gutscheine sind an der Kasse und im Online-Shop einlösbar (siehe <a href="#gutscheine-shop">Online-Shop</a>).</p>
            <p><strong>Einmalig einrichten:</strong> Artikel mit <strong>Artikeltyp "Gutschein"</strong> und <strong>Artikelgruppe "4700 – Gutscheine"</strong> anlegen. Die Steuerklasse wird beim Speichern automatisch auf steuerfrei gesetzt, ein Preis ist nicht nötig.</p>

            <h3 id="gutscheine-verkaufen">An der Kasse verkaufen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div>⚙ Menü → <strong>🎁 Gutschein verkaufen</strong> (oder Gutschein-Artikel suchen/scannen)</div></div>
            <div class="ba-step"><div class="ba-step-nr">2</div><div>Betrag eingeben oder Schnellbetrag antippen, optional <strong>Für</strong> (Name des Beschenkten)</div></div>
            <div class="ba-step"><div class="ba-step-nr">3</div><div><strong>+ Hinzufügen</strong> → normal bezahlen (Bar / Karte / Kombi)</div></div>
            <div class="ba-step"><div class="ba-step-nr">4</div><div>Code + <strong>📄 PDF öffnen</strong> erscheinen, danach wie gewohnt der Bon</div></div>
            <div class="ba-hint">💡 Der Code steht zusätzlich auf dem Kassenbon. Auf Gutscheine gibt es keinen Rabatt.</div>

            <h3 id="gutscheine-bezahlen">Mit Gutschein bezahlen</h3>
            <div class="ba-step"><div class="ba-step-nr">1</div><div><strong>BEZAHLEN</strong> → <strong>🎁 Gutschein</strong> → Code eintippen → <strong>Prüfen</strong></div></div>
            <table class="ba-table">
                <tr><th>Fall</th><th>Was passiert</th></tr>
                <tr><td>Guthaben reicht</td><td><strong>✓ Einlösen</strong> — bei Restguthaben bekommt der Kunde einen neuen Code (PDF + auf dem Bon)</td></tr>
                <tr><td>Guthaben reicht nicht</td><td>"Offen: € …" → <strong>Rest bar</strong> (Rückgeld wird angezeigt) oder <strong>Rest Karte</strong></td></tr>
                <tr><td>Code schon eingelöst</td><td>Kasse zeigt den neuen Code, auf dem das Restguthaben liegt</td></tr>
                <tr><td>storniert / abgelaufen / unbekannt</td><td>Rote Meldung, keine Einlösung</td></tr>
            </table>

            <h3 id="gutscheine-storno">Storno &amp; Retoure</h3>
            <p><strong>Retoure als Gutschein:</strong> im Retour-Dialog <strong>🎁 Als Gutschein ausstellen</strong> statt Bargeld auszuzahlen.</p>
            <p><strong>Storno</strong> (Bon-Journal): ein auf dem Bon verkaufter Gutschein wird mitstorniert, solange er unbenutzt ist (sonst Warnung → manuell klären). Wurde mit Gutschein bezahlt, bekommt der Kunde den Betrag als neuen Code zurück.</p>
            <p><strong>Verwaltung:</strong> Verkauf → Gutscheine — Liste, manuell ausstellen, Historie jedes Codes inkl. Nachfolger-Codes, PDF, erneut versenden.</p>
            <p><strong>Abfragen an der Kasse:</strong> ⚙ Menü → <strong>🔍 Gutschein abfragen</strong> — zeigt Status, Restguthaben, Gültigkeit und ggf. den Nachfolger-Code, ohne etwas zu buchen.</p>

            <h3 id="gutscheine-shop">Online-Shop</h3>
            <p><strong>Kaufen:</strong> Gutschein-Artikel im Shop ("ab 10,00 € – Betrag frei wählbar") → Betrag, "Selbst ausdrucken" oder "direkt an den Empfänger senden" (Name, E-Mail, optional Zustelldatum), Grußtext. Sobald die Bestellung bezahlt ist, legt das ERP beim nächsten Abgleich den Gutschein an und verschickt PDF + Mail.</p>
            <p><strong>Einlösen:</strong> Jeder Gutschein (Kasse, ERP oder online gekauft) ist im Shop als Code einlösbar — wird <strong>nach der Steuer</strong> wie ein Zahlungsmittel abgezogen und deckt auch die <strong>Versandkosten</strong>. Bei Teileinlösung steht im Warenkorb "Rest … kommt per E-Mail als neuer Code", der Kunde bekommt ihn nach der Bestellung zugeschickt.</p>
            <div class="ba-hint">💡 An der Kasse eingelöste oder stornierte Gutscheine werden im Shop beim nächsten Abgleich (spätestens nach 15 Minuten) ungültig. Die Codes im wp-admin (Germanized-"Wertgutscheine") verwaltet das ERP — dort nicht von Hand ändern.</div>

            <!-- INVENTUR -->
            <h2 id="inventur">Inventur <span class="ba-badge ba-badge-geplant">Geplant</span></h2>
            <p>Kommt nach der Kasse. Geplant: Mobile Zählliste per EAN-Scan, Abschluss mit Differenzprotokoll, Übernahme in Lagerbestand.</p>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/shell_bottom.php'; ?>
