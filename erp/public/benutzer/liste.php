<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/benutzer/BenutzerService.php';

$service  = new BenutzerService();
$benutzer = $service->getAll();
$rollen   = $service->getAlleRollen();

$pageTitle        = 'Benutzer';
$activeModule     = 'benutzer';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neuer Benutzer</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <table class="erp-table">
        <thead>
            <tr>
                <th>NAME</th>
                <th>BENUTZERNAME</th>
                <th style="width:140px">ROLLE</th>
                <th style="width:80px">STATUS</th>
                <th style="width:120px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($benutzer)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-text-muted);padding:32px">Keine Benutzer gefunden.</td></tr>
        <?php endif; ?>
        <?php foreach ($benutzer as $b): ?>
            <tr <?= $b['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td>
                    <strong><?= htmlspecialchars($b['formularname']) ?></strong>
                    <?php if ($b['email']): ?>
                        <div style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($b['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>@<?= htmlspecialchars($b['username']) ?></td>
                <td><?= htmlspecialchars($b['rolle_name'] ?? '–') ?></td>
                <td>
                    <?php if ($b['aktiv']): ?>
                        <span class="chip chip-aktiv">Aktiv</span>
                    <?php else: ?>
                        <span class="chip">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <button class="btn btn-secondary btn-sm"
                            onclick="modalBearbeitenOeffnen(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)"
                            title="Bearbeiten">✎</button>
                    <button class="btn btn-secondary btn-sm"
                            onclick="linkErneutSenden(<?= $b['id'] ?>)"
                            title="Passwort-Link erneut senden">✉️</button>
                    <?php if ($b['aktiv']): ?>
                        <button class="btn btn-secondary btn-sm"
                                onclick="statusDeaktivieren(<?= $b['id'] ?>, '<?= htmlspecialchars($b['formularname'], ENT_QUOTES) ?>')"
                                title="Deaktivieren">🗑️</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ================================================================
     MODAL: Benutzer Neu
================================================================ -->
<div id="modal-neu" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:520px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Neuer Benutzer</h3>
            <button onclick="modalNeuSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-neu" onsubmit="benutzerSpeichern(event)">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <div style="display:flex;gap:10px">
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Vorname</label>
                        <input type="text" name="vorname" id="vorname" class="erp-input" style="width:100%;box-sizing:border-box" oninput="autoVorschlaege()">
                    </div>
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Nachname</label>
                        <input type="text" name="nachname" id="nachname" class="erp-input" style="width:100%;box-sizing:border-box" oninput="autoVorschlaege()">
                    </div>
                </div>
                <div>
                    <label class="erp-label">Formularname *</label>
                    <input type="text" name="formularname" id="formularname" class="erp-input" style="width:100%;box-sizing:border-box" required oninput="markiereManuellBearbeitet('formularname')">
                    <small style="color:var(--color-text-muted);font-size:11px">Erscheint in der Shell und auf Dokumenten</small>
                </div>
                <div>
                    <label class="erp-label">Benutzername *</label>
                    <input type="text" name="username" id="username" class="erp-input" style="width:100%;box-sizing:border-box" required oninput="markiereManuellBearbeitet('username')">
                </div>
                <div>
                    <label class="erp-label">E-Mail *</label>
                    <input type="email" name="email" id="email" class="erp-input" style="width:100%;box-sizing:border-box" required>
                </div>
                <div>
                    <label class="erp-label">Rolle *</label>
                    <select name="rolle_id" id="rolle_id" class="erp-select" required>
                        <?php foreach ($rollen as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars(ucfirst($r['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="border-top:1px solid #eee;padding-top:12px">
                    <label class="erp-label">Passwort</label>
                    <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                            <input type="radio" name="passwort_modus" value="link" checked onchange="passwortModusToggle()">
                            Link per E-Mail senden (empfohlen)
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                            <input type="radio" name="passwort_modus" value="direkt" onchange="passwortModusToggle()">
                            Direkt setzen
                        </label>
                    </div>
                    <div id="passwort-direkt-felder" style="display:none;margin-top:10px;display:flex;gap:10px">
                        <div style="flex:1;min-width:0">
                            <label class="erp-label">Passwort</label>
                            <input type="password" name="passwort" id="passwort" class="erp-input" style="width:100%;box-sizing:border-box" autocomplete="new-password">
                        </div>
                        <div style="flex:1;min-width:0">
                            <label class="erp-label">Wiederholung</label>
                            <input type="password" name="passwort_wdh" id="passwort_wdh" class="erp-input" style="width:100%;box-sizing:border-box" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalNeuSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Anlegen</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Benutzer Bearbeiten
================================================================ -->
<div id="modal-bearbeiten" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;overflow-y:auto">
    <div style="background:#fff;max-width:480px;width:calc(100% - 32px);margin:40px auto;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.2);box-sizing:border-box">
        <div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px">Benutzer bearbeiten</h3>
            <button onclick="modalBearbeitenSchliessen()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666">×</button>
        </div>
        <form id="form-bearbeiten" onsubmit="benutzerAktualisieren(event)">
            <input type="hidden" name="id" id="edit-id">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="erp-label">Benutzername</label>
                    <input type="text" id="edit-username" class="erp-input" style="width:100%;box-sizing:border-box" disabled>
                </div>
                <div style="display:flex;gap:10px">
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Vorname</label>
                        <input type="text" name="vorname" id="edit-vorname" class="erp-input" style="width:100%;box-sizing:border-box">
                    </div>
                    <div style="flex:1;min-width:0">
                        <label class="erp-label">Nachname</label>
                        <input type="text" name="nachname" id="edit-nachname" class="erp-input" style="width:100%;box-sizing:border-box">
                    </div>
                </div>
                <div>
                    <label class="erp-label">Formularname *</label>
                    <input type="text" name="formularname" id="edit-formularname" class="erp-input" style="width:100%;box-sizing:border-box" required>
                </div>
                <div>
                    <label class="erp-label">E-Mail *</label>
                    <input type="email" name="email" id="edit-email" class="erp-input" style="width:100%;box-sizing:border-box" required>
                </div>
                <div>
                    <label class="erp-label">Rolle *</label>
                    <select name="rolle_id" id="edit-rolle_id" class="erp-select" required>
                        <?php foreach ($rollen as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars(ucfirst($r['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="aktiv" id="edit-aktiv" value="1">
                    <label for="edit-aktiv" style="cursor:pointer;font-size:13px">Aktiv</label>
                </div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid #e0e0e0;display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="modalBearbeitenSchliessen()">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<div id="banner" style="display:none;position:fixed;top:16px;right:16px;z-index:2000;padding:10px 18px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2)"></div>

<script src="<?= BASE_PATH ?>/js/benutzer_liste.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
