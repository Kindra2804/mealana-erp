<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../../src/modules/packplatz/RetourService.php';
require_once __DIR__ . '/../../../src/modules/dokumente/DokumentService.php';

$db           = Database::getInstance();
$lagerService = new LagerService();

// Auftrag per Nr oder ID laden
$auftragId = (int)($_GET['auftrag_id'] ?? 0);
$auftragNr = trim($_GET['auftrag_nr'] ?? '');

if (!$auftragId && $auftragNr) {
    $stmt = $db->prepare("SELECT id FROM auftraege WHERE auftrag_nr = :nr LIMIT 1");
    $stmt->execute([':nr' => $auftragNr]);
    $auftragId = (int)$stmt->fetchColumn();
}

if (!$auftragId) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php'); exit;
}

$stmt = $db->prepare("SELECT * FROM auftraege WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $auftragId]);
$auftrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auftrag) {
    $_SESSION['fehler'] = 'Auftrag nicht gefunden.';
    header('Location: index.php'); exit;
}
// Reiner Kassenverkauf (ohne Online-/manuellen Auftrag): Rückgabe läuft an der Kasse
if (($auftrag['kanal'] ?? '') === 'kasse') {
    $_SESSION['fehler'] = $auftrag['auftrag_nr'] . ' ist ein Kassenverkauf — Rückgabe bitte an der Kasse (Bon laden bzw. Freitext-Retour).';
    header('Location: index.php'); exit;
}

$positionen = $db->prepare("
    SELECT ap.*, a.zustand_vater_id, a.charge_pflicht,
           (SELECT code FROM artikel_codes WHERE artikel_id = ap.artikel_id AND typ = 'GTIN13' LIMIT 1) AS ean
    FROM auftrag_positionen ap
    LEFT JOIN artikel a ON a.id = ap.artikel_id
    WHERE ap.auftrag_id = :id
    ORDER BY ap.sort_order, ap.id
");
$positionen->execute([':id' => $auftragId]);
$positionen = $positionen->fetchAll(PDO::FETCH_ASSOC);

// Pro Position: was ist noch offen (gegen Doppel-Retoure/-Gutschrift, z.B. wenn die
// Kasse schon zurückgenommen hat) + welche Chargen gingen mit diesem Auftrag raus.
$retourSvc = new RetourService();
foreach ($positionen as &$p) {
    $p['offen_physisch'] = max(0, (int)$p['menge'] - (int)$p['menge_retourniert']);
    // korrigierbar ist nur, was verrechnet ist (Rechnung oder Kassenbon)
    $p['offen_gs']       = max(0, min((int)$p['menge'], (int)($p['menge_verrechnet'] ?? 0)) - (int)$p['menge_gutgeschrieben']);
    $p['verkauft']       = $p['artikel_id'] ? $retourSvc->verkaufteChargen($auftragId, (int)$p['artikel_id']) : [];
}
unset($p);

// Rechnung vorhanden?
$rechnung = $db->prepare("SELECT id, rechnung_nr FROM rechnungen WHERE auftrag_id = :id AND storniert = 0 ORDER BY id DESC LIMIT 1");
$rechnung->execute([':id' => $auftragId]);
$rechnung = $rechnung->fetch(PDO::FETCH_ASSOC);
// An der Kasse bezahlte Ware: Beleg ist der Kassenbon -> Rechnungskorrektur bezieht sich darauf
$bonBelege  = (new DokumentService())->kassenbonBelege($auftragId);
$belegText  = $rechnung ? 'Rg. ' . $rechnung['rechnung_nr'] : ($bonBelege ? implode(', ', array_column($bonBelege, 'nr')) : '');
$korrekturMoeglich = (bool)($rechnung || $bonBelege);

$alleLager = $lagerService->getAlleLager();

$kd = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
$kdName = trim(($kd['vorname'] ?? '') . ' ' . ($kd['nachname'] ?? ''));
if (!empty($kd['firma'])) $kdName = $kd['firma'] . ($kdName ? ' / ' . $kdName : '');
if (!$kdName) $kdName = $kd['name'] ?? '';
$kdEmail = $kd['email'] ?? '';

$pageTitle = 'Retoure — ' . htmlspecialchars($auftrag['auftrag_nr']);
$backUrl   = BASE_PATH . '/packplatz/retoure/index.php';
$headerSub = 'Retoure — ' . $auftrag['auftrag_nr'];
require_once __DIR__ . '/../shell_top.php';
?>

<style>
.ret-card { background:#16213e; border:1px solid #0f3460; border-radius:10px; padding:20px; margin-bottom:16px; }
.ret-input { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:16px; padding:8px 12px; outline:none; }
.ret-input:focus { border-color:#e94560; }
.ret-select { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:15px; padding:8px 10px; outline:none; }
.ret-table { width:100%; border-collapse:collapse; }
.ret-table th { background:#0f3460; color:#aaa; font-size:12px; text-align:left; padding:8px 12px; text-transform:uppercase; letter-spacing:.5px; }
.ret-table td { padding:10px 12px; border-bottom:1px solid #1a1a3e; font-size:14px; vertical-align:middle; }
.teil { display:flex; gap:6px; align-items:center; margin-bottom:6px; flex-wrap:wrap; }
.ret-mini { background:#0f3460; border:none; color:#ccc; border-radius:6px; padding:4px 10px; font-size:12px; cursor:pointer; }
</style>

<form method="post" action="speichern.php">
<input type="hidden" name="auftrag_id" value="<?= $auftragId ?>">
<?php if ($rechnung): ?>
    <input type="hidden" name="rechnung_id" value="<?= $rechnung['id'] ?>">
<?php endif; ?>

<div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start">

    <!-- Linke Spalte -->
    <div>
        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:12px;color:#e94560">
                Auftrag <?= htmlspecialchars($auftrag['auftrag_nr']) ?>
            </div>
            <div style="font-size:13px;color:#aaa;line-height:1.8">
                <div>Kunde: <span style="color:#eee"><?= htmlspecialchars($kdName ?: '—') ?></span></div>
                <div>Datum: <?= date('d.m.Y', strtotime($auftrag['erstellt_am'])) ?></div>
                <div>Betrag: <strong style="color:#eee"><?= number_format((float)$auftrag['bruttobetrag'], 2, ',', '.') ?> €</strong></div>
                <?php if ($rechnung): ?>
                    <div>Rechnung: <span style="color:#4caf50"><?= htmlspecialchars($rechnung['rechnung_nr']) ?></span></div>
                <?php else: ?>
                    <?php if ($bonBelege): ?>
                        <div>Beleg: <span style="color:#4caf50"><?= htmlspecialchars($belegText) ?></span> <span style="color:#888">(an der Kasse bezahlt)</span></div>
                    <?php else: ?>
                        <div style="color:#93c5fd">Noch kein Beleg (Rechnung/Kassenbon) — Rechnungskorrektur nicht möglich</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="ret-card">
            <div style="font-size:14px;font-weight:700;margin-bottom:12px;color:#aaa">Positionen auswählen</div>
            <table class="ret-table">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Artikel</th>
                        <th>Orig.</th>
                        <th>Menge · Charge · Zustand</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positionen as $i => $p): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="positionen[<?= $i ?>][checked]" value="1"
                                   id="chk<?= $i ?>" style="width:18px;height:18px;accent-color:#e94560">
                        </td>
                        <td>
                            <label for="chk<?= $i ?>" style="cursor:pointer">
                                <div style="font-weight:600"><?= htmlspecialchars($p['bezeichnung']) ?></div>
                                <?php if ($p['ean']): ?>
                                    <div style="font-size:11px;color:#6c8ebf"><?= htmlspecialchars($p['ean']) ?></div>
                                <?php endif; ?>
                            </label>
                            <input type="hidden" name="positionen[<?= $i ?>][pos_id]" value="<?= $p['id'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][artikel_id]" value="<?= $p['artikel_id'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][bezeichnung]" value="<?= htmlspecialchars($p['bezeichnung']) ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][einzelpreis_netto]" value="<?= $p['einzelpreis_netto'] ?>">
                            <input type="hidden" name="positionen[<?= $i ?>][steuer_prozent]" value="<?= $p['steuer_prozent'] ?>">
                        </td>
                        <td style="color:#aaa">
                            <?= (int)$p['menge'] ?>
                            <?php if ((int)$p['menge_retourniert'] > 0): ?>
                                <div style="font-size:11px;color:#ff9800">↩ <?= (int)$p['menge_retourniert'] ?> schon zurück</div>
                            <?php endif; ?>
                            <?php if ((int)$p['menge_gutgeschrieben'] > 0): ?>
                                <div style="font-size:11px;color:#ff9800">€ <?= (int)$p['menge_gutgeschrieben'] ?> gutgeschrieben</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['offen_physisch'] <= 0 || !$p['artikel_id']): ?>
                                <span style="color:#666;font-size:13px">Bereits vollständig zurückgekommen (Kasse/Rücklagerung/frühere Retoure)</span>
                                <script>document.getElementById('chk<?= $i ?>').disabled = true;</script>
                            <?php else: ?>
                                <?php if ($p['verkauft']): ?>
                                    <div style="font-size:11px;color:#6c8ebf;margin-bottom:4px">
                                        verkauft: <?= htmlspecialchars(implode(', ', array_map(fn($v) => ($v['charge'] ?? 'ohne Charge') . ' (' . (int)$v['menge'] . ')', $p['verkauft']))) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="teile" id="teile<?= $i ?>" data-idx="<?= $i ?>" data-max="<?= $p['offen_physisch'] ?>"
                                     data-pflicht="<?= $p['charge_pflicht'] ? 1 : 0 ?>"
                                     data-chargen="<?= htmlspecialchars(json_encode(array_values(array_filter(array_column($p['verkauft'], 'charge'))))) ?>"></div>
                                <button type="button" class="ret-mini" onclick="teilHinzufuegen(<?= $i ?>)">＋ Charge</button>
                                <span style="font-size:11px;color:#666">max. <?= $p['offen_physisch'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rechte Spalte: Aktion -->
    <div>
        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">Einbuchen in Lager</div>
            <select name="lager_id" class="ret-select" style="width:100%">
                <?php foreach ($alleLager as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ret-card">
            <div style="font-size:16px;font-weight:700;margin-bottom:14px;color:#e94560">Ergebnis</div>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee" id="lbl-gs">
                    <input type="radio" name="ergebnis" value="gutschrift" <?= $korrekturMoeglich ? '' : 'disabled' ?> onchange="ergebnisGewaehlt('gutschrift')"
                           style="width:18px;height:18px;accent-color:#e94560" <?= !$rechnung ? '' : '' ?>>
                    <div>
                        <div style="font-weight:600">Rechnungskorrektur erstellen</div>
                        <div style="font-size:11px;color:#aaa"><?= $korrekturMoeglich ? htmlspecialchars($belegText) : 'Kein Beleg vorhanden' ?></div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee">
                    <input type="radio" name="ergebnis" value="ersatz" onchange="ergebnisGewaehlt('ersatz')"
                           style="width:18px;height:18px;accent-color:#e94560">
                    <div>
                        <div style="font-weight:600">Ersatzlieferung</div>
                        <div style="font-size:11px;color:#aaa">Kein Dokument — nur Einbuchen</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee">
                    <input type="radio" name="ergebnis" value="nur_einbuchen" checked onchange="ergebnisGewaehlt('nur_einbuchen')"
                           style="width:18px;height:18px;accent-color:#e94560">
                    <div>
                        <div style="font-weight:600">Nur einbuchen</div>
                        <div style="font-size:11px;color:#aaa">Kein Dokument, kein Mail</div>
                    </div>
                </label>
            </div>

            <div id="gs-bereich" style="display:none;border-top:1px solid #0f3460;padding-top:12px;margin-top:4px">
                <label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Grund (Rechnungskorrektur)</label>
                <input type="text" name="gs_grund" class="ret-input" style="width:100%" placeholder="z.B. Reklamation, falsche Ware…">
                <?php if (!Auth::kann('packplatz.gutschrift')): ?>
                <label style="font-size:12px;color:#aaa;display:block;margin:10px 0 4px">🔒 Manager-PIN (Freigabe nötig)</label>
                <input type="password" name="manager_pin" class="ret-input" inputmode="numeric" pattern="\d{4,6}" maxlength="6"
                       style="width:100%;letter-spacing:4px" placeholder="PIN" autocomplete="off">
                <?php endif; ?>
            </div>
        </div>

        <?php if ($kdEmail): ?>
        <div class="ret-card">
            <div style="font-size:14px;font-weight:700;margin-bottom:10px;color:#aaa">E-Mail an Kunden</div>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:#eee;font-size:14px">
                <input type="checkbox" name="mail_senden" value="1" style="width:18px;height:18px;accent-color:#e94560">
                Benachrichtigung senden
            </label>
            <div style="font-size:12px;color:#555;margin-top:6px"><?= htmlspecialchars($kdEmail) ?></div>
            <div style="margin-top:10px">
                <label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Notiz (optional)</label>
                <input type="text" name="mail_notiz" class="ret-input" style="width:100%;font-size:14px" placeholder="Interne Anmerkung für den Kunden…">
            </div>
        </div>
        <?php else: ?>
        <div class="ret-card">
            <div style="font-size:13px;color:#555">⚠ Keine E-Mail-Adresse beim Kunden hinterlegt</div>
        </div>
        <?php endif; ?>

        <button type="submit" id="submit-btn" class="pp-btn pp-btn-success" style="width:100%;font-size:20px;padding:18px">
            ✓ Retoure verarbeiten
        </button>
    </div>

</div>
</form>

<!-- Overlay beim Absenden -->
<div id="sende-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px">
    <div style="width:52px;height:52px;border:5px solid #0f3460;border-top-color:#e94560;border-radius:50%;animation:spin .8s linear infinite"></div>
    <div style="color:#eee;font-size:18px;font-weight:700">Retoure wird verarbeitet…</div>
    <div style="color:#aaa;font-size:13px">Bitte warten</div>
</div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
function ergebnisGewaehlt(val) {
    document.getElementById('gs-bereich').style.display = val === 'gutschrift' ? 'block' : 'none';
}

// Teile-Editor: pro Position eine oder mehrere Zeilen (Menge · Charge · Zustand).
// Erste Zeile ist mit der ersten verkauften Charge vorbelegt.
var teilZaehler = {};
function teilHinzufuegen(idx, vorCharge) {
    var box = document.getElementById('teile' + idx);
    if (!box) return;
    var k = teilZaehler[idx] = (teilZaehler[idx] || 0) + 1;
    var chargen = JSON.parse(box.dataset.chargen || '[]');
    var listId = 'cl' + idx;
    if (!document.getElementById(listId)) {
        var dl = document.createElement('datalist');
        dl.id = listId;
        chargen.forEach(function(c) { var o = document.createElement('option'); o.value = c; dl.appendChild(o); });
        box.appendChild(dl);
    }
    var n = 'positionen[' + idx + '][teile][' + k + ']';
    var div = document.createElement('div');
    div.className = 'teil';
    div.innerHTML =
        '<input type="number" name="' + n + '[menge]" min="0" value="' + (k === 1 ? 1 : 0) + '" class="ret-input" style="width:64px;text-align:center" oninput="teilGeaendert(' + idx + ')">' +
        '<input type="text" name="' + n + '[charge]" list="' + listId + '" class="ret-input" style="width:120px;font-size:14px" placeholder="Charge' + (box.dataset.pflicht === '1' ? ' (Pflicht)' : '') + '">' +
        '<select name="' + n + '[zustand]" class="ret-select">' +
            '<option value="neu">Neu</option><option value="retour">Retour → -RET</option>' +
            '<option value="gebraucht">Gebraucht → -GEB</option><option value="beschaedigt">Beschädigt → -BSC</option>' +
            '<option value="defekt">Defekt (Schwund)</option></select>';
    box.appendChild(div);
    div.querySelector('input[type=text]').value = vorCharge || '';
    teilGeaendert(idx);
}
function teilGeaendert(idx) {
    var box = document.getElementById('teile' + idx);
    var summe = 0;
    box.querySelectorAll('input[type=number]').forEach(function(i) { summe += parseInt(i.value || 0, 10); });
    var chk = document.getElementById('chk' + idx);
    if (chk && !chk.disabled) chk.checked = summe > 0;
    box.style.outline = summe > parseInt(box.dataset.max, 10) ? '2px solid #e94560' : '';
}
document.querySelectorAll('.teile').forEach(function(box) {
    var chargen = JSON.parse(box.dataset.chargen || '[]');
    teilHinzufuegen(parseInt(box.dataset.idx, 10), chargen[0] || '');
    document.getElementById('chk' + box.dataset.idx).checked = false; // erst beim bewussten Anhaken/Ändern
});

document.querySelector('form').addEventListener('submit', function () {
    var overlay = document.getElementById('sende-overlay');
    overlay.style.display = 'flex';
    document.getElementById('submit-btn').disabled = true;
});
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
