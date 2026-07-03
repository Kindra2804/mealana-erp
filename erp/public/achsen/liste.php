<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service = new AchsenService();
$achsen  = $service->findAll();

$kategorienBaum = (new ArtikelService())->getKategorienBaum();

$flash        = $_SESSION['erfolg'] ?? null;
$flashFehler  = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle    = 'Variantenachsen';
$activeModule = 'artikel';

$actionBarContent = '<button onclick="achseNeuOeffnen()" class="btn btn-primary btn-sm">+ Neue Achse</button>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($flash): ?>
<div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#155724;font-size:13px">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>
<?php if ($flashFehler): ?>
<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:8px 12px;margin-bottom:var(--space-md);color:#721c24;font-size:13px">
    <?= is_array($flashFehler) ? implode(', ', array_map('htmlspecialchars', $flashFehler)) : htmlspecialchars($flashFehler) ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-md)">
        <h3 style="margin:0">Achsen</h3>
        <button onclick="achseNeuOeffnen()" class="btn btn-primary btn-sm">+ Neue Achse</button>
    </div>

    <?php if (empty($achsen)): ?>
        <p style="color:var(--color-text-muted)">Noch keine Achsen angelegt.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Darstellung</th>
                    <th>Abhängig von</th>
                    <th style="width:72px;text-align:center">Sort.</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody>
                <?php $n = count($achsen); foreach ($achsen as $i => $a): ?>
                <tr class="artikel-zeile">
                    <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
                    <td style="font-family:monospace;font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($a['code']) ?></td>
                    <td style="font-size:12px">
                        <span style="background:#EDF2F7;color:#4A5568;border-radius:10px;padding:2px 8px;font-size:11px">
                            <?= htmlspecialchars($a['darstellungsform']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--color-text-muted)">
                        <?php if ($a['abhaengig_von_name']): ?>
                            <span style="background:#ede9fe;color:#5b21b6;border-radius:10px;padding:2px 8px;font-size:11px">
                                <?= htmlspecialchars($a['abhaengig_von_name']) ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        <?php if ($a['ist_gruppe']): ?>
                            <span style="background:#fef3c7;color:#92400e;border-radius:10px;padding:2px 8px;font-size:11px;margin-left:4px">Gruppe</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap">
                        <?php if ($i > 0): ?>
                            <button onclick="achseSortieren(<?= $a['id'] ?>, 'hoch')"
                                    class="btn btn-secondary btn-xs" title="Nach oben">▲</button>
                        <?php endif; ?>
                        <?php if ($i < $n - 1): ?>
                            <button onclick="achseSortieren(<?= $a['id'] ?>, 'runter')"
                                    class="btn btn-secondary btn-xs" title="Nach unten">▼</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-aktionen">
                            <button onclick="achseBearbeitenOeffnen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>, <?= htmlspecialchars(json_encode($a['code'])) ?>, <?= htmlspecialchars(json_encode($a['darstellungsform'])) ?>, <?= (int)$a['ist_gruppe'] ?>, <?= (int)$a['sort_order'] ?>, <?= $a['abhaengig_von_achse_id'] ?? 'null' ?>)"
                                    class="btn btn-secondary btn-xs">Bearb.</button>
                            <?php if ($a['in_use']): ?>
                                <span title="Achse ist Artikeln zugewiesen – kann nicht gelöscht werden"
                                      style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:24px;border-radius:4px;background:#f1f5f9;color:#94a3b8;font-size:13px;cursor:default">🔒</span>
                            <?php else: ?>
                                <button onclick="achseLoeschen(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['name'])) ?>)"
                                        class="btn btn-danger btn-xs">Löschen</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Bearbeiten/Neu-Modal -->
<div id="edit-modal" class="modal-backdrop">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <span id="edit-modal-titel">Neue Achse</span>
            <button onclick="editSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit-id" value="0">

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                    Name <span style="color:var(--color-danger)">*</span>
                </label>
                <input type="text" id="edit-name" class="erp-input" style="width:100%" placeholder="z.B. Farbe">
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                    Code <span style="color:var(--color-danger)">*</span>
                </label>
                <input type="text" id="edit-code" class="erp-input" style="width:100%;font-family:monospace" placeholder="z.B. farbe">
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Kleinbuchstaben, kein Leerzeichen. Wird automatisch bereinigt.</div>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Darstellungsform</label>
                <select id="edit-darstellung" class="erp-select" style="width:100%">
                    <option value="swatches">swatches</option>
                    <option value="dropdown">dropdown</option>
                    <option value="radiobutton">radiobutton</option>
                    <option value="freitext">freitext</option>
                    <option value="pflichtfreitext">pflicht-freitext</option>
                </select>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="edit-ist-gruppe" style="width:16px;height:16px">
                    <span style="font-size:13px;font-weight:600">Gruppenachse</span>
                </label>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px;margin-left:24px">
                    Kann Unterachsen enthalten (und trotzdem eigene Werte haben)
                </div>
            </div>

            <div style="margin-bottom:var(--space-md)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Abhängig von Achse (= Sub-Achse von)</label>
                <select id="edit-abhaengig" class="erp-select" style="width:100%">
                    <option value="">— keine Abhängigkeit —</option>
                    <?php foreach ($achsen as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">
                    Werte dieser Achse werden pro Wert der Eltern-Achse gefiltert (z.B. Farbe abhängig von Typ)
                </div>
            </div>

            <div style="margin-bottom:var(--space-sm)">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Reihenfolge</label>
                <input type="number" id="edit-sort" class="erp-input" style="width:80px" min="0" step="1" value="0">
            </div>

            <div id="edit-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:var(--space-sm)"></div>
        </div>
        <div class="modal-footer">
            <button onclick="editSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="edit-btn" onclick="editAbsenden()" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </div>
</div>

<!-- Löschen-Modal -->
<div id="del-modal" class="modal-backdrop">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span>Achse löschen</span>
            <button onclick="delSchliessen()" class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <p>Achse <strong id="del-name"></strong> wirklich löschen?</p>
            <p style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
                Eine Achse kann nur gelöscht werden wenn sie keinem Artikel zugewiesen ist.
            </p>
            <div id="del-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>
        </div>
        <div class="modal-footer">
            <button onclick="delSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
            <button id="del-btn" onclick="delBestaetigt()" class="btn btn-danger btn-sm">Löschen</button>
        </div>
    </div>
</div>

<script src="<?= BASE_PATH ?>/js/achsen_liste.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
