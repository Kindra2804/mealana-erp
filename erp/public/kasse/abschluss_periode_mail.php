<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json; charset=utf-8');

$inp     = json_decode(file_get_contents('php://input'), true);
$typ     = in_array($inp['typ'] ?? '', ['monat', 'quartal']) ? $inp['typ'] : null;
$kasseId = (int)($inp['kasse_id'] ?? 1);

if (!$typ) { echo json_encode(['erfolg' => false, 'fehler' => 'Fehlende Parameter']); exit; }

$jahr    = (int)($inp['jahr']    ?? date('Y'));
$monat   = max(1, min(12, (int)($inp['monat']   ?? 1)));
$quartal = max(1, min(4,  (int)($inp['quartal'] ?? 1)));

if ($typ === 'monat') {
    $von = sprintf('%04d-%02d-01', $jahr, $monat);
    $bis = date('Y-m-t', strtotime($von));
    $periodLabel = date('F Y', strtotime($von));
} else {
    $qVon = ($quartal - 1) * 3 + 1;
    $qBis = $quartal * 3;
    $von  = sprintf('%04d-%02d-01', $jahr, $qVon);
    $bis  = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $jahr, $qBis)));
    $periodLabel = 'Q' . $quartal . ' ' . $jahr;
}

$service = new KassenService();
$daten   = $service->getPeriodeKennzahlen($kasseId, $von, $bis);

if ((int)$daten['anzahl_bons'] === 0) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Daten im Zeitraum']);
    exit;
}

$db     = Database::getInstance();
$konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(\PDO::FETCH_KEY_PAIR);

$kasseRow  = $db->query("SELECT kasse_nr, name FROM kassen WHERE id = " . (int)$kasseId)->fetch();
$empfaenger = $konfig['mail_abschluss_empfaenger'] ?? ($konfig['firma_email'] ?? '');
if (!$empfaenger) { echo json_encode(['erfolg' => false, 'fehler' => 'Kein Mail-Empfänger konfiguriert']); exit; }

function eur2(float $v): string { return '€ ' . number_format($v, 2, ',', '.'); }
function esc2(string $v): string { return htmlspecialchars($v); }

$kz          = $daten;
$st          = $daten['steuer'] ?? [];
$zBons       = $daten['z_bons'] ?? [];
$umsatzBar   = (float)$kz['umsatz_bar']   + (float)$kz['umsatz_kombi_bar'];
$umsatzKarte = (float)$kz['umsatz_karte'] + (float)$kz['umsatz_kombi_karte'];
$umsatzGes   = (float)$kz['umsatz_gesamt'];
$nettoBetrag = $steuerGes = 0;
foreach ($st as $s) { $nettoBetrag += (float)$s['netto']; $steuerGes += (float)$s['steuer']; }

$typLabel = ($typ === 'monat' ? 'MONATSABSCHLUSS' : 'QUARTALSABSCHLUSS') . ' — ' . strtoupper($periodLabel);

ob_start();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; }
  .kopf { display: flex; justify-content: space-between; margin-bottom: 8mm; border-bottom: 2px solid #7c3aed; padding-bottom: 4mm; }
  .firma { font-size: 14pt; font-weight: bold; color: #1e3a5f; }
  .sub   { font-size: 8pt; color: #64748b; }
  .typ   { font-size: 12pt; font-weight: bold; color: #7c3aed; text-align: right; }
  h2 { font-size: 9pt; font-weight: bold; color: #1e3a5f; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin: 5mm 0 2mm; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
  th { background: #1e3a5f; color: #fff; font-size: 8.5pt; padding: 4px 6px; text-align: left; }
  th.r { text-align: right; }
  td { padding: 4px 6px; font-size: 9pt; border-bottom: 1px solid #e2e8f0; }
  td.r { text-align: right; }
  .total { font-weight: bold; border-top: 2px solid #1e3a5f; }
  .kacheln { display: flex; gap: 6px; margin-bottom: 5mm; }
  .kachel { flex: 1; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; text-align: center; }
  .kachel-label { font-size: 7.5pt; color: #64748b; }
  .kachel-wert  { font-size: 12pt; font-weight: bold; color: #7c3aed; }
</style></head><body>
<div class="kopf">
  <div>
    <div class="firma"><?= esc2($konfig['firmenname'] ?? 'MeaLana') ?></div>
    <?php $ort = trim(($konfig['firma_plz'] ?? '') . ' ' . ($konfig['firma_ort'] ?? '')); if ($ort): ?><div class="sub"><?= esc2($ort) ?></div><?php endif; ?>
    <?php if (!empty($konfig['firma_uid'])): ?><div class="sub">UID: <?= esc2($konfig['firma_uid']) ?></div><?php endif; ?>
  </div>
  <div>
    <div class="typ"><?= $typLabel ?></div>
    <div style="text-align:right;font-size:8.5pt;color:#64748b">Kasse: <?= esc2($kasseRow['kasse_nr'] ?? '') ?> — <?= esc2($kasseRow['name'] ?? '') ?></div>
    <div style="text-align:right;font-size:8.5pt;color:#64748b">Zeitraum: <?= date('d.m.Y', strtotime($von)) ?> – <?= date('d.m.Y', strtotime($bis)) ?></div>
    <div style="text-align:right;font-size:8.5pt;color:#64748b">Erstellt: <?= date('d.m.Y H:i') ?></div>
    <?php if (!empty($kz['bon_nr_von'])): ?>
    <div style="text-align:right;font-size:8.5pt;color:#64748b">Bons: <?= esc2($kz['bon_nr_von']) ?> – <?= esc2($kz['bon_nr_bis']) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="kacheln">
  <div class="kachel"><div class="kachel-label">Umsatz gesamt</div><div class="kachel-wert"><?= eur2($umsatzGes) ?></div><div style="font-size:7.5pt;color:#94a3b8"><?= (int)$kz['anzahl_bons'] ?> Bons</div></div>
  <div class="kachel"><div class="kachel-label">Bar</div><div class="kachel-wert" style="color:#16a34a"><?= eur2($umsatzBar) ?></div></div>
  <div class="kachel"><div class="kachel-label">Karte</div><div class="kachel-wert" style="color:#d97706"><?= eur2($umsatzKarte) ?></div></div>
  <div class="kachel"><div class="kachel-label">Z-Bons</div><div class="kachel-wert" style="color:#1e3a5f"><?= (int)$kz['anzahl_z_bons'] ?></div></div>
</div>

<h2>Zahlungsarten</h2>
<table>
  <thead><tr><th>Zahlungsart</th><th class="r">Betrag</th></tr></thead>
  <tbody>
    <tr><td>Bar</td><td class="r"><?= eur2((float)$kz['umsatz_bar']) ?></td></tr>
    <tr><td>Karte</td><td class="r"><?= eur2((float)$kz['umsatz_karte']) ?></td></tr>
    <tr><td>Gutschein</td><td class="r"><?= eur2((float)$kz['umsatz_gs']) ?></td></tr>
    <tr><td>Kombi-Bar</td><td class="r"><?= eur2((float)$kz['umsatz_kombi_bar']) ?></td></tr>
    <tr><td>Kombi-Karte</td><td class="r"><?= eur2((float)$kz['umsatz_kombi_karte']) ?></td></tr>
    <?php if ((int)$kz['anzahl_stornos'] > 0): ?>
    <tr style="color:#dc2626"><td>Stornos (<?= (int)$kz['anzahl_stornos'] ?>)</td><td class="r">−<?= eur2(abs((float)$kz['storniert_betrag'])) ?></td></tr>
    <?php endif; ?>
    <tr class="total"><td>Gesamt</td><td class="r"><?= eur2($umsatzGes) ?></td></tr>
  </tbody>
</table>

<?php if (!empty($st)): ?>
<h2>Steueraufstellung</h2>
<table>
  <thead><tr><th>Satz</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr></thead>
  <tbody>
    <?php foreach ($st as $s): ?>
    <tr><td><?= number_format((float)$s['satz'], 0) ?> %</td><td class="r"><?= eur2((float)$s['netto']) ?></td><td class="r"><?= eur2((float)$s['steuer']) ?></td><td class="r"><?= eur2((float)$s['brutto']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="total"><td>Summe</td><td class="r"><?= eur2($nettoBetrag) ?></td><td class="r"><?= eur2($steuerGes) ?></td><td class="r"><?= eur2($nettoBetrag + $steuerGes) ?></td></tr>
  </tbody>
</table>
<?php endif; ?>

<?php if (!empty($zBons)): ?>
<h2>Tagesabschlüsse (Z-Bons) im Zeitraum</h2>
<table>
  <thead><tr><th>Bon-Nr.</th><th>Datum</th><th class="r">Kassenstand</th></tr></thead>
  <tbody>
    <?php foreach ($zBons as $zb): ?>
    <tr><td><?= esc2($zb['bon_nr'] ?? '—') ?></td><td><?= date('d.m.Y', strtotime($zb['datum'])) ?></td><td class="r"><?= eur2((float)($zb['kassenstand'] ?? 0)) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</body></html>
<?php
$html = ob_get_clean();

$opt = new Options();
$opt->set('isRemoteEnabled', false);
$opt->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($opt);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdfContent = $pdf->output();

$dateiname = ($typ === 'monat' ? 'Monatsabschluss_' : 'Quartalsabschluss_') . str_replace(' ', '_', $periodLabel) . '_' . date('Ymd_His') . '.pdf';

try {
    $mail = new PHPMailer(true);
    $host = $konfig['smtp_host'] ?? 'localhost';
    if ($host && $host !== 'localhost') {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = (int)($konfig['smtp_port'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $konfig['smtp_user'] ?? '';
        $mail->Password   = $konfig['smtp_pass'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($konfig['mail_absender'] ?? $konfig['firma_email'] ?? 'kasse@mealana.at', $konfig['firmenname'] ?? 'MeaLana Kasse');
    foreach (explode(',', $empfaenger) as $addr) { $addr = trim($addr); if ($addr) $mail->addAddress($addr); }
    $mail->Subject = $typLabel . ' — Kasse ' . esc2($kasseRow['kasse_nr'] ?? '');
    $mail->isHTML(true);
    $mail->Body    = '<p>Anbei der ' . ($typ === 'monat' ? 'Monatsabschluss' : 'Quartalsabschluss') . ' <strong>' . esc2($periodLabel) . '</strong>.</p>'
                   . '<p>Kasse: ' . esc2($kasseRow['kasse_nr'] ?? '') . ' — ' . esc2($kasseRow['name'] ?? '') . '</p>'
                   . '<p>Zeitraum: ' . date('d.m.Y', strtotime($von)) . ' – ' . date('d.m.Y', strtotime($bis)) . '</p>'
                   . '<p>Umsatz: <strong>' . eur2($umsatzGes) . '</strong> · ' . (int)$kz['anzahl_bons'] . ' Bons · ' . (int)$kz['anzahl_z_bons'] . ' Z-Bons</p>';
    $mail->AltBody = strip_tags($mail->Body);
    $mail->addStringAttachment($pdfContent, $dateiname, 'base64', 'application/pdf');
    $mail->send();
    echo json_encode(['erfolg' => true, 'empfaenger' => $empfaenger]);
} catch (MailException $e) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Mail-Fehler: ' . $mail->ErrorInfo]);
} catch (\Exception $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
