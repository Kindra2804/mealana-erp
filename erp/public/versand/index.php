<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db   = Database::getInstance();
$rows = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
$s    = fn(string $key, string $fallback = '') => htmlspecialchars($rows[$key] ?? $fallback);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$versandklassen = $db->query("
    SELECT vk.id, vk.name, vk.code, vk.kuerzel, vk.preis_brutto, vk.sortierung,
           vk.artikel_gruppe_id,
           ag.konto_nr, ag.name AS gruppe_name
    FROM versandklassen vk
    LEFT JOIN artikel_gruppen ag ON ag.id = vk.artikel_gruppe_id
    ORDER BY vk.sortierung, vk.name
")->fetchAll();

$artikelGruppen = $db->query("
    SELECT id, konto_nr, name FROM artikel_gruppen WHERE aktiv = 1 ORDER BY sortierung, konto_nr
")->fetchAll();

$pageTitle        = 'Versand';
$activeModule     = 'versand';
$actionBarContent = '<span style="font-weight:600">🚚 Versand</span>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
    <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
        <?= htmlspecialchars($erfolg) ?>
    </div>
<?php endif; ?>
<?php if ($fehler): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
        <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start">

    <!-- ═══ PLC EINSTELLUNGEN ══════════════════════════════════════════════════════ -->
    <div>
        <form method="post" action="speichern.php">

            <div class="card" style="margin-bottom:16px">
                <div class="card-header">PLC / EasyPak – Österreichische Post</div>
                <div style="padding:16px">

                    <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:16px;line-height:1.6">
                        Das ERP erzeugt beim Versandabschluss eine XML-Datei in den Polling-Ordner.
                        Der <strong>Post Label Creator (PLC)</strong> liest diese Datei und druckt den Paketschein.
                        Absenderadresse und Post.at-Kundennummer werden im PLC selbst konfiguriert.
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label">Polling-Ordner (UNC-Pfad oder lokaler Pfad)</label>
                        <input type="text" name="plc_polling_ordner" class="erp-input" style="width:100%"
                            value="<?= $s('plc_polling_ordner') ?>"
                            placeholder="z.B. \\nsa310\mealana\EasyPak_Export\">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">
                            Muss für den Webserver (Apache) beschreibbar sein. Leer = EasyPak deaktiviert.
                        </div>
                    </div>

                    <hr style="border:none;border-top:1px solid var(--color-border);margin:16px 0">
                    <div style="font-size:12px;font-weight:600;margin-bottom:12px">Item-IDs (aus dem Post.at EasyPak-Vertrag)</div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="form-group">
                            <label class="form-label">AT – Paket Österreich</label>
                            <input type="text" name="plc_item_at" class="erp-input"
                                value="<?= $s('plc_item_at', '430101') ?>" placeholder="430101">
                        </div>
                        <div class="form-group">
                            <label class="form-label">AT Express – Paket EMS Österreich</label>
                            <input type="text" name="plc_item_at_express" class="erp-input"
                                value="<?= $s('plc_item_at_express', '430107') ?>" placeholder="430107">
                        </div>
                        <div class="form-group">
                            <label class="form-label">EU – Paket Premium International</label>
                            <input type="text" name="plc_item_eu" class="erp-input"
                                value="<?= $s('plc_item_eu', '430106') ?>" placeholder="430106">
                        </div>
                        <div class="form-group">
                            <label class="form-label">International (Non-EU)</label>
                            <input type="text" name="plc_item_international" class="erp-input"
                                value="<?= $s('plc_item_international', '430104') ?>" placeholder="430104">
                        </div>
                    </div>

                    <hr style="border:none;border-top:1px solid var(--color-border);margin:16px 0">
                    <div style="font-size:12px;font-weight:600;margin-bottom:12px">Bankverbindung (für Nachnahme)</div>
                    <div style="font-size:12px;color:var(--color-text-muted)">
                        Bankverbindung wird aus den
                        <a href="<?= BASE_PATH ?>/einstellungen/index.php?tab=firma" style="color:var(--color-nav)">Firmen-Einstellungen</a>
                        übernommen (IBAN, BIC, Bank).
                    </div>

                </div>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
            </div>

        </form>
    </div>



    <!-- ═══ RECHTE SPALTE: Schnellzugriff ════════════════════════════════════════ -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">Versand-Workflow</div>
            <div style="padding:16px">
                <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:14px;line-height:1.6">
                    Der eigentliche Versandworkflow (Scannen, EasyPak-Export, Versandmail) läuft im Packplatz.
                </div>
                <a href="<?= BASE_PATH ?>/packplatz/warenausgang/index.php" class="btn btn-primary" style="width:100%;text-align:center;margin-bottom:8px">
                    📦 Packplatz öffnen
                </a>
                <a href="<?= BASE_PATH ?>/auftraege/liste.php" class="btn btn-secondary" style="width:100%;text-align:center">
                    📋 Aufträge
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Versandarten – Picklisten-Freigabe</div>
            <div style="padding:12px 16px">
                <table style="width:100%;font-size:12px;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:4px 6px;color:var(--color-text-muted);border-bottom:1px solid var(--color-border)">Zahlungsart</th>
                            <th style="text-align:left;padding:4px 6px;color:var(--color-text-muted);border-bottom:1px solid var(--color-border)">Freigabe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (
                            [
                                ['Bar',        'bar',       'Sofort'],
                                ['Rechnung',   'rechnung',  'Sofort'],
                                ['Nachnahme',  'nachnahme', 'Sofort'],
                                ['Vorkasse',   'vorkasse',  'Nur bei bezahlt'],
                                ['PayPal',     'paypal',    'Nur bei bezahlt'],
                                ['Abholung',   '—',         'Sofort (Lieferart)'],
                            ] as [$label, $art, $regel]
                        ): ?>
                            <tr>
                                <td style="padding:5px 6px;border-bottom:1px solid var(--color-border)"><?= $label ?></td>
                                <td style="padding:5px 6px;border-bottom:1px solid var(--color-border);color:<?= $regel === 'Sofort' || $regel === 'Sofort (Lieferart)' ? '#059669' : '#d97706' ?>">
                                    <?= $regel ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<!-- ═══ VERSANDKLASSEN ══════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:16px">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>Versandklassen</span>
        <button onclick="vskNeu()" class="btn btn-primary btn-sm">+ Neue Klasse</button>
    </div>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:40px">Sort.</th>
                <th>Name</th>
                <th style="width:70px">Code</th>
                <th style="width:80px;text-align:right">Preis</th>
                <th style="width:180px">Artikelgruppe</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($versandklassen as $vk): ?>
                <tr>
                    <td style="color:var(--color-text-muted)"><?= (int)$vk['sortierung'] ?></td>
                    <td>
                        <?= htmlspecialchars($vk['name']) ?>
                        <?php if ($vk['kuerzel']): ?>
                            <span style="font-size:11px;color:var(--color-text-muted);margin-left:4px">(<?= htmlspecialchars($vk['kuerzel']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><code style="font-size:11px"><?= htmlspecialchars($vk['code'] ?? '') ?></code></td>
                    <td style="text-align:right">
                        <?= $vk['preis_brutto'] !== null ? '€ ' . number_format((float)$vk['preis_brutto'], 2, ',', '.') : '—' ?>
                    </td>
                    <td style="font-size:12px">
                        <?php if ($vk['artikel_gruppe_id']): ?>
                            <code style="color:var(--color-nav)"><?= htmlspecialchars($vk['konto_nr']) ?></code>
                            <?= htmlspecialchars($vk['gruppe_name']) ?>
                        <?php else: ?>
                            <span style="color:#dc2626">⚠ nicht zugeordnet</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right">
                        <button onclick="vskBearbeiten(<?= htmlspecialchars(json_encode($vk)) ?>)"
                            class="btn btn-secondary btn-sm">Bearbeiten</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Versandklasse Neu/Bearbeiten -->
<div id="vsk-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;width:440px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px;color:var(--color-nav)" id="vsk-modal-titel">Neue Versandklasse</div>

        <form id="vsk-form" method="post" action="<?= BASE_PATH ?>/versand/versandklasse_speichern.php">
            <input type="hidden" name="id" id="vsk-id">

            <div class="form-group" style="margin-bottom:10px">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="vsk-name" class="erp-input" style="width:100%" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div class="form-group">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" id="vsk-code" class="erp-input" style="width:100%" maxlength="50" placeholder="z.B. SAT">
                </div>
                <div class="form-group">
                    <label class="form-label">Kürzel</label>
                    <input type="text" name="kuerzel" id="vsk-kuerzel" class="erp-input" style="width:100%" maxlength="10" placeholder="z.B. Std. AT">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div class="form-group">
                    <label class="form-label">Preis brutto (€)</label>
                    <input type="number" name="preis_brutto" id="vsk-preis" class="erp-input" style="width:100%"
                        step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Sortierung</label>
                    <input type="number" name="sortierung" id="vsk-sort" class="erp-input" style="width:100%"
                        min="0" value="10">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label">Artikelgruppe (Konto) *</label>
                <select name="artikel_gruppe_id" id="vsk-gruppe" class="erp-select" style="width:100%" required>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($artikelGruppen as $ag): ?>
                        <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['konto_nr'] . ' – ' . $ag['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;gap:8px;justify-content:space-between">
                <button type="button" id="vsk-btn-loeschen" onclick="vskLoeschen()"
                    class="btn btn-danger btn-sm" style="display:none">Löschen</button>
                <div style="display:flex;gap:8px;margin-left:auto">
                    <button type="button" onclick="vskModalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_PATH ?>/js/versand_klassen.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>