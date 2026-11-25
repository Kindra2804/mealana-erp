<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/import/JtlVaterKindImportService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/import/ImportKategorieZuordnung.php';
require_once __DIR__ . '/../../src/modules/import/JtlImportListe.php';

$service = new ArtikelService();
$db      = Database::getInstance();

$kategorienBaum = $service->getKategorienBaum();
$alleArtikeltypen = $service->getAllArtikelTypen();

/** Rendert die Kategorien als eingerückte <option>-Liste, damit gleichnamige Unterkategorien
 *  (z.B. mehrere "Zubehör" unter verschiedenen Hauptkategorien) unterscheidbar bleiben. */
function renderKategorieOptionen(array $knoten, int $tiefe = 0): void
{
    echo kategorieOptionenHtml($knoten, $tiefe);
}

/** Wie renderKategorieOptionen(), aber als String -- die Zuordnungstabelle braucht die Liste je Zeile (einmal bauen, je Zeile nur „selected“ setzen). */
function kategorieOptionenHtml(array $knoten, int $tiefe = 0): string
{
    $html = '';
    foreach ($knoten as $k) {
        $html .= '<option value="' . $k['id'] . '">' . htmlspecialchars(str_repeat('— ', $tiefe) . $k['name']) . '</option>';
        if (!empty($k['kinder'])) {
            $html .= kategorieOptionenHtml($k['kinder'], $tiefe + 1);
        }
    }
    return $html;
}
$alleShops = $db->query("SELECT id, name, nr_kuerzel FROM shops ORDER BY id")->fetchAll();
$alleArtikelgruppen = $db->query("
    SELECT id, konto_nr, name FROM artikel_gruppen WHERE aktiv = 1 ORDER BY sortierung, konto_nr
")->fetchAll();
$alleEinheiten = $service->getAllEinheiten();

/** Rendert das Artikeltyp+Artikelgruppe-Dropdown-Paar für eine einzelne Zeile in der
 *  Kontrollliste (Vater ODER normaler Artikel) -- gemischte Kategorien können pro Artikel
 *  unterschiedliche Werte brauchen, daher nicht mehr global für den ganzen Import. */
function renderArtikeltypGruppeFelder(string $namePrefix, array $alleArtikeltypen, array $alleArtikelgruppen, string $standardTyp, int $standardGruppeId): void
{
    ?>
    <div>
        <label class="erp-label" style="font-size:11px">Artikeltyp</label>
        <select name="<?= $namePrefix ?>[artikeltyp_code]" class="erp-select" style="width:100%">
            <option value="">– wählen –</option>
            <?php foreach ($alleArtikeltypen as $t): ?>
                <option value="<?= htmlspecialchars($t['code']) ?>" <?= $t['code'] === $standardTyp ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="erp-label" style="font-size:11px">Artikelgruppe</label>
        <select name="<?= $namePrefix ?>[artikel_gruppe_id]" class="erp-select" style="width:100%">
            <option value="">– wählen –</option>
            <?php foreach ($alleArtikelgruppen as $g): ?>
                <option value="<?= $g['id'] ?>" <?= (int) $g['id'] === $standardGruppeId ? 'selected' : '' ?>><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

$vorschau = null;
$fehler   = [];
if (!empty($_SESSION['fehler'])) { // z.B. abgeschnittenes Formular aus jtl_import_commit.php
    $fehler = (array) $_SESSION['fehler'];
    unset($_SESSION['fehler']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_stammdaten']['tmp_name'])) {
    $kategorieId  = (int) ($_POST['kategorie_id'] ?? 0);
    // Nur noch eine VORAUSWAHL zum Vorbefüllen der Pro-Artikel-Dropdowns in der Kontrollliste
    // (siehe unten) -- gemischte Kategorien können Väter/normale Artikel mit unterschiedlichem
    // Artikeltyp/Artikelgruppe enthalten (z.B. Garn + Zubehör in derselben "Sale"-Kategorie),
    // daher nicht mehr global für den ganzen Import bindend.
    $artikeltypStandard      = trim($_POST['artikeltyp_code'] ?? '');
    $artikelGruppeIdStandard = (int) ($_POST['artikel_gruppe_id'] ?? 0);
    $katZuordnen = isset($_POST['kategorien_zuordnen']);
    $kanalShopId = (int) ($_POST['kanal_shop_id'] ?? 0);

    if ($kategorieId <= 0 && !$katZuordnen) {
        $fehler[] = 'Bitte eine feste Kategorie auswählen oder „Kategorien aus JTL zuordnen“ anhaken.';
    } else {
        $importService = new JtlVaterKindImportService();
        // MeaLana-Übernahme: Babsis Liste + neues Nummernschema (artikel/jtl_benennung.php)
        if (isset($_POST['liste_anwenden'])) $importService->setzeListe(new JtlImportListe());
        $mitKindern     = $importService->parseMitKindern($_FILES['csv_stammdaten']['tmp_name']);
        // Kategorien-CSV (optional, Jackys „Eigener Export“ mit KategorieIDs + Kategorien): Die
        // Ameise-Stammdaten haben keine Kategorie-Spalten -- über die Artikelnummer dazuholen.
        // Zwei Formate:
        //  a) Eigener Export: je Artikel EINE Zeile mit KategorieIDs + Kategorien (pipe-getrennt, paarweise)
        //  b) Ameise „Artikelkategorien“: je Artikel UND Kategorie eine Zeile mit „Kategorie Ebene 1..10“
        //     → der volle Pfad („Wolle und Garne › Zusammensetzung › 100% Schurwolle“) ist eindeutig und
        //     wird selbst als Kennung verwendet (fremd_id), Name = Pfad. Gleiche Weiterverarbeitung wie a).
        $katDateiZeilen = 0;
        if (!empty($_FILES['csv_kategorien']['tmp_name'])) {
            $pfadeJeNr = [];
            foreach (JtlCsvReader::lese($_FILES['csv_kategorien']['tmp_name']) as $katRow) {
                $nr = trim((string)($katRow['Artikelnummer'] ?? ''));
                if ($nr === '' || !isset($mitKindern[$nr])) continue;
                if (array_key_exists('Kategorie Ebene 1', $katRow)) {
                    $teile = [];
                    for ($ebene = 1; $ebene <= 10; $ebene++) {
                        $t = trim((string)($katRow["Kategorie Ebene $ebene"] ?? ''));
                        if ($t === '') break;
                        $teile[] = str_replace('|', '/', $t);
                    }
                    if ($teile) $pfadeJeNr[$nr][implode(' › ', $teile)] = true;
                } else {
                    $mitKindern[$nr]['KategorieIDs'] = $katRow['KategorieIDs'] ?? '';
                    $mitKindern[$nr]['Kategorien']   = $katRow['Kategorien'] ?? '';
                }
                $katDateiZeilen++;
            }
            foreach ($pfadeJeNr as $nr => $pfade) {
                $liste = implode('|', array_map(fn($p) => mb_substr($p, 0, 255), array_keys($pfade)));
                $mitKindern[$nr]['KategorieIDs'] = $liste;   // Pfad = Kennung
                $mitKindern[$nr]['Kategorien']   = $liste;   // Pfad = Anzeigename
            }
        }
        if ($katZuordnen && $katDateiZeilen === 0
            && !array_filter($mitKindern, fn($r) => trim((string)($r['KategorieIDs'] ?? '')) !== '')) {
            $fehler[] = 'Für „Kategorien aus JTL zuordnen“ fehlen die Kategorien: Die Stammdaten-CSV hat keine Spalten KategorieIDs/Kategorien '
                . 'und es wurde keine passende Kategorien-CSV (Eigener Export) hochgeladen — oder deren Artikelnummern passen nicht zur Stammdaten-CSV.';
        }
        // Variationskombinationen-CSV ist optional -- fehlt sie (z.B. Kategorie besteht
        // nur aus normalen Artikeln ohne jede Vater/Kind-Variante), gibt es einfach keine
        // Väter/Kinder zu erkennen, die Stammdaten-Zeilen laufen komplett über den
        // "normale Artikel"-Zweig.
        $kombisProVater = !empty($_FILES['csv_kombinationen']['tmp_name'])
            ? $importService->parseVariationskombinationen($_FILES['csv_kombinationen']['tmp_name'])
            : [];
        $vorschauDaten  = $importService->baueVorschau($mitKindern, $kombisProVater);

        if ($fehler) {
            // z.B. Kategorien fehlen -- Formular mit Meldung erneut zeigen, keine Vorschau
        } elseif (empty($vorschauDaten['vaeter']) && empty($vorschauDaten['normale'])) {
            if ($vorschauDaten['vater_ohne_kombidatei_anzahl'] > 0) {
                // Stammdaten wurden erkannt (Väter gefunden), aber KEINE ihrer Artikelnummern
                // kommt in der Kombinationen-Datei vor -- typischerweise weil die falsche
                // Kombinationen-CSV hochgeladen wurde (z.B. Export einer anderen Kategorie).
                $fehler[] = "Es wurden {$vorschauDaten['vater_ohne_kombidatei_anzahl']} Vater-Artikel in der Stammdaten-CSV gefunden, "
                    . 'aber keiner davon kommt in der Variationskombinationen-CSV vor. Die beiden Dateien passen '
                    . 'vermutlich nicht zusammen (falsche/veraltete Kombinationen-Datei hochgeladen, z.B. Export einer anderen Kategorie?). '
                    . 'Bitte die Variationskombinationen-CSV gezielt für diese Kategorie neu aus JTL exportieren.';
            } elseif (($vorschauDaten['liste']['ausgeschlossen'] ?? 0) > 0 || !empty($vorschauDaten['liste']['ungeprueft'])) {
                // Dateien waren richtig, aber Babsis Liste hat alles herausgefiltert
                $ung = (array) $vorschauDaten['liste']['ungeprueft'];
                $fehler[] = 'Alle Artikel wurden durch die Liste („Liste anwenden“) herausgefiltert: '
                    . (int) ($vorschauDaten['liste']['ausgeschlossen'] ?? 0) . ' stehen auf „nicht übernehmen“, '
                    . count($ung) . ' sind noch nicht geprüft'
                    . ($ung ? ' (z. B. ' . implode(', ', array_slice($ung, 0, 5)) . ')' : '') . '. '
                    . 'Ist die Liste in diesem ERP vollständig (Datentransfer „jtl_liste“)? Sonst in Artikel → JTL: neue Nummern prüfen.';
            } else {
                $fehler[] = 'Keine Artikel erkannt. Bitte Dateien prüfen (Stammdaten-CSV + Variationskombinationen-CSV vertauscht?).';
            }
        } else {
            // UVP nur mit Häkchen übernehmen (Jacky 2026-11-15): JTL-„UVP“ ist oft keine echte Herstellerempfehlung
            if (!isset($_POST['uvp_uebernehmen'])) {
                foreach ($vorschauDaten['vaeter'] as &$vv) {
                    $vv['uvp'] = null;
                    foreach ($vv['kinder'] as &$kk) { $kk['uvp'] = null; }
                    unset($kk);
                }
                unset($vv);
                foreach ($vorschauDaten['normale'] as &$nn) { $nn['uvp'] = null; }
                unset($nn);
            }
            $_SESSION['jtl_import_vorschau']            = $vorschauDaten['vaeter'];
            $_SESSION['jtl_import_vorschau_normale']    = $vorschauDaten['normale'];
            $_SESSION['jtl_import_einheiten_unbekannt'] = $vorschauDaten['einheiten_unbekannt'];
            $_SESSION['jtl_import_verwaiste_kinder']    = $vorschauDaten['verwaiste_kinder_anzahl'];
            $_SESSION['jtl_import_vater_ohne_kombidatei'] = $vorschauDaten['vater_ohne_kombidatei_anzahl'];
            $_SESSION['jtl_import_kategorie_id']      = $kategorieId;
            $_SESSION['jtl_import_artikeltyp_standard']      = $artikeltypStandard;
            $_SESSION['jtl_import_artikel_gruppe_id_standard'] = $artikelGruppeIdStandard;
            $_SESSION['jtl_import_kanal_shop_id'] = $kanalShopId;
            $_SESSION['jtl_import_liste']         = $vorschauDaten['liste'];

            // Kategorie-Zuordnung (Migration 230): JTL-Kategorien je Import-Artikel -- beim Vater
            // zählen die Kategorien der Kinder-Zeilen mit (JTL hängt Unterkategorien oft nur an die Kinder).
            $katJeNr = [];
            $relevanteZeilen = [];
            foreach ($vorschauDaten['vaeter'] as $v) {
                // Stammdaten-Zeile über die JTL-Nummer (mit Babsis Liste ist 'artikelnummer' schon die neue)
                $vZeile = $mitKindern[$v['jtl_roh'] ?? $v['artikelnummer']] ?? null;
                $ids = ImportKategorieZuordnung::idsAusZeile($vZeile ?? []);
                if ($vZeile) $relevanteZeilen[] = $vZeile;
                foreach ($v['kinder'] as $k) {
                    $kZeile = $mitKindern[$k['jtl_nr'] ?? $k['artikelnummer']] ?? null;
                    $ids = array_merge($ids, ImportKategorieZuordnung::idsAusZeile($kZeile ?? []));
                    if ($kZeile) $relevanteZeilen[] = $kZeile;
                }
                $katJeNr[$v['artikelnummer']] = array_values(array_unique($ids));
            }
            foreach ($vorschauDaten['normale'] as $n) {
                $nZeile = $mitKindern[$n['jtl_roh'] ?? $n['artikelnummer']] ?? null;
                $katJeNr[$n['artikelnummer']] = ImportKategorieZuordnung::idsAusZeile($nZeile ?? []);
                if ($nZeile) $relevanteZeilen[] = $nZeile;
            }
            $_SESSION['jtl_import_kat_zuordnen'] = $katZuordnen;
            $_SESSION['jtl_import_kat_je_nr']    = $katZuordnen ? $katJeNr : [];
            $_SESSION['jtl_import_kat_liste']    = $katZuordnen ? ImportKategorieZuordnung::sammle($relevanteZeilen) : [];
            $vorschau = $vorschauDaten;
        }
    }
} elseif (!empty($_SESSION['jtl_import_vorschau']) || !empty($_SESSION['jtl_import_vorschau_normale'])) {
    // Zurück von der Kontrollliste (z.B. Validierungsfehler beim Commit) -> Vorschau erneut zeigen
    $vorschau = [
        'vaeter'                       => $_SESSION['jtl_import_vorschau'] ?? [],
        'normale'                      => $_SESSION['jtl_import_vorschau_normale'] ?? [],
        'einheiten_unbekannt'          => $_SESSION['jtl_import_einheiten_unbekannt'] ?? [],
        'verwaiste_kinder_anzahl'      => $_SESSION['jtl_import_verwaiste_kinder'] ?? 0,
        'vater_ohne_kombidatei_anzahl' => $_SESSION['jtl_import_vater_ohne_kombidatei'] ?? 0,
        'liste'                        => $_SESSION['jtl_import_liste'] ?? null,
    ];
}

$artikeltypStandard      = $_SESSION['jtl_import_artikeltyp_standard'] ?? '';
$artikelGruppeIdStandard = (int) ($_SESSION['jtl_import_artikel_gruppe_id_standard'] ?? 0);

if (isset($_GET['neu'])) {
    unset($_SESSION['jtl_import_vorschau'], $_SESSION['jtl_import_vorschau_normale'], $_SESSION['jtl_import_einheiten_unbekannt'],
          $_SESSION['jtl_import_verwaiste_kinder'], $_SESSION['jtl_import_vater_ohne_kombidatei'],
          $_SESSION['jtl_import_kategorie_id'], $_SESSION['jtl_import_artikeltyp_standard'], $_SESSION['jtl_import_artikel_gruppe_id_standard'],
          $_SESSION['jtl_import_kat_zuordnen'], $_SESSION['jtl_import_kat_je_nr'], $_SESSION['jtl_import_kat_liste'], $_SESSION['jtl_import_kanal_shop_id'], $_SESSION['jtl_import_liste']);
    header('Location: jtl_import.php');
    exit;
}

$pageTitle    = 'JTL Vater+Kind-Import';
$activeModule = 'artikel';
$actionBarContent = <<<HTML
<a href="liste.php" class="btn btn-secondary btn-sm">← Zurück zur Liste</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="margin-bottom:16px;border-color:#fca5a5;background:#fef2f2">
        <strong style="color:#dc2626">Fehler:</strong>
        <ul style="margin:8px 0 0 20px">
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($vorschau === null): ?>

<div class="card">
    <h2 style="margin:0 0 16px">JTL Vater+Kind-Import mit Achsenerkennung</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Legt aus zwei JTL-Exporten (Stammdaten "mit Kindern" + "Variationskombinationen") neue Vater-Artikel
        mit ihren Kind-Varianten inkl. erkannter Achsen an. Erkennt außerdem "normale" Einzelartikel in
        derselben Stammdaten-CSV (kein Vater, kein Kind) und legt sie separat an. Kategorien: entweder
        <strong>aus JTL zuordnen</strong> (mit der Kategorien-CSV aus dem Eigenen Export — die Kontrollliste zeigt jede
        JTL-Kategorie, du wählst die ERP-Kategorie, die Wahl wird gemerkt) und/oder eine feste Kategorie für den ganzen
        Durchlauf. Optional wird ein Shop-Kanal gleich eingeschaltet. Artikeltyp/Artikelgruppe hier sind nur eine Vorauswahl zum
        Vorbefüllen — da gemischte Kategorien Väter/Artikel mit unterschiedlichem Artikeltyp oder
        Artikelgruppe enthalten können (z.B. Garn + Zubehör in derselben Kategorie), lässt sich das
        pro Artikel in der Kontrollliste noch ändern.
        <strong>Artikel, die bereits existieren (gleiche Artikelnummer), werden nicht erneut angelegt —
        es wird nur die Kategorie zusätzlich zugewiesen, alle anderen Daten bleiben unangetastet.</strong>
        Vor dem eigentlichen Import wird eine Kontrollliste zur Korrektur angezeigt.
    </p>

    <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:800px;margin-bottom:20px">
            <div>
                <label class="erp-label">Stammdaten-CSV ("mit Kindern") *</label>
                <input type="file" name="csv_stammdaten" accept=".csv,text/csv" required
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
            </div>
            <div>
                <label class="erp-label">Variationskombinationen-CSV (optional — nur bei Vater+Kind-Artikeln nötig)</label>
                <input type="file" name="csv_kombinationen" accept=".csv,text/csv"
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
            </div>

            <div style="grid-column:1/-1">
                <label class="erp-label">Kategorien-CSV (optional — Ameise-Export „Artikelkategorien“ mit Kategorie Ebene 1–10, oder Eigener Export mit KategorieIDs/Kategorien)</label>
                <input type="file" name="csv_kategorien" accept=".csv,text/csv"
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">Die Ameise-Stammdaten enthalten keine Kategorien. Die Kategorien-CSV wird über die Artikelnummer dazugeholt; sie darf auch mehr Artikel enthalten (z.B. alle Artikel). Beim Artikelkategorien-Export ist der volle Pfad die Kennung.</div>
            </div>
            <div style="grid-column:1/-1;background:var(--color-bg-subtle,#f8fafc);border:1px solid var(--color-border);border-radius:6px;padding:10px 12px">
                <label style="font-size:13px;cursor:pointer;display:flex;gap:8px;align-items:flex-start">
                    <input type="checkbox" name="kategorien_zuordnen" value="1" checked style="margin-top:3px">
                    <span><strong>Kategorien aus JTL zuordnen</strong> — die Kontrollliste zeigt alle JTL-Kategorien dieses Exports,
                    du wählst je Zeile die passende ERP-Kategorie. Die Wahl wird gemerkt und beim nächsten Import vorausgefüllt.</span>
                </label>
                <label style="font-size:13px;cursor:pointer;display:flex;gap:8px;align-items:flex-start;margin-top:8px">
                    <input type="checkbox" name="liste_anwenden" value="1" checked style="margin-top:3px">
                    <span><strong>Babsis Liste + neues Nummernschema anwenden</strong> (MeaLana-Übernahme) — wie in
                    <a href="jtl_benennung.php" target="_blank">JTL: neue Nummern</a>: Ausgeschlossenes und noch nicht Geprüftes wird übersprungen,
                    Nummern/Namen nach Schema (z.B. <code>LN-113-0001</code>), Hersteller-Zuordnungen gelten. Die JTL-Nummer wird am Artikel gemerkt.
                    Gibt es einen Artikel schon mit der alten JTL-Nummer, bekommt er die neue Nummer (statt doppelt angelegt zu werden).</span>
                </label>
                <label style="font-size:13px;cursor:pointer;display:flex;gap:8px;align-items:flex-start;margin-top:8px">
                    <input type="checkbox" name="uvp_uebernehmen" value="1" style="margin-top:3px">
                    <span><strong>UVP aus JTL übernehmen</strong> — nur anhaken, wenn die JTL-Spalte „UVP“ echte Preisempfehlungen
                    der Hersteller enthält. Ohne Häkchen bleibt die UVP leer.</span>
                </label>
            </div>
            <div>
                <label class="erp-label">Feste Kategorie für alle (optional)</label>
                <select name="kategorie_id" class="erp-select" style="width:100%">
                    <option value="">– keine –</option>
                    <?php renderKategorieOptionen($kategorienBaum); ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikeltyp (Vorauswahl, pro Artikel änderbar)</label>
                <select name="artikeltyp_code" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach ($alleArtikeltypen as $t): ?>
                        <option value="<?= htmlspecialchars($t['code']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikelgruppe (Vorauswahl, pro Artikel änderbar)</label>
                <select name="artikel_gruppe_id" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach ($alleArtikelgruppen as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Shop-Kanal einschalten (optional)</label>
                <select name="kanal_shop_id" class="erp-select" style="width:100%">
                    <option value="0">– keinen –</option>
                    <?php foreach ($alleShops as $sh): ?>
                        <option value="<?= (int)$sh['id'] ?>"><?= htmlspecialchars($sh['name'] . ($sh['nr_kuerzel'] ? ' (' . $sh['nr_kuerzel'] . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">Alle importierten Artikel (auch schon vorhandene) samt Kindern werden für diesen Shop aktiv.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">Dateien einlesen &amp; Vorschau zeigen</button>
    </form>
</div>

<?php else: ?>

<?php
$einheitenUnbekannt   = $vorschau['einheiten_unbekannt'];
$vaeter               = $vorschau['vaeter'];
$normale              = $vorschau['normale'] ?? [];
$verwaisteKinderAnzahl = $vorschau['verwaiste_kinder_anzahl'] ?? 0;
$vaterOhneKombidatei   = $vorschau['vater_ohne_kombidatei_anzahl'] ?? 0;
$anzahlKinder         = array_sum(array_map(fn($v) => count($v['kinder']), $vaeter));
$listeStat            = $vorschau['liste'] ?? null;
// Hinweise aus Babsis Liste/Schema (wie in "JTL: neue Nummern"); reine Infos grau
$listeInfo  = ['Farbnummer aus Kindnummer', 'Nummer vorgegeben', 'Hersteller zugeordnet'];
$listeChips = function (array $e) use ($listeInfo): string {
    $html = '';
    if (($e['vorhanden_status'] ?? null) === 'umbenennen') {
        $html .= '<span style="background:#f5f3ff;color:#6d28d9;padding:2px 6px;border-radius:4px;font-size:11px" title="Gibt es schon unter der alten JTL-Nummer – bekommt die neue Nummer und den neuen Namen">vorhanden mit alter Nr. → wird umnummeriert</span> ';
    }
    foreach ($e['liste_hinweise'] ?? [] as $t) {
        $c = in_array($t, $listeInfo, true) ? '#718096' : '#C05621';
        $html .= '<span style="display:inline-block;font-size:11px;padding:1px 6px;margin:1px 2px 1px 0;border-radius:3px;border:1px solid ' . $c . ';color:' . $c . '">' . htmlspecialchars($t) . '</span>';
    }
    return $html;
};
$jtlNrKlein = fn(array $e) => isset($e['jtl_nr']) && $e['jtl_nr'] !== $e['artikelnummer']
    ? '<div style="font-size:11px;color:var(--color-text-muted);font-family:monospace">JTL: ' . htmlspecialchars($e['jtl_nr']) . '</div>' : '';
?>

<div class="card">
    <h2 style="margin:0 0 8px">Kontrollliste — <?= count($vaeter) ?> Väter, <?= $anzahlKinder ?> Kinder, <?= count($normale) ?> normale Artikel</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Artikelnummern und Namen können hier noch korrigiert werden. Es wird noch nichts in die Datenbank geschrieben —
        der eigentliche Import startet erst mit Klick auf "Import starten" unten. Artikel mit dem Badge
        "bereits vorhanden" existieren schon (gleiche Artikelnummer) — bei ihnen wird beim Import nur die
        Kategorie zusätzlich zugewiesen, alle anderen Felder bleiben unangetastet.
        <a href="jtl_import.php?neu=1">Abbrechen &amp; neu beginnen</a>
    </p>

    <?php if ($verwaisteKinderAnzahl > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong><?= $verwaisteKinderAnzahl ?> Kind-Zeile(n) übersprungen</strong> — Stammdaten-CSV markiert sie als
            Kind eines Vaters, aber die Variationskombinationen-CSV enthält für sie keine Zeile (Achsen unbekannt).
            Sie wurden weder als Vater/Kind noch als normaler Artikel importiert.
        </div>
    <?php endif; ?>

    <?php if ($listeStat !== null): ?>
        <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong>Babsis Liste + neues Nummernschema angewendet</strong> — Nummern und Namen unten sind schon die neuen (JTL-Nummer klein darunter).
            <?= (int) $listeStat['ausgeschlossen'] ?> ausgeschlossene Artikel/Kinder übersprungen.
            <?php if ($listeStat['ungeprueft']): ?>
                <div style="margin-top:6px;color:#b45309"><strong><?= count($listeStat['ungeprueft']) ?> noch nicht geprüft — übersprungen</strong>
                    (erst in <a href="jtl_benennung.php?auswahl=neu" target="_blank">JTL: neue Nummern</a> entscheiden, dann neu einlesen):
                    <span style="font-family:monospace;font-size:12px"><?= htmlspecialchars(implode(', ', array_slice($listeStat['ungeprueft'], 0, 30))) ?><?= count($listeStat['ungeprueft']) > 30 ? ' …' : '' ?></span></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($vaterOhneKombidatei > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong><?= $vaterOhneKombidatei ?> Vater-Artikel übersprungen</strong> — als Vater markiert, aber keine
            Variationskombinationen-CSV hochgeladen (oder sie enthält diese Väter nicht). Ohne Kombinationsdaten
            sind die Achsen/Kinder unbekannt, daher wurden sie nicht importiert.
        </div>
    <?php endif; ?>

    <form method="POST" action="jtl_import_commit.php">
    <?php
    $katListe = $_SESSION['jtl_import_kat_liste'] ?? [];
    if (!empty($_SESSION['jtl_import_kat_zuordnen']) && $katListe):
        $gemerkt = (new ImportKategorieZuordnung())->lade(array_map('strval', array_keys($katListe)));
        $optHtml = kategorieOptionenHtml($kategorienBaum);
        $offen   = count(array_diff_key($katListe, $gemerkt));
    ?>
        <div style="border:1px solid var(--color-border);border-radius:6px;margin-bottom:20px">
            <div style="padding:10px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--color-border)">
                <strong>Kategorie-Zuordnung</strong>
                <span style="font-size:12px;color:var(--color-text-muted)"><?= count($katListe) ?> JTL-Kategorien in diesem Export · <?= $offen ?> noch nie zugeordnet</span>
                <input type="search" id="kat-filter" placeholder="Filtern …" class="erp-input" style="margin-left:auto;max-width:220px">
            </div>
            <div style="font-size:12px;color:var(--color-text-muted);padding:8px 14px">
                Je JTL-Kategorie die ERP-Kategorie wählen. „– nicht zuordnen –“ = diese JTL-Kategorie wird ignoriert.
                Gleichnamige JTL-Kategorien (z.B. aus verschiedenen Zweigen) erkennst du an „zusammen mit“ und den Beispielen.
                Väter bekommen die Kategorien ihrer Kinder-Zeilen mit dazu. Bestehende Kategorien eines Artikels bleiben erhalten.
            </div>
            <table class="erp-table" id="kat-tabelle">
                <thead><tr><th style="width:70px">JTL-ID</th><th>JTL-Kategorie</th><th style="width:60px">Zeilen</th><th>zusammen mit / Beispiele</th><th style="width:300px">ERP-Kategorie</th></tr></thead>
                <tbody>
                <?php foreach ($katListe as $fid => $kat):
                    $sel  = $gemerkt[(string)$fid] ?? null;
                    $opts = $sel ? str_replace('value="' . $sel . '"', 'value="' . $sel . '" selected', $optHtml) : $optHtml;
                ?>
                    <tr data-text="<?= htmlspecialchars(mb_strtolower($kat['name'] . ' ' . implode(' ', $kat['mit']) . ' ' . implode(' ', $kat['beispiele']))) ?>">
                        <td style="font-size:12px;color:var(--color-text-muted)"><?= (string)$fid === $kat['name'] ? 'Pfad' : htmlspecialchars((string)$fid) ?></td>
                        <td><strong><?= htmlspecialchars($kat['name']) ?></strong><?= $sel === null ? ' <span style="background:#fffbeb;color:#b45309;padding:1px 6px;border-radius:4px;font-size:11px">neu</span>' : '' ?></td>
                        <td><?= (int)$kat['anzahl'] ?></td>
                        <td style="font-size:12px">
                            <?php if ($kat['mit']): ?><div>zusammen mit: <?= htmlspecialchars(implode(', ', $kat['mit'])) ?></div><?php endif; ?>
                            <div style="color:var(--color-text-muted)"><?= htmlspecialchars(implode(' · ', $kat['beispiele'])) ?></div>
                        </td>
                        <td>
                            <select name="kat_zuordnung[<?= htmlspecialchars((string)$fid) ?>]" class="erp-select" style="width:100%">
                                <option value="0">– nicht zuordnen –</option>
                                <?= $opts ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            document.getElementById('kat-filter').addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('#kat-tabelle tbody tr').forEach(tr => {
                    tr.style.display = !q || tr.dataset.text.includes(q) ? '' : 'none';
                });
            });
        </script>
    <?php endif; ?>
    <?php if (!empty($einheitenUnbekannt)): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong>Einheiten-Mapping nötig</strong> — diese Verkaufseinheiten aus der CSV entsprechen keiner bestehenden Einheit:
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px,1fr));gap:10px;margin-top:10px">
                <?php foreach ($einheitenUnbekannt as $roh): ?>
                    <div>
                        <label class="erp-label"><?= $roh === '' ? '(leer)' : htmlspecialchars($roh) ?></label>
                        <select name="einheit_mapping[<?= base64_encode($roh) ?>]" class="erp-select" style="width:100%">
                            <option value="">– nicht zuordnen –</option>
                            <?php foreach ($alleEinheiten as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name'] . ' (' . $e['kuerzel'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($vaeter as $vIdx => $v): ?>
        <?php
        $hatWarnung = !$v['hersteller_treffer'] || array_filter($v['kinder'], fn($k) => $k['stammdaten_fehlen']);
        $achsenText = implode(', ', array_map(fn($a) => $a['name'] . ': ' . $a['wert'], $v['achsen']));
        ?>
        <details style="margin-bottom:10px;border:1px solid var(--color-border);border-radius:6px" <?= $hatWarnung ? 'open' : '' ?>>
            <summary style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px">
                <strong><?= htmlspecialchars($v['artikelnummer']) ?></strong>
                <?php if (isset($v['jtl_nr']) && $v['jtl_nr'] !== $v['artikelnummer']): ?>
                    <span style="font-size:11px;color:var(--color-text-muted);font-family:monospace">JTL: <?= htmlspecialchars($v['jtl_nr']) ?></span>
                <?php endif; ?>
                <span><?= htmlspecialchars($v['name']) ?></span>
                <span style="color:var(--color-text-muted);font-size:12px"><?= count($v['kinder']) ?> Kinder</span>
                <?php if (!empty($v['bereits_vorhanden'])): ?>
                    <span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:4px;font-size:12px">bereits vorhanden → nur Kategorie</span>
                <?php endif; ?>
                <?php if (!$v['hersteller_treffer']): ?>
                    <span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:4px;font-size:12px">Hersteller unbekannt: "<?= htmlspecialchars($v['hersteller_name']) ?>"</span>
                <?php endif; ?>
                <?= $listeChips($v) ?>
            </summary>
            <div style="padding:0 14px 14px">
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px;margin:10px 0">
                    <div>
                        <label class="erp-label">Vater-Artikelnummer</label>
                        <input type="text" name="vater[<?= $vIdx ?>][artikelnummer]" value="<?= htmlspecialchars($v['artikelnummer']) ?>" class="erp-select" style="width:100%">
                    </div>
                    <div>
                        <label class="erp-label">Vater-Name</label>
                        <input type="text" name="vater[<?= $vIdx ?>][name]" value="<?= htmlspecialchars($v['name']) ?>" class="erp-select" style="width:100%">
                    </div>
                </div>
                <?php if (empty($v['bereits_vorhanden'])): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 10px">
                    <?php renderArtikeltypGruppeFelder("vater[$vIdx]", $alleArtikeltypen, $alleArtikelgruppen, $artikeltypStandard, $artikelGruppeIdStandard); ?>
                </div>
                <?php endif; ?>
                <p style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px">Erkannte Achsen: <?= $achsenText !== '' ? htmlspecialchars($achsenText) : '–' ?></p>

                <table class="erp-table">
                    <thead>
                        <tr>
                            <th style="width:160px">Kind-Artikelnummer</th>
                            <th>Kind-Name</th>
                            <th>Achsenwerte</th>
                            <th style="width:90px">Preis brutto</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($v['kinder'] as $kIdx => $k): ?>
                        <tr>
                            <td><input type="text" name="vater[<?= $vIdx ?>][kinder][<?= $kIdx ?>][artikelnummer]" value="<?= htmlspecialchars($k['artikelnummer']) ?>" class="erp-select" style="width:100%"><?= $jtlNrKlein($k) ?></td>
                            <td><input type="text" name="vater[<?= $vIdx ?>][kinder][<?= $kIdx ?>][name]" value="<?= htmlspecialchars($k['name']) ?>" class="erp-select" style="width:100%"></td>
                            <td style="font-size:12px"><?= htmlspecialchars(implode(', ', array_map(fn($a) => $a['name'] . ': ' . $a['wert'], $k['achsen']))) ?></td>
                            <td><?= $k['brutto_vk'] !== null ? number_format($k['brutto_vk'], 2, ',', '.') : '–' ?></td>
                            <td>
                                <?php if (!empty($k['bereits_vorhanden'])): ?>
                                    <span style="background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-size:11px">bereits vorhanden → nur Kategorie</span>
                                <?php elseif ($k['stammdaten_fehlen']): ?>
                                    <span style="background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px">Stammdaten fehlen</span>
                                <?php endif; ?>
                                <?= $listeChips($k) ?>
                                <?php if (!empty($k['ist_auslauf'])): ?>
                                    <span style="background:#fff7ed;color:#c2410c;padding:2px 6px;border-radius:4px;font-size:11px" title="Barbaras &quot;#&quot;-Markierung erkannt">🔶 Auslauf</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endforeach; ?>

    <?php if (!empty($normale)): ?>
        <h3 style="margin:20px 0 10px">Normale Artikel (kein Vater, kein Kind)</h3>
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:160px">Artikelnummer</th>
                    <th>Name</th>
                    <th style="width:150px">Artikeltyp</th>
                    <th style="width:180px">Artikelgruppe</th>
                    <th style="width:90px">Preis brutto</th>
                    <th style="width:220px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($normale as $nIdx => $n): ?>
                <tr>
                    <td><input type="text" name="normal[<?= $nIdx ?>][artikelnummer]" value="<?= htmlspecialchars($n['artikelnummer']) ?>" class="erp-select" style="width:100%"><?= $jtlNrKlein($n) ?></td>
                    <td><input type="text" name="normal[<?= $nIdx ?>][name]" value="<?= htmlspecialchars($n['name']) ?>" class="erp-select" style="width:100%"></td>
                    <?php if (empty($n['bereits_vorhanden'])): ?>
                        <td><select name="normal[<?= $nIdx ?>][artikeltyp_code]" class="erp-select" style="width:100%">
                            <option value="">– wählen –</option>
                            <?php foreach ($alleArtikeltypen as $t): ?>
                                <option value="<?= htmlspecialchars($t['code']) ?>" <?= $t['code'] === $artikeltypStandard ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select></td>
                        <td><select name="normal[<?= $nIdx ?>][artikel_gruppe_id]" class="erp-select" style="width:100%">
                            <option value="">– wählen –</option>
                            <?php foreach ($alleArtikelgruppen as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= (int) $g['id'] === $artikelGruppeIdStandard ? 'selected' : '' ?>><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    <?php else: ?>
                        <td colspan="2" style="color:var(--color-text-muted);font-size:12px">–</td>
                    <?php endif; ?>
                    <td><?= $n['brutto_vk'] !== null ? number_format($n['brutto_vk'], 2, ',', '.') : '–' ?></td>
                    <td>
                        <?php if (!empty($n['bereits_vorhanden'])): ?>
                            <span style="background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-size:11px">bereits vorhanden → nur Kategorie</span>
                        <?php endif; ?>
                        <?php if (!$n['hersteller_treffer']): ?>
                            <span style="background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px">Hersteller unbekannt: "<?= htmlspecialchars($n['hersteller_name']) ?>"</span>
                        <?php endif; ?>
                        <?= $listeChips($n) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

        <div style="margin-top:16px">
            <?php // Muss das LETZTE Feld bleiben: fehlt es beim Commit, hat PHP (max_input_vars) das Formular abgeschnitten ?>
            <input type="hidden" name="formular_vollstaendig" value="1">
            <button type="submit" class="btn btn-primary btn-sm">Import starten</button>
            <a href="jtl_import.php?neu=1" class="btn btn-secondary btn-sm">Abbrechen</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
