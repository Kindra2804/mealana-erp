<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$db   = Database::getInstance();
$rows = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
$s    = fn(string $key, string $fallback = '') => htmlspecialchars($rows[$key] ?? $fallback);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

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
                    <a href="/mealana/einstellungen/index.php?tab=firma" style="color:var(--color-nav)">Firmen-Einstellungen</a>
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
            <a href="/mealana/packplatz/warenausgang/index.php" class="btn btn-primary" style="width:100%;text-align:center;margin-bottom:8px">
                📦 Packplatz öffnen
            </a>
            <a href="/mealana/auftraege/liste.php" class="btn btn-secondary" style="width:100%;text-align:center">
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
                    <?php foreach ([
                        ['Bar',        'bar',       'Sofort'],
                        ['Rechnung',   'rechnung',  'Sofort'],
                        ['Nachnahme',  'nachnahme', 'Sofort'],
                        ['Vorkasse',   'vorkasse',  'Nur bei bezahlt'],
                        ['PayPal',     'paypal',    'Nur bei bezahlt'],
                        ['Abholung',   '—',         'Sofort (Lieferart)'],
                    ] as [$label, $art, $regel]): ?>
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

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
