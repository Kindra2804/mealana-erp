<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellDokumentService.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$dokumentId = (int)($_GET['dokument_id'] ?? 0);

$dokService = new BestellDokumentService();
$dokument   = $dokumentId ? $dokService->getDokumentMitBestellung($dokumentId) : null;

if (!$dokument) {
    header('Location: ' . BASE_PATH . '/bestellungen/liste.php');
    exit;
}

$bestellungService = new BestellungService();
$bestellung = $bestellungService->getById((int)$dokument['bestellung_id']);
$nummer     = BestellungService::bestellnummer((int)$dokument['bestellung_id'], $bestellung['bestelldatum']);

$pageTitle    = 'Bestellung per Mail senden';
$activeModule = 'einkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/bestellungen/detail.php?id=' . (int)$dokument['bestellung_id'] . '" class="btn btn-secondary btn-sm">← Zurück zur Bestellung</a>';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card" style="max-width:640px;margin:0 auto">
    <strong style="font-size:14px;display:block;margin-bottom:4px"><?= htmlspecialchars($nummer) ?> an <?= htmlspecialchars($dokument['lieferant_name']) ?> senden</strong>
    <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:16px">
        PDF-Anhang: <a href="<?= BASE_PATH ?>/bestellungen/dokument_download.php?bestellung_id=<?= (int)$dokument['bestellung_id'] ?>&datei=<?= urlencode($dokument['dateiname']) ?>" target="_blank">
            <?= htmlspecialchars($dokument['dateiname']) ?> ↗
        </a>
    </div>

    <form method="post" action="<?= BASE_PATH ?>/bestellungen/mail_senden.php">
        <input type="hidden" name="dokument_id" value="<?= (int)$dokumentId ?>">

        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">An</label>
        <input type="email" name="empfaenger" class="erp-input" style="width:100%;margin-bottom:12px"
               value="<?= htmlspecialchars($dokument['lieferant_email'] ?? '') ?>" required>

        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Betreff</label>
        <input type="text" name="betreff" class="erp-input" style="width:100%;margin-bottom:12px"
               value="Bestellung <?= htmlspecialchars($nummer) ?>" required>

        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Nachricht</label>
        <textarea name="nachricht" class="erp-input" style="width:100%;min-height:120px;margin-bottom:16px;font-family:inherit"
        >anbei erhalten Sie unsere Bestellung <?= htmlspecialchars($nummer) ?>. Wir bitten um kurze Auftragsbestätigung mit Liefertermin.</textarea>

        <div style="text-align:right">
            <a href="<?= BASE_PATH ?>/bestellungen/detail.php?id=<?= (int)$dokument['bestellung_id'] ?>" class="btn btn-secondary btn-sm">Abbrechen</a>
            <button type="submit" class="btn btn-primary btn-sm">Mail senden</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
