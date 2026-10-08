<?php
/**
 * Messe-Abschluss (Papier-Messe) — A4 zum Ablegen bei den Beleg-Durchschriften.
 *
 * Fasst zusammen: nacherfasste Belege (Nr. von–bis, bar/Bankomat), Lager laut
 * Strichliste (mit/verkauft/zurück/Schwund je Zeile), Freitext-Liste und den
 * Abgleich Strichliste-Wert ↔ Belegsumme (Differenz = Rabatte/Zugaben/Fehler).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';

$svc    = new MesseSyncService();
$syncId = (int)($_GET['sync_id'] ?? 0);
$sync   = $svc->getSyncById($syncId);
if (!$sync || $sync['variante'] !== 'papier') {
    die('<p style="font-family:sans-serif;padding:20px">Papier-Messe nicht gefunden.</p>');
}

$db = Database::getInstance();
$lagerName = $db->prepare("SELECT name FROM lager WHERE id = ?");
$lagerName->execute([$sync['lager_id']]);
$lagerName = $lagerName->fetchColumn() ?: '';
$firma = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'firmenname'")->fetchColumn() ?: '';

$belege = array_values(array_filter($svc->getNacherfassteBelege($syncId), fn($b) => !$b['storniert']));
$umb    = $svc->getUmbuchungenBySyncId($syncId);
$wert   = $sync['rueckkehr_am'] !== null ? $svc->getStrichlistenWert($syncId) : null;

$ft = $db->prepare("
    SELECT f.*, ag.name AS gruppe_name FROM kassen_messe_freitext f
    LEFT JOIN artikel_gruppen ag ON ag.id = f.artikel_gruppe_id
    WHERE f.sync_id = ? ORDER BY f.id
");
$ft->execute([$syncId]);
$freitext = $ft->fetchAll(PDO::FETCH_ASSOC);

$summeBar = $summeKarte = 0.0;
$datumVon = $datumBis = null;
foreach ($belege as $b) {
    if ($b['zahlungsart'] === 'bar') $summeBar += (float)$b['bruttobetrag'];
    else                             $summeKarte += (float)$b['bruttobetrag'];
    $datumVon = $datumVon === null ? $b['handbeleg_datum'] : min($datumVon, $b['handbeleg_datum']);
    $datumBis = $datumBis === null ? $b['handbeleg_datum'] : max($datumBis, $b['handbeleg_datum']);
}
$summeGesamt = round($summeBar + $summeKarte, 2);
$nummern = array_column($belege, 'handbeleg_nr');

$fmt  = fn($b) => number_format((float)$b, 2, ',', '.');
$fmtM = fn($m) => rtrim(rtrim(number_format((float)$m, 3, ',', ''), '0'), ',');
$fmtD = fn($d) => $d ? date('d.m.Y', strtotime($d)) : '–';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Messe-Abschluss #<?= $syncId ?></title>
<style>
  @page { size: A4; margin: 12mm 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; margin: 0; }
  h1 { font-size: 16pt; margin: 0 0 2px; }
  h2 { font-size: 11pt; margin: 14px 0 4px; border-bottom: 1.5px solid #000; padding-bottom: 2px; }
  table { width: 100%; border-collapse: collapse; }
  th { font-size: 8.5pt; text-align: left; border-bottom: 1px solid #000; padding: 2px 4px; }
  td { padding: 2px 4px; border-bottom: 1px solid #ccc; }
  .r { text-align: right; }
  .klein { font-size: 8.5pt; color: #333; }
  .kz { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 20px; margin-top: 6px; }
  .kz div { display: flex; justify-content: space-between; border-bottom: 1px dotted #999; padding: 2px 0; }
  .fuss { margin-top: 24px; display: flex; gap: 30px; }
  .fuss div { flex: 1; border-top: 1px solid #000; padding-top: 3px; font-size: 9pt; }
  .knopf { position: fixed; top: 10px; right: 10px; padding: 8px 16px; font-size: 14px; cursor: pointer; }
  @media print { .knopf { display: none; } }
</style>
</head>
<body>
<button class="knopf" onclick="window.print()">🖨 Drucken</button>

<h1>Messe-Abschluss</h1>
<div class="klein"><?= htmlspecialchars($firma) ?> · <?= htmlspecialchars($lagerName) ?> · Messe-Nr. <?= $syncId ?> · erstellt <?= date('d.m.Y H:i') ?></div>

<h2>Belege (nacherfasst)</h2>
<div class="kz">
  <div><span>Anzahl Belege</span><strong><?= count($belege) ?></strong></div>
  <div><span>Beleg-Nr.</span><strong><?= $nummern ? htmlspecialchars(reset($nummern) . ' – ' . end($nummern)) : '–' ?></strong></div>
  <div><span>Belegdatum</span><strong><?= $fmtD($datumVon) ?><?= $datumBis !== $datumVon ? ' – ' . $fmtD($datumBis) : '' ?></strong></div>
  <div><span>Bar</span><strong>€ <?= $fmt($summeBar) ?></strong></div>
  <div><span>Bankomat</span><strong>€ <?= $fmt($summeKarte) ?></strong></div>
  <div><span>Gesamt</span><strong>€ <?= $fmt($summeGesamt) ?></strong></div>
</div>
<?php if ($wert): ?>
<div class="kz">
  <div><span>Wert Strichliste (Lager)</span><strong>€ <?= $fmt($wert['lager']) ?></strong></div>
  <div><span>Freitext (ohne Zugaben)</span><strong>€ <?= $fmt($wert['freitext']) ?></strong></div>
  <div><span>Differenz Belege − Liste</span><strong>€ <?= $fmt($summeGesamt - $wert['gesamt']) ?></strong></div>
</div>
<p class="klein" style="margin:4px 0 0">Differenz = Rabatte, Zugaben, Preisänderungen oder Zählfehler. Listenwert zum aktuellen Standard-VK.</p>
<?php endif; ?>

<table style="margin-top:8px">
  <thead><tr><th>Beleg-Nr.</th><th>Datum</th><th>Zahlart</th><th class="r">Betrag</th><th>Bon (signiert)</th></tr></thead>
  <tbody>
  <?php foreach ($belege as $b): ?>
    <tr>
      <td><?= htmlspecialchars($b['handbeleg_nr']) ?></td>
      <td><?= $fmtD($b['handbeleg_datum']) ?></td>
      <td><?= $b['zahlungsart'] === 'bar' ? 'Bar' : 'Bankomat' ?></td>
      <td class="r">€ <?= $fmt($b['bruttobetrag']) ?></td>
      <td class="klein"><?= htmlspecialchars($b['bon_nr']) ?> · <?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?><?= $b['signiert'] ? ' ✓' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Lager laut Strichliste</h2>
<?php if ($sync['rueckkehr_am'] === null): ?>
  <p class="klein">Noch nicht zurückgebucht.</p>
<?php else: ?>
<table>
  <thead><tr><th>Artikel</th><th>Charge</th><th class="r">mit</th><th class="r">verkauft</th><th class="r">zurück</th><th class="r">Schwund</th></tr></thead>
  <tbody>
  <?php foreach ($umb as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['bezeichnung']) ?></td>
      <td class="klein"><?= htmlspecialchars($u['charge'] ?? '') ?></td>
      <td class="r"><?= $fmtM($u['menge_raus']) ?></td>
      <td class="r"><?= $fmtM($u['menge_strich'] ?? 0) ?></td>
      <td class="r"><?= $fmtM($u['menge_rueck']) ?></td>
      <td class="r"><?= (float)$u['menge_schwund'] > 0 ? '<strong>' . $fmtM($u['menge_schwund']) . '</strong>' : '0' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($freitext): ?>
<h2>Freitext / Zugaben (Info)</h2>
<table>
  <thead><tr><th>Bezeichnung</th><th>Gruppe</th><th class="r">Menge</th><th class="r">Preis</th><th>Zugabe</th></tr></thead>
  <tbody>
  <?php foreach ($freitext as $f): ?>
    <tr>
      <td><?= htmlspecialchars($f['bezeichnung']) ?></td>
      <td><?= htmlspecialchars($f['gruppe_name'] ?? '–') ?></td>
      <td class="r"><?= (int)$f['menge'] ?></td>
      <td class="r">€ <?= $fmt($f['einzelpreis']) ?></td>
      <td><?= $f['zugabe'] ? 'ja' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="fuss">
  <div>Bargeld übergeben / eingelegt am</div>
  <div>Geprüft / Unterschrift</div>
</div>

</body>
</html>
