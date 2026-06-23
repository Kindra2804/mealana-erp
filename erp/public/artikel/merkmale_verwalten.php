<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MerkmaleRepository.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$repo     = new MerkmaleRepository();
$merkmale = $repo->findAllMitWerten();
$artikelService = new ArtikelService();
$db       = Database::getInstance();
$artikeltypen = $db->query("SELECT id, name FROM artikel_typen ORDER BY name")->fetchAll();

$pageTitle    = 'Merkmale verwalten';
$activeModule = 'artikel';
$kategorienBaum = $artikelService->getKategorienBaum();

$actionBarContent = <<<HTML
<button onclick="merkmalNeu()" class="btn btn-primary btn-sm">+ Neues Merkmal</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div style="padding:var(--space-md)">

    <?php if (empty($merkmale)): ?>
        <div class="card" style="color:var(--color-text-muted);font-size:13px">
            Noch keine Merkmale angelegt. Klicke auf „+ Neues Merkmal".
        </div>
    <?php endif; ?>

    <?php foreach ($merkmale as $mi => $m): ?>
        <div class="card" style="margin-bottom:var(--space-sm)" id="merkmal-<?= $m['id'] ?>">

            <div style="display:flex;align-items:center;gap:var(--space-sm);flex-wrap:wrap">
                <strong style="font-size:14px;flex:1"><?= htmlspecialchars($m['name']) ?></strong>

                <?php if ($m['slug']): ?>
                    <span style="font-size:11px;color:var(--color-text-muted);font-family:monospace">pa_<?= htmlspecialchars($m['slug']) ?></span>
                <?php endif; ?>

                <span class="chip <?= $m['mehrfach_auswahl'] ? 'chip-aktiv' : '' ?>" style="font-size:11px">
                    <?= $m['mehrfach_auswahl'] ? 'Multi' : 'Single' ?>
                </span>

                <?php if ($m['filterbar']): ?>
                    <span class="chip chip-aktiv" style="font-size:11px;background:#d1fae5;color:#065f46;border-color:#6ee7b7">Filterbar</span>
                <?php endif; ?>

                <?php if (!empty($m['artikeltyp_ids'])): ?>
                    <?php foreach ($artikeltypen as $at): ?>
                        <?php if (in_array($at['id'], $m['artikeltyp_ids'])): ?>
                            <span class="chip" style="font-size:11px;background:#ede9fe;color:#5b21b6;border-color:#c4b5fd"><?= htmlspecialchars($at['name']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="font-size:11px;color:var(--color-text-muted)">Alle Typen</span>
                <?php endif; ?>

                <div style="display:flex;gap:4px;margin-left:auto">
                    <?php if ($mi > 0): ?>
                        <button class="btn btn-secondary btn-xs" onclick="merkmalSort(<?= $m['id'] ?>, 'hoch')" title="Nach oben">▲</button>
                    <?php endif; ?>
                    <?php if ($mi < count($merkmale) - 1): ?>
                        <button class="btn btn-secondary btn-xs" onclick="merkmalSort(<?= $m['id'] ?>, 'runter')" title="Nach unten">▼</button>
                    <?php endif; ?>
                    <button class="btn btn-secondary btn-xs" onclick="merkmalBearbeiten(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m)) ?>)">Bearb.</button>
                    <button class="btn btn-danger btn-xs" onclick="merkmalLoeschen(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['name'])) ?>)">Löschen</button>
                </div>
            </div>

            <!-- Werte -->
            <div style="margin-top:var(--space-sm);padding-top:var(--space-sm);border-top:1px solid var(--color-border)">
                <div id="werte-<?= $m['id'] ?>">
                    <?php foreach ($m['werte'] as $wi => $w): ?>
                        <div class="mw-zeile" id="wert-<?= $w['id'] ?>" style="display:flex;align-items:center;gap:var(--space-sm);padding:3px 0">
                            <span style="font-size:13px;flex:1"><?= htmlspecialchars($w['wert']) ?></span>
                            <div style="display:flex;gap:2px">
                                <?php if ($wi > 0): ?>
                                    <button class="btn btn-secondary btn-xs" onclick="wertSort(<?= $w['id'] ?>, 'hoch')">▲</button>
                                <?php endif; ?>
                                <?php if ($wi < count($m['werte']) - 1): ?>
                                    <button class="btn btn-secondary btn-xs" onclick="wertSort(<?= $w['id'] ?>, 'runter')">▼</button>
                                <?php endif; ?>
                                <button class="btn btn-secondary btn-xs" onclick="wertBearbeiten(<?= $w['id'] ?>, <?= htmlspecialchars(json_encode($w['wert'])) ?>)">✏️</button>
                                <button class="btn btn-danger btn-xs" onclick="wertLoeschen(<?= $w['id'] ?>, <?= htmlspecialchars(json_encode($w['wert'])) ?>)">✕</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;gap:var(--space-sm);margin-top:var(--space-xs)">
                    <input type="text" id="neu-wert-<?= $m['id'] ?>" class="erp-input" style="flex:1" placeholder="Neuer Wert...">
                    <button class="btn btn-secondary btn-sm" onclick="wertNeu(<?= $m['id'] ?>)">+ Wert</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- Modal: Merkmal anlegen / bearbeiten -->
<div id="merkmal-backdrop" class="modal-backdrop" style="display:none" onclick="merkmalModalSchliessen()">
    <div class="modal" style="max-width:480px" onclick="event.stopPropagation()">
        <div class="modal-header">Merkmal</div>
        <input type="hidden" id="mf-id">

        <div class="form-group" style="margin-top:var(--space-sm)">
            <label class="form-label">Name *</label>
            <input type="text" id="mf-name" class="erp-input" style="width:100%" placeholder="z.B. Maschenprobe">
        </div>
        <div class="form-group">
            <label class="form-label">Slug (WooCommerce)</label>
            <input type="text" id="mf-slug" class="erp-input" style="width:100%" placeholder="z.B. maschenprobe">
            <span style="font-size:11px;color:var(--color-text-muted)">Wird als pa_{slug} in WooCommerce exportiert</span>
        </div>
        <div style="display:flex;gap:var(--space-md);margin-top:var(--space-sm)">
            <label class="form-check">
                <input type="checkbox" id="mf-mehrfach"> Mehrfach-Auswahl
            </label>
            <label class="form-check">
                <input type="checkbox" id="mf-filterbar"> Im Shop filterbar
            </label>
        </div>
        <div class="form-group" style="margin-top:var(--space-sm)">
            <label class="form-label">Nur für Artikeltypen (leer = alle)</label>
            <div id="mf-typen" style="display:flex;flex-wrap:wrap;gap:var(--space-xs)">
                <?php foreach ($artikeltypen as $at): ?>
                    <label class="form-check" style="margin:0">
                        <input type="checkbox" class="mf-typ-cb" value="<?= $at['id'] ?>">
                        <?= htmlspecialchars($at['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="mf-fehler" style="color:var(--color-danger);font-size:13px;min-height:18px"></div>
        <div style="display:flex;gap:var(--space-sm);justify-content:flex-end;margin-top:var(--space-sm)">
            <button class="btn btn-secondary" onclick="merkmalModalSchliessen()">Abbrechen</button>
            <button class="btn btn-primary" id="mf-btn-speichern" onclick="merkmalSpeichern()">Speichern</button>
        </div>
    </div>
</div>

<script src="/mealana/js/merkmale_verwalten.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
