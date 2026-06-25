<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db = Database::getInstance();

// ── Filter-Parameter ───────────────────────────────────────────────────────
$typ    = trim($_GET['typ']  ?? '');
$von    = trim($_GET['von']  ?? '');
$bis    = trim($_GET['bis']  ?? '');
$suche  = trim($_GET['suche'] ?? '');
$seite  = max(1, (int)($_GET['seite'] ?? 1));
$proSeite = 50;
$offset = ($seite - 1) * $proSeite;

// ── Query aufbauen ─────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($typ) {
    $where[]       = 'ad.typ = :typ';
    $params['typ'] = $typ;
}
if ($von) {
    $where[]       = 'ad.erstellt_am >= :von';
    $params['von'] = $von . ' 00:00:00';
}
if ($bis) {
    $where[]       = 'ad.erstellt_am <= :bis';
    $params['bis'] = $bis . ' 23:59:59';
}
if ($suche) {
    $where[]          = '(a.auftrag_nr LIKE :suche OR a.kunden_snapshot LIKE :suche2)';
    $params['suche']  = '%' . $suche . '%';
    $params['suche2'] = '%' . $suche . '%';
}

$whereStr = implode(' AND ', $where);

// Anzahl für Paginierung
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM auftrag_dokumente ad
    JOIN auftraege a ON a.id = ad.auftrag_id
    WHERE $whereStr
");
$countStmt->execute($params);
$gesamtAnzahl = (int)$countStmt->fetchColumn();
$seitenAnzahl = max(1, (int)ceil($gesamtAnzahl / $proSeite));

// Hauptabfrage
$stmt = $db->prepare("
    SELECT
        ad.id,
        ad.auftrag_id,
        ad.typ,
        ad.dateiname,
        ad.erstellt_am,
        a.auftrag_nr,
        a.kunden_snapshot,
        a.lieferstatus,
        a.zahlungsstatus,
        r.rechnung_nr,
        r.storniert        AS rechnung_storniert,
        b.formularname     AS erstellt_von_name
    FROM auftrag_dokumente ad
    JOIN auftraege a  ON a.id = ad.auftrag_id
    LEFT JOIN rechnungen r ON r.auftrag_id = ad.auftrag_id
                          AND r.storniert = 0
                          AND ad.typ IN ('rechnung','gutschrift')
    LEFT JOIN benutzer b ON b.id = ad.erstellt_von
    WHERE $whereStr
    ORDER BY ad.erstellt_am DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $proSeite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
$stmt->execute();
$dokumente = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Typen für Filter-Dropdown ──────────────────────────────────────────────
$typen = [
    ''                   => 'Alle Typen',
    'auftragsbestaetigung' => 'Auftragsbestätigung',
    'lieferschein'       => 'Lieferschein',
    'rechnung'           => 'Rechnung',
    'gutschrift'         => 'Gutschrift',
    'abholzettel'        => 'Abholzettel',
    'mahnung'            => 'Mahnung',
];

$typLabels = [
    'auftragsbestaetigung' => ['label' => 'AB',  'color' => '#3b82f6', 'bg' => '#eff6ff'],
    'lieferschein'         => ['label' => 'LS',  'color' => '#0891b2', 'bg' => '#ecfeff'],
    'rechnung'             => ['label' => 'RE',  'color' => '#16a34a', 'bg' => '#dcfce7'],
    'gutschrift'           => ['label' => 'GS',  'color' => '#dc2626', 'bg' => '#fef2f2'],
    'abholzettel'          => ['label' => 'AZ',  'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'mahnung'              => ['label' => 'MA',  'color' => '#d97706', 'bg' => '#fffbeb'],
];

// ── Helper ─────────────────────────────────────────────────────────────────
function kundenName(string $snapshot): string
{
    $d = json_decode($snapshot, true);
    if (!$d) return '—';
    $firma = $d['firma'] ?? null;
    $name  = trim(($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''));
    return $firma ? $firma . ($name ? ' / ' . $name : '') : ($name ?: '—');
}

function buildUrl(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}

// ── Seitenheader ──────────────────────────────────────────────────────────
$pageTitle        = 'Dokumentenarchiv';
$activeModule     = 'verkauf';
$actionBarContent = '
    <span style="font-weight:600">Dokumentenarchiv</span>
    <span style="color:var(--color-text-muted);font-size:13px;margin-left:12px">' . $gesamtAnzahl . ' Dokumente</span>
';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<style>
.dok-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    margin-bottom: 18px;
}
.dok-filter label { font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .05em; color: var(--color-text-muted); display: block; margin-bottom: 4px; }
.dok-filter input, .dok-filter select {
    padding: 6px 10px; border: 1px solid var(--color-border);
    border-radius: 4px; font-size: 13px; background: var(--color-card); color: var(--color-text);
}
.dok-chip {
    display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; padding: 2px 8px; border-radius: 10px; white-space: nowrap;
}
.dok-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dok-table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--color-border);
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    color: var(--color-text-muted); white-space: nowrap; }
.dok-table td { padding: 8px 10px; border-bottom: 1px solid var(--color-border); vertical-align: middle; }
.dok-table tr:hover td { background: var(--color-bg); }
.dok-pagination { display: flex; gap: 6px; margin-top: 18px; align-items: center; font-size: 13px; }
.dok-pagination a, .dok-pagination span {
    padding: 5px 10px; border-radius: 4px; border: 1px solid var(--color-border);
    text-decoration: none; color: var(--color-text); }
.dok-pagination a:hover { background: var(--color-bg); }
.dok-pagination .aktiv { background: var(--color-nav); color: #fff; border-color: var(--color-nav); }
</style>

<div class="card" style="padding:20px">

    <!-- Filter -->
    <form method="get" class="dok-filter">
        <div>
            <label>Suche</label>
            <input type="text" name="suche" value="<?= htmlspecialchars($suche) ?>"
                   placeholder="Auftragsnr. oder Kundenname" style="width:200px">
        </div>
        <div>
            <label>Typ</label>
            <select name="typ">
                <?php foreach ($typen as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $typ === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Von</label>
            <input type="date" name="von" value="<?= htmlspecialchars($von) ?>">
        </div>
        <div>
            <label>Bis</label>
            <input type="date" name="bis" value="<?= htmlspecialchars($bis) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding:7px 16px">Filtern</button>
            <?php if ($typ || $von || $bis || $suche): ?>
                <a href="?" class="btn btn-secondary" style="padding:7px 16px;margin-left:4px">Zurücksetzen</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabelle -->
    <?php if (empty($dokumente)): ?>
        <p style="color:var(--color-text-muted);text-align:center;padding:40px">Keine Dokumente gefunden.</p>
    <?php else: ?>
    <table class="dok-table">
        <thead>
            <tr>
                <th>Typ</th>
                <th>Auftrag</th>
                <th>Kunde</th>
                <th>Dokument-Nr.</th>
                <th>Erstellt am</th>
                <th>Erstellt von</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dokumente as $dok):
            $kunden = json_decode($dok['kunden_snapshot'] ?? '{}', true);
            $name   = kundenName($dok['kunden_snapshot'] ?? '{}');
            $tInfo  = $typLabels[$dok['typ']] ?? ['label' => strtoupper($dok['typ']), 'color' => '#64748b', 'bg' => '#f1f5f9'];
            $istStorniert = $dok['rechnung_storniert'] ?? false;
        ?>
        <tr>
            <td>
                <span class="dok-chip" style="background:<?= $tInfo['bg'] ?>;color:<?= $tInfo['color'] ?>">
                    <?= $tInfo['label'] ?>
                </span>
            </td>
            <td>
                <a href="/mealana/auftraege/detail.php?id=<?= $dok['auftrag_id'] ?>"
                   style="font-weight:600;text-decoration:none;color:var(--color-nav)">
                    <?= htmlspecialchars($dok['auftrag_nr']) ?>
                </a>
            </td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($name) ?>
            </td>
            <td style="font-family:monospace;font-size:12px">
                <?php if ($dok['rechnung_nr']): ?>
                    <?= htmlspecialchars($dok['rechnung_nr']) ?>
                <?php else: ?>
                    <span style="color:var(--color-text-muted)"><?= htmlspecialchars(pathinfo($dok['dateiname'], PATHINFO_FILENAME)) ?></span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;color:var(--color-text-muted)">
                <?= date('d.m.Y H:i', strtotime($dok['erstellt_am'])) ?>
            </td>
            <td style="color:var(--color-text-muted)">
                <?= htmlspecialchars($dok['erstellt_von_name'] ?? '—') ?>
            </td>
            <td>
                <?php if ($istStorniert): ?>
                    <span style="font-size:11px;color:#dc2626;font-weight:600">STORNIERT</span>
                <?php elseif ($dok['zahlungsstatus'] === 'storniert'): ?>
                    <span style="font-size:11px;color:#6b7280">Auftrag storniert</span>
                <?php else: ?>
                    <span style="font-size:11px;color:#16a34a">Aktiv</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <a href="/mealana/auftraege/dokument_download.php?auftrag_id=<?= $dok['auftrag_id'] ?>&datei=<?= urlencode($dok['dateiname']) ?>"
                   target="_blank"
                   class="btn btn-secondary"
                   style="padding:3px 10px;font-size:12px">
                    PDF
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginierung -->
    <?php if ($seitenAnzahl > 1): ?>
    <div class="dok-pagination">
        <span style="color:var(--color-text-muted);margin-right:6px">
            Seite <?= $seite ?> von <?= $seitenAnzahl ?>
        </span>
        <?php if ($seite > 1): ?>
            <a href="<?= buildUrl(['seite' => $seite - 1]) ?>">‹ Zurück</a>
        <?php endif; ?>
        <?php
        $start = max(1, $seite - 2);
        $end   = min($seitenAnzahl, $seite + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i === $seite): ?>
                <span class="aktiv"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= buildUrl(['seite' => $i]) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($seite < $seitenAnzahl): ?>
            <a href="<?= buildUrl(['seite' => $seite + 1]) ?>">Weiter ›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
