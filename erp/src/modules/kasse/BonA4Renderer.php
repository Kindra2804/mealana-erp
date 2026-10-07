<?php

require_once __DIR__ . '/KassenService.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/QrCode.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';

/**
 * Baut die A4-Rechnungsansicht eines Kassenbons als HTML.
 * Wird sowohl von der Browser-Seite (bon_a4.php) als auch beim
 * Mailversand (Dompdf-Rendering für den PDF-Anhang) genutzt.
 */
class BonA4Renderer
{
    /** @param bool $fuerPdf true = ohne Druck-/Schließen-Buttons (für Dompdf-Anhang) */
    public static function render(int $bonId, bool $fuerPdf = false): ?string
    {
        $service = new KassenService();
        $bon     = $service->getBon($bonId);
        if (!$bon) {
            return null;
        }

        $db = Database::getInstance();

        // Firmen-Stammdaten
        $keys = ['firmenname','firma_email','firma_strasse','firma_plz','firma_ort','firma_tel','firma_uid','firma_web','firma_iban','firma_bic','firma_bank'];
        $konfig = $db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('" . implode("','", $keys) . "')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $firmenname = $konfig['firmenname'] ?? 'MeaLana';

        // Logo — Kasse ist nicht shop-gebunden, nimmt bewusst Shop 1 (Ladengeschäft) als
        // physische Filiale. Gleicher logo_pfad-Mechanismus wie bei den normalen Web-Auftrag-
        // Dokumenten (DokumentService::ladeDaten()), dort schon funktionierend.
        $logoPfad = $db->query("SELECT logo_pfad FROM shops WHERE id = 1")->fetchColumn();
        $logoDateipfad = $logoPfad ? __DIR__ . '/../../../public/' . $logoPfad : null;
        $logoBase64 = ($logoDateipfad && file_exists($logoDateipfad)) ? base64_encode(file_get_contents($logoDateipfad)) : '';

        // Kundendaten (wenn Stammkunde) — Name/Adresse sind AES-verschlüsselt,
        // deshalb über KundenRepository (entschlüsselt), keine rohe SQL-Abfrage.
        $kundeZeilen = [];
        $kd = null;
        if ($bon['kunden_id']) {
            $kundenRepo = new KundenRepository();
            $kd = $kundenRepo->findById((int)$bon['kunden_id']);
            if ($kd) {
                $adresse = $kundenRepo->findAdressen((int)$bon['kunden_id'])[0] ?? null;

                if ($kd['firmenname']) $kundeZeilen[] = $kd['firmenname'];
                $name = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
                if ($name) $kundeZeilen[] = $name;
                if ($adresse) {
                    $strasseZeile = trim(($adresse['strasse'] ?? '') . ' ' . ($adresse['hausnummer'] ?? ''));
                    if ($strasseZeile) $kundeZeilen[] = $strasseZeile;
                    if ($adresse['plz'] || $adresse['ort']) $kundeZeilen[] = trim(($adresse['plz'] ?? '') . ' ' . ($adresse['ort'] ?? ''));
                    if ($adresse['land'] && $adresse['land'] !== 'AT') $kundeZeilen[] = $adresse['land'];
                }
            }
        }
        if (empty($kundeZeilen)) $kundeZeilen = ['Laufkunde / Barzahler'];

        // Steuer-Totale
        $steuerTotale = [];
        $nettoBetrag  = 0;
        $steuerSumme  = 0;
        $gutscheinSumme = 0; // Gutschein-Kauf/-Ausgabe: nicht steuerbar, eigene Summenzeile
        $zahlungSumme   = 0; // Zahlbeleg: Zahlung auf eine Rechnung, USt steht schon auf der Rechnung
        foreach ($bon['positionen'] as $p) {
            $rohmenge = (float)$p['menge'];
            // Retour- und Zahlungszeilen (Rückzahlung negativ) behalten ihr Vorzeichen
            $menge    = in_array($p['block'], ['retour', 'zahlung'], true) ? $rohmenge : abs($rohmenge);
            $brutto   = $menge * (float)$p['einzelpreis_brutto'] * (1 - (float)$p['rabatt_prozent'] / 100);
            $satz     = (float)$p['steuer_prozent'];
            $netto    = $satz > 0 ? $brutto / (1 + $satz / 100) : $brutto;
            $steuer   = $brutto - $netto;
            $key      = number_format($satz, 0);
            if (!isset($steuerTotale[$key])) $steuerTotale[$key] = ['satz' => $satz, 'netto' => 0, 'steuer' => 0, 'brutto' => 0];
            $steuerTotale[$key]['netto']  += $netto;
            $steuerTotale[$key]['steuer'] += $steuer;
            $steuerTotale[$key]['brutto'] += $brutto;
            $steuerSumme += $steuer;
            if (($p['block'] ?? null) === 'zahlung') {
                $zahlungSumme += $brutto;
            } elseif (in_array($p['block'] ?? null, ['gutschein_kauf', 'gutschein_verkauf'], true)) {
                $gutscheinSumme += $brutto;
            } else {
                $nettoBetrag += $netto;
            }
        }

        $bonBrutto    = (float)$bon['bruttobetrag'];
        $istRetour    = $bonBrutto < -0.005;
        $istStorno    = $bon['typ'] === 'storno';
        $bruttoBetrag = abs($bonBrutto);
        $stSign       = $istStorno ? '-' : '';

        $zahlungsartLabel = [
            'bar'          => 'Bar',
            'karte_extern' => 'Karte (extern)',
            'gutschein'    => 'Gutschein',
            'gutschein_ausgabe' => 'Erstattung als Gutschein',
            'kombi'        => 'Bar + Karte',
        ][$bon['zahlungsart']] ?? $bon['zahlungsart'];

        // Gutscheine rund um diesen Bon (gleiche Logik wie kasse/bon_druck.php)
        $gsStmt = $db->prepare("
            SELECT g.code, g.betrag, g.gueltig_bis
            FROM gutschein_transaktionen t JOIN gutscheine g ON g.id = t.gutschein_id
            WHERE t.kassen_bon_id = ? AND t.betrag > 0
            ORDER BY t.id
        ");
        $gsStmt->execute([(int)$bon['id']]);
        $gsAusgestellt = $gsStmt->fetchAll(PDO::FETCH_ASSOC);
        $gsRest = null;
        if (!empty($bon['gutschein_id'])) {
            $gsStmt = $db->prepare("SELECT code, betrag FROM gutscheine WHERE vorgaenger_gutschein_id = ? ORDER BY id LIMIT 1");
            $gsStmt->execute([(int)$bon['gutschein_id']]);
            $gsRest = $gsStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // Positionen gruppieren
        $posBlocks = [];
        foreach ($bon['positionen'] as $pos) {
            $posBlocks[$pos['block'] ?? 'normal'][] = $pos;
        }

        // Ein Bon mit Minusbetrag ist trotzdem ein Kassenbeleg -- "Gutschrift" stellt nur der
        // Leistungsempfänger aus (Belege-Umbau 2026-10-07). Beträge stehen mit echtem Vorzeichen.
        $dokumentTitel = $istStorno ? 'STORNO' : 'KASSENBELEG';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= $dokumentTitel ?> <?= htmlspecialchars($bon['bon_nr']) ?></title>
  <style>
    @page { size: A4 portrait; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 10pt;
      color: #1e293b;
      background: #fff;
      /* Body-Padding statt @page-margin — Dompdf wendet @page-margin beim
         PDF-Export (Mailanhang) unzuverlässig an, Body-Padding funktioniert
         in beiden Fällen (Screen + Dompdf), siehe rechnung/standard.html.twig */
      padding: 15mm 18mm;
    }

    <?php if (!$fuerPdf): ?>
    /* Screen: zusätzlicher Seitenrahmen (Padding kommt schon von body oben) */
    @media screen {
      body { max-width: 210mm; margin: 20px auto; background: #fff;
             box-shadow: 0 2px 16px rgba(0,0,0,.12); min-height: 297mm; }
    }
    <?php endif; ?>

    /* ── Kopfbereich ── */
    .kopf { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12mm; }
    .firma-block { max-width: 55%; }
    .firma-name  { font-size: 18pt; font-weight: 700; color: #1e3a5f; line-height: 1.2; }
    .firma-zeile { font-size: 9pt; color: #64748b; margin-top: 2px; }
    .dok-block   { text-align: right; }
    .dok-titel   { font-size: 16pt; font-weight: 700; color: #1e3a5f; letter-spacing: .5px; }
    .dok-nr      { font-size: 11pt; font-weight: 700; color: #1e293b; margin-top: 4px; }
    .dok-datum   { font-size: 9pt; color: #64748b; margin-top: 2px; }

    <?php if ($istStorno || $istRetour): ?>
    .dok-titel { color: #dc2626; }
    <?php endif; ?>

    /* ── Empfängerblock ── */
    .empfaenger { margin-bottom: 8mm; }
    .emp-label  { font-size: 8pt; color: #94a3b8; letter-spacing: .5px; margin-bottom: 4px; }
    .emp-zeile  { font-size: 10pt; line-height: 1.5; }

    /* ── Infoleiste ── */
    .infobar {
      display: flex; gap: 20px; background: #f8fafc;
      border: 1px solid #e2e8f0; border-radius: 6px;
      padding: 8px 14px; margin-bottom: 7mm; font-size: 9pt;
    }
    .infobar-item label { color: #64748b; display: block; font-size: 8pt; }
    .infobar-item span  { font-weight: 700; color: #1e293b; }

    /* ── Positionstabelle ── */
    table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    thead th {
      background: #1e3a5f; color: #fff; font-size: 9pt; font-weight: 700;
      padding: 6px 8px; text-align: left;
    }
    thead th.r { text-align: right; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 5px 8px; font-size: 9.5pt; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    tbody td.r { text-align: right; white-space: nowrap; }
    .pos-sub { font-size: 8pt; color: #64748b; margin-top: 1px; }
    .block-header td { background: #e2e8f0 !important; font-weight: 700; font-size: 9pt; color: #1e3a5f; }
    .retour-zeile td { color: #dc2626; }

    /* ── Summenbereich ── */
    .summen { margin-left: auto; width: 220px; margin-bottom: 6mm; }
    .sum-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 10pt; }
    .sum-row.gesamt {
      border-top: 2px solid #1e3a5f; margin-top: 4px; padding-top: 6px;
      font-size: 13pt; font-weight: 700; color: #1e3a5f;
    }
    .sum-row.sub { font-size: 9pt; color: #64748b; }

    /* ── Steueraufstellung ── */
    .steuer-box {
      border: 1px solid #e2e8f0; border-radius: 6px;
      padding: 8px 12px; font-size: 8.5pt; margin-bottom: 8mm;
    }
    .steuer-box table { margin: 0; }
    .steuer-box thead th { background: #e2e8f0; color: #1e293b; font-size: 8pt; padding: 4px 8px; }
    .steuer-box tbody td { font-size: 8.5pt; padding: 3px 8px; border: none; }

    /* ── Zahlungsinfo ── */
    .zahl-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 10px 14px; margin-bottom: 8mm; font-size: 9.5pt; }
    .zahl-box .zahl-row { display: flex; justify-content: space-between; padding: 2px 0; }
    .zahl-box .zahl-row.fett { font-weight: 700; }

    /* ── Fusszeile ── */
    .fusszeile { border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 8pt; color: #94a3b8; display: flex; justify-content: space-between; }

    <?php if (!$fuerPdf): ?>
    /* ── Druck-Button (nur Screen) ── */
    @media screen {
      .druck-leiste { display: flex; gap: 10px; margin-bottom: 20px; }
      .druck-btn { padding: 10px 22px; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; }
      .druck-btn-prim { background: #1e3a5f; color: #fff; }
      .druck-btn-sec  { background: #e2e8f0; color: #1e293b; }
    }
    @media print { .druck-leiste { display: none !important; } }
    <?php endif; ?>
  </style>
</head>
<body>

<?php if (!$fuerPdf): ?>
<div class="druck-leiste">
  <button class="druck-btn druck-btn-prim" onclick="window.print()">🖨 A4 drucken / als PDF speichern</button>
  <button class="druck-btn druck-btn-sec" onclick="window.close()">✕ Schließen</button>
</div>
<?php endif; ?>

<!-- ── KOPF ── -->
<div class="kopf">
  <div class="firma-block">
    <?php if ($logoBase64): ?>
    <img src="data:image/png;base64,<?= $logoBase64 ?>" style="max-height:16mm;max-width:50mm;margin-bottom:3mm">
    <?php endif; ?>
    <div class="firma-name"><?= htmlspecialchars($firmenname) ?></div>
    <?php foreach (['firma_strasse','firma_plz firma_ort','firma_tel','firma_email','firma_uid','firma_web'] as $k): ?>
      <?php
        $zeile = '';
        if ($k === 'firma_plz firma_ort') {
            $plz = $konfig['firma_plz'] ?? ''; $ort = $konfig['firma_ort'] ?? '';
            $zeile = trim("$plz $ort");
        } elseif (isset($konfig[$k]) && $konfig[$k] !== '') {
            $prefix = match($k) { 'firma_uid' => 'UID: ', 'firma_tel' => 'Tel: ', 'firma_email' => '', 'firma_web' => '', default => '' };
            $zeile = $prefix . $konfig[$k];
        }
        if ($zeile):
      ?>
      <div class="firma-zeile"><?= htmlspecialchars($zeile) ?></div>
    <?php endif; endforeach; ?>
  </div>
  <div class="dok-block">
    <div class="dok-titel"><?= $dokumentTitel ?></div>
    <div class="dok-nr"><?= htmlspecialchars($bon['bon_nr']) ?></div>
    <div class="dok-datum"><?= date('d.m.Y H:i', strtotime($bon['erstellt_am'])) ?> Uhr</div>
    <div class="dok-datum" style="margin-top:4px;color:#1e293b">Kasse: <?= htmlspecialchars($bon['kasse_nr'] ?? 'K1') ?></div>
  </div>
</div>

<!-- ── EMPFÄNGER ── -->
<div class="empfaenger">
  <div class="emp-label">RECHNUNGSEMPFÄNGER</div>
  <?php foreach ($kundeZeilen as $z): ?>
    <div class="emp-zeile"><?= htmlspecialchars($z) ?></div>
  <?php endforeach; ?>
  <?php if (!empty($kd['kundennummer'])): ?>
    <div class="emp-zeile" style="color:#94a3b8;font-size:8.5pt;margin-top:2px">Kd.-Nr.: <?= htmlspecialchars($kd['kundennummer']) ?></div>
  <?php endif; ?>
</div>

<!-- ── INFOLEISTE ── -->
<div class="infobar">
  <div class="infobar-item">
    <label>Belegnummer</label>
    <span><?= htmlspecialchars($bon['bon_nr']) ?></span>
  </div>
  <div class="infobar-item">
    <label>Datum</label>
    <span><?= date('d.m.Y', strtotime($bon['erstellt_am'])) ?></span>
  </div>
  <div class="infobar-item">
    <label>Zahlungsart</label>
    <span><?= htmlspecialchars($zahlungsartLabel) ?></span>
  </div>
  <?php $alleNr = array_column($bon['web_auftraege'] ?? [], 'auftrag_nr') ?: array_filter([$bon['web_auftrag_nr'] ?? '']); ?>
  <?php if ($alleNr): ?>
  <div class="infobar-item">
    <label><?= count($alleNr) > 1 ? 'Aufträge' : 'Auftrag' ?></label>
    <span><?= htmlspecialchars(implode(', ', $alleNr)) ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- ── POSITIONEN ── -->
<table>
  <thead>
    <tr>
      <th style="width:32px">#</th>
      <th>Artikel</th>
      <th class="r" style="width:55px">Menge</th>
      <th class="r" style="width:75px">E-Preis</th>
      <th class="r" style="width:55px">Rabatt</th>
      <th class="r" style="width:80px">Gesamt</th>
    </tr>
  </thead>
  <tbody>
<?php
$zeilennr = 0;
$renderBlock = function(array $positionen, string $blockLabel = '') use (&$zeilennr, $stSign) {
    if ($blockLabel): ?>
    <tr class="block-header"><td colspan="6"><?= htmlspecialchars($blockLabel) ?></td></tr>
<?php endif;
    foreach ($positionen as $pos):
        $zeilennr++;
        $menge  = (float)$pos['menge'];
        $preis  = (float)$pos['einzelpreis_brutto'];
        $rabatt = (float)$pos['rabatt_prozent'];
        $gesamt = abs($menge) * $preis * (1 - $rabatt / 100);
        // Rückgabe / Rückzahlung (negative Menge) mit Minus bei Menge und Betrag
        $istRetourZeile = $pos['block'] === 'retour' || ($pos['block'] === 'zahlung' && $menge < 0);
        $cls = $istRetourZeile ? ' class="retour-zeile"' : '';
        $vorzeichen = $istRetourZeile ? '-' : $stSign;
        $satz = number_format((float)$pos['steuer_prozent'], 0);
?>
    <tr<?= $cls ?>>
      <td><?= $zeilennr ?></td>
      <td>
        <?= htmlspecialchars($pos['bezeichnung']) ?>
        <div class="pos-sub"><?= $satz ?>% MwSt<?= $pos['charge'] ? ' · Partie: ' . htmlspecialchars($pos['charge']) : '' ?></div>
      </td>
      <td class="r"><?= ($istRetourZeile ? '-' : '') . abs($menge) ?></td>
      <td class="r">€ <?= number_format(abs($preis), 2, ',', '.') ?></td>
      <td class="r"><?= $rabatt > 0 ? number_format($rabatt, 0) . ' %' : '—' ?></td>
      <td class="r"><strong><?= $vorzeichen ?>€ <?= number_format($gesamt, 2, ',', '.') ?></strong></td>
    </tr>
<?php endforeach; };

// Sammelabholung: je Auftrag ein eigener Block mit seiner Nummer
$auftragNr = array_column($bon['web_auftraege'] ?? [], 'auftrag_nr', 'id');
$auftragGruppen = [];
foreach ($posBlocks['auftrag'] ?? [] as $pos) {
    $auftragGruppen[(int)($pos['web_auftrag_id'] ?? 0)][] = $pos;
}
foreach ($auftragGruppen as $gid => $gPositionen) {
    $nr = $auftragNr[$gid] ?? $bon['web_auftrag_nr'] ?? '';
    $renderBlock($gPositionen, $nr ? 'Auftrag ' . $nr : 'Auftrag');
}
$vorherBezahlt = array_column(array_filter($bon['web_auftraege'] ?? [], fn($a) => $a['vorher_bezahlt']), 'auftrag_nr');
if ($vorherBezahlt): ?>
    <tr class="block-header"><td colspan="6">Abgeholt, bereits bezahlt: <?= htmlspecialchars(implode(', ', $vorherBezahlt)) ?></td></tr>
<?php endif;
if (isset($posBlocks['retour'])) {
    $retourNr = array_values(array_unique(array_filter(array_map(fn($p) => $auftragNr[(int)($p['web_auftrag_id'] ?? 0)] ?? null, $posBlocks['retour']))));
    if (!$retourNr && !empty($bon['web_auftrag_nr']) && count($auftragNr) <= 1) $retourNr = [$bon['web_auftrag_nr']];
    $renderBlock($posBlocks['retour'], $retourNr ? 'Rückgabe aus Auftrag ' . implode(', ', $retourNr) : 'Rückgabe');
}
// alles außer Auftrag/Retour (normal, addon, Gutschein verkauft/ausgegeben)
$rest = array_merge(...array_values(array_diff_key($posBlocks, ['auftrag' => 1, 'retour' => 1])));
if ($rest)                         $renderBlock($rest, (isset($posBlocks['auftrag']) || isset($posBlocks['retour']) || $vorherBezahlt) ? 'Weitere Positionen' : '');
?>
  </tbody>
</table>

<!-- ── STEUERAUFSTELLUNG ── -->
<?php if (count($steuerTotale) > 0): ?>
<div class="steuer-box">
  <table>
    <thead><tr><th>Steuersatz</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr></thead>
    <tbody>
    <?php foreach ($steuerTotale as $st): ?>
      <tr>
        <td><?= number_format($st['satz'], 0) ?> %</td>
        <td class="r">€ <?= number_format($st['netto'], 2, ',', '.') ?></td>
        <td class="r">€ <?= number_format($st['steuer'], 2, ',', '.') ?></td>
        <td class="r">€ <?= number_format($st['brutto'], 2, ',', '.') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ── SUMMEN ── -->
<div class="summen">
  <?php
  // Rückgabe/Storno: wie der Gesamtbetrag ohne Vorzeichen (aus Kundensicht), sonst wie berechnet
  $sumVz = 1; // Rückgabe: Summen bleiben negativ (Minus), Storno rechnet Positionen ohne Vorzeichen + "−"
  ?>
  <div class="sum-row sub">
    <span>Nettobetrag:</span>
    <span>€ <?= number_format($sumVz * $nettoBetrag, 2, ',', '.') ?></span>
  </div>
  <div class="sum-row sub">
    <span>MwSt gesamt:</span>
    <span>€ <?= number_format($sumVz * $steuerSumme, 2, ',', '.') ?></span>
  </div>
  <?php if (abs($zahlungSumme) > 0.004): ?>
  <div class="sum-row sub">
    <span><?= $zahlungSumme < 0 ? 'Rückzahlung Anzahlung (nicht steuerbar):' : 'Zahlung auf Rechnung (USt bereits auf der Rechnung):' ?></span>
    <span>€ <?= number_format($sumVz * $zahlungSumme, 2, ',', '.') ?></span>
  </div>
  <?php endif; ?>
  <?php if (abs($gutscheinSumme) > 0.004): ?>
  <div class="sum-row sub">
    <span>Gutschein (nicht steuerbar):</span>
    <span>€ <?= number_format($sumVz * $gutscheinSumme, 2, ',', '.') ?></span>
  </div>
  <?php endif; ?>
  <div class="sum-row gesamt">
    <span><?= $istStorno ? 'STORNO' : ($istRetour ? 'GESAMT (Auszahlung)' : 'GESAMT') ?>:</span>
    <span><?= ($istStorno || $istRetour) ? '-' : '' ?>€ <?= number_format($bruttoBetrag, 2, ',', '.') ?></span>
  </div>
</div>

<!-- ── ZAHLUNGSDETAIL ── -->
<div class="zahl-box">
  <div class="zahl-row"><span>Zahlungsart:</span><span><?= htmlspecialchars($zahlungsartLabel) ?></span></div>
  <?php if ($bon['zahlungsart'] === 'bar' && $istRetour && !$istStorno): // Auszahlung aus Kundensicht ?>
    <div class="zahl-row"><span>Gegeben:</span><span>€ 0,00</span></div>
    <div class="zahl-row fett"><span>Rückgeld:</span><span>€ <?= number_format($bruttoBetrag, 2, ',', '.') ?></span></div>
  <?php elseif ($bon['zahlungsart'] === 'bar' && $bon['gegeben'] !== null): ?>
    <div class="zahl-row"><span>Gegeben:</span><span>€ <?= number_format((float)$bon['gegeben'], 2, ',', '.') ?></span></div>
    <div class="zahl-row fett"><span>Rückgeld:</span><span>€ <?= number_format((float)($bon['rueckgeld'] ?? 0), 2, ',', '.') ?></span></div>
  <?php elseif ($bon['zahlungsart'] === 'kombi'): ?>
    <div class="zahl-row"><span>Karte:</span><span>€ <?= number_format((float)$bon['karten_betrag'], 2, ',', '.') ?></span></div>
    <div class="zahl-row"><span>Bar:</span><span>€ <?= number_format((float)$bon['bar_betrag'], 2, ',', '.') ?></span></div>
    <?php if ((float)($bon['rueckgeld'] ?? 0) > 0): ?>
      <div class="zahl-row fett"><span>Rückgeld:</span><span>€ <?= number_format((float)$bon['rueckgeld'], 2, ',', '.') ?></span></div>
    <?php endif; ?>
  <?php elseif ($bon['zahlungsart'] === 'gutschein'): ?>
    <div class="zahl-row"><span>Gutschein-Code:</span><span><?= htmlspecialchars($bon['gutschein_code'] ?? '') ?></span></div>
    <?php if ($bon['gutschein_betrag'] !== null): ?>
      <div class="zahl-row"><span>Gutschein:</span><span>€ <?= number_format((float)$bon['gutschein_betrag'], 2, ',', '.') ?></span></div>
    <?php endif; ?>
    <?php if ((float)($bon['bar_betrag'] ?? 0) > 0): ?>
      <div class="zahl-row"><span>Rest bar:</span><span>€ <?= number_format((float)$bon['bar_betrag'], 2, ',', '.') ?></span></div>
      <?php if ($bon['gegeben'] !== null): ?>
        <div class="zahl-row"><span>Gegeben:</span><span>€ <?= number_format((float)$bon['gegeben'], 2, ',', '.') ?></span></div>
        <div class="zahl-row fett"><span>Rückgeld:</span><span>€ <?= number_format((float)($bon['rueckgeld'] ?? 0), 2, ',', '.') ?></span></div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ((float)($bon['karten_betrag'] ?? 0) > 0): ?>
      <div class="zahl-row"><span>Rest Karte:</span><span>€ <?= number_format((float)$bon['karten_betrag'], 2, ',', '.') ?></span></div>
    <?php endif; ?>
    <?php if ($gsRest): ?>
      <div class="zahl-row fett"><span>Restguthaben (neuer Code <?= htmlspecialchars($gsRest['code']) ?>):</span><span>€ <?= number_format((float)$gsRest['betrag'], 2, ',', '.') ?></span></div>
    <?php endif; ?>
  <?php endif; ?>
  <?php foreach ($gsAusgestellt as $ga): ?>
    <div class="zahl-row fett"><span>Gutschein-Code <?= htmlspecialchars($ga['code']) ?><?= $ga['gueltig_bis'] ? ' (gültig bis ' . date('d.m.Y', strtotime($ga['gueltig_bis'])) . ')' : '' ?>:</span><span>€ <?= number_format((float)$ga['betrag'], 2, ',', '.') ?></span></div>
  <?php endforeach; ?>
</div>

<!-- ── BANKVERBINDUNG (wenn vorhanden) ── -->
<?php if (!empty($konfig['firma_iban'])): ?>
<div style="font-size:8.5pt;color:#64748b;margin-bottom:6mm">
  Bankverbindung: <?= htmlspecialchars($konfig['firma_bank'] ?? '') ?>
  · IBAN: <?= htmlspecialchars($konfig['firma_iban']) ?>
  <?= !empty($konfig['firma_bic']) ? '· BIC: ' . htmlspecialchars($konfig['firma_bic']) : '' ?>
</div>
<?php endif; ?>

<!-- ── RKSV ── -->
<?php if (!empty($bon['rksv_signatur'])): ?>
<div style="margin-bottom:6mm;border-top:1px solid #e2e8f0;padding-top:4px;display:flex;align-items:center;gap:8px">
  <?php if ($bon['rksv_qr']): ?>
  <img src="<?= QrCode::dataUri($bon['rksv_qr'], 300) ?>" style="width:30mm;height:30mm">
  <?php endif; ?>
  <div style="font-size:8pt;color:#94a3b8">RKSV: <?= htmlspecialchars($bon['rksv_signatur']) ?></div>
</div>
<?php endif; ?>

<!-- ── FUSSZEILE ── -->
<div class="fusszeile">
  <span><?= htmlspecialchars($firmenname) ?><?= !empty($konfig['firma_uid']) ? ' · UID: ' . htmlspecialchars($konfig['firma_uid']) : '' ?></span>
  <span>Kassenbeleg <?= htmlspecialchars($bon['bon_nr']) ?> · <?= date('d.m.Y', strtotime($bon['erstellt_am'])) ?></span>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
