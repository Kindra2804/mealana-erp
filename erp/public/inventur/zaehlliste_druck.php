<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/inventur/InventurService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service = new InventurService();
$laufId  = (int)($_GET['lauf_id'] ?? 0);
$lauf    = $service->getById($laufId);
if (!$lauf) {
    die('<p style="font-family:sans-serif;padding:20px">Inventur-Lauf nicht gefunden.</p>');
}

$modus = $_GET['modus'] ?? 'alles';

$lagerplaetzeFuerAuswahl = $lauf['scope_tabelle'] === 'lager'
    ? (new LagerService())->getAlleLagerplaetze((int)$lauf['scope_id'], 1)
    : [];

$sollListe              = [];
$blankoLagerplatz       = null;
$zeigeLagerplatzHinweis = false;
$leereZeilenAnzahl      = 30;

if ($modus === 'lagerplatz') {
    if (!empty($_GET['lagerplatz_id'])) {
        $lpId = (int)$_GET['lagerplatz_id'];
        foreach ($lagerplaetzeFuerAuswahl as $lp) {
            if ((int)$lp['id'] === $lpId) { $blankoLagerplatz = $lp; break; }
        }
    }
    if (!$blankoLagerplatz) {
        $zeigeLagerplatzHinweis = true;
    }
} else {
    $sollListe = $service->getSollListe($lauf);
    if ($modus === 'artikel' && !empty($_GET['q'])) {
        $q = mb_strtolower(trim($_GET['q']));
        $sollListe = array_filter($sollListe, function ($s) use ($q) {
            return str_contains(mb_strtolower($s['artikel_name']), $q)
                || str_contains(mb_strtolower($s['artikelnummer']), $q);
        });
    }
}

$positionen = $service->getPositionenFuerLauf($laufId);
$positionenIndex = [];
foreach ($positionen as $p) {
    $key = $p['artikel_id'] . '|' . $p['lager_id'] . '|' . ($p['lagerplatz_id'] ?? '') . '|' . ($p['charge'] ?? '');
    $positionenIndex[$key] = $p;
}

$scopeLabels = [
    'lager'        => 'Ganzes Lager',
    'lagerplaetze' => 'Lagerplatz',
    'kategorien'   => 'Kategorie',
    'artikel'      => 'Einzelner Artikel',
    'mietfaecher'  => 'Mietfach',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Zählliste — <?= htmlspecialchars($lauf['scope_bezeichnung']) ?></title>
<style>
  @page { size: A4 portrait; margin: 14mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #1e293b; background: #fff; }

  @media screen {
    body { max-width: 210mm; margin: 20px auto; padding: 18mm; background: #fff; box-shadow: 0 2px 16px rgba(0,0,0,.12); min-height: 297mm; }
  }
  @media print { .aktions-leiste, .filter-leiste { display: none !important; } }

  .aktions-leiste { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .al-btn { padding: 10px 22px; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: none; }
  .al-prim { background: #1e3a5f; color: #fff; }
  .al-sec  { background: #e2e8f0; color: #1e293b; }

  .filter-leiste { display: flex; gap: 10px; align-items: end; margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; flex-wrap: wrap; }
  .filter-leiste label { display: block; font-size: 11px; color: #64748b; margin-bottom: 3px; }
  .filter-leiste select, .filter-leiste input { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit; }

  .kopf  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8mm; border-bottom: 3px solid #1e3a5f; padding-bottom: 5mm; }
  .titel { font-size: 16pt; font-weight: 800; color: #1e3a5f; }
  .sub   { font-size: 9pt; color: #64748b; margin-top: 2px; }
  .meta  { text-align: right; font-size: 9pt; color: #64748b; }

  table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
  th { text-align: left; border-bottom: 2px solid #1e3a5f; padding: 5px 6px; font-size: 8.5pt; text-transform: uppercase; color: #1e3a5f; }
  td { border-bottom: 1px solid #e2e8f0; padding: 6px 6px; }
  .schreib-spalte { border-bottom: 1px solid #94a3b8; height: 20px; }
</style>
</head>
<body>

<div class="aktions-leiste">
  <button type="button" class="al-btn al-prim" onclick="window.print()">🖨 Drucken</button>
  <a href="<?= BASE_PATH ?>/inventur/zaehlen.php?lauf_id=<?= $laufId ?>" class="al-btn al-sec">← Zurück zur Zählliste</a>
</div>

<?php if ($lauf['scope_tabelle'] === 'lager'): ?>
<form class="filter-leiste" method="get">
  <input type="hidden" name="lauf_id" value="<?= $laufId ?>">
  <div>
    <label>Ansicht</label>
    <select name="modus" onchange="this.form.submit()">
      <option value="alles" <?= $modus === 'alles' ? 'selected' : '' ?>>Gesamte Zählliste</option>
      <option value="artikel" <?= $modus === 'artikel' ? 'selected' : '' ?>>Bestimmter Artikel (Suche)</option>
      <option value="lagerplatz" <?= $modus === 'lagerplatz' ? 'selected' : '' ?>>Ein Lagerplatz (Blanko-Liste)</option>
    </select>
  </div>
  <?php if ($modus === 'artikel'): ?>
  <div>
    <label>Suche (Name/Nummer)</label>
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
  </div>
  <?php endif; ?>
  <?php if ($modus === 'lagerplatz'): ?>
  <div>
    <label>Lagerplatz</label>
    <select name="lagerplatz_id">
      <option value="">— wählen —</option>
      <?php foreach ($lagerplaetzeFuerAuswahl as $lp): ?>
      <option value="<?= $lp['id'] ?>" <?= (int)($_GET['lagerplatz_id'] ?? 0) === (int)$lp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lp['bezeichnung']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if (in_array($modus, ['artikel', 'lagerplatz'], true)): ?>
  <button type="submit" class="al-btn al-sec" style="padding:7px 16px">Anwenden</button>
  <?php endif; ?>
</form>
<?php endif; ?>

<div class="kopf">
  <div>
    <div class="titel">Inventur-Zählliste</div>
    <div class="sub">
      <?= $scopeLabels[$lauf['scope_tabelle']] ?? htmlspecialchars($lauf['scope_tabelle']) ?>:
      <?= htmlspecialchars($blankoLagerplatz['bezeichnung'] ?? $lauf['scope_bezeichnung']) ?>
    </div>
  </div>
  <div class="meta">
    Gedruckt am <?= date('d.m.Y H:i') ?><br>
    <?= $lauf['blind_modus'] ? 'Blind-Modus (Soll ausgeblendet)' : 'Soll sichtbar' ?>
  </div>
</div>

<?php if ($blankoLagerplatz): ?>
  <table>
    <thead><tr><th>Artikel</th><th>Charge</th><th style="width:70px">Menge</th><th>Notiz</th></tr></thead>
    <tbody>
      <?php for ($i = 0; $i < $leereZeilenAnzahl; $i++): ?>
      <tr><td class="schreib-spalte">&nbsp;</td><td class="schreib-spalte">&nbsp;</td><td class="schreib-spalte">&nbsp;</td><td class="schreib-spalte">&nbsp;</td></tr>
      <?php endfor; ?>
    </tbody>
  </table>
<?php elseif ($zeigeLagerplatzHinweis): ?>
  <p style="color:#64748b;padding:20px 0">Bitte oben einen Lagerplatz wählen.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Artikel</th>
        <th>Lager</th>
        <th>Charge</th>
        <?php if (!$lauf['blind_modus']): ?><th style="text-align:right">Soll</th><?php endif; ?>
        <th style="width:80px">Ist</th>
        <th>Notiz</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sollListe as $s):
          $key = $s['artikel_id'] . '|' . $s['lager_id'] . '|' . ($s['lagerplatz_id'] ?? '') . '|' . ($s['charge'] ?? '');
          $bestehend = $positionenIndex[$key] ?? null;
      ?>
      <tr>
        <td><?= htmlspecialchars($s['artikel_name']) ?> <span style="color:#94a3b8;font-size:8pt">(<?= htmlspecialchars($s['artikelnummer']) ?>)</span></td>
        <td><?= htmlspecialchars($s['lager_name']) ?></td>
        <td><?= htmlspecialchars($s['charge'] ?? '—') ?></td>
        <?php if (!$lauf['blind_modus']): ?>
        <td style="text-align:right"><?= $s['soll_menge'] !== null ? number_format((float)$s['soll_menge'], 0) : '—' ?></td>
        <?php endif; ?>
        <td class="schreib-spalte"><?= $bestehend ? number_format((float)$bestehend['ist_menge'], 0) : '' ?></td>
        <td class="schreib-spalte"><?= htmlspecialchars($bestehend['notiz'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($sollListe)): ?>
      <tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px">Keine Positionen gefunden.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>

</body>
</html>
