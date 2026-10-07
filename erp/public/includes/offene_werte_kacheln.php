<?php
/**
 * Kacheln "Offene Werte" (Belege-Umbau 2026-10-07, Mockup docs/design/belege_spalte_uebersicht_mockup.svg).
 * Eingebunden in Buchhaltung → Zahlungs-Kontrolle und im Dashboard. Klick = gefilterte Auftragsliste.
 * Optional vor dem Einbinden: $offeneWerteKompakt = true (kleinere Schrift, z.B. Dashboard).
 */
require_once __DIR__ . '/../../src/modules/auftraege/OffeneWerte.php';

$ow      = OffeneWerte::berechnen();
$owKlein = !empty($offeneWerteKompakt);
$owEuro  = fn(float $b) => number_format($b, 2, ',', '.') . ' €';
$owListe = BASE_PATH . '/auftraege/liste.php';
$owKacheln = [
    [
        'titel' => 'Auftragsbestand', 'sub' => 'bestellt, noch nicht geliefert', 'balken' => '#7EC8E3', 'farbe' => '#1B3A6B',
        'wert'  => $owEuro($ow['bestand']['betrag']),
        'info'  => $ow['bestand']['anzahl'] . ' Aufträge · erwarteter Umsatz',
        'url'   => $owListe . '?belege=bestand',
    ],
    [
        'titel' => 'Geliefert, nicht verrechnet', 'sub' => 'sollte immer 0 sein', 'balken' => '#2563eb',
        'farbe' => $ow['nicht_verrechnet']['anzahl'] ? '#1e40af' : '#15803d',
        'wert'  => $owEuro($ow['nicht_verrechnet']['betrag']),
        'info'  => $ow['nicht_verrechnet']['anzahl'] ? $ow['nicht_verrechnet']['anzahl'] . ' Aufträge → Liste anzeigen' : 'alles verrechnet ✓',
        'url'   => $owListe . '?belege=nicht_verrechnet',
        'warn'  => $ow['nicht_verrechnet']['anzahl'] > 0,
    ],
    [
        'titel' => 'Offene Rechnungen', 'sub' => 'verrechnet, noch nicht bezahlt', 'balken' => '#fd7e14', 'farbe' => '#9a3412',
        'wert'  => $owEuro($ow['rechnungen_offen']['betrag']),
        'info'  => $ow['rechnungen_offen']['anzahl'] . ' Rechnungen'
                 . ($ow['rechnungen_offen']['ueberfaellig_anzahl']
                    ? ' · davon ' . $ow['rechnungen_offen']['ueberfaellig_anzahl'] . ' überfällig (' . $owEuro($ow['rechnungen_offen']['ueberfaellig_betrag']) . ')'
                    : ''),
        'url'   => $owListe . '?belege=rechnung_offen',
    ],
    [
        'titel' => 'Retoure offen', 'sub' => 'Ware zurück, Gutschrift/Einlagerung ausständig', 'balken' => '#f59e0b', 'farbe' => '#92400e',
        'wert'  => $ow['retoure_offen']['anzahl'] . ' Aufträge',
        'info'  => $owEuro($ow['retoure_offen']['betrag']) . ' noch zu erstatten · → Packplatz / Rechnungskorrektur',
        'url'   => $owListe . '?lieferung=retoure_offen',
    ],
];
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin-bottom:16px">
    <?php foreach ($owKacheln as $k): ?>
        <a href="<?= htmlspecialchars($k['url']) ?>" style="display:block;position:relative;background:#fff;border:1px solid var(--color-border);
           border-radius:10px;padding:<?= $owKlein ? '12px 14px' : '16px 18px' ?>;text-decoration:none;color:inherit;border-top:6px solid <?= $k['balken'] ?>">
            <div style="font-size:13px;color:var(--color-text-muted)"><?= $k['titel'] ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= $k['sub'] ?></div>
            <div style="font-size:<?= $owKlein ? '22px' : '28px' ?>;font-weight:700;color:<?= $k['farbe'] ?>;margin:8px 0 4px"><?= $k['wert'] ?></div>
            <div style="font-size:12px;color:var(--color-text-muted)"><?= $k['info'] ?></div>
            <?php if (!empty($k['warn'])): ?>
                <span class="beleg-warn" style="position:absolute;right:14px;top:<?= $owKlein ? '40px' : '48px' ?>;width:26px;height:26px;line-height:26px;
                      border-radius:50%;background:#2563eb;color:#fff;font-weight:700;text-align:center">!</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
