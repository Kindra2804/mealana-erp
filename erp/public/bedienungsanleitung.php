<?php
require_once __DIR__ . '/../src/core/Auth.php';
Auth::requireLogin();

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
.ba-toc a.sub { padding-left: 18px; font-size: 12px; color: var(--color-text-muted); }
.ba-content h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-nav);
    margin: 32px 0 8px;
    padding-top: 8px;
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
.ba-badge-fertig   { background: #dcfce7; color: #166534; }
.ba-badge-arbeit   { background: #fef9c3; color: #854d0e; }
.ba-badge-geplant  { background: #f1f5f9; color: #64748b; }
.ba-placeholder {
    background: var(--color-bg);
    border: 1px dashed var(--color-border);
    border-radius: 6px;
    padding: 16px 20px;
    color: var(--color-text-muted);
    font-size: 13px;
    margin: 8px 0 16px;
}
.ba-content p { font-size: 13px; line-height: 1.7; margin: 6px 0 12px; color: var(--color-text); }
.ba-content ul { font-size: 13px; line-height: 1.8; padding-left: 20px; margin: 6px 0 12px; color: var(--color-text); }
</style>

<div class="card" style="padding:24px">
    <div class="ba-layout">

        <!-- ── Inhaltsverzeichnis ──────────────────────────────────────────── -->
        <nav class="ba-toc">
            <h3>Inhalt</h3>
            <a href="#einleitung">Einleitung</a>
            <a href="#navigation">Navigation</a>
            <a href="#artikel">Artikel</a>
            <a href="#artikel-varianten"  class="sub">↳ Varianten & Achsen</a>
            <a href="#artikel-bilder"     class="sub">↳ Bilder</a>
            <a href="#artikel-merkmale"   class="sub">↳ Merkmale</a>
            <a href="#artikel-preise"     class="sub">↳ Preise & Aktionen</a>
            <a href="#lager">Lager</a>
            <a href="#lager-wareneingang" class="sub">↳ Wareneingang</a>
            <a href="#einkauf">Einkauf & Bestellungen</a>
            <a href="#kunden">Kunden</a>
            <a href="#lieferanten">Lieferanten</a>
            <a href="#hersteller">Hersteller</a>
            <a href="#partner">Partner & Mietfächer</a>
            <a href="#benutzer">Benutzer & Rechte</a>
            <a href="#kasse">Kasse</a>
            <a href="#verkauf">Verkauf & Aufträge</a>
            <a href="#inventur">Inventur</a>
        </nav>

        <!-- ── Kapitel ────────────────────────────────────────────────────── -->
        <div class="ba-content">

            <h2 id="einleitung">Einleitung <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>MeaLana ERP ist das Warenwirtschaftssystem der Wollboutique MeaLana. Es umfasst Artikelverwaltung, Lager, Einkauf, Kunden und (zukünftig) Kasse und Onlineshop-Anbindung.</p>
            <p>Das System läuft lokal auf einem Windows-PC mit XAMPP. Geöffnet wird es im Browser unter <strong>http://localhost/mealana/</strong>.</p>

            <h2 id="navigation">Navigation <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Die Hauptnavigation befindet sich oben: <strong>Artikel · Lager · Kunden · Einkauf · Partner</strong>. Ausgegraut = noch nicht fertig.</p>
            <p>Links neben dem Inhalt befindet sich die <strong>Sidebar</strong> mit Unterseiten des aktiven Moduls. Zum Beispiel zeigt die Artikel-Sidebar: Liste · Neu erstellen · Kategorien · Merkmale · Preise/Aktionen · Hersteller.</p>

            <h2 id="artikel">Artikel <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Unter <em>Artikel → Liste</em> sieht man alle Artikel. Mit dem Filter oben kann nach aktiv/inaktiv, Typ, Kategorie und Qualitätsproblemen (fehlende EAN, fehlende Bilder) gefiltert werden.</p>

            <h3 id="artikel-varianten">Varianten & Achsen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-placeholder">Kapitel wird ergänzt: Achsen anlegen, Varianten-Kombi-Generator, Vater-Kind-Prinzip.</div>

            <h3 id="artikel-bilder">Bilder <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-placeholder">Kapitel wird ergänzt: Upload, Hauptbild setzen, Reihenfolge, Alt-Text.</div>

            <h3 id="artikel-merkmale">Merkmale <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-placeholder">Kapitel wird ergänzt: Merkmal-Gruppen, Werte befüllen, WooCommerce-Slug.</div>

            <h3 id="artikel-preise">Preise & Aktionen <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-placeholder">Kapitel wird ergänzt: Brutto/Netto, Kundengruppen, Aktionen anlegen, Kategorie-Zuweisung, Jarvis-Cronjob.</div>

            <h2 id="lager">Lager <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <p>Das Lager-Modul verwaltet Lagerbestände und Lagerbewegungen. Es gibt zwei Lager: <strong>K1 (Laden)</strong> und <strong>K2 (Lager/Messe)</strong>.</p>

            <h3 id="lager-wareneingang">Wareneingang <span class="ba-badge ba-badge-fertig">Fertig</span></h3>
            <div class="ba-placeholder">Kapitel wird ergänzt: EAN scannen, Charge eingeben, Mengen buchen, Abschluss-Dialog.</div>

            <h2 id="einkauf">Einkauf & Bestellungen <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Bestellung anlegen, Positionen, Bestellung bei Lieferant bestätigen, Wareneingang erfassen, Teillieferungen, Sammelliste/Retroaktiv.</div>

            <h2 id="kunden">Kunden <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Neuen Kunden anlegen, B2B/B2C-Unterschied, Adressen, DSGVO-Consent, Datenverschlüsselung.</div>

            <h2 id="lieferanten">Lieferanten <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Lieferant anlegen, Vertreter, Artikel-Verknüpfung (EK-Preis, Bestellnummer, Lieferzeit).</div>

            <h2 id="hersteller">Hersteller <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Hersteller anlegen, EU/REO-Feld, Logo, Verknüpfung mit Artikel.</div>

            <h2 id="partner">Partner & Mietfächer <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Partnertypen (Kommission/Spende/Mietfach), Mietfach als physische Einheit, Vertragshistorie.</div>

            <h2 id="benutzer">Benutzer & Rechte <span class="ba-badge ba-badge-fertig">Fertig</span></h2>
            <div class="ba-placeholder">Kapitel wird ergänzt: Rollen (superadmin/admin/mitarbeiter), Benutzer anlegen, Berechtigungsmatrix.</div>

            <h2 id="kasse">Kasse <span class="ba-badge ba-badge-geplant">Geplant</span></h2>
            <div class="ba-placeholder">Kapitel folgt wenn Kasse implementiert ist: RKSV, Fiskaly, Barcode-Scan, Zahlarten, Tagesabschluss.</div>

            <h2 id="verkauf">Verkauf & Aufträge <span class="ba-badge ba-badge-geplant">Geplant</span></h2>
            <div class="ba-placeholder">Kapitel folgt wenn Verkauf implementiert ist: Auftragserfassung, Vorkasse/PayPal, Mahnläufe, Fehlbestand-Konzept.</div>

            <h2 id="inventur">Inventur <span class="ba-badge ba-badge-geplant">Geplant</span></h2>
            <div class="ba-placeholder">Kapitel folgt wenn Inventur implementiert ist: mobile Zählliste, EAN-Scan, Abschluss mit Differenzprotokoll.</div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/shell_bottom.php'; ?>
