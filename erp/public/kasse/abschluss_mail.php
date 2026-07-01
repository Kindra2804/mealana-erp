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

$inp = json_decode(file_get_contents('php://input'), true);
$id  = (int)($inp['id'] ?? 0);
if (!$id) { echo json_encode(['erfolg' => false, 'fehler' => 'Keine ID']); exit; }

$service = new KassenService();
$a = $service->getAbschluss($id);
if (!$a) { echo json_encode(['erfolg' => false, 'fehler' => 'Abschluss nicht gefunden']); exit; }

$db     = Database::getInstance();
$konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(\PDO::FETCH_KEY_PAIR);

$empfaenger = $konfig['mail_abschluss_empfaenger'] ?? ($konfig['firma_email'] ?? '');
if (!$empfaenger) { echo json_encode(['erfolg' => false, 'fehler' => 'Kein Mail-Empfänger konfiguriert (mail_abschluss_empfaenger in Einstellungen)']); exit; }

// ── PDF erzeugen ──────────────────────────────────────────────────────────────
$daten = $a['daten'];
$kz    = $daten['kennzahlen'];
$st    = $daten['steuer'] ?? [];
$istZ  = $a['typ'] === 'z';
$datum = date('d.m.Y', strtotime($a['datum']));

function eur(float $v): string { return '€ ' . number_format($v, 2, ',', '.'); }
function esc(string $v): string { return htmlspecialchars($v); }

$umsatzBar   = (float)$kz['umsatz_bar']   + (float)$kz['umsatz_kombi_bar'];
$umsatzKarte = (float)$kz['umsatz_karte'] + (float)$kz['umsatz_kombi_karte'];
$umsatzGes   = (float)$kz['umsatz_gesamt'];
$netto = $steuerGes = 0;
foreach ($st as $s) { $netto += $s['netto']; $steuerGes += $s['steuer']; }

$typLabel = $istZ ? 'Z-BON — TAGESABSCHLUSS' : 'X-BON — ZWISCHENABSCHLUSS';

ob_start();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; }
  .kopf { display: flex; justify-content: space-between; margin-bottom: 8mm; border-bottom: 2px solid #1e3a5f; padding-bottom: 4mm; }
  .firma { font-size: 14pt; font-weight: bold; color: #1e3a5f; }
  .sub   { font-size: 8pt; color: #64748b; }
  .typ   { font-size: 13pt; font-weight: bold; color: <?= $istZ ? '#dc2626' : '#1e3a5f' ?>; text-align: right; }
  h2 { font-size: 9pt; font-weight: bold; color: #1e3a5f; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin: 5mm 0 2mm; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
  th { background: #1e3a5f; color: #fff; font-size: 8.5pt; padding: 4px 6px; text-align: left; }
  th.r { text-align: right; }
  td { padding: 4px 6px; font-size: 9pt; border-bottom: 1px solid #e2e8f0; }
  td.r { text-align: right; }
  .total { font-weight: bold; border-top: 2px solid #1e3a5f; }
  .ksbox { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 10px; margin-bottom: 5mm; display: flex; justify-content: space-between; }
  .kswert { font-size: 14pt; font-weight: bold; color: <?= $istZ ? '#dc2626' : '#1e3a5f' ?>; }
</style></head><body>
<div class="kopf">
  <div>
    <div class="firma"><?= esc($konfig['firmenname'] ?? 'MeaLana') ?></div>
    <div class="sub"><?= esc(trim(($konfig['firma_plz'] ?? '') . ' ' . ($konfig['firma_ort'] ?? ''))) ?></div>
    <?php if (!empty($konfig['firma_uid'])): ?><div class="sub">UID: <?= esc($konfig['firma_uid']) ?></div><?php endif; ?>
  </div>
  <div>
    <div class="typ"><?= $typLabel ?></div>
    <div style="text-align:right;font-size:9pt;font-weight:bold"><?= esc($a['bon_nr']) ?></div>
    <div class="sub" style="text-align:right">Kasse: <?= esc($a['kasse_nr'] ?? '') ?> · <?= $datum ?></div>
    <div class="sub" style="text-align:right">Kassierer: <?= esc($a['kassierer_name'] ?? '—') ?></div>
  </div>
</div>

<div class="ksbox">
  <span style="font-weight:bold"><?= $istZ ? 'Kassenstand Tagesabschluss' : 'Kassenstand (Zwischenstand)' ?></span>
  <span class="kswert"><?= eur((float)$a['kassenstand']) ?></span>
</div>

<h2>Zahlungsarten</h2>
<table>
  <thead><tr><th>Zahlungsart</th><th class="r">Betrag</th></tr></thead>
  <tbody>
    <tr><td>Bar</td><td class="r"><?= eur((float)$kz['umsatz_bar']) ?></td></tr>
    <tr><td>Karte</td><td class="r"><?= eur((float)$kz['umsatz_karte']) ?></td></tr>
    <tr><td>Gutschein</td><td class="r"><?= eur((float)$kz['umsatz_gs']) ?></td></tr>
    <tr><td>Kombi-Bar</td><td class="r"><?= eur((float)$kz['umsatz_kombi_bar']) ?></td></tr>
    <tr><td>Kombi-Karte</td><td class="r"><?= eur((float)$kz['umsatz_kombi_karte']) ?></td></tr>
    <tr class="total"><td>Gesamt</td><td class="r"><?= eur($umsatzGes) ?></td></tr>
  </tbody>
</table>

<?php if (!empty($st)): ?>
<h2>Steueraufstellung</h2>
<table>
  <thead><tr><th>Satz</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr></thead>
  <tbody>
    <?php foreach ($st as $s): ?>
    <tr><td><?= number_format((float)$s['satz'], 0) ?> %</td><td class="r"><?= eur((float)$s['netto']) ?></td><td class="r"><?= eur((float)$s['steuer']) ?></td><td class="r"><?= eur((float)$s['brutto']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="total"><td>Summe</td><td class="r"><?= eur($netto) ?></td><td class="r"><?= eur($steuerGes) ?></td><td class="r"><?= eur($netto + $steuerGes) ?></td></tr>
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

// ── Mail senden ───────────────────────────────────────────────────────────────
$dateiname = 'Abschluss_' . strtoupper($a['typ']) . '_' . $a['bon_nr'] . '_' . date('Ymd', strtotime($a['datum'])) . '.pdf';

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

    $mail->CharSet  = 'UTF-8';
    $mail->setFrom($konfig['mail_absender'] ?? $konfig['firma_email'] ?? 'kasse@mealana.at', $konfig['firmenname'] ?? 'MeaLana Kasse');
    foreach (explode(',', $empfaenger) as $addr) {
        $addr = trim($addr);
        if ($addr) $mail->addAddress($addr);
    }

    $mail->Subject = $typLabel . ' — ' . esc($a['bon_nr']) . ' — ' . $datum;
    $mail->isHTML(true);
    $mail->Body    = '<p>Anbei der ' . ($istZ ? 'Tagesabschluss' : 'Zwischenabschluss') . ' vom <strong>' . $datum . '</strong>.</p>'
                   . '<p>Kasse: ' . esc($a['kasse_nr'] ?? '') . ' · Bon-Nr.: ' . esc($a['bon_nr']) . '</p>'
                   . '<p>Umsatz: <strong>' . eur($umsatzGes) . '</strong> · Kassenstand: <strong>' . eur((float)$a['kassenstand']) . '</strong></p>';
    $mail->AltBody = strip_tags($mail->Body);
    $mail->addStringAttachment($pdfContent, $dateiname, 'base64', 'application/pdf');

    $mail->send();
    echo json_encode(['erfolg' => true, 'empfaenger' => $empfaenger]);

} catch (MailException $e) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Mail-Fehler: ' . $mail->ErrorInfo]);
} catch (\Exception $e) {
    echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
}
