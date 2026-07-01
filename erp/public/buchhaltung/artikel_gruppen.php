<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db     = Database::getInstance();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$gruppen = $db->query("
    SELECT id, konto_nr, name, aktiv, sortierung
    FROM artikel_gruppen
    ORDER BY sortierung, konto_nr
")->fetchAll();

// Anzahl Artikel pro Gruppe
$anzahlen = $db->query("
    SELECT artikel_gruppe_id, COUNT(*) AS anzahl
    FROM artikel
    WHERE artikel_gruppe_id IS NOT NULL
    GROUP BY artikel_gruppe_id
")->fetchAll(\PDO::FETCH_KEY_PAIR);

// Anzahl Versandklassen pro Gruppe
$anzVsk = $db->query("
    SELECT artikel_gruppe_id, COUNT(*) AS anzahl
    FROM versandklassen
    WHERE artikel_gruppe_id IS NOT NULL
    GROUP BY artikel_gruppe_id
")->fetchAll(\PDO::FETCH_KEY_PAIR);

// Aktive Väter/Standalone ohne Gruppe (die müssen manuell bearbeitet werden)
$ohneGruppeVater = (int)$db->query("
    SELECT COUNT(*) FROM artikel
    WHERE artikel_gruppe_id IS NULL AND aktiv = 1
      AND vaterartikel_id IS NULL AND zustand_vater_id IS NULL
")->fetchColumn();

// Kind-Artikel ohne Gruppe (erben beim nächsten Vater-Speichern automatisch)
$ohneGruppeKind = (int)$db->query("
    SELECT COUNT(*) FROM artikel
    WHERE artikel_gruppe_id IS NULL AND aktiv = 1
      AND vaterartikel_id IS NOT NULL
")->fetchColumn();

$pageTitle        = 'Artikelgruppen';
$activeModule     = 'buchhaltung';
$actionBarContent = '<button onclick="gruppeNeu()" class="btn btn-primary btn-sm">+ Neue Gruppe</button>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>
<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>

<?php if ($ohneGruppeVater > 0): ?>
<div class="card" style="border-left:3px solid #f59e0b;margin-bottom:12px;padding:10px 16px;display:flex;align-items:center;gap:10px">
    <span style="font-size:18px">⚠</span>
    <span>
        <strong><?= $ohneGruppeVater ?> Artikel</strong> (Väter / Standalone) haben noch keine Artikelgruppe —
        <a href="/mealana/artikel/liste.php?status=keine_gruppe" style="color:var(--color-nav)">→ Liste anzeigen</a>
        <?php if ($ohneGruppeKind > 0): ?>
        <br><span style="font-size:11px;color:var(--color-text-muted)">
            + <?= $ohneGruppeKind ?> Kind-Artikel erben die Gruppe automatisch wenn ihr Vater gespeichert wird.
        </span>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Artikelgruppen mit Kontozuordnung</div>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:80px">Konto</th>
                <th>Name</th>
                <th style="width:60px;text-align:center">Sort.</th>
                <th style="width:80px;text-align:center">Artikel</th>
                <th style="width:90px;text-align:center">Versandkl.</th>
                <th style="width:70px;text-align:center">Aktiv</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gruppen as $g): ?>
            <tr style="<?= $g['aktiv'] ? '' : 'opacity:.5' ?>">
                <td><code style="font-size:12px;color:var(--color-nav)"><?= htmlspecialchars($g['konto_nr']) ?></code></td>
                <td><?= htmlspecialchars($g['name']) ?></td>
                <td style="text-align:center;color:var(--color-text-muted)"><?= (int)$g['sortierung'] ?></td>
                <td style="text-align:center">
                    <?php $a = (int)($anzahlen[$g['id']] ?? 0); ?>
                    <?php if ($a > 0): ?>
                        <a href="/mealana/artikel/liste.php?gruppe_id=<?= $g['id'] ?>"
                           style="color:var(--color-nav);font-size:12px"><?= $a ?></a>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted);font-size:12px">0</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php $v = (int)($anzVsk[$g['id']] ?? 0); ?>
                    <span style="color:var(--color-text-muted);font-size:12px"><?= $v ?></span>
                </td>
                <td style="text-align:center">
                    <?= $g['aktiv'] ? '<span style="color:#16a34a">✓</span>' : '<span style="color:#dc2626">✗</span>' ?>
                </td>
                <td style="text-align:right">
                    <button onclick="gruppeBearbeiten(<?= htmlspecialchars(json_encode($g)) ?>)"
                            class="btn btn-secondary btn-sm">Bearbeiten</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Neue/Bearbeiten -->
<div id="gruppe-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;width:400px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px;color:var(--color-nav)" id="modal-titel">Neue Artikelgruppe</div>

        <form id="gruppe-form" method="post">
            <input type="hidden" name="id" id="f-id">

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Kontonummer *</label>
                <input type="text" name="konto_nr" id="f-konto" class="erp-input" style="width:100%"
                       placeholder="z.B. 4050" maxlength="10" required>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Muss eindeutig sein (z.B. 4000, 4050, 4900)</div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="f-name" class="erp-input" style="width:100%"
                       placeholder="z.B. Wolle" maxlength="100" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group">
                    <label class="form-label">Sortierung</label>
                    <input type="number" name="sortierung" id="f-sort" class="erp-input" style="width:100%"
                           value="10" min="0" max="9999">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:2px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="aktiv" id="f-aktiv" value="1" checked
                               style="width:15px;height:15px">
                        Aktiv
                    </label>
                </div>
            </div>

            <div id="modal-fehler" style="font-size:12px;color:var(--color-danger);min-height:16px;margin-bottom:8px"></div>

            <div style="display:flex;gap:8px;justify-content:space-between">
                <button type="button" id="btn-loeschen" onclick="gruppeLoeschen()"
                        class="btn btn-danger btn-sm" style="display:none">Löschen</button>
                <div style="display:flex;gap:8px;margin-left:auto">
                    <button type="button" onclick="modalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="/mealana/js/buchhaltung_artikel_gruppen.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
