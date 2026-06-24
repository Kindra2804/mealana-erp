<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /mealana/auftraege/liste.php'); exit; }

$service   = new AuftragService();
$auftrag   = $service->getById($id);
if (!$auftrag) { header('Location: /mealana/auftraege/liste.php'); exit; }

$positionen = $service->getPositionen($id);
$statuslog  = $service->getStatuslog($id);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$zahlungsLabels = [
    'ausstehend'  => ['label' => 'Ausstehend',  'class' => 'chip-auslauf'],
    'bezahlt'     => ['label' => 'Bezahlt',      'class' => 'chip-aktiv'],
    'teilbezahlt' => ['label' => 'Teilbezahlt',  'class' => 'chip-auslauf'],
    'erstattet'   => ['label' => 'Erstattet',    'class' => 'chip-inaktiv'],
    'storniert'   => ['label' => 'Storniert',    'class' => 'chip-inaktiv'],
];
$lieferLabels = [
    'neu'             => ['label' => 'Neu',             'class' => 'chip-aktiv'],
    'in_bearbeitung'  => ['label' => 'In Bearbeitung',  'class' => 'chip-auslauf'],
    'versandbereit'   => ['label' => 'Versandbereit',   'class' => 'chip-auslauf'],
    'teilgeliefert'   => ['label' => 'Teilgeliefert',   'class' => 'chip-auslauf'],
    'zurueckgestellt' => ['label' => 'Zurückgestellt',  'class' => 'chip-inaktiv'],
    'versendet'       => ['label' => 'Versendet',       'class' => 'chip-aktiv'],
    'abgeschlossen'   => ['label' => 'Abgeschlossen',   'class' => 'chip-inaktiv'],
    'storniert'       => ['label' => 'Storniert',       'class' => 'chip-inaktiv'],
];
$kanalLabels = [
    'woocommerce' => ['label' => 'WooCommerce', 'class' => 'chip-aktiv'],
    'manuell'     => ['label' => 'Manuell',     'class' => 'chip-auslauf'],
    'kasse'       => ['label' => 'Kasse',        'class' => 'chip-inaktiv'],
];

$zl  = $zahlungsLabels[$auftrag['zahlungsstatus']] ?? ['label' => $auftrag['zahlungsstatus'], 'class' => ''];
$ll  = $lieferLabels[$auftrag['lieferstatus']]     ?? ['label' => $auftrag['lieferstatus'],   'class' => ''];
$kl  = $kanalLabels[$auftrag['kanal']]             ?? ['label' => $auftrag['kanal'],          'class' => ''];

$istStorniert = in_array($auftrag['lieferstatus'], ['storniert']);

$pageTitle        = 'Auftrag ' . htmlspecialchars($auftrag['auftrag_nr']);
$activeModule     = 'verkauf';
$actionBarContent = '<a href="/mealana/auftraege/liste.php" class="btn btn-secondary btn-sm">← Liste</a>';
if (!$istStorniert) {
    $actionBarContent .= ' <button type="button" class="btn btn-danger btn-sm" onclick="storniereAuftrag()">Stornieren</button>';
}
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
    <div class="banner banner-success" id="erfolg-banner"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if (!empty($fehler)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px">
        <?php foreach ($fehler as $f): ?>
            <p style="color:var(--color-danger);margin:4px 0"><?= htmlspecialchars($f) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Kopf-Übersicht -->
<div class="card" style="margin-bottom:12px">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;padding:16px">

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Auftrag</div>
            <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($auftrag['auftrag_nr']) ?></div>
            <div style="margin-top:4px"><span class="chip <?= $kl['class'] ?>"><?= $kl['label'] ?></span></div>
        </div>

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Kunde</div>
            <div style="font-weight:600"><?= htmlspecialchars($auftrag['kunden_name']) ?></div>
            <?php if ($auftrag['kunden_email']): ?>
                <div style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($auftrag['kunden_email']) ?></div>
            <?php endif; ?>
        </div>

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Zahlung</div>
            <span class="chip <?= $zl['class'] ?>"><?= $zl['label'] ?></span>
            <div style="font-size:12px;color:var(--color-text-muted);margin-top:4px">
                <?= htmlspecialchars(ucfirst($auftrag['zahlungsart'])) ?>
                <?php if ($auftrag['bezahlt_am']): ?>
                    · <?= date('d.m.Y', strtotime($auftrag['bezahlt_am'])) ?>
                <?php endif; ?>
            </div>
            <?php if (!$istStorniert && $auftrag['zahlungsstatus'] === 'ausstehend'): ?>
                <button class="btn btn-primary btn-sm" style="margin-top:8px"
                        onclick="statusSetzen('zahlungsstatus','bezahlt','Zahlung manuell bestätigt')">
                    ✓ Als bezahlt markieren
                </button>
            <?php endif; ?>
        </div>

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Lieferstatus</div>
            <span class="chip <?= $ll['class'] ?>"><?= $ll['label'] ?></span>
            <?php if (!$istStorniert): ?>
                <div style="margin-top:8px">
                    <select class="erp-select" style="font-size:12px" id="lieferstatus-select">
                        <?php foreach ($lieferLabels as $val => $info): ?>
                            <?php if ($val === 'storniert') continue; ?>
                            <option value="<?= $val ?>" <?= $auftrag['lieferstatus'] === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary btn-sm" style="margin-top:4px;width:100%"
                            onclick="lieferstatusAktualisieren()">Status setzen</button>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Tracking -->
    <?php if (!$istStorniert): ?>
    <div style="border-top:1px solid var(--color-border);padding:12px 16px;display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1">
            <label class="form-label" style="font-size:11px">Tracking-Nr.</label>
            <input type="text" class="erp-input" id="tracking-nr" value="<?= htmlspecialchars($auftrag['tracking_nr'] ?? '') ?>" placeholder="z.B. 12345678">
        </div>
        <div class="form-group" style="margin:0;flex:1">
            <label class="form-label" style="font-size:11px">Versanddienstleister</label>
            <select class="erp-select" id="versand-dl">
                <option value="">—</option>
                <option value="post_at"  <?= $auftrag['versanddienstleister'] === 'post_at'  ? 'selected':'' ?>>Österreichische Post</option>
                <option value="dhl"      <?= $auftrag['versanddienstleister'] === 'dhl'      ? 'selected':'' ?>>DHL</option>
                <option value="dpd"      <?= $auftrag['versanddienstleister'] === 'dpd'      ? 'selected':'' ?>>DPD</option>
                <option value="gls"      <?= $auftrag['versanddienstleister'] === 'gls'      ? 'selected':'' ?>>GLS</option>
            </select>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="trackingSpeichern()">Tracking speichern</button>
    </div>
    <?php endif; ?>
</div>

<!-- Positionen -->
<div class="card" style="margin-bottom:12px">
    <div class="card-header">Positionen</div>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:50px"></th>
                <th>Artikel</th>
                <th>EAN</th>
                <th style="text-align:center">Menge</th>
                <th style="text-align:center">Geliefert</th>
                <th style="text-align:right">Einzelpreis (Netto)</th>
                <th style="text-align:right">Gesamt (Netto)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($positionen as $p): ?>
                <tr>
                    <td>
                        <?php if (!empty($p['bild_pfad'])): ?>
                            <img src="/mealana/<?= htmlspecialchars($p['bild_pfad']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:3px">
                        <?php else: ?>
                            <div style="width:36px;height:36px;background:var(--color-bg);border-radius:3px;border:1px solid var(--color-border)"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:600"><?= htmlspecialchars($p['bezeichnung']) ?></span>
                        <?php if ($p['charge']): ?>
                            <span style="font-size:11px;color:var(--color-text-muted)"> · Charge: <?= htmlspecialchars($p['charge']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($p['ean'] ?? '—') ?></td>
                    <td style="text-align:center"><?= (int)$p['menge'] ?></td>
                    <td style="text-align:center">
                        <?php
                            $geliefert = (int)$p['menge_geliefert'];
                            $gesamt    = (int)$p['menge'];
                            $farbe     = $geliefert >= $gesamt ? 'var(--color-success)' : ($geliefert > 0 ? 'var(--color-warning)' : 'var(--color-text-muted)');
                        ?>
                        <span style="color:<?= $farbe ?>;font-weight:600"><?= $geliefert ?> / <?= $gesamt ?></span>
                    </td>
                    <td style="text-align:right"><?= number_format((float)$p['einzelpreis_netto'], 4, ',', '.') ?> €</td>
                    <td style="text-align:right;font-weight:600"><?= number_format((float)$p['gesamtpreis_netto'], 2, ',', '.') ?> €</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align:right;color:var(--color-text-muted)">Netto</td>
                <td style="text-align:right"><?= number_format((float)$auftrag['nettobetrag'], 2, ',', '.') ?> €</td>
            </tr>
            <tr>
                <td colspan="6" style="text-align:right;color:var(--color-text-muted)">MwSt.</td>
                <td style="text-align:right"><?= number_format((float)$auftrag['steuerbetrag'], 2, ',', '.') ?> €</td>
            </tr>
            <?php if ((float)$auftrag['versandkosten'] > 0): ?>
            <tr>
                <td colspan="6" style="text-align:right;color:var(--color-text-muted)">Versandkosten</td>
                <td style="text-align:right"><?= number_format((float)$auftrag['versandkosten'], 2, ',', '.') ?> €</td>
            </tr>
            <?php endif; ?>
            <tr style="font-size:15px;font-weight:700">
                <td colspan="6" style="text-align:right">Gesamt (Brutto)</td>
                <td style="text-align:right"><?= number_format((float)$auftrag['bruttobetrag'], 2, ',', '.') ?> €</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Notizen -->
<?php if ($auftrag['notiz_intern'] || $auftrag['notiz_versand']): ?>
<div class="card" style="margin-bottom:12px">
    <div class="card-header">Notizen</div>
    <div style="padding:12px 16px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <?php if ($auftrag['notiz_intern']): ?>
            <div><div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">INTERN</div><?= nl2br(htmlspecialchars($auftrag['notiz_intern'])) ?></div>
        <?php endif; ?>
        <?php if ($auftrag['notiz_versand']): ?>
            <div><div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">VERSAND / PACKERL</div><?= nl2br(htmlspecialchars($auftrag['notiz_versand'])) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Statuslog -->
<?php if (!empty($statuslog)): ?>
<div class="card">
    <div class="card-header">Verlauf</div>
    <table class="erp-table">
        <thead><tr><th>Zeitpunkt</th><th>Benutzer</th><th>Änderung</th><th>Notiz</th></tr></thead>
        <tbody>
            <?php foreach ($statuslog as $log):
                $changes = json_decode($log['felder_geaendert'] ?? '{}', true) ?: [];
                $changeTexts = [];
                foreach ($changes as $feld => $werte) {
                    $changeTexts[] = ucfirst(str_replace('_', ' ', $feld)) . ': ' . ($werte[0] ?? '—') . ' → ' . ($werte[1] ?? '—');
                }
            ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12px"><?= date('d.m.Y H:i', strtotime($log['erstellt_am'])) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($log['erstellt_von_name']) ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars(implode(', ', $changeTexts)) ?></td>
                    <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($log['notiz'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
window.AUFTRAG_ID       = <?= $id ?>;
window.STATUS_AJAX_URL  = '/mealana/auftraege/status_ajax.php';
window.STORNO_URL       = '/mealana/auftraege/stornieren.php';
</script>
<script src="/mealana/js/auftraege_detail.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
