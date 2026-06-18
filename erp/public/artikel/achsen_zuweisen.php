<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$artikelId = (int)($_GET['artikel_id'] ?? 0);
if ($artikelId <= 0) { header('Location: liste.php'); exit; }

$varService     = new VariantenService();
$achsService    = new AchsenService();
$artikelService = new ArtikelService();

$artikel         = $artikelService->findById($artikelId);
$alleAchsen      = $achsService->findAll();
$zugewieseneIds  = array_column($varService->findAchsenByArtikelId($artikelId), 'achse_id');
$vorhandeneWerte = $varService->findWerteByArtikelId($artikelId);

$achsenById = [];
foreach ($alleAchsen as $a) { $achsenById[$a['id']] = $a; }

// Welche Achsen-IDs sind "Eltern" (andere Achsen hängen von ihnen ab)
$elternAchsenIds = [];
foreach ($alleAchsen as $a) {
    if ($a['abhaengig_von_achse_id']) {
        $elternAchsenIds[(int)$a['abhaengig_von_achse_id']] = true;
    }
}

// Werte nach Achse gruppieren
$werteProAchse = [];
foreach ($vorhandeneWerte as $w) {
    $werteProAchse[$w['achse_id']][] = $w;
}

// Kind-Werte nach Eltern-Wert-Text gruppieren
// $kindWerteNachGruppe[kindAchseId]['Uni'] = [{wert-rows}]
$kindWerteNachGruppe = [];
foreach ($vorhandeneWerte as $w) {
    $achse = $achsenById[$w['achse_id']] ?? null;
    if (!$achse || !$achse['abhaengig_von_achse_id']) continue;
    $elternAchseId = $achse['abhaengig_von_achse_id'];
    if ($w['bedingungs_wert_id']) {
        foreach ($werteProAchse[$elternAchseId] ?? [] as $ew) {
            if ($ew['id'] == $w['bedingungs_wert_id']) {
                $kindWerteNachGruppe[$w['achse_id']][$ew['wert']][] = $w;
                break;
            }
        }
    } else {
        $kindWerteNachGruppe[$w['achse_id']]['__keine__'][] = $w;
    }
}

// Baum
$roots  = [];
$kinder = [];
foreach ($alleAchsen as $a) {
    $pid = $a['abhaengig_von_achse_id'];
    if ($pid && isset($achsenById[$pid])) { $kinder[$pid][] = $a; }
    else { $roots[] = $a; }
}

$flash       = $_SESSION['erfolg'] ?? null;
$flashFehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle        = 'Achsen & Werte';
$activeModule     = 'artikel';
$actionBarContent = '<a href="detail.php?id=' . $artikelId . '" class="btn btn-secondary btn-sm">← Zurück zum Artikel</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#155724;font-size:13px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if (!empty($flashFehler)): ?>
<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#721c24;font-size:13px">
    <?= is_array($flashFehler) ? implode('<br>', array_map('htmlspecialchars', $flashFehler)) : htmlspecialchars($flashFehler) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--space-md)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em">Artikel</div>
            <div style="font-weight:600"><?= htmlspecialchars($artikel['name'] ?? '') ?></div>
            <div style="font-size:12px;color:var(--color-text-muted)">
                <?= htmlspecialchars($artikel['artikelnummer'] ?? '') ?> &nbsp;·&nbsp;
                Fehlende Achse? <a href="/mealana/achsen/liste.php" style="color:var(--color-primary)">Achsen verwalten ↗</a>
            </div>
        </div>
        <a href="detail.php?id=<?= $artikelId ?>" class="btn btn-secondary btn-sm">← Zum Artikel</a>
    </div>
</div>

<form action="achsen_speichern.php" method="POST">
    <input type="hidden" name="artikel_id" value="<?= $artikelId ?>">

    <?php
    $rendered = [];

    function renderAchse(
        array  $achse,
        array  $kinder,
        array  $zugewieseneIds,
        array  $werteProAchse,
        array  $kindWerteNachGruppe,
        array  $elternAchsenIds,
        array  $achsenById,
        bool   $istKind,
        ?int   $elternId,
        array  &$rendered
    ): void {
        $id          = $achse['id'];
        $istChecked  = in_array($id, $zugewieseneIds);
        $istDisabled = $istKind && !in_array($elternId, $zugewieseneIds);
        $istEltern   = isset($elternAchsenIds[$id]);
        $hatKinder   = !empty($kinder[$id]);
        $rendered[]  = $id;
        ?>
        <div id="achse-block-<?= $id ?>"
             style="<?= $istKind ? 'margin-left:28px;padding-left:16px;border-left:2px solid var(--color-border);' : '' ?>margin-bottom:var(--space-sm)">

            <div style="display:flex;align-items:center;gap:10px;padding:6px 0">
                <label style="display:flex;align-items:center;gap:8px;cursor:<?= $istDisabled ? 'not-allowed' : 'pointer' ?>;flex:1">
                    <input type="checkbox"
                           name="achsen[]"
                           value="<?= $id ?>"
                           id="achse-cb-<?= $id ?>"
                           <?= $istChecked  ? 'checked'  : '' ?>
                           <?= $istDisabled ? 'disabled' : '' ?>
                           <?php if ($istKind): ?>data-parent-id="<?= $elternId ?>"<?php endif; ?>
                           <?php if ($hatKinder): ?>data-has-kinder="1"<?php endif; ?>
                           onchange="achseGeaendert(this)"
                           style="width:16px;height:16px;flex-shrink:0">
                    <span style="font-weight:600;font-size:14px;<?= $istDisabled ? 'color:var(--color-text-muted)' : '' ?>">
                        <?= htmlspecialchars($achse['name']) ?>
                    </span>
                    <span style="font-size:11px;background:#EDF2F7;color:#4A5568;border-radius:8px;padding:1px 7px">
                        <?= htmlspecialchars($achse['darstellungsform']) ?>
                    </span>
                    <?php if ($istKind): ?>
                        <span style="font-size:11px;background:#ede9fe;color:#7c3aed;border-radius:8px;padding:1px 7px">abhängig</span>
                    <?php endif; ?>
                    <?php if ($istEltern): ?>
                        <span style="font-size:11px;background:#fef3c7;color:#92400e;border-radius:8px;padding:1px 7px">Gruppenachse</span>
                    <?php endif; ?>
                </label>
            </div>

            <div id="werte-block-<?= $id ?>"
                 style="<?= !$istChecked ? 'display:none;' : '' ?>padding:10px 12px 6px;background:#f8fafc;border-radius:4px;margin-bottom:4px">

                <?php if ($achse['abhaengig_von_achse_id'] && isset($achsenById[$achse['abhaengig_von_achse_id']])): ?>
                    <?php
                    $elternAchse           = $achsenById[$achse['abhaengig_von_achse_id']];
                    $elternAchseId         = $elternAchse['id'];
                    $vorhandeneElternWerte = $werteProAchse[$elternAchseId] ?? [];
                    ?>
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">
                        Werte für <?= htmlspecialchars($achse['name']) ?>
                        <span style="font-weight:400;color:#7c3aed"> · gruppiert nach <?= htmlspecialchars($elternAchse['name']) ?></span>
                    </div>

                    <?php if (empty($vorhandeneElternWerte)): ?>
                        <div style="font-size:12px;color:#92400e;background:#fef3c7;border-radius:4px;padding:6px 10px;margin-bottom:8px">
                            Sobald Werte für „<?= htmlspecialchars($elternAchse['name']) ?>" gespeichert sind, werden hier Gruppen angezeigt. Werte können jetzt schon eingegeben werden:
                        </div>
                        <!-- Input auch ohne Eltern-Werte: Werte kommen als "ohne Zuordnung" rein -->
                        <?php $ohneZVorab = $kindWerteNachGruppe[$id]['__keine__'] ?? []; ?>
                        <div style="border:1px dashed #d1d5db;border-radius:4px;padding:8px;background:#fafaf8">
                            <div class="wert-chips" id="chips-root-<?= $id ?>"
                                 style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:28px;margin-bottom:6px">
                                <?php foreach ($ohneZVorab as $gw): ?>
                                    <span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">
                                        <?= htmlspecialchars($gw['wert']) ?>
                                        <input type="hidden" name="werte[<?= $id ?>][<?= $gw['id'] ?>][wert]" value="<?= htmlspecialchars($gw['wert']) ?>">
                                        <button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="text" class="erp-input" id="wert-input-<?= $id ?>"
                                       placeholder="Wert + Enter" style="flex:1;max-width:220px"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();wertRoot(<?= $id ?>)}">
                                <button type="button" onclick="wertRoot(<?= $id ?>)" class="btn btn-secondary btn-xs">+ Wert hinzufügen</button>
                            </div>
                            <div id="neue-eltern-werte-<?= $id ?>"></div>
                        </div>
                    <?php else: ?>
                        <div id="gruppen-container-<?= $id ?>" data-eltern-achse-id="<?= $elternAchseId ?>">

                            <?php foreach ($vorhandeneElternWerte as $ew): ?>
                                <?php
                                $gName  = $ew['wert'];
                                $gKey   = 'ex_' . $ew['id'];
                                $gWerte = $kindWerteNachGruppe[$id][$gName] ?? [];
                                ?>
                                <div class="unterachse-gruppe"
                                     id="gruppe-<?= $id ?>-<?= $gKey ?>"
                                     data-gruppe-name="<?= htmlspecialchars($gName) ?>"
                                     data-eltern-achse-id="<?= $elternAchseId ?>"
                                     style="border:1px solid var(--color-border);border-radius:4px;padding:8px;margin-bottom:8px;background:#fff">
                                    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:6px">
                                        <?= htmlspecialchars($gName) ?>
                                        <span style="font-weight:400;font-size:11px;color:var(--color-text-muted)">(<?= htmlspecialchars($elternAchse['name']) ?>)</span>
                                    </div>
                                    <div class="wert-chips"
                                         id="chips-<?= $id ?>-<?= $gKey ?>"
                                         style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:28px;margin-bottom:6px">
                                        <?php foreach ($gWerte as $gw): ?>
                                            <span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">
                                                <?= htmlspecialchars($gw['wert']) ?>
                                                <input type="hidden" name="werte[<?= $id ?>][<?= $gw['id'] ?>][wert]" value="<?= htmlspecialchars($gw['wert']) ?>">
                                                <input type="hidden" name="werte[<?= $id ?>][<?= $gw['id'] ?>][bedingungs_wert_name]" value="<?= htmlspecialchars($gName) ?>">
                                                <input type="hidden" name="werte[<?= $id ?>][<?= $gw['id'] ?>][bedingungs_achse_id]" value="<?= $elternAchseId ?>">
                                                <button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <input type="text" class="erp-input" placeholder="Wert + Enter" style="flex:1;max-width:220px"
                                               onkeydown="if(event.key==='Enter'){event.preventDefault();wertInGruppe(<?= $id ?>,this,'<?= addslashes($gName) ?>',<?= $elternAchseId ?>)}">
                                        <button type="button" onclick="wertInGruppe(<?= $id ?>,this.previousElementSibling,'<?= addslashes($gName) ?>',<?= $elternAchseId ?>)" class="btn btn-secondary btn-xs">+ Wert</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php $ohneZ = $kindWerteNachGruppe[$id]['__keine__'] ?? []; ?>
                            <?php if (!empty($ohneZ)): ?>
                                <div class="unterachse-gruppe" data-gruppe-name=""
                                     style="border:1px dashed #d1d5db;border-radius:4px;padding:8px;margin-bottom:8px;background:#fafaf8">
                                    <div style="font-size:12px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px">Ohne Zuordnung</div>
                                    <div class="wert-chips" style="display:flex;flex-wrap:wrap;gap:6px">
                                        <?php foreach ($ohneZ as $gw): ?>
                                            <span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">
                                                <?= htmlspecialchars($gw['wert']) ?>
                                                <input type="hidden" name="werte[<?= $id ?>][<?= $gw['id'] ?>][wert]" value="<?= htmlspecialchars($gw['wert']) ?>">
                                                <button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Neue Gruppen (via JS) kommen hier rein -->
                            <div id="neue-gruppen-<?= $id ?>"></div>

                        </div>

                        <div id="unterachse-neu-<?= $id ?>">
                            <div id="unterachse-btn-<?= $id ?>">
                                <button type="button" onclick="unterachseZeigen(<?= $id ?>,<?= $elternAchseId ?>)"
                                        class="btn btn-secondary btn-xs">+ Unterachse (<?= htmlspecialchars($elternAchse['name']) ?>) hinzufügen</button>
                            </div>
                            <div id="unterachse-form-<?= $id ?>" style="display:none;gap:6px;align-items:center;flex-wrap:wrap">
                                <input type="text" id="unterachse-input-<?= $id ?>" class="erp-input"
                                       placeholder="Name z.B. LongPrint" style="max-width:200px"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();unterachseSpeichern(<?= $id ?>,<?= $elternAchseId ?>)}"
                                       onkeyup="if(event.key==='Escape')unterachseSchliessen(<?= $id ?>)">
                                <button type="button" onclick="unterachseSpeichern(<?= $id ?>,<?= $elternAchseId ?>)" class="btn btn-primary btn-xs">Hinzufügen</button>
                                <button type="button" onclick="unterachseSchliessen(<?= $id ?>)" class="btn btn-secondary btn-xs">Abbrechen</button>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php $existingWerte = $werteProAchse[$id] ?? []; ?>
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">
                        Werte für <?= htmlspecialchars($achse['name']) ?>
                        <?php if ($istEltern): ?>
                            <span style="font-weight:400;color:#92400e"> · dienen als Gruppen-Header für abhängige Achsen</span>
                        <?php endif; ?>
                    </div>

                    <div class="wert-chips" id="chips-root-<?= $id ?>"
                         style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:28px;margin-bottom:6px">
                        <?php foreach ($existingWerte as $ew): ?>
                            <span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">
                                <?= htmlspecialchars($ew['wert']) ?>
                                <input type="hidden" name="werte[<?= $id ?>][<?= $ew['id'] ?>][wert]" value="<?= htmlspecialchars($ew['wert']) ?>">
                                <button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="text" class="erp-input" id="wert-input-<?= $id ?>"
                               placeholder="Wert + Enter" style="flex:1;max-width:220px"
                               onkeydown="if(event.key==='Enter'){event.preventDefault();wertRoot(<?= $id ?>)}">
                        <button type="button" onclick="wertRoot(<?= $id ?>)" class="btn btn-secondary btn-xs">+ Wert hinzufügen</button>
                    </div>

                    <div id="neue-eltern-werte-<?= $id ?>"></div>
                <?php endif; ?>

            </div><!-- /werte-block -->

            <?php if (!empty($kinder[$id])): ?>
                <div id="kinder-von-<?= $id ?>">
                    <?php foreach ($kinder[$id] as $kind): ?>
                        <?php renderAchse($kind, $kinder, $zugewieseneIds, $werteProAchse, $kindWerteNachGruppe, $elternAchsenIds, $achsenById, true, $id, $rendered); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    foreach ($roots as $root) {
        echo '<div style="border-bottom:1px solid var(--color-border);padding-bottom:var(--space-sm);margin-bottom:var(--space-sm)">';
        renderAchse($root, $kinder, $zugewieseneIds, $werteProAchse, $kindWerteNachGruppe, $elternAchsenIds, $achsenById, false, null, $rendered);
        echo '</div>';
    }
    foreach ($alleAchsen as $a) {
        if (!in_array($a['id'], $rendered)) {
            renderAchse($a, $kinder, $zugewieseneIds, $werteProAchse, $kindWerteNachGruppe, $elternAchsenIds, $achsenById, false, null, $rendered);
        }
    }
    ?>

    <div style="display:flex;gap:var(--space-sm);margin-top:var(--space-md)">
        <button type="submit" class="btn btn-primary btn-sm">Achsen &amp; Werte speichern</button>
        <a href="detail.php?id=<?= $artikelId ?>" class="btn btn-secondary btn-sm">Abbrechen</a>
    </div>
</form>

<script>
// Index-Zähler pro Achse (startet hoch genug um keine ID-Kollisionen zu haben)
var wIdx = {};
<?php foreach ($alleAchsen as $a): ?>
wIdx[<?= $a['id'] ?>] = <?= count($werteProAchse[$a['id']] ?? []) + 10000 ?>;
<?php endforeach; ?>

// ── Checkbox-Logik ─────────────────────────────────────────────────────────
function achseGeaendert(cb) {
    var id = parseInt(cb.value);
    var wb = document.getElementById('werte-block-' + id);
    if (wb) wb.style.display = cb.checked ? '' : 'none';

    if (cb.checked) {
        var pid = cb.getAttribute('data-parent-id');
        if (pid) {
            var pcb = document.getElementById('achse-cb-' + pid);
            if (pcb && !pcb.checked) { pcb.checked = true; achseGeaendert(pcb); }
            cb.disabled = false;
        }
        aktiviereKinder(id);
    } else {
        if (cb.getAttribute('data-has-kinder')) deaktiviereKinder(id);
    }
}

function aktiviereKinder(pid) {
    var b = document.getElementById('kinder-von-' + pid);
    if (b) b.querySelectorAll('[data-parent-id="' + pid + '"]').forEach(function(c) { c.disabled = false; });
}

function deaktiviereKinder(pid) {
    var b = document.getElementById('kinder-von-' + pid);
    if (!b) return;
    b.querySelectorAll('input[type=checkbox]').forEach(function(c) {
        c.checked = false; c.disabled = true;
        var wb = document.getElementById('werte-block-' + parseInt(c.value));
        if (wb) wb.style.display = 'none';
        if (c.getAttribute('data-has-kinder')) deaktiviereKinder(parseInt(c.value));
    });
}

// ── Chip entfernen ─────────────────────────────────────────────────────────
function chipEntfernen(btn) { btn.closest('.wert-chip').remove(); }

// ── Root-Achse: Wert hinzufügen ───────────────────────────────────────────
function wertRoot(achseId) {
    var inp  = document.getElementById('wert-input-' + achseId);
    var text = inp.value.trim();
    if (!text) return;
    var chips = document.getElementById('chips-root-' + achseId);
    var idx   = wIdx[achseId]++;
    chips.insertAdjacentHTML('beforeend',
        '<span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">' +
        escHtml(text) +
        '<input type="hidden" name="werte[' + achseId + '][' + idx + '][wert]" value="' + escAttr(text) + '">' +
        '<button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>' +
        '</span>'
    );
    inp.value = ''; inp.focus();
}

// ── Kind-Achse: Wert in Gruppe ────────────────────────────────────────────
function wertInGruppe(achseId, inputEl, gruppenName, elternAchseId) {
    var text = inputEl.value.trim();
    if (!text) return;

    // Chips-Container der richtigen Gruppe finden
    var container = document.getElementById('gruppen-container-' + achseId)
                 || document.getElementById('neue-gruppen-' + achseId);
    var chipsEl = null;
    document.querySelectorAll('#gruppen-container-' + achseId + ' .unterachse-gruppe, #neue-gruppen-' + achseId + ' .unterachse-gruppe').forEach(function(g) {
        if (g.getAttribute('data-gruppe-name') === gruppenName) {
            chipsEl = g.querySelector('.wert-chips');
        }
    });
    if (!chipsEl) return;

    var idx = wIdx[achseId]++;
    chipsEl.insertAdjacentHTML('beforeend',
        '<span class="wert-chip" style="display:inline-flex;align-items:center;gap:4px;background:#EDF2F7;border-radius:12px;padding:2px 10px;font-size:12px">' +
        escHtml(text) +
        '<input type="hidden" name="werte[' + achseId + '][' + idx + '][wert]" value="' + escAttr(text) + '">' +
        '<input type="hidden" name="werte[' + achseId + '][' + idx + '][bedingungs_wert_name]" value="' + escAttr(gruppenName) + '">' +
        '<input type="hidden" name="werte[' + achseId + '][' + idx + '][bedingungs_achse_id]" value="' + elternAchseId + '">' +
        '<button type="button" onclick="chipEntfernen(this)" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;font-size:12px;line-height:1">✕</button>' +
        '</span>'
    );
    inputEl.value = ''; inputEl.focus();
}

// ── Unterachse hinzufügen ─────────────────────────────────────────────────
function unterachseZeigen(kindId, elternId) {
    document.getElementById('unterachse-btn-' + kindId).style.display = 'none';
    var f = document.getElementById('unterachse-form-' + kindId);
    f.style.display = 'flex';
    document.getElementById('unterachse-input-' + kindId).focus();
}

function unterachseSchliessen(kindId) {
    document.getElementById('unterachse-btn-' + kindId).style.display = '';
    document.getElementById('unterachse-form-' + kindId).style.display = 'none';
    document.getElementById('unterachse-input-' + kindId).value = '';
}

function unterachseSpeichern(kindId, elternId) {
    var name = document.getElementById('unterachse-input-' + kindId).value.trim();
    if (!name) { document.getElementById('unterachse-input-' + kindId).focus(); return; }

    // 1. Eltern-Achse bekommt neuen Wert (hidden input in "neue-eltern-werte-ELTERNid")
    var elternCont = document.getElementById('neue-eltern-werte-' + elternId);
    if (elternCont) {
        var ei = document.createElement('input');
        ei.type = 'hidden';
        ei.name = 'werte[' + elternId + '][' + (wIdx[elternId]++) + '][wert]';
        ei.value = name;
        elternCont.appendChild(ei);
    }

    // 2. Neue Gruppe in Kind-Achse
    var neueGruppen = document.getElementById('neue-gruppen-' + kindId);
    if (!neueGruppen) return;

    var gId = 'new_' + Date.now();
    neueGruppen.insertAdjacentHTML('beforeend',
        '<div class="unterachse-gruppe" id="gruppe-' + kindId + '-' + gId + '"' +
        ' data-gruppe-name="' + escAttr(name) + '" data-eltern-achse-id="' + elternId + '"' +
        ' style="border:1px solid var(--color-border);border-radius:4px;padding:8px;margin-bottom:8px;background:#fff">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">' +
            '<span style="font-size:12px;font-weight:700;color:#374151">' + escHtml(name) + ' <span style="font-weight:400;font-size:11px;color:var(--color-text-muted)">(neu)</span></span>' +
            '<button type="button" onclick="gruppeEntfernen(this,' + kindId + ',' + elternId + ',\'' + escJs(name) + '\')" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:12px">✕ Gruppe</button>' +
        '</div>' +
        '<div class="wert-chips" id="chips-' + kindId + '-' + gId + '" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:28px;margin-bottom:6px"></div>' +
        '<div style="display:flex;gap:6px;align-items:center">' +
            '<input type="text" class="erp-input" placeholder="Wert + Enter" style="flex:1;max-width:220px"' +
            ' onkeydown="if(event.key===\'Enter\'){event.preventDefault();wertInGruppe(' + kindId + ',this,\'' + escJs(name) + '\',' + elternId + ')}">' +
            '<button type="button" onclick="wertInGruppe(' + kindId + ',this.previousElementSibling,\'' + escJs(name) + '\',' + elternId + ')" class="btn btn-secondary btn-xs">+ Wert</button>' +
        '</div>' +
        '</div>'
    );

    unterachseSchliessen(kindId);
}

function gruppeEntfernen(btn, kindId, elternId, name) {
    var ec = document.getElementById('neue-eltern-werte-' + elternId);
    if (ec) ec.querySelectorAll('input[type=hidden]').forEach(function(hi) { if (hi.value === name) hi.remove(); });
    btn.closest('.unterachse-gruppe').remove();
}

// ── Escape-Helfer ─────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escAttr(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function escJs(s) {
    return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
