<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle        = 'Chargen-Nachverfolgung';
$activeModule     = 'lager';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lager/uebersicht.php" class="btn btn-secondary btn-sm">← Lagerübersicht</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card" style="margin-bottom:12px">
    <div style="font-weight:600;margin-bottom:8px">Artikel suchen</div>
    <div style="position:relative;max-width:480px">
        <input type="text" id="cn-suche" class="erp-input" style="width:100%"
               placeholder="Name, Artikelnummer oder EAN…" autocomplete="off">
        <div id="cn-dropdown" style="position:absolute;z-index:100;background:#fff;border:1px solid var(--color-border);border-radius:4px;width:100%;max-height:320px;overflow-y:auto;display:none"></div>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
        Beliebig oft einen anderen Artikel nachschlagen, ohne die Seite zu verlassen.
    </div>
</div>

<div id="cn-ergebnis" style="display:none">
    <div class="card" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <div>
                <div style="font-weight:700;font-size:15px" id="cn-artikel-name"></div>
                <div style="font-size:12px;color:var(--color-text-muted)" id="cn-artikel-nr"></div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <label for="cn-charge-filter" style="font-size:12px;color:var(--color-text-muted)">Charge:</label>
                <select id="cn-charge-filter" class="erp-select" style="font-size:13px;padding:4px 8px"></select>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="font-weight:600;margin-bottom:8px" id="cn-bewegungslog-titel">Letzte Lagerbewegungen</div>
        <div id="cn-bewegungslog-inhalt"></div>
    </div>
</div>

<script src="<?= BASE_PATH ?>/js/lager_chargen_nachverfolgung.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
