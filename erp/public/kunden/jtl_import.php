<?php
/**
 * JTL-Kunden- und Archiv-Import (JtlArchivLaufService, Migration 257).
 * GET: Übersicht.  POST JSON {aktion: neu|hochgeladen|abschnitt|verwerfen|offene}: Schritte.
 * POST ?aktion=teil&id=X mit Rohdaten: ein Stück der Datei (JS lädt in 4-MB-Stücken hoch,
 * dadurch spielt upload_max_filesize keine Rolle).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/JtlArchivLaufService.php';

$svc = new JtlArchivLaufService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (($_GET['aktion'] ?? '') === 'teil') {
            $svc->teil((int) ($_GET['id'] ?? 0), (string) file_get_contents('php://input'));
            echo json_encode(['erfolg' => true]);
            exit;
        }
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $id = (int) ($in['id'] ?? 0);
        $antwort = match ($in['aktion'] ?? '') {
            'neu'         => ['id' => $svc->neu((string) ($in['art'] ?? ''), (string) ($in['dateiname'] ?? 'export.csv'), (int) ($_SESSION['benutzer']['id'] ?? 0) ?: null)],
            'hochgeladen' => ['lauf' => $svc->hochgeladen($id)],
            'abschnitt'   => $svc->abschnitt($id, !empty($in['probelauf']), !empty($in['neu_start'])),
            'verwerfen'   => (function () use ($svc, $id) { $svc->loeschen($id); return []; })(),
            'offene'      => $svc->offeneEntscheiden((array) ($in['alle'] ?? []), (array) ($in['erledigt'] ?? [])),
            default       => throw new InvalidArgumentException('Unbekannte Aktion.'),
        };
        echo json_encode(['erfolg' => true] + $antwort, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$laeufe = $svc->laeufe();
$offene = $svc->offene();
$zahlText = function (array $z, string $art): string {
    $teile = [];
    $namen = $art === 'kunden'
        ? ['neu' => 'neu', 'verknuepft' => 'mit vorhandenem Kunden verknüpft (gleiche E-Mail)', 'uebersprungen' => 'schon importiert', 'fehler' => 'Fehler']
        : ['neu' => 'neu', 'uebersprungen' => 'schon importiert', 'alt_bezahlt' => 'davon ohne Zahlung, älter als 1 Jahr → bezahlt',
           'offen' => 'davon ohne Zahlung, jünger → Prüfliste', 'angebote' => 'Angebote ausgelassen', 'fehler' => 'Fehler'];
    foreach ($namen as $k => $n) if (!empty($z[$k])) $teile[] = '<strong>' . number_format($z[$k], 0, ',', '.') . '</strong> ' . $n;
    return $teile ? implode(' · ', $teile) : '–';
};
$statusText = [
    'hochgeladen' => 'hochgeladen', 'probelauf' => 'Probelauf unterbrochen', 'fertig_probelauf' => 'Probelauf fertig',
    'import' => 'Import unterbrochen', 'fertig' => '✓ importiert',
];

$pageTitle    = 'JTL-Import Kunden + Aufträge';
$activeModule = 'kunden';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1200px">

<div class="card" style="font-size:13px;line-height:1.6;margin-bottom:var(--space-sm)">
    <strong>So geht's</strong> (vorher eine Datenbank-Sicherung machen):
    <ol style="margin:4px 0 0 18px">
        <li><strong>Kundendaten-Export</strong> aus JTL hochladen → Probelauf → Import. Schon importierte Kunden werden übersprungen, Kunden mit gleicher E-Mail (z.B. aus den Woo-Shops) nur verknüpft.</li>
        <li><strong>Auftrags-Export</strong> hochladen → Probelauf → Import. Die Aufträge kommen als <em>Archiv</em> (Kanal „Archiv“), schon importierte werden übersprungen. Fehlen Artikel, wird ein inaktiver Platzhalter <code>JTL-…</code> angelegt.</li>
        <li>Aufträge ohne Zahlungseingang in JTL: <strong>älter als 1 Jahr</strong> gelten als bezahlt. <strong>Jüngere</strong> kommen unten in die Prüfliste – dort ist alles als erledigt angehakt, nur die wirklich offenen abhaken.</li>
    </ol>
    Beliebig oft wiederholbar (z.B. jeden Monat, solange MeaLana auf JTL läuft). Große Dateien (90 MB) sind kein Problem, ein unterbrochener Lauf macht beim nächsten Klick weiter.
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);margin-bottom:var(--space-sm)">
    <?php foreach (['kunden' => '👤 Kundendaten-Export', 'auftraege' => '🧾 Auftrags-Export'] as $art => $titel): ?>
        <div class="card">
            <div style="font-weight:700;color:var(--color-nav);margin-bottom:8px"><?= $titel ?> hochladen</div>
            <input type="file" accept=".csv,.txt" class="ji-datei" data-art="<?= $art ?>" style="font-size:13px">
            <button type="button" class="btn btn-primary btn-sm ji-hochladen" data-art="<?= $art ?>" disabled>⬆ Hochladen</button>
            <div class="ji-upload-info" data-art="<?= $art ?>" style="font-size:12px;color:var(--color-text-muted);margin-top:6px"></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-bottom:var(--space-sm)">
    <div style="font-weight:700;color:var(--color-nav);margin-bottom:8px">Läufe</div>
    <?php if (!$laeufe): ?>
        <div style="font-size:13px;color:var(--color-text-muted)">Noch keine Datei hochgeladen.</div>
    <?php else: ?>
    <table class="erp-table">
        <thead><tr><th>#</th><th>Datei</th><th>Art</th><th>Status</th><th>Ergebnis</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($laeufe as $l): $z = json_decode((string) $l['zahlen'], true) ?: []; $f = json_decode((string) $l['fehler'], true) ?: []; ?>
            <tr data-id="<?= (int) $l['id'] ?>">
                <td><?= (int) $l['id'] ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($l['dateiname']) ?><br><span style="color:var(--color-text-muted)"><?= number_format($l['groesse'] / 1048576, 1, ',', '.') ?> MB · <?= date('d.m.Y H:i', strtotime($l['erstellt_am'])) ?><?= $l['benutzer'] ? ' · ' . htmlspecialchars($l['benutzer']) : '' ?></span></td>
                <td><?= $l['art'] === 'kunden' ? 'Kunden' : 'Aufträge' ?></td>
                <td style="white-space:nowrap"><?= $statusText[$l['status']] ?></td>
                <td style="font-size:12px" class="ji-zahlen">
                    <?= in_array($l['status'], ['probelauf', 'fertig_probelauf'], true) ? '<em>Probelauf:</em> ' : '' ?><?= $zahlText($z, $l['art']) ?>
                    <?php if ($f): ?><details style="margin-top:4px"><summary style="cursor:pointer;color:#9B2C2C"><?= count($f) ?> Fehler anzeigen</summary><div style="max-height:200px;overflow:auto"><?= implode('<br>', array_map('htmlspecialchars', $f)) ?></div></details><?php endif; ?>
                    <div class="ji-fortschritt" style="display:none;margin-top:6px">
                        <div style="background:#e5e7eb;border-radius:4px;height:8px;overflow:hidden"><div class="ji-balken" style="background:var(--color-nav);height:8px;width:0"></div></div>
                        <div class="ji-text" style="margin-top:3px"></div>
                    </div>
                </td>
                <td style="white-space:nowrap">
                    <?php if ($l['status'] === 'hochgeladen' || $l['status'] === 'fertig_probelauf'): ?>
                        <button type="button" class="btn btn-secondary btn-sm ji-lauf" data-probelauf="1" data-neu="1">🔍 Probelauf<?= $l['status'] === 'fertig_probelauf' ? ' wiederholen' : '' ?></button>
                    <?php endif; ?>
                    <?php if ($l['status'] === 'probelauf'): ?>
                        <button type="button" class="btn btn-secondary btn-sm ji-lauf" data-probelauf="1" data-neu="0">🔍 Probelauf fortsetzen</button>
                    <?php endif; ?>
                    <?php if ($l['status'] === 'fertig_probelauf' || ($l['status'] === 'hochgeladen' && $l['art'] === 'kunden')): ?>
                        <button type="button" class="btn btn-primary btn-sm ji-lauf" data-probelauf="0" data-neu="1">⬇ Import starten</button>
                    <?php endif; ?>
                    <?php if ($l['status'] === 'import'): ?>
                        <button type="button" class="btn btn-primary btn-sm ji-lauf" data-probelauf="0" data-neu="0">⬇ Import fortsetzen</button>
                    <?php endif; ?>
                    <?php if (in_array($l['status'], ['hochgeladen', 'probelauf', 'fertig_probelauf'], true)): ?>
                        <button type="button" class="btn btn-secondary btn-sm ji-verwerfen" title="Datei und Lauf löschen">🗑</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if ($offene): ?>
<div class="card" id="ji-offene">
    <div style="font-weight:700;color:var(--color-nav);margin-bottom:4px">Prüfliste: <?= count($offene) ?> Archiv-Aufträge ohne Zahlungseingang (jünger als 1 Jahr)</div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px">
        Angehakt = erledigt, wird auf <strong>bezahlt</strong> gesetzt. Nur die <strong>wirklich noch offenen</strong> abhaken – die bleiben „ausstehend“. Danach verschwinden alle aus dieser Liste.
    </div>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <button type="button" class="btn btn-secondary btn-sm" id="ji-alle">Alle</button>
        <button type="button" class="btn btn-secondary btn-sm" id="ji-keine">Keine</button>
        <span id="ji-offen-zaehler" style="font-size:13px;color:var(--color-text-muted)"></span>
        <button type="button" class="btn btn-primary btn-sm" id="ji-offene-speichern" style="margin-left:auto">✓ Speichern</button>
    </div>
    <table class="erp-table">
        <thead><tr><th style="width:28px">✓</th><th>Auftrag</th><th>Datum</th><th>Kunde</th><th style="text-align:right">Betrag</th><th>Zahlungsart</th></tr></thead>
        <tbody>
        <?php foreach ($offene as $o): $k = json_decode((string) $o['kunden_snapshot'], true) ?: []; ?>
            <tr>
                <td><input type="checkbox" class="ji-offen-cb" value="<?= (int) $o['id'] ?>" checked></td>
                <td><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int) $o['id'] ?>" target="_blank" style="color:var(--color-nav)"><?= htmlspecialchars($o['auftrag_nr']) ?></a></td>
                <td><?= date('d.m.Y', strtotime($o['erstellt_am'])) ?></td>
                <td><?= htmlspecialchars(trim(($k['firma'] ?? '') . ' ' . ($k['name'] ?? ''))) ?></td>
                <td style="text-align:right;white-space:nowrap"><?= number_format((float) $o['bruttobetrag'], 2, ',', '.') ?> €</td>
                <td><?= htmlspecialchars($o['zahlungsart']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
</div>

<script src="<?= BASE_PATH ?>/js/jtl_archiv_import.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
