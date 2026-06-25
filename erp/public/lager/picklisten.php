<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db = Database::getInstance();

// ── Lieferbereite Aufträge laden ────────────────────────────────────────────
// Kriterien: bezahlt (oder Zahlungsart ohne Vorauszahlung) + neu + nicht auf offener Pickliste
$auftraegeRaw = $db->query("
    SELECT a.id, a.auftrag_nr, a.erstellt_am, a.kunden_snapshot,
           a.zahlungsart, a.zahlungsstatus, a.lieferart
    FROM auftraege a
    WHERE a.lieferstatus = 'neu'
      AND a.zahlungsstatus != 'storniert'
      AND (
          a.zahlungsstatus = 'bezahlt'
          OR a.zahlungsart IN ('bar', 'rechnung', 'nachnahme')
      )
      AND a.id NOT IN (
          SELECT pa.auftrag_id FROM pickliste_auftraege pa
          JOIN picklisten pl ON pl.id = pa.pickliste_id
          WHERE pl.status IN ('offen','gedruckt')
      )
    ORDER BY a.erstellt_am ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Positionen pro Auftrag laden
$posStmt = $db->prepare("
    SELECT p.artikel_id, p.bezeichnung, p.menge
    FROM auftrag_positionen p
    WHERE p.auftrag_id = :id
    ORDER BY p.sort_order, p.id
");

$auftraege = [];
foreach ($auftraegeRaw as $a) {
    $posStmt->execute([':id' => $a['id']]);
    $a['positionen'] = $posStmt->fetchAll(PDO::FETCH_ASSOC);
    $auftraege[]     = $a;
}

// ── Lagerbestand laden ──────────────────────────────────────────────────────
$bestand = $db->query("
    SELECT lb.artikel_id, COALESCE(SUM(lb.bestand), 0) AS ist
    FROM lagerbestand lb
    GROUP BY lb.artikel_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

$reserviert = $db->query("
    SELECT r.artikel_id, SUM(r.menge) AS res
    FROM reservierungen r
    WHERE r.status = 'offen'
    GROUP BY r.artikel_id
")->fetchAll(PDO::FETCH_ASSOC);

$verfuegbar = $bestand; // [artikel_id => ist]
foreach ($reserviert as $r) {
    $aid = $r['artikel_id'];
    $verfuegbar[$aid] = max(0, ($verfuegbar[$aid] ?? 0) - (int)$r['res']);
}

// ── Greedy-Allocation: Ältester Auftrag zuerst ─────────────────────────────
$allocated = []; // artikel_id => für diese Berechnung reserviert
foreach ($auftraege as &$a) {
    $ok    = 0;
    $total = count($a['positionen']);
    $fehlende = [];

    foreach ($a['positionen'] as $pos) {
        $aid       = (int)($pos['artikel_id'] ?? 0);
        $needed    = (int)$pos['menge'];
        $available = ($verfuegbar[$aid] ?? 0) - ($allocated[$aid] ?? 0);
        if ($available >= $needed) {
            $ok++;
        } else {
            $fehlende[] = $pos['bezeichnung'] . ' (fehlt: ' . max(0, $needed - $available) . ')';
        }
    }

    if ($ok === $total) {
        $a['lieferbar'] = 'vollstaendig';
        // In "allocated" eintragen damit nachfolgende Aufträge das berücksichtigen
        foreach ($a['positionen'] as $pos) {
            $allocated[(int)($pos['artikel_id'] ?? 0)] =
                ($allocated[(int)($pos['artikel_id'] ?? 0)] ?? 0) + (int)$pos['menge'];
        }
    } elseif ($ok > 0) {
        $a['lieferbar'] = 'teilweise';
    } else {
        $a['lieferbar'] = 'nicht';
    }
    $a['fehlende'] = $fehlende;
}
unset($a);

// ── Vorhandene Picklisten ───────────────────────────────────────────────────
$picklisten = $db->query("
    SELECT pl.id, pl.nummer, pl.status, pl.erstellt_am,
           COUNT(pa.auftrag_id) AS anzahl_auftraege,
           b.formularname AS erstellt_von_name
    FROM picklisten pl
    LEFT JOIN pickliste_auftraege pa ON pa.pickliste_id = pl.id
    LEFT JOIN benutzer b ON b.id = pl.erstellt_von
    WHERE pl.status IN ('offen','gedruckt')
    GROUP BY pl.id
    ORDER BY pl.erstellt_am DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Lagerstand-Übersicht (für collapsible Bereich) ─────────────────────────
$lagerstand = $db->query("
    SELECT ar.artikelnummer, ar.bezeichnung,
           COALESCE(SUM(lb.bestand), 0)          AS ist,
           COALESCE(SUM(r.res), 0)               AS reserviert,
           COALESCE(SUM(lb.bestand), 0)
               - COALESCE(SUM(r.res), 0)         AS verfuegbar
    FROM artikel ar
    JOIN lagerbestand lb ON lb.artikel_id = ar.id
    LEFT JOIN (
        SELECT artikel_id, SUM(menge) AS res
        FROM reservierungen WHERE status = 'offen'
        GROUP BY artikel_id
    ) r ON r.artikel_id = ar.id
    WHERE lb.bestand > 0
    GROUP BY ar.id, ar.artikelnummer, ar.bezeichnung
    ORDER BY verfuegbar ASC, ar.artikelnummer ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Session-Meldungen ───────────────────────────────────────────────────────
$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);

// ── Helper ──────────────────────────────────────────────────────────────────
function kundeName(string $snap): string
{
    $d = json_decode($snap, true);
    if (!$d) return '—';
    $n = trim(($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''));
    return ($d['firma'] ?? null) ? ($d['firma'] . ($n ? ' / ' . $n : '')) : ($n ?: '—');
}

// ── Seite ───────────────────────────────────────────────────────────────────
$pageTitle    = 'Picklisten-Manager';
$activeModule = 'lager';
$actionBarContent = '<span style="font-weight:600">📋 Picklisten-Manager</span>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<style>
.pl-card        { background:var(--color-card);border:1px solid var(--color-border);border-radius:6px;padding:18px;margin-bottom:18px; }
.pl-card h3     { margin:0 0 14px;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted); }
.pl-order       { display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--color-border);border-radius:6px;margin-bottom:8px;background:var(--color-bg); }
.pl-order:hover { border-color:var(--color-nav); }
.pl-badge       { font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px;white-space:nowrap; }
.pl-badge-voll  { background:#dcfce7;color:#16a34a; }
.pl-badge-teil  { background:#fff7ed;color:#c2410c; }
.pl-badge-nein  { background:#fef2f2;color:#dc2626; }
.pl-table       { width:100%;border-collapse:collapse;font-size:13px; }
.pl-table th    { text-align:left;padding:7px 10px;border-bottom:2px solid var(--color-border);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted); }
.pl-table td    { padding:7px 10px;border-bottom:1px solid var(--color-border);vertical-align:middle; }
.pl-table tr:hover td { background:var(--color-bg); }
.pl-lager-low   { color:#dc2626;font-weight:700; }
.pl-lager-warn  { color:#d97706; }
.pl-lager-ok    { color:#16a34a; }
.pl-fehlende    { font-size:11px;color:#dc2626;margin-top:2px; }
</style>

<?php if ($erfolg): ?>
<div class="alert alert-success" style="margin-bottom:16px"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
<div class="alert alert-danger" style="margin-bottom:16px"><?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:18px;align-items:start">

<!-- ═══ LINKE SPALTE: Neue Pickliste ═══════════════════════════════════════ -->
<div>
    <div class="pl-card">
        <h3>Neue Pickliste erstellen</h3>

        <?php if (empty($auftraege)): ?>
            <p style="color:var(--color-text-muted);text-align:center;padding:20px 0">
                Keine lieferbereiten Aufträge vorhanden.
            </p>
        <?php else: ?>
        <form method="post" action="/mealana/lager/pickliste_erstellen.php">
            <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px">
                <?= count($auftraege) ?> Auftrag/Aufträge wartend — Reihenfolge: ältester zuerst.
                Vollständig lieferbare Aufträge sind vorausgewählt.
            </div>

            <?php foreach ($auftraege as $a):
                $isVoll   = $a['lieferbar'] === 'vollstaendig';
                $isTeil   = $a['lieferbar'] === 'teilweise';
                $precheck = $isVoll || $isTeil; // Teillieferung auch anbieten
                $kunde    = kundeName($a['kunden_snapshot'] ?? '{}');
                $alter    = (new DateTime($a['erstellt_am']))->diff(new DateTime())->days;
            ?>
            <label class="pl-order" style="cursor:pointer;<?= $a['lieferbar']==='nicht' ? 'opacity:.6' : '' ?>">
                <input type="checkbox" name="auftrag_ids[]" value="<?= $a['id'] ?>"
                       <?= $precheck ? 'checked' : '' ?>
                       style="width:18px;height:18px;flex-shrink:0;accent-color:var(--color-nav)">
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span style="font-weight:700"><?= htmlspecialchars($a['auftrag_nr']) ?></span>
                        <span style="font-size:12px;color:var(--color-text-muted)">
                            <?= date('d.m.Y', strtotime($a['erstellt_am'])) ?>
                            <?php if ($alter > 0): ?>
                                <span style="color:<?= $alter > 7 ? '#dc2626' : ($alter > 3 ? '#d97706' : 'inherit') ?>">
                                    (vor <?= $alter ?> Tag<?= $alter !== 1 ? 'en' : '' ?>)
                                </span>
                            <?php endif; ?>
                        </span>
                        <?php if ($isVoll): ?>
                            <span class="pl-badge pl-badge-voll">✓ Vollständig</span>
                        <?php elseif ($isTeil): ?>
                            <span class="pl-badge pl-badge-teil">⚠ Teillieferung</span>
                        <?php else: ?>
                            <span class="pl-badge pl-badge-nein">✗ Nicht lieferbar</span>
                        <?php endif; ?>
                        <?php if ($a['lieferart'] === 'abholung'): ?>
                            <span class="pl-badge" style="background:#f1f5f9;color:#475569">🏪 Abholung</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:3px">
                        <?= htmlspecialchars($kunde) ?> &bull;
                        <?= count($a['positionen']) ?> Position<?= count($a['positionen']) !== 1 ? 'en' : '' ?>
                    </div>
                    <?php if (!empty($a['fehlende'])): ?>
                        <div class="pl-fehlende">Fehlend: <?= htmlspecialchars(implode(', ', $a['fehlende'])) ?></div>
                    <?php endif; ?>
                </div>
            </label>
            <?php endforeach; ?>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px">
                    📋 Pickliste erstellen und drucken
                </button>
                <span style="font-size:12px;color:var(--color-text-muted)">
                    Erstellt PDF mit Barcode — zum Abscannen am Packplatz
                </span>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Lagerstand Collapsible -->
    <details>
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-nav);padding:8px 0;user-select:none">
            📦 Lagerstand anzeigen (<?= count($lagerstand) ?> Artikel mit Bestand)
        </summary>
        <div class="pl-card" style="margin-top:8px">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Artikelnr.</th>
                        <th>Bezeichnung</th>
                        <th style="text-align:right">Ist</th>
                        <th style="text-align:right">Reserviert</th>
                        <th style="text-align:right">Verfügbar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lagerstand as $l):
                    $v = (int)$l['verfuegbar'];
                    $cls = $v <= 0 ? 'pl-lager-low' : ($v <= 2 ? 'pl-lager-warn' : 'pl-lager-ok');
                ?>
                <tr>
                    <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($l['artikelnummer'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['bezeichnung']) ?></td>
                    <td style="text-align:right"><?= (int)$l['ist'] ?></td>
                    <td style="text-align:right;color:var(--color-text-muted)"><?= (int)$l['reserviert'] ?></td>
                    <td style="text-align:right" class="<?= $cls ?>"><?= $v ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
</div>

<!-- ═══ RECHTE SPALTE: Vorhandene Picklisten ════════════════════════════════ -->
<div>
    <div class="pl-card">
        <h3>Offene Picklisten</h3>

        <?php if (empty($picklisten)): ?>
            <p style="color:var(--color-text-muted);font-size:13px;text-align:center;padding:10px 0">
                Keine offenen Picklisten.
            </p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($picklisten as $pl): ?>
            <div style="border:1px solid var(--color-border);border-radius:6px;padding:12px 14px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                    <div>
                        <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($pl['nummer']) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                            <?= (int)$pl['anzahl_auftraege'] ?> Auftrag/Aufträge &bull;
                            <?= date('d.m.Y H:i', strtotime($pl['erstellt_am'])) ?>
                        </div>
                        <?php if ($pl['erstellt_von_name']): ?>
                            <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($pl['erstellt_von_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px;white-space:nowrap;background:<?= $pl['status']==='gedruckt' ? '#dcfce7' : '#eff6ff' ?>;color:<?= $pl['status']==='gedruckt' ? '#16a34a' : '#2563eb' ?>">
                        <?= $pl['status'] === 'gedruckt' ? '🖨 Gedruckt' : '● Offen' ?>
                    </span>
                </div>
                <div style="display:flex;gap:6px;margin-top:10px">
                    <a href="/mealana/lager/pickliste_pdf.php?id=<?= $pl['id'] ?>"
                       target="_blank"
                       class="btn btn-secondary" style="font-size:12px;padding:4px 12px">
                        📄 PDF
                    </a>
                    <form method="post" action="/mealana/lager/pickliste_loeschen.php"
                          onsubmit="return confirm('Pickliste <?= htmlspecialchars($pl['nummer']) ?> löschen?')">
                        <input type="hidden" name="id" value="<?= $pl['id'] ?>">
                        <button type="submit" class="btn btn-secondary"
                                style="font-size:12px;padding:4px 12px;color:#dc2626;border-color:#dc2626">
                            🗑
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Legende -->
    <div class="pl-card" style="font-size:12px;color:var(--color-text-muted)">
        <div style="font-weight:600;margin-bottom:6px">Legende</div>
        <div><span class="pl-badge pl-badge-voll">✓ Vollständig</span> Alle Positionen lieferbar</div>
        <div style="margin-top:4px"><span class="pl-badge pl-badge-teil">⚠ Teillieferung</span> Einige Positionen fehlen</div>
        <div style="margin-top:4px"><span class="pl-badge pl-badge-nein">✗ Nicht lieferbar</span> Kein einziger Artikel verfügbar</div>
        <div style="margin-top:8px;font-size:11px">
            Die Zuteilung erfolgt greedy (ältester Auftrag zuerst).
            Deaktivierte Aufträge freigeben durch Abwählen.
        </div>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
