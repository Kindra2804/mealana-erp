<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$artikelId = (int)($_GET['artikel_id'] ?? 0);
if ($artikelId <= 0) { header('Location: liste.php'); exit; }

$varService  = new VariantenService();
$achsService = new AchsenService();
$artService  = new ArtikelService();

$artikel = $artService->findById($artikelId);
if (!$artikel) { header('Location: liste.php'); exit; }

$alleAchsen      = $achsService->findAll();
$vorhandeneWerte = $varService->findWerteByArtikelId($artikelId);
$zugewieseneRaw  = $varService->findAchsenByArtikelId($artikelId);
$zugewieseneIds  = array_map(fn($a) => (int)$a['achse_id'], $zugewieseneRaw);
$wertIdsInUse    = array_map('intval', $varService->findWertIdsInUse($artikelId));
$wertIdsInUseSet = array_flip($wertIdsInUse);
$hatKindArtikel  = !empty($wertIdsInUse);

// Preis-Map: achse_id → {preis_modus, preis_wert} aus artikel_achsen
$preisMap = [];
foreach ($zugewieseneRaw as $zr) {
    $preisMap[(int)$zr['achse_id']] = ['preis_modus' => $zr['preis_modus'] ?? 'aufpreis', 'preis_wert' => (float)($zr['preis_wert'] ?? 0)];
}

// Achsenbaum aufbauen
$roots  = [];
$kinder = [];
foreach ($alleAchsen as $a) {
    $pid = (int)($a['abhaengig_von_achse_id'] ?? 0);
    if ($pid > 0) $kinder[$pid][] = $a;
    else $roots[] = $a;
}

// Vorhandene Werte nach Achse gruppieren
$werteProAchse = [];
foreach ($vorhandeneWerte as $w) {
    $werteProAchse[(int)$w['achse_id']][] = $w;
}

// Achsen-Namen für JS (id => name)
$achsenNamenJson = json_encode(array_column($alleAchsen, 'name', 'id'), JSON_UNESCAPED_UNICODE);

$flash       = $_SESSION['erfolg'] ?? null;
$flashFehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$kategorienBaum = $artService->getKategorienBaum();

$pageTitle    = 'Achsen & Werte';
$activeModule = 'artikel';
$actionBarContent = '<a href="detail.php?id=' . $artikelId . '" class="btn btn-secondary btn-sm">← Zum Artikel</a>';

// ── Hilfsfunktionen ───────────────────────────────────────────────────────────

function chipHtml(int $achseId, int|string $idx, string $wert, bool $isLocked = false): string
{
    $esc  = htmlspecialchars($wert, ENT_QUOTES);
    $name = "werte[{$achseId}][{$idx}][wert]";

    if ($isLocked) {
        return <<<HTML
<span class="wert-chip" data-achse-id="{$achseId}"
      title="In Varianten verwendet – kann nicht entfernt werden"
      style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#64748b;
             border:1px solid #cbd5e1;border-radius:16px;padding:3px 10px 3px 8px;font-size:12px;line-height:1.5">
  <span style="font-size:9px;opacity:.7">🔒</span>
  <span class="chip-text">{$esc}</span>
  <input type="hidden" name="{$name}" value="{$esc}">
  <button type="button" onclick="chipBearbeiten(this)" title="Text bearbeiten"
          style="background:none;border:none;cursor:pointer;padding:0 0 0 3px;color:#94a3b8;font-size:11px;line-height:1">✎</button>
</span>
HTML;
    }

    return <<<HTML
<span class="wert-chip" data-achse-id="{$achseId}"
      style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1e40af;
             border-radius:16px;padding:3px 10px 3px 8px;font-size:12px;line-height:1.5">
  <button type="button" onclick="chipSortieren(this,'links')" title="Nach links"
          style="background:none;border:none;cursor:pointer;padding:0 1px;color:#93c5fd;font-size:10px;line-height:1">◀</button>
  <span class="chip-text">{$esc}</span>
  <input type="hidden" name="{$name}" value="{$esc}">
  <button type="button" onclick="chipBearbeiten(this)" title="Text bearbeiten"
          style="background:none;border:none;cursor:pointer;padding:0 2px;color:#93c5fd;font-size:11px;line-height:1">✎</button>
  <button type="button" onclick="chipSortieren(this,'rechts')" title="Nach rechts"
          style="background:none;border:none;cursor:pointer;padding:0 1px;color:#93c5fd;font-size:10px;line-height:1">▶</button>
  <button type="button" onclick="chipVerschieben(this)" title="In andere Achse verschieben"
          style="background:none;border:none;cursor:pointer;padding:0 2px;color:#3b82f6;font-size:11px;line-height:1">↔</button>
  <button type="button" onclick="chipEntfernen(this)"
          style="background:none;border:none;cursor:pointer;padding:0 0 0 2px;color:#3b82f6;font-size:14px;line-height:1">✕</button>
  <select class="chip-move-sel"
          style="display:none;font-size:11px;border:1px solid #93c5fd;border-radius:4px;margin-left:2px"
          onchange="moveAusfuehren(this)"><option value="">→ verschieben nach...</option></select>
</span>
HTML;
}

function renderAchse(array $achse, array $kinder, array $zugewieseneIds, array $werteProAchse, int $pos = 0, int $total = 1, array $wertIdsInUseSet = [], array $preisMap = []): void
{
    $id             = (int)$achse['id'];
    $checked        = in_array($id, $zugewieseneIds);
    $isGruppe       = (bool)$achse['ist_gruppe'];
    $hatKinder      = !empty($kinder[$id]);
    $isKind         = (int)($achse['abhaengig_von_achse_id'] ?? 0) > 0;
    $parentId       = (int)($achse['abhaengig_von_achse_id'] ?? 0);
    $wertListe      = $werteProAchse[$id] ?? [];
    $showUa         = $isGruppe || $hatKinder;
    $achseGesperrt  = !empty(array_filter($wertListe, fn($v) => isset($wertIdsInUseSet[(int)$v['id']])));

    $indentStyle = $isKind ? 'margin-left:28px;border-left:3px solid #c7d2fe;padding-left:16px;' : '';
    $headerBg    = $isKind ? '#f5f3ff' : '#f8fafc';
    $borderR     = $checked ? '6px 6px 0 0' : '6px';
    ?>
    <div class="achse-block" id="achse-blk-<?= $id ?>" style="margin-bottom:10px;<?= $indentStyle ?>">

        <!-- Achse-Header -->
        <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;
                    background:<?= $headerBg ?>;border:1px solid #e2e8f0;border-radius:<?= $borderR ?>;
                    cursor:pointer" onclick="document.getElementById('cb-<?= $id ?>').click()">
            <?php if ($achseGesperrt): ?>
            <input type="hidden" name="achsen[]" value="<?= $id ?>">
            <?php endif; ?>
            <input type="checkbox"
                   name="achsen[]"
                   value="<?= $id ?>"
                   id="cb-<?= $id ?>"
                   <?= $checked ? 'checked' : '' ?>
                   <?= $achseGesperrt ? 'disabled title="Werte in Verwendung – Achse kann nicht abgewählt werden"' : '' ?>
                   onchange="achseGeaendert(<?= $id ?>)"
                   onclick="event.stopPropagation()"
                   style="width:16px;height:16px;cursor:<?= $achseGesperrt ? 'not-allowed' : 'pointer' ?>;flex-shrink:0">
            <?php
            $pm    = $preisMap[$id] ?? ['preis_modus' => 'aufpreis', 'preis_wert' => 0];
            $mod   = $pm['preis_modus'];
            $pv    = number_format((float)$pm['preis_wert'], 2, '.', '');
            $apAct = $mod === 'aufpreis'    ? 'background:#1e40af;color:#fff;border-color:#1e40af' : 'background:#f1f5f9;color:#64748b;border-color:#e2e8f0';
            $dpAct = $mod === 'direktpreis' ? 'background:#1e40af;color:#fff;border-color:#1e40af' : 'background:#f1f5f9;color:#64748b;border-color:#e2e8f0';
            ?>
            <span style="flex:1;display:inline-flex;align-items:center;gap:8px">
                <span style="font-weight:600;font-size:14px"><?= htmlspecialchars($achse['name']) ?></span>
                <span onclick="event.stopPropagation()" style="display:inline-flex;align-items:center;gap:0;flex-shrink:0">
                    <input type="hidden" name="preis_modi[<?= $id ?>]" id="pm-<?= $id ?>" value="<?= $mod ?>">
                    <button type="button" id="pm-ap-<?= $id ?>"
                            onclick="preisModiSetzen(<?= $id ?>,'aufpreis')"
                            title="Aufpreis (wird auf Vater-VK addiert)"
                            style="<?= $apAct ?>;border:1px solid;border-radius:4px 0 0 4px;padding:2px 6px;font-size:11px;cursor:pointer;line-height:1.4">+€</button>
                    <button type="button" id="pm-dp-<?= $id ?>"
                            onclick="preisModiSetzen(<?= $id ?>,'direktpreis')"
                            title="Direktpreis (absoluter VK für alle Kinder dieser Achse)"
                            style="<?= $dpAct ?>;border:1px solid;border-left:none;border-radius:0 4px 4px 0;padding:2px 6px;font-size:11px;cursor:pointer;line-height:1.4">€</button>
                    <input type="number" name="preis_werte[<?= $id ?>]" id="pv-<?= $id ?>"
                           value="<?= $pv ?>" step="0.01" min="0"
                           style="width:64px;font-size:12px;border:1px solid #e2e8f0;border-left:none;border-radius:0 4px 4px 0;padding:3px 6px;text-align:right"
                           onchange="formDirty=true" onclick="event.stopPropagation()">
                </span>
            </span>
            <?php if ($isGruppe): ?>
                <span style="background:#fef3c7;color:#92400e;border-radius:10px;padding:2px 8px;font-size:11px;flex-shrink:0">Gruppenachse</span>
            <?php endif; ?>
            <?php if ($isKind): ?>
                <span style="background:#ede9fe;color:#5b21b6;border-radius:10px;padding:2px 8px;font-size:11px;flex-shrink:0">Sub-Achse</span>
            <?php endif; ?>
            <span style="background:#EDF2F7;color:#4A5568;border-radius:10px;padding:2px 8px;font-size:11px;flex-shrink:0"><?= htmlspecialchars($achse['darstellungsform']) ?></span>
            <span id="werte-count-<?= $id ?>"
                  style="background:#dcfce7;color:#166534;border-radius:10px;padding:2px 8px;font-size:11px;flex-shrink:0<?= empty($wertListe) ? ';display:none' : '' ?>">
                <?= count($wertListe) ?> <?= count($wertListe) === 1 ? 'Wert' : 'Werte' ?>
            </span>
            <!-- Sortier-Buttons -->
            <span style="display:inline-flex;flex-direction:column;gap:1px;flex-shrink:0">
                <?php if ($pos > 0): ?>
                    <button type="button"
                            onclick="event.stopPropagation();achseSort(<?= $id ?>, 'hoch', <?= $parentId ?>)"
                            style="background:none;border:1px solid #e2e8f0;border-radius:3px;cursor:pointer;
                                   padding:0 4px;font-size:10px;color:#94a3b8;line-height:1.4"
                            title="Nach oben">▲</button>
                <?php endif; ?>
                <?php if ($pos < $total - 1): ?>
                    <button type="button"
                            onclick="event.stopPropagation();achseSort(<?= $id ?>, 'runter', <?= $parentId ?>)"
                            style="background:none;border:1px solid #e2e8f0;border-radius:3px;cursor:pointer;
                                   padding:0 4px;font-size:10px;color:#94a3b8;line-height:1.4"
                            title="Nach unten">▼</button>
                <?php endif; ?>
            </span>
            <?php $achseJs = htmlspecialchars(json_encode([
                'id'                     => $id,
                'name'                   => $achse['name'],
                'darstellungsform'       => $achse['darstellungsform'],
                'ist_gruppe'             => (int)$isGruppe,
                'hat_kinder'             => (int)$hatKinder,
                'in_use'                 => (int)($achse['in_use'] ?? 0),
                'abhaengig_von_achse_id' => $parentId,
                'sort_order'             => (int)$achse['sort_order'],
            ], JSON_UNESCAPED_UNICODE)); ?>
            <button type="button"
                    onclick="event.stopPropagation();achseBBOeffnen(<?= $achseJs ?>)"
                    style="background:none;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;
                           padding:2px 6px;font-size:12px;color:#64748b;flex-shrink:0;line-height:1.4"
                    title="Achse global bearbeiten">✎</button>
        </div>

        <!-- Werte-Block (sichtbar wenn Achse ausgewählt) -->
        <div id="werte-blk-<?= $id ?>"
             <?= !$checked ? 'hidden' : '' ?>
             style="padding:12px 14px 10px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 6px 6px;background:#fff">
            <?php if ($isGruppe && $showUa): ?>
                <div style="font-size:11px;color:#64748b;margin-bottom:8px">
                    Direkte Werte (optional — für Garne ohne Untergruppe):
                </div>
            <?php endif; ?>
            <div class="chip-cont" id="chips-<?= $id ?>"
                 style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;min-height:28px">
                <?php foreach ($wertListe as $idx => $v): ?>
                    <?= chipHtml($id, $idx, $v['wert'], isset($wertIdsInUseSet[(int)$v['id']])) ?>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
                <input type="text"
                       id="inp-<?= $id ?>"
                       class="erp-input"
                       placeholder="Wert + Enter"
                       style="max-width:260px"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();wertHinzufuegen(<?= $id ?>)}">
                <button type="button" onclick="wertHinzufuegen(<?= $id ?>)"
                        class="btn btn-secondary btn-xs">+ Wert</button>
            </div>
        </div>

        <!-- Unterachsen -->
        <?php if ($hatKinder): ?>
        <div id="kinder-<?= $id ?>" style="margin-top:8px">
            <?php $kindListe = $kinder[$id]; $kindTotal = count($kindListe); ?>
            <?php foreach ($kindListe as $ki => $kind): ?>
                <?php renderAchse($kind, $kinder, $zugewieseneIds, $werteProAchse, $ki, $kindTotal, $wertIdsInUseSet, $preisMap); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Unterachse anlegen -->
        <?php if ($showUa): ?>
        <div id="ua-bereich-<?= $id ?>" style="margin-top:6px;<?= !$isKind ? 'margin-left:28px;' : '' ?>">
            <div id="ua-btn-<?= $id ?>" <?= !$checked ? 'hidden' : '' ?>>
                <button type="button" onclick="uaZeigen(<?= $id ?>)"
                        class="btn btn-secondary btn-xs"
                        style="font-size:11px;color:#6b7280;border-style:dashed">
                    + Unterachse anlegen
                </button>
            </div>
            <div id="ua-form-<?= $id ?>"
                 style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:10px;margin-top:4px">
                <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:#15803d">
                    Neue Unterachse von „<?= htmlspecialchars($achse['name']) ?>"
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="ua-name-<?= $id ?>" class="erp-input"
                           placeholder="Name (z.B. Uni)" style="width:180px"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();uaSpeichern(<?= $id ?>)}">
                    <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer">
                        <input type="checkbox" id="ua-gruppe-<?= $id ?>"> Gruppenachse
                    </label>
                    <button type="button" onclick="uaSpeichern(<?= $id ?>)"
                            class="btn btn-primary btn-xs">Anlegen</button>
                    <button type="button" onclick="uaAbbrechen(<?= $id ?>)"
                            class="btn btn-secondary btn-xs">Abbrechen</button>
                </div>
                <div id="ua-fehler-<?= $id ?>" style="color:var(--color-danger);font-size:11px;margin-top:5px"></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php
}

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;padding:10px 14px;margin-bottom:var(--space-md);color:#155724;font-size:13px">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php if ($flashFehler): ?>
<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:10px 14px;margin-bottom:var(--space-md);color:#721c24;font-size:13px">
    <?= is_array($flashFehler) ? implode('<br>', array_map('htmlspecialchars', $flashFehler)) : htmlspecialchars($flashFehler) ?>
</div>
<?php endif; ?>

<?php if ($hatKindArtikel): ?>
<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin-bottom:var(--space-md);color:#856404;font-size:13px">
    <strong>Hinweis:</strong> Einige Werte sind bereits in Varianten-Kombinationen vergeben (🔒).
    Diese können nicht entfernt werden. Neue Werte hinzufügen und freie Werte löschen ist weiterhin möglich.
</div>
<?php endif; ?>

<!-- Artikel-Info -->
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:var(--space-lg);display:flex;align-items:center;gap:16px">
    <div>
        <div style="font-size:11px;color:var(--color-text-muted)">Artikel</div>
        <div style="font-weight:700;font-size:16px"><?= htmlspecialchars($artikel['name']) ?></div>
    </div>
    <div style="color:#94a3b8;font-size:12px"><?= htmlspecialchars($artikel['artikelnummer']) ?></div>
    <div style="flex:1"></div>
    <a href="detail.php?id=<?= $artikelId ?>" class="btn btn-secondary btn-sm">← Zum Artikel</a>
</div>

<form method="post" action="achsen_speichern.php" id="achsen-form">
    <input type="hidden" name="artikel_id" value="<?= $artikelId ?>">

    <div class="card" style="margin-bottom:var(--space-md)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-md)">
            <h3 style="margin:0">Achsen & Werte</h3>
            <button type="button" onclick="neueAchseZeigen()"
                    class="btn btn-secondary btn-sm" style="font-size:12px">
                + Neue Achse (global)
            </button>
        </div>

        <?php if (empty($alleAchsen)): ?>
            <p style="color:var(--color-text-muted);font-size:13px">
                Noch keine globalen Achsen definiert.
                <button type="button" onclick="neueAchseZeigen()" class="btn btn-primary btn-sm">Erste Achse anlegen</button>
            </p>
        <?php else: ?>
            <?php $rootTotal = count($roots); ?>
            <?php foreach ($roots as $ri => $root): ?>
                <?php renderAchse($root, $kinder, $zugewieseneIds, $werteProAchse, $ri, $rootTotal, $wertIdsInUseSet, $preisMap); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="detail.php?id=<?= $artikelId ?>" class="btn btn-secondary">Abbrechen</a>
    </div>
</form>

<!-- ── Neue globale Achse Modal ──────────────────────────────────────────────── -->
<div id="neue-achse-modal" class="modal-backdrop" style="display:none">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span>Neue Achse (global)</span>
            <button onclick="neueAchseSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Name <span style="color:var(--color-danger)">*</span></label>
                <input type="text" id="na-name" class="erp-input" style="width:100%" placeholder="z.B. Stärke">
            </div>
            <div style="margin-bottom:var(--space-md)">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="na-gruppe" style="width:16px;height:16px">
                    <span style="font-size:13px;font-weight:600">Gruppenachse</span>
                </label>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px;margin-left:24px">
                    Kann Unterachsen enthalten (und trotzdem eigene Werte haben)
                </div>
            </div>
            <div style="margin-bottom:var(--space-sm)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Darstellungsform</label>
                <select id="na-darst" class="erp-select" style="width:100%">
                    <option value="swatches">swatches</option>
                    <option value="dropdown">dropdown</option>
                    <option value="radiobutton">radiobutton</option>
                    <option value="freitext">freitext</option>
                </select>
            </div>
            <div id="na-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:var(--space-sm)"></div>
        </div>
        <div class="modal-footer">
            <button onclick="neueAchseSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="na-btn" onclick="neueAchseSpeichern()" class="btn btn-primary btn-sm">Anlegen</button>
        </div>
    </div>
</div>

<!-- ── Achse global bearbeiten Modal ─────────────────────────────────────────── -->
<div id="achse-bearb-modal" class="modal-backdrop" style="display:none">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <span>Achse bearbeiten</span>
            <button onclick="achseBBSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="abb-id">
            <input type="hidden" id="abb-abhaengig">
            <input type="hidden" id="abb-sort">

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Name <span style="color:var(--color-danger)">*</span></label>
                <input type="text" id="abb-name" class="erp-input" style="width:100%">
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Darstellungsform</label>
                <select id="abb-darst" class="erp-select" style="width:100%">
                    <option value="swatches">swatches</option>
                    <option value="dropdown">dropdown</option>
                    <option value="radiobutton">radiobutton</option>
                    <option value="freitext">freitext</option>
                </select>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="abb-gruppe" style="width:16px;height:16px">
                    <span style="font-size:13px;font-weight:600">Gruppenachse</span>
                </label>
                <div id="abb-gruppe-hinweis"
                     style="display:none;font-size:11px;color:#92400e;background:#fef3c7;
                            border-radius:4px;padding:4px 8px;margin-top:4px">
                    Gruppenachse-Flag kann nicht entfernt werden solange Unterachsen vorhanden sind.
                </div>
            </div>

            <div id="abb-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px"></div>
        </div>
        <div class="modal-footer" style="display:flex;gap:8px;align-items:center">
            <button id="abb-del-btn" onclick="achseBBLoeschen()"
                    class="btn btn-danger btn-sm" style="margin-right:auto">Löschen</button>
            <button onclick="achseBBSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="abb-save-btn" onclick="achseBBSpeichern()" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </div>
</div>

<script>window.ACHSEN_NAMEN = <?= $achsenNamenJson ?>;</script>
<script src="/mealana/js/achsen_zuweisen.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
