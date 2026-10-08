<?php
/**
 * Strichliste für die Papier-Messe (A4, zum Ausdrucken + Abhaken vor Ort).
 *
 * Inhalt = alles, was für diese Papier-Messe ins Messe-Lager gebucht wurde
 * (kassen_messe_umbuchungen), gruppiert nach Artikelgruppe. Pro Zeile ein breites
 * Stricherl-Feld (verkauft) und ein Feld für die bei der Rückkehr gezählte Menge.
 * Am Ende leere Freitext-Zeilen für Ware ohne Lagerstand bzw. Werbe-Zugaben.
 * Die Zahlen werden bei der Rückkehr in messe_rueckkehr.php abgetippt.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';

$syncId = (int)($_GET['sync_id'] ?? 0);
$sync   = (new MesseSyncService())->getSyncById($syncId);
if (!$sync || $sync['variante'] !== 'papier') {
    die('<p style="font-family:sans-serif;padding:20px">Papier-Messe nicht gefunden.</p>');
}

$db = Database::getInstance();

$lagerName = $db->prepare("SELECT name FROM lager WHERE id = ?");
$lagerName->execute([$sync['lager_id']]);
$lagerName = $lagerName->fetchColumn() ?: '';

$firma = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'firmenname'")->fetchColumn() ?: '';

// Preis = aktueller Standard-VK (gleiche Logik wie Kasse/Schnellwahl)
$stmt = $db->prepare("
    SELECT u.id, u.bezeichnung, u.charge, u.menge_raus, a.artikelnummer,
           COALESCE(ag.name, 'Ohne Artikelgruppe') AS gruppe_name,
           COALESCE(ag.sortierung, 9999) AS gruppe_sort,
           COALESCE(
               (SELECT ap.brutto_vk FROM artikel_preise ap
                INNER JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                WHERE ap.artikel_id = a.id
                  AND (ap.gueltig_ab IS NULL OR ap.gueltig_ab <= CURDATE())
                  AND (ap.gueltig_bis IS NULL OR ap.gueltig_bis >= CURDATE())
                ORDER BY ap.gueltig_ab DESC LIMIT 1), 0
           ) AS preis
    FROM kassen_messe_umbuchungen u
    INNER JOIN artikel a ON a.id = u.artikel_id
    LEFT JOIN artikel_gruppen ag ON ag.id = a.artikel_gruppe_id
    WHERE u.sync_id = ?
    ORDER BY gruppe_sort, gruppe_name, u.bezeichnung, u.charge
");
$stmt->execute([$syncId]);
$gruppen = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
    $gruppen[$z['gruppe_name']][] = $z;
}

$fmtMenge = fn($m) => rtrim(rtrim(number_format((float)$m, 3, ',', ''), '0'), ',');
$fmtEuro  = fn($b) => number_format((float)$b, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Strichliste Messe #<?= $syncId ?></title>
<style>
  @page { size: A4; margin: 12mm 10mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #000; margin: 0; }
  .kopf { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 8px; }
  .kopf h1 { font-size: 16pt; margin: 0; }
  .kopf .meta { font-size: 9pt; text-align: right; }
  table { width: 100%; border-collapse: collapse; }
  th { font-size: 8.5pt; text-align: left; border-bottom: 1.5px solid #000; padding: 3px 4px; }
  td { border-bottom: 1px solid #999; padding: 0 4px; height: 8.5mm; vertical-align: middle; }
  tr { page-break-inside: avoid; }
  .gruppe td { font-weight: bold; background: #e5e5e5; height: 6mm; font-size: 9.5pt; border-bottom: 1px solid #000;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .r { text-align: right; }
  .klein { font-size: 8pt; color: #333; }
  .strich { border-left: 1px solid #999; }
  .feld { border-left: 1px solid #999; width: 16mm; }
  h2 { font-size: 11pt; margin: 14px 0 4px; }
  .fuss { margin-top: 14px; display: flex; gap: 30px; font-size: 10pt; }
  .fuss div { flex: 1; border-top: 1px solid #000; padding-top: 3px; }
  .knopf { position: fixed; top: 10px; right: 10px; padding: 8px 16px; font-size: 14px; cursor: pointer; }
  @media print { .knopf { display: none; } }
</style>
</head>
<body>
<button class="knopf" onclick="window.print()">🖨 Drucken</button>

<div class="kopf">
  <div>
    <h1>Strichliste Messe</h1>
    <div class="klein"><?= htmlspecialchars($firma) ?> · <?= htmlspecialchars($lagerName) ?></div>
  </div>
  <div class="meta">
    Messe-Nr. <?= $syncId ?> · gedruckt <?= date('d.m.Y') ?><br>
    Messe/Ort: ______________________ Datum: ____________
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Artikel</th>
      <th style="width:22mm">Charge</th>
      <th class="r" style="width:16mm">Preis €</th>
      <th class="r" style="width:10mm">mit</th>
      <th class="strich">verkauft (Stricherl)</th>
      <th class="feld r">Summe</th>
      <th class="feld r">zurück</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($gruppen as $name => $zeilen): ?>
    <tr class="gruppe"><td colspan="7"><?= htmlspecialchars($name) ?></td></tr>
    <?php foreach ($zeilen as $z): ?>
    <tr>
      <td><?= htmlspecialchars($z['bezeichnung']) ?> <span class="klein"><?= htmlspecialchars($z['artikelnummer'] ?? '') ?></span></td>
      <td class="klein"><?= htmlspecialchars($z['charge'] ?? '') ?></td>
      <td class="r"><?= $fmtEuro($z['preis']) ?></td>
      <td class="r"><strong><?= $fmtMenge($z['menge_raus']) ?></strong></td>
      <td class="strich"></td>
      <td class="feld"></td>
      <td class="feld"></td>
    </tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Freitext — ohne Lagerstand / Zugaben</h2>
<table>
  <thead>
    <tr>
      <th>Bezeichnung</th>
      <th style="width:35mm">Artikelgruppe</th>
      <th class="r" style="width:14mm">Menge</th>
      <th class="r" style="width:18mm">Preis €</th>
      <th style="width:16mm">Zugabe</th>
    </tr>
  </thead>
  <tbody>
  <?php for ($i = 0; $i < 10; $i++): ?>
    <tr><td></td><td class="strich"></td><td class="strich"></td><td class="strich"></td><td class="strich">☐</td></tr>
  <?php endfor; ?>
  </tbody>
</table>

<div class="fuss">
  <div>Händische Belege Nr. von ________ bis ________</div>
  <div>Gezählt von / Unterschrift</div>
</div>

</body>
</html>
