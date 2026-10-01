<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db     = Database::getInstance();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$konten = $db->query("SELECT id, kontonummer, name, typ, aktiv FROM kontenplan ORDER BY kontonummer")->fetchAll();

// Wo wird welches Konto verwendet? (Warengruppen über Kontonummer, Zahlungsarten und
// Steuerklassen über Konto-ID) -- damit sichtbar ist, was ein Umnummerieren/Deaktivieren betrifft.
$verwendung = [];
foreach ($db->query("SELECT konto_nr, name FROM artikel_gruppen WHERE aktiv = 1 ORDER BY name") as $r) {
    $verwendung['nr:' . $r['konto_nr']][] = 'Warengruppe ' . $r['name'];
}
foreach ($db->query("SELECT konto_id, zahlungsart FROM zahlungsart_konten WHERE konto_id IS NOT NULL ORDER BY zahlungsart") as $r) {
    $verwendung['id:' . $r['konto_id']][] = 'Zahlungsart ' . $r['zahlungsart'];
}
foreach ($db->query("SELECT sk.steuer_konto_id, s.name FROM steuerklassen_konten sk JOIN steuerklassen s ON s.id = sk.steuerklasse_id WHERE sk.steuer_konto_id IS NOT NULL ORDER BY s.name") as $r) {
    $verwendung['id:' . $r['steuer_konto_id']][] = 'Steuerklasse ' . $r['name'];
}

// Warengruppen, deren Kontonummer im Kontenplan fehlt (Export hätte ein unbekanntes Konto)
$kontoNummern = array_column($konten, 'kontonummer');
$fehlendeKonten = $db->query("SELECT name, konto_nr FROM artikel_gruppen WHERE aktiv = 1 AND konto_nr IS NOT NULL AND konto_nr <> '' ORDER BY konto_nr")->fetchAll();
$fehlendeKonten = array_filter($fehlendeKonten, fn($g) => !in_array($g['konto_nr'], $kontoNummern, true));

$typLabel = [
    'erloes' => 'Erlös', 'aufwand' => 'Aufwand', 'steuer' => 'Steuer',
    'bank' => 'Bank', 'kasse' => 'Kasse', 'verbindlichkeit' => 'Verbindlichkeit',
];

$pageTitle        = 'Kontenplan';
$activeModule     = 'buchhaltung';
$actionBarContent = '<button onclick="kontoNeu()" class="btn btn-primary btn-sm">+ Neues Konto</button>';
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

<?php if ($fehlendeKonten): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px">
    <strong>Warengruppen mit Konto, das im Kontenplan fehlt:</strong>
    <?php foreach ($fehlendeKonten as $g): ?>
        <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['konto_nr']) ?>)<?= $g !== end($fehlendeKonten) ? ',' : '' ?>
    <?php endforeach; ?>
    — bitte hier anlegen oder in der Warengruppe korrigieren.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Kontenplan — Basis für Kontierung und DATEV-Export</div>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:100px">Kontonr.</th>
                <th>Name</th>
                <th style="width:120px">Typ</th>
                <th>Verwendet in</th>
                <th style="width:70px;text-align:center">Aktiv</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($konten as $k): ?>
            <tr style="<?= $k['aktiv'] ? '' : 'opacity:.5' ?>">
                <td><code style="font-size:12px;color:var(--color-nav)"><?= htmlspecialchars($k['kontonummer']) ?></code></td>
                <td><?= htmlspecialchars($k['name']) ?></td>
                <td><span class="chip"><?= $typLabel[$k['typ']] ?? htmlspecialchars($k['typ']) ?></span></td>
                <?php $verw = array_merge($verwendung['nr:' . $k['kontonummer']] ?? [], $verwendung['id:' . $k['id']] ?? []); ?>
                <td style="font-size:12px;color:var(--color-text-muted)">
                    <?= $verw ? htmlspecialchars(implode(' · ', $verw)) : '—' ?>
                    <?php if ($verw && !$k['aktiv']): ?>
                        <span style="color:var(--color-danger)" title="Inaktives Konto wird noch verwendet">● inaktiv, aber zugeordnet</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?= $k['aktiv'] ? '<span style="color:#16a34a">✓</span>' : '<span style="color:#dc2626">✗</span>' ?>
                </td>
                <td style="text-align:right">
                    <button onclick="kontoBearbeiten(<?= htmlspecialchars(json_encode($k)) ?>)"
                            class="btn btn-secondary btn-sm">Bearbeiten</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Neues/Bearbeiten Konto -->
<div id="konto-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;width:400px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px;color:var(--color-nav)" id="modal-titel">Neues Konto</div>

        <form id="konto-form" method="post">
            <input type="hidden" name="id" id="f-id">

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Kontonummer *</label>
                <input type="text" name="kontonummer" id="f-kontonummer" class="erp-input" style="width:100%"
                       placeholder="z.B. 4080" maxlength="10" required>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px">Muss eindeutig sein</div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="f-name" class="erp-input" style="width:100%"
                       placeholder="z.B. Erlöse Sonderposten" maxlength="100" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group">
                    <label class="form-label">Typ *</label>
                    <select name="typ" id="f-typ" class="erp-input" style="width:100%" required>
                        <option value="erloes">Erlös</option>
                        <option value="aufwand">Aufwand</option>
                        <option value="steuer">Steuer</option>
                        <option value="bank">Bank</option>
                        <option value="kasse">Kasse</option>
                        <option value="verbindlichkeit">Verbindlichkeit (z.B. Gutschein-Anzahlung)</option>
                    </select>
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

            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" onclick="modalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_PATH ?>/js/buchhaltung_kontenplan.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
