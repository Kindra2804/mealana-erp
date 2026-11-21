<?php
/**
 * Verkauf → Zahlungseingänge (2026-11-21, Entwurf docs/design/zahlungseingaenge_mockup.svg):
 * Babsis Kontencheck — alle Aufträge mit offenem Betrag (Vorkasse, Rechnung, PayPal, Nachnahme)
 * in einer Liste, Zahlung Zeile für Zeile einbuchen. Gebucht wird über zahlung_buchen.php,
 * also wie im Auftrag selbst (Zahlungsmail, Shop-Status, Abschluss-Prüfung).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';

$service = new AuftragService();
$liste   = $service->getOffeneZahlungseingaenge();
$heute   = $service->getZahlungenHeuteVon((int)($_SESSION['benutzer']['id'] ?? 0));

const ZE_ZAHLART = ['vorkasse' => 'Vorkasse', 'rechnung' => 'Rechnung', 'paypal' => 'PayPal', 'nachnahme' => 'Nachnahme', 'gemischt' => 'Gemischt'];
const ZE_WEGE    = ['ueberweisung' => '🏦 Bank', 'paypal' => '🅿 PayPal', 'nachnahme' => '📦 Nachnahme', 'sonstig' => 'Sonstig'];
const ZE_MAHNUNG = ['erinnerung' => 'Erinnerung verschickt', 'mahnung1' => '1. Mahnung verschickt', 'mahnung2' => '2. Mahnung verschickt',
                    'hinweis' => 'Hinweis verschickt', 'stornierung' => 'Storno-Mail verschickt'];

function zeEur(float $v): string { return number_format($v, 2, ',', '.'); }

// Zähler für die Zahlart-Chips
$anzahl = ['alle' => count($liste), 'ueberfaellig' => 0];
$summeOffen = $summeUeber = 0.0;
foreach ($liste as $a) {
    $anzahl[$a['zahlungsart']] = ($anzahl[$a['zahlungsart']] ?? 0) + 1;
    $summeOffen += $a['offen'];
    if ($a['faellig_tage'] < 0) { $anzahl['ueberfaellig']++; $summeUeber += $a['offen']; }
}
$heuteSumme = array_sum(array_map(fn($z) => (float)$z['betrag'], $heute));

$pageTitle    = 'Zahlungseingänge';
$activeModule = 'verkauf';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.ze-kopf { display:flex; gap:16px; align-items:flex-start; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; }
.ze-kopf p { margin:0; color:var(--color-text-muted); font-size:13px; }
.ze-heute { background:#eaf7ef; border:1px solid #9fd5b2; border-radius:8px; padding:8px 14px; min-width:280px; }
.ze-heute-titel { font-size:11px; font-weight:700; color:#1e6b3a; letter-spacing:.3px; }
.ze-heute-zahl { font-size:17px; font-weight:700; color:#1e6b3a; display:flex; justify-content:space-between; align-items:baseline; gap:12px; }
.ze-heute-zahl button { background:none; border:none; color:#1e6b3a; font-size:12px; cursor:pointer; padding:0; }
.ze-heute-liste { display:none; margin-top:6px; font-size:12px; color:#1e6b3a; max-height:220px; overflow:auto; }
.ze-heute-liste.offen { display:block; }
.ze-heute-liste div { display:flex; justify-content:space-between; gap:12px; border-top:1px solid #cdeedb; padding:2px 0; }

.ze-filter { background:#fff; border:1px solid var(--color-border, #e2e8f0); border-radius:8px; padding:12px 16px; margin-bottom:12px;
             display:flex; flex-wrap:wrap; gap:12px 20px; align-items:flex-end; }
.ze-tab td > .ze-klein { white-space:nowrap; }
.ze-suche { width:300px; max-width:100%; font-size:15px !important; padding:7px 10px !important; border:2px solid #2563eb !important; }
.ze-hilfe { font-size:11px; color:var(--color-text-muted); line-height:1.35; }
.ze-chips { display:flex; flex-wrap:wrap; gap:6px; width:100%; }
.ze-chip { border:1px solid #cfd6df; background:#fff; color:#2b3a4d; border-radius:14px; padding:3px 12px; font-size:12px; cursor:pointer; }
.ze-chip.aktiv { background:#2563eb; border-color:#2563eb; color:#fff; }
.ze-chip.rot { background:#fdecec; border-color:#f2b8b5; color:#b42318; }
.ze-chip.rot.aktiv { background:#b42318; border-color:#b42318; color:#fff; }

.ze-tab { width:100%; border-collapse:collapse; background:#fff; font-size:13px; }
.ze-tab th { background:#f7f9fb; color:#64748b; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase;
             padding:8px; text-align:left; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.ze-tab td { padding:8px; border-bottom:1px solid #eef1f5; vertical-align:top; }
.ze-tab .r { text-align:right; white-space:nowrap; }
.ze-tab .trenner { border-left:1px solid #e2e8f0; }
.ze-klein { font-size:11px; color:var(--color-text-muted); }
.ze-nr { font-weight:700; color:#2563eb; white-space:nowrap; }
.ze-rot { color:#b42318; font-weight:700; }
.ze-zeile.ze-aktiv td { background:#fff8e1; }
.ze-zeile.ze-aktiv td:first-child { box-shadow:inset 4px 0 0 #f0a500; }
.ze-zeile input, .ze-zeile select { height:30px; }
.ze-betrag { width:90px; text-align:right; }
.ze-betrag.teil { border-color:#f0a500 !important; }
.ze-datum { width:135px; }
.ze-hinweis { font-size:11px; color:#a05a00; margin-top:3px; white-space:nowrap; }
.ze-hinweis.ueber { color:#b42318; }
.ze-meldung td { background:#eaf7ef; color:#1e6b3a; font-weight:600; vertical-align:middle; }
.ze-meldung.fertig td { transition:opacity 1s; }
.ze-meldung .ze-nr { color:#64748b; text-decoration:line-through; }
.ze-leer { text-align:center; color:#94a3b8; padding:24px !important; }
.ze-fuss { padding:10px 16px; font-size:12px; color:var(--color-text-muted); display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; }
</style>

<div class="ze-kopf">
    <p>Kontoauszug (Sparkasse / PayPal) daneben öffnen, Betrag oder Auftragsnummer suchen, <b>Enter</b> bucht.<br>
       Nach dem Klick 8 Sekunden Zeit für „Rückgängig“ — erst dann gehen Zahlungsmail und Shop-Status raus.</p>
    <div class="ze-heute">
        <div class="ze-heute-titel">HEUTE VON DIR GEBUCHT</div>
        <div class="ze-heute-zahl">
            <span><span id="ze-heute-anzahl"><?= count($heute) ?></span> Zahlungen · <span id="ze-heute-summe"><?= zeEur($heuteSumme) ?></span> €</span>
            <button type="button" id="ze-heute-knopf">Liste ▾</button>
        </div>
        <div class="ze-heute-liste" id="ze-heute-liste">
            <?php foreach ($heute as $z): ?>
                <div><span><?= date('H:i', strtotime($z['erfasst_am'])) ?> · <?= htmlspecialchars($z['auftrag_nr']) ?> · <?= ZE_WEGE[$z['zahlungsweg']] ?? htmlspecialchars((string)$z['zahlungsweg']) ?></span><b><?= zeEur((float)$z['betrag']) ?></b></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="ze-filter">
    <div>
        <input type="search" id="ze-suche" class="erp-input ze-suche" placeholder="🔍 Nr., Name oder Betrag …" autocomplete="off" autofocus>
    </div>
    <div class="ze-hilfe">Auftrags-/Rechnungsnummer, Name oder<br>Betrag – Enter springt in die erste Zeile</div>
    <div class="form-group" style="margin:0">
        <label class="form-label" for="ze-datum-alle">Buchungsdatum vorbelegen</label>
        <input type="date" id="ze-datum-alle" class="erp-input" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="form-group" style="margin:0">
        <label class="form-label" for="ze-sort">Sortierung</label>
        <select id="ze-sort" class="erp-select">
            <option value="faellig">Älteste Fälligkeit zuerst</option>
            <option value="bestellt">Älteste Bestellung zuerst</option>
            <option value="zahlart">nach Zahlart</option>
            <option value="betrag">Höchster offener Betrag zuerst</option>
        </select>
    </div>
    <div class="ze-chips" id="ze-chips">
        <button type="button" class="ze-chip aktiv" data-filter="alle">Alle <span><?= $anzahl['alle'] ?></span></button>
        <?php foreach (ZE_ZAHLART as $art => $label): if (empty($anzahl[$art])) continue; ?>
            <button type="button" class="ze-chip" data-filter="<?= $art ?>"><?= $label ?> <span><?= $anzahl[$art] ?></span></button>
        <?php endforeach; ?>
        <?php if ($anzahl['ueberfaellig']): ?>
            <button type="button" class="ze-chip rot" data-filter="ueberfaellig">🔴 überfällig <span><?= $anzahl['ueberfaellig'] ?></span></button>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding:0;overflow-x:auto">
    <table class="ze-tab">
        <thead>
            <tr>
                <th>Auftrag</th><th>Kunde</th><th>Kanal</th><th>Zahlart</th><th>Fällig</th><th class="r">Offen €</th>
                <th class="trenner">Betrag €</th><th>Datum</th><th>Eingang auf</th><th></th>
            </tr>
        </thead>
        <tbody id="ze-body">
        <?php foreach ($liste as $a):
            $ft = $a['faellig_tage'];
            if ($ft < 0)      $faelligText = '<span class="ze-rot">🔴 seit ' . -$ft . ($ft === -1 ? ' Tag' : ' Tagen') . '</span>';
            elseif ($ft === 0) $faelligText = '<b>heute</b>';
            else              $faelligText = 'in ' . $ft . ($ft === 1 ? ' Tag' : ' Tagen');
            $unter = [];
            if ($a['letzte_mahnung']) $unter[] = ZE_MAHNUNG[$a['letzte_mahnung']] ?? $a['letzte_mahnung'];
            if ($a['storno_in'] !== null && $a['storno_in'] <= 16) {
                $unter[] = $a['storno_in'] > 0 ? 'Auto-Storno in ' . $a['storno_in'] . ' T.' : 'Auto-Storno fällig';
            }
            if ($a['zahlungsstatus'] === 'teilbezahlt') $unter[] = 'teilbezahlt';
            if ($a['kanal'] === 'woocommerce' && $a['shop_id']) {
                $kanal = '<span class="kc kc-s' . (int)$a['shop_id'] . '" title="' . htmlspecialchars($a['shop_name'] ?? '') . '">'
                       . htmlspecialchars($a['nr_kuerzel'] !== '' ? $a['nr_kuerzel'] : 'S' . (int)$a['shop_id']) . '</span>';
            } else {
                $kanal = '<span class="ze-klein">' . ($a['kanal'] === 'haendler' ? 'Händler' : 'Manuell') . '</span>';
            }
            $suche = mb_strtolower($a['auftrag_nr'] . ' ' . $a['kunden_name'] . ' ' . ($a['kundennummer'] ?? '') . ' ' . ($a['rechnung_nr'] ?? ''));
        ?>
            <tr class="ze-zeile" data-id="<?= (int)$a['id'] ?>" data-nr="<?= htmlspecialchars($a['auftrag_nr']) ?>"
                data-zahlart="<?= $a['zahlungsart'] ?>" data-faellig="<?= $a['faellig_am'] ?>" data-faellig-tage="<?= $ft ?>"
                data-bestellt="<?= substr($a['erstellt_am'], 0, 10) ?>" data-offen="<?= $a['offen'] ?>"
                data-brutto="<?= (float)$a['bruttobetrag'] ?>" data-suche="<?= htmlspecialchars($suche) ?>">
                <td><a class="ze-nr" href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= (int)$a['id'] ?>" target="_blank" data-no-loader><?= htmlspecialchars($a['auftrag_nr']) ?></a></td>
                <td>
                    <?= htmlspecialchars($a['kunden_name']) ?>
                    <div class="ze-klein">Bestellt <?= date('d.m.', strtotime($a['erstellt_am'])) ?><?= $a['rechnung_nr'] ? ' · ' . htmlspecialchars($a['rechnung_nr']) : '' ?></div>
                </td>
                <td><?= $kanal ?></td>
                <td><?= ZE_ZAHLART[$a['zahlungsart']] ?? htmlspecialchars($a['zahlungsart']) ?></td>
                <td style="white-space:nowrap"><?= $faelligText ?><?php if ($unter): ?><div class="ze-klein"><?= htmlspecialchars(implode(' · ', $unter)) ?></div><?php endif; ?></td>
                <td class="r"><b class="ze-offen"><?= zeEur($a['offen']) ?></b>
                    <?php if ((float)$a['gebuehren'] > 0): ?><div class="ze-klein">inkl. <?= zeEur((float)$a['gebuehren']) ?> Mahngeb.</div><?php endif; ?></td>
                <td class="trenner">
                    <input type="text" inputmode="decimal" class="erp-input ze-betrag" value="<?= zeEur($a['offen']) ?>" aria-label="Betrag">
                    <div class="ze-hinweis" hidden></div>
                </td>
                <td><input type="date" class="erp-input ze-datum" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" aria-label="Buchungsdatum"></td>
                <td>
                    <select class="erp-select ze-weg" aria-label="Eingang auf">
                        <?php foreach (ZE_WEGE as $w => $label): ?>
                            <option value="<?= $w ?>" <?= $w === $a['weg_vorschlag'] ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" class="btn btn-primary btn-sm ze-buchen" style="background:#1e8e3e;border-color:#1e8e3e;white-space:nowrap">✓ Buchen</button></td>
            </tr>
        <?php endforeach; ?>
        <tr id="ze-leer" <?= $liste ? 'hidden' : '' ?>><td colspan="10" class="ze-leer"><?= $liste ? 'Keine Treffer für diese Suche.' : '🎉 Keine offenen Zahlungen.' ?></td></tr>
        </tbody>
    </table>
    <div class="ze-fuss">
        <span>Gesamt offen: <b id="ze-gesamt"><?= count($liste) ?> Aufträge · <?= zeEur($summeOffen) ?> €</b>
            <?php if ($anzahl['ueberfaellig']): ?> (davon überfällig <?= $anzahl['ueberfaellig'] ?> · <?= zeEur($summeUeber) ?> €)<?php endif; ?></span>
        <span>⏎ bucht die Zeile und springt zur nächsten · ↑ ↓ Zeile wechseln · Esc leert die Suche · Nummer anklicken öffnet den Auftrag</span>
    </div>
</div>

<p class="ze-klein" style="margin-top:10px">Nicht in der Liste: Kassen-Aufträge, Bar/Karte bei Abholung (wird an der Kasse bezahlt),
PayPal-Bestellungen aus dem Shop (kommen bezahlt an), JTL-Archiv, stornierte Aufträge.</p>

<script src="<?= BASE_PATH ?>/js/zahlungseingaenge.js?v=<?= filemtime(__DIR__ . '/../js/zahlungseingaenge.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
