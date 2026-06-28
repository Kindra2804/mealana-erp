<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$service   = new AuftragService();
$auftrag   = $service->getById($id);
if (!$auftrag) {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$positionen      = $service->getPositionen($id);
$statuslog       = $service->getStatuslog($id);
$dokumentService = new DokumentService();
$dokumente       = $dokumentService->getDokumente($id);
$vorhandeneRechnung = $dokumentService->getRechnung($id);

$db = Database::getInstance();
$preisanzeige = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'preisanzeige_auftrag'")->fetchColumn() ?: 'brutto';

$zahlungen   = $service->getZahlungen($id);
$summeBezahlt = array_sum(array_column($zahlungen, 'betrag'));
$offenBetrag  = (float)$auftrag['bruttobetrag'] - $summeBezahlt;

$lieferungen = $db->prepare("
    SELECT al.tracking_nr, al.versanddienstleister, al.versand_datum, al.ist_teillieferung,
           b.formularname AS benutzer
    FROM auftrag_lieferungen al
    LEFT JOIN benutzer b ON b.id = al.benutzer_id
    WHERE al.auftrag_id = ?
    ORDER BY al.versand_datum ASC
");
$lieferungen->execute([$id]);
$lieferungen = $lieferungen->fetchAll(PDO::FETCH_ASSOC);

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
$sperrZustände = ['versendet', 'abgeschlossen', 'storniert'];

// Kassen-Auftrag: eigene Logik, keine normalen Dokumente erlaubt
$istKasse  = ($auftrag['kanal'] === 'kasse');
$kasseBon  = null;
if ($istKasse) {
    $stmtBon = $db->prepare("SELECT id, bon_nr FROM kassen_bons WHERE auftrag_id = :aid AND typ='verkauf' LIMIT 1");
    $stmtBon->execute([':aid' => $id]);
    $kasseBon = $stmtBon->fetch() ?: null;
}
$istGesperrt = in_array($auftrag['lieferstatus'], $sperrZustände);

// Adress-Snapshots dekodieren
$rgnAdr  = json_decode($auftrag['rechnungsadresse_snapshot'] ?? $auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
$liefAdr = json_decode($auftrag['lieferadresse_snapshot'] ?? '{}', true) ?: [];
$hatLiefAdr = !empty($liefAdr['strasse']);

function formatAdr(array $a): string {
    $zeilen = [];
    $name = trim(($a['vorname'] ?? '') . ' ' . ($a['nachname'] ?? ''));
    if (!empty($a['firmenname'])) $zeilen[] = '<strong>' . htmlspecialchars($a['firmenname']) . '</strong>';
    if ($name) $zeilen[] = htmlspecialchars($name);
    if (!empty($a['strasse'])) $zeilen[] = htmlspecialchars($a['strasse']);
    $plzOrt = trim(($a['plz'] ?? '') . ' ' . ($a['ort'] ?? ''));
    if ($plzOrt) $zeilen[] = htmlspecialchars($plzOrt);
    if (!empty($a['land']) && $a['land'] !== 'AT') $zeilen[] = htmlspecialchars($a['land']);
    return implode('<br>', $zeilen);
}

$pageTitle        = 'Auftrag ' . htmlspecialchars($auftrag['auftrag_nr']);
$activeModule     = 'verkauf';
$actionBarContent = '<a href="/mealana/auftraege/liste.php" class="btn btn-secondary btn-sm">← Liste</a>';
if (!$istGesperrt) {
    $actionBarContent .= '<a href="/mealana/auftraege/bearbeiten.php?id=' . $auftrag['id'] . '" class="btn btn-secondary btn-sm">Bearbeiten</a>';
}
if (!$istStorniert) {
    $actionBarContent .= '<div class="actionbar-sep"></div><div class="actionbar-right"><button type="button" class="btn btn-danger btn-sm" onclick="storniereAuftrag()">Stornieren</button></div>';
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
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:16px;padding:16px">

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

        <?php if (!empty($rgnAdr)): ?>
        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Rechnungsadresse</div>
            <div style="font-size:12px;line-height:1.6"><?= formatAdr($rgnAdr) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($hatLiefAdr): ?>
        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Lieferadresse</div>
            <div style="font-size:12px;line-height:1.6"><?= formatAdr($liefAdr) ?></div>
        </div>
        <?php endif; ?>

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Zahlung</div>
            <span class="chip <?= $zl['class'] ?>"><?= $zl['label'] ?></span>
            <div style="font-size:12px;color:var(--color-text-muted);margin-top:4px">
                <?= htmlspecialchars(ucfirst($auftrag['zahlungsart'])) ?>
                <?php if ($auftrag['bezahlt_am']): ?>
                    · <?= date('d.m.Y', strtotime($auftrag['bezahlt_am'])) ?>
                <?php endif; ?>
            </div>
            <?php if (!$istStorniert && $auftrag['zahlungsart'] === 'nachnahme'): ?>
                <form method="post" action="/mealana/auftraege/zahlungsart_aendern.php"
                      onsubmit="return confirm('Zahlungsart auf Vorkasse (Überweisung) umstellen? Der EasyPak-Export enthält dann keinen Nachnahme-Aufschlag mehr.')">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="zahlungsart" value="vorkasse">
                    <button type="submit" class="btn btn-secondary btn-sm" style="margin-top:6px;font-size:11px">
                        💳 Zahlung eingegangen – kein Nachnahme
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!$istStorniert && !empty($zahlungen)): ?>
                <div style="margin-top:10px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px">
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Zahlungsverlauf</div>
                    <?php foreach ($zahlungen as $z): ?>
                        <div style="display:flex;justify-content:space-between;font-size:12px;padding:2px 0;border-bottom:1px solid #e2e8f0">
                            <span style="color:var(--color-text-muted)"><?= date('d.m.Y', strtotime($z['buchungsdatum'])) ?><?= $z['notiz'] ? ' · ' . htmlspecialchars($z['notiz']) : '' ?></span>
                            <span style="font-weight:600;color:#059669">+<?= number_format((float)$z['betrag'], 2, ',', '.') ?> €</span>
                        </div>
                    <?php endforeach; ?>
                    <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0">
                        <?php if ($offenBetrag < 0): ?>
                            <span style="color:#d97706;font-weight:600">Überbezahlt</span>
                            <span style="font-weight:600;color:#d97706"><?= number_format(abs($offenBetrag), 2, ',', '.') ?> € Gutschrift</span>
                        <?php elseif ($offenBetrag > 0): ?>
                            <span style="color:var(--color-text-muted)">Offen</span>
                            <span style="font-weight:600;color:#dc2626"><?= number_format($offenBetrag, 2, ',', '.') ?> €</span>
                        <?php else: ?>
                            <span style="color:#059669;font-weight:600">Vollständig bezahlt</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!$istStorniert && in_array($auftrag['zahlungsstatus'], ['ausstehend','teilbezahlt'])): ?>
                <div style="margin-top:6px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px">
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Zahlung buchen</div>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <input type="number" id="zahl-betrag" class="erp-input" style="width:100px"
                               value="<?= $offenBetrag > 0 ? number_format($offenBetrag, 2, '.', '') : '' ?>"
                               step="0.01" min="0.01" placeholder="Betrag">
                        <input type="date" id="zahl-datum" class="erp-input" style="width:140px"
                               value="<?= date('Y-m-d') ?>">
                        <input type="text" id="zahl-notiz" class="erp-input" style="flex:1;min-width:80px"
                               placeholder="Notiz (optional)">
                        <button class="btn btn-primary btn-sm" onclick="zahlungBuchen(<?= $id ?>)">✓ Buchen</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px">Lieferstatus</div>
            <span class="chip <?= $ll['class'] ?>"><?= $ll['label'] ?></span>
            <?php if ($auftrag['versand_datum']): ?>
                <div style="margin-top:5px;font-size:12px;color:var(--color-text-muted)">
                    📅 <?= date('d.m.Y H:i', strtotime($auftrag['versand_datum'])) ?>
                </div>
            <?php endif; ?>
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
        <?php
        $dlLabels = [
            'post_at' => 'Österreichische Post',
            'dhl'     => 'DHL',
            'dpd'     => 'DPD',
            'gls'     => 'GLS',
        ];
        $trackingUrls = [
            'post_at' => 'https://www.post.at/sv/sendungsdetails?snr=',
            'dhl'     => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=',
            'dpd'     => 'https://tracking.dpd.de/status/de_DE/parcel/',
            'gls'     => 'https://gls-group.eu/track/',
        ];
        // Fallback auf alte Einzelspalte falls noch kein History-Eintrag
        $trackingNr  = $auftrag['tracking_nr'] ?: ($auftrag['versand_tracking'] ?? '');
        ?>
        <?php if (!empty($lieferungen)): ?>
        <!-- Lieferhistory-Tabelle -->
        <div style="border-top:1px solid var(--color-border);padding:12px 16px">
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:8px">
                Lieferungen / Tracking
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="color:var(--color-text-muted);border-bottom:1px solid var(--color-border)">
                        <th style="text-align:left;padding:4px 8px 6px 0;font-weight:600">#</th>
                        <th style="text-align:left;padding:4px 8px 6px 0;font-weight:600">Datum</th>
                        <th style="text-align:left;padding:4px 8px 6px 0;font-weight:600">Dienstleister</th>
                        <th style="text-align:left;padding:4px 8px 6px 0;font-weight:600">Tracking-Nr.</th>
                        <th style="text-align:left;padding:4px 0 6px 0;font-weight:600">Typ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lieferungen as $i => $lf): ?>
                    <tr style="border-bottom:1px solid var(--color-border-light,#f0f0f0)">
                        <td style="padding:5px 8px 5px 0;color:var(--color-text-muted)"><?= $i + 1 ?></td>
                        <td style="padding:5px 8px 5px 0;white-space:nowrap"><?= date('d.m.Y H:i', strtotime($lf['versand_datum'])) ?></td>
                        <td style="padding:5px 8px 5px 0"><?= htmlspecialchars($dlLabels[$lf['versanddienstleister']] ?? ($lf['versanddienstleister'] ?: '—')) ?></td>
                        <td style="padding:5px 8px 5px 0;font-family:monospace;font-weight:600">
                            <?php $tUrl = ($trackingUrls[$lf['versanddienstleister']] ?? '') . urlencode($lf['tracking_nr']); ?>
                            <?php if ($tUrl): ?>
                                <a href="<?= htmlspecialchars($tUrl) ?>" target="_blank" rel="noopener"
                                   style="color:var(--color-nav);text-decoration:none;font-family:monospace;font-weight:600">
                                    <?= htmlspecialchars($lf['tracking_nr']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($lf['tracking_nr']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="padding:5px 0">
                            <?php if ($lf['ist_teillieferung']): ?>
                                <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:10px">Teillieferung</span>
                            <?php else: ?>
                                <span style="font-size:10px;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:10px">Vollständig</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!empty($trackingNr)): ?>
        <!-- Fallback: Einzelnes Tracking aus alter Spalte (kein History-Eintrag) -->
        <div style="border-top:1px solid var(--color-border);padding:12px 16px">
            <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:6px">Tracking</div>
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                <?php if ($auftrag['versanddienstleister']): ?>
                <span style="font-size:13px;color:var(--color-text-muted)"><?= htmlspecialchars($dlLabels[$auftrag['versanddienstleister']] ?? $auftrag['versanddienstleister']) ?></span>
                <?php endif; ?>
                <?php $tUrlFb = ($trackingUrls[$auftrag['versanddienstleister'] ?? ''] ?? '') . urlencode($trackingNr); ?>
                <?php if ($tUrlFb): ?>
                    <a href="<?= htmlspecialchars($tUrlFb) ?>" target="_blank" rel="noopener"
                       style="font-size:14px;font-weight:600;font-family:monospace;color:var(--color-nav);text-decoration:none">
                        <?= htmlspecialchars($trackingNr) ?>
                    </a>
                <?php else: ?>
                    <span style="font-size:14px;font-weight:600;font-family:monospace"><?= htmlspecialchars($trackingNr) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tracking manuell hinzufügen (immer sichtbar wenn nicht storniert) -->
        <div style="border-top:1px solid var(--color-border);padding:10px 16px">
            <details style="font-size:12px">
                <summary style="cursor:pointer;color:var(--color-text-muted);user-select:none">
                    <?= !empty($lieferungen) ? '+ Weiteres Tracking hinzufügen' : 'Tracking eingeben' ?>
                </summary>
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:10px">
                    <div class="form-group" style="margin:0;flex:1;min-width:120px">
                        <label class="form-label" style="font-size:11px">Tracking-Nr.</label>
                        <input type="text" class="erp-input" id="tracking-nr" value="" placeholder="z.B. 12345678">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:120px">
                        <label class="form-label" style="font-size:11px">Versanddienstleister</label>
                        <select class="erp-select" id="versand-dl">
                            <option value="">—</option>
                            <option value="post_at">Österreichische Post</option>
                            <option value="dhl">DHL</option>
                            <option value="dpd">DPD</option>
                            <option value="gls">GLS</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="trackingSpeichern()">Speichern</button>
                </div>
            </details>
        </div>
    <?php endif; ?>
</div>

<!-- Positionen -->
<div class="card" style="margin-bottom:12px">
    <div class="card-header">Positionen</div>
    <?php
    $tabellenSpalten = 6; // Bild, Artikel, EAN, Menge, Geliefert, Rabatt — immer fix
    if (in_array($preisanzeige, ['brutto', 'beides'])) $tabellenSpalten += 2; // EP-Brutto + Gesamt-Brutto
    if (in_array($preisanzeige, ['netto', 'beides'])) $tabellenSpalten += 2; // EP-Netto + Gesamt-Netto
    $tfootColspan = $tabellenSpalten - 1; // alles außer der letzten Wertspalte
    ?>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:50px"></th>
                <th>Artikel</th>
                <th>EAN</th>
                <th style="text-align:center">Menge</th>
                <th style="text-align:center">Geliefert</th>
                <?php if (in_array($preisanzeige, ['brutto', 'beides'])): ?>
                    <th style="text-align:right">Einzelpreis (Brutto)</th>
                <?php endif; ?>
                <?php if (in_array($preisanzeige, ['netto', 'beides'])): ?>
                    <th style="text-align:right">Einzelpreis (Netto)</th>
                <?php endif; ?>
                <th style="text-align:right">Rabatt</th>
                <?php if (in_array($preisanzeige, ['brutto', 'beides'])): ?>
                    <th style="text-align:right">Gesamt (Brutto)</th>
                <?php endif; ?>
                <?php if (in_array($preisanzeige, ['netto', 'beides'])): ?>
                    <th style="text-align:right">Gesamt (Netto)</th>
                <?php endif; ?>
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
                    <?php if (in_array($preisanzeige, ['brutto', 'beides'])): ?>
                        <td style="text-align:right"><?= number_format((float)$p['einzelpreis_netto'] * (1 + $p['steuer_prozent'] / 100), 4, ',', '.') ?> €</td>
                    <?php endif; ?>
                    <?php if (in_array($preisanzeige, ['netto', 'beides'])): ?>
                        <td style="text-align:right"><?= number_format((float)$p['einzelpreis_netto'], 4, ',', '.') ?> €</td>
                    <?php endif; ?>
                    <td style="text-align:right">
                        <?= $p['rabatt_prozent'] > 0 ? number_format($p['rabatt_prozent'], 1, ',', '.') . ' %' : '—' ?>
                    </td>
                    <?php if (in_array($preisanzeige, ['brutto', 'beides'])): ?>
                        <td style="text-align:right;font-weight:600"><?= number_format((float)$p['gesamtpreis_netto'] * (1 + $p['steuer_prozent'] / 100), 2, ',', '.') ?> €</td>
                    <?php endif; ?>
                    <?php if (in_array($preisanzeige, ['netto', 'beides'])): ?>
                        <td style="text-align:right;font-weight:600"><?= number_format((float)$p['gesamtpreis_netto'], 2, ',', '.') ?> €</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- NACH der </table> -->
    <div style="display:flex;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--color-border)">
        <table style="min-width:220px">
            <tr>
                <td style="color:var(--color-text-muted);padding:2px 16px 2px 0">Netto</td>
                <td style="text-align:right;font-weight:600"><?= number_format($auftrag['nettobetrag'], 2, ',', '.') ?> €</td>
            </tr>
            <tr>
                <td style="color:var(--color-text-muted);padding:2px 16px 2px 0">MwSt.</td>
                <td style="text-align:right"><?= number_format($auftrag['steuerbetrag'], 2, ',', '.') ?> €</td>
            </tr>
            <?php if ($auftrag['versandkosten'] > 0): ?>
                <tr>
                    <td style="color:var(--color-text-muted);padding:2px 16px 2px 0">Versandkosten</td>
                    <td style="text-align:right"><?= number_format($auftrag['versandkosten'], 2, ',', '.') ?> €</td>
                </tr>
            <?php endif; ?>
            <tr style="font-size:15px;border-top:1px solid var(--color-border)">
                <td style="padding:6px 16px 2px 0;font-weight:700">Gesamt (Brutto)</td>
                <td style="text-align:right;font-weight:700"><?= number_format($auftrag['bruttobetrag'], 2, ',', '.') ?> €</td>
            </tr>
        </table>
    </div>

</div>

<!-- Notizen -->
<?php if ($auftrag['notiz_intern'] || $auftrag['notiz_versand']): ?>
    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Notizen</div>
        <div style="padding:12px 16px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <?php if ($auftrag['notiz_intern']): ?>
                <div>
                    <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">INTERN</div><?= nl2br(htmlspecialchars($auftrag['notiz_intern'])) ?>
                </div>
            <?php endif; ?>
            <?php if ($auftrag['notiz_versand']): ?>
                <div>
                    <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">VERSAND / PACKERL</div><?= nl2br(htmlspecialchars($auftrag['notiz_versand'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Statuslog -->
<?php if (!empty($statuslog)): ?>
    <div class="card">
        <div class="card-header" style="cursor:pointer;display:flex;justify-content:space-between;align-items:center"
             onclick="toggleAbschnitt('verlauf-body', this)">
            <span>Verlauf</span>
            <span class="toggle-arrow" style="font-size:11px;color:var(--color-text-muted)">▼</span>
        </div>
        <div id="verlauf-body" style="display:none">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Benutzer</th>
                    <th>Änderung</th>
                    <th>Notiz</th>
                </tr>
            </thead>
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
        </div><!-- /verlauf-body -->
    </div>
<?php endif; ?>

<!-- Dokumente -->
<div style="margin-top:24px;">
    <h3 style="margin-bottom:10px;">Dokumente</h3>

    <?php if ($istKasse): ?>
    <!-- Kassen-Auftrag: Kassenbon ist der Beleg, keine zusätzlichen Dokumente erlaubt -->
    <div style="background:#fff8e1; border:1px solid #f0c040; border-radius:6px;
                padding:12px 16px; margin-bottom:14px; font-size:0.92em; color:#7a5f00;">
        Dieser Auftrag wurde an der Kasse erstellt. Der Kassenbon ist der steuerliche Beleg —
        eine zusätzliche Rechnung würde zur Doppelbesteuerung führen und ist daher gesperrt.
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
        <?php if ($kasseBon): ?>
            <a href="/mealana/kasse/bon_druck.php?id=<?= $kasseBon['id'] ?>" target="_blank"
               class="erp-btn erp-btn-secondary">
                Kassenbon <?= htmlspecialchars($kasseBon['bon_nr']) ?> drucken
            </a>
        <?php else: ?>
            <span style="color:#aaa; font-size:0.9em;">Kassenbon nicht gefunden.</span>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Erzeugen-Buttons (normale Aufträge) -->
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
        <?php if ($vorhandeneRechnung): ?>
            <span style="display:inline-flex; align-items:center; gap:6px; padding:6px 10px;
                         border:1px solid #ccc; border-radius:4px; font-size:0.9em; color:#555;">
                &#10003; <?= htmlspecialchars($vorhandeneRechnung['rechnung_nr']) ?>
            </span>
            <a href="/mealana/auftraege/gutschrift_erstellen.php?auftrag_id=<?= $id ?>"
               class="erp-btn erp-btn-secondary">Gutschrift erstellen</a>
        <?php else: ?>
            <form method="post" action="/mealana/auftraege/dokument_erstellen.php" style="display:inline;">
                <input type="hidden" name="auftrag_id" value="<?= $id ?>">
                <input type="hidden" name="typ" value="rechnung">
                <button type="submit" class="erp-btn">Rechnung erstellen</button>
            </form>
        <?php endif; ?>
        <form method="post" action="/mealana/auftraege/dokument_erstellen.php" style="display:inline;">
            <input type="hidden" name="auftrag_id" value="<?= $id ?>">
            <input type="hidden" name="typ" value="auftragsbestaetigung">
            <button type="submit" class="erp-btn erp-btn-secondary">Auftragsbestätigung</button>
        </form>
        <form method="post" action="/mealana/auftraege/dokument_erstellen.php" style="display:inline;">
            <input type="hidden" name="auftrag_id" value="<?= $id ?>">
            <input type="hidden" name="typ" value="lieferschein">
            <button type="submit" class="erp-btn erp-btn-secondary">Lieferschein</button>
        </form>
        <?php if (($auftrag['lieferart'] ?? '') === 'abholung'): ?>
        <form method="post" action="/mealana/auftraege/dokument_erstellen.php" style="display:inline;">
            <input type="hidden" name="auftrag_id" value="<?= $id ?>">
            <input type="hidden" name="typ" value="abholzettel">
            <button type="submit" class="erp-btn erp-btn-secondary">Abholzettel</button>
        </form>
        <?php endif; ?>
    </div>
    <div style="margin-top:6px;font-size:11px;color:var(--color-text-muted)">
        📧 <strong>Auftragsbestätigung</strong> und <strong>Rechnung</strong> senden beim Erstellen automatisch eine E-Mail an den Kunden.
        Lieferschein und Abholzettel werden nur als PDF erstellt (kein automatisches Mail).
    </div>
    <?php endif; ?>

    <!-- Bereits erzeugte Dokumente (nur bei normalen Aufträgen) -->
    <?php if (!$istKasse && !empty($dokumente)): ?>
        <table class="erp-table" style="max-width:700px;">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Dateiname</th>
                    <th>Erstellt am</th>
                    <th>Von</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $typLabels = [
                    'rechnung'             => 'Rechnung',
                    'auftragsbestaetigung' => 'Auftragsbestätigung',
                    'lieferschein'         => 'Lieferschein',
                    'abholzettel'          => 'Abholzettel',
                    'gutschrift'           => 'Gutschrift',
                    'mahnung'              => 'Mahnung',
                ];
                foreach ($dokumente as $dok): ?>
                <tr>
                    <td><?= htmlspecialchars($typLabels[$dok['typ']] ?? $dok['typ']) ?></td>
                    <td><?= htmlspecialchars($dok['dateiname']) ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($dok['erstellt_am']))) ?></td>
                    <td><?= htmlspecialchars($dok['erstellt_von_name'] ?? '—') ?></td>
                    <td>
                        <a href="/mealana/auftraege/dokument_download.php?auftrag_id=<?= $id ?>&datei=<?= urlencode($dok['dateiname']) ?>"
                           class="erp-btn erp-btn-secondary erp-btn-sm" target="_blank">
                            PDF &darr;
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#888; font-size:0.9em;">Noch keine Dokumente erstellt.</p>
    <?php endif; ?>
</div>

<script>
    window.AUFTRAG_ID = <?= $id ?>;
    window.STATUS_AJAX_URL = '/mealana/auftraege/status_ajax.php';
    window.STORNO_URL = '/mealana/auftraege/stornieren.php';

    function toggleAbschnitt(bodyId, header) {
        const body   = document.getElementById(bodyId);
        const arrow  = header.querySelector('.toggle-arrow');
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        if (arrow) arrow.textContent = hidden ? '▲' : '▼';
    }
</script>
<script src="/mealana/js/auftraege_detail.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>