<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service    = new BestellungService();
$id         = (int)($_GET['id'] ?? 0);
$bestellung = $service->getById($id);
if (!$bestellung) { header('Location: /mealana/bestellungen/liste.php'); exit; }

$positionen = $service->getPositionen($id);
$nr         = BestellungService::bestellnummer($id, $bestellung['bestelldatum']);

$erfolg  = $_SESSION['erfolg'] ?? '';
$fehler  = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$statusLabels = [
    'offen'         => ['label' => 'Offen',         'class' => 'chip-aktiv'],
    'teilgeliefert' => ['label' => 'Teilgeliefert', 'class' => 'chip-auslauf'],
    'erledigt'      => ['label' => 'Erledigt',      'class' => 'chip-inaktiv'],
    'storniert'     => ['label' => 'Storniert',     'class' => 'chip-inaktiv'],
    'entwurf'       => ['label' => 'Entwurf',       'class' => 'chip-inaktiv'],
];
$sl = $statusLabels[$bestellung['status']] ?? ['label' => $bestellung['status'], 'class' => ''];

$kannWareneingang = in_array($bestellung['status'], ['offen', 'teilgeliefert']);
$istAbgeschlossen = in_array($bestellung['status'], ['erledigt', 'storniert']);

// Aktivitäten
$db    = Database::getInstance();
$stmt  = $db->prepare("
    SELECT a.erstellt_am, a.aktion, a.details, b.formularname
    FROM aktivitaeten a
    LEFT JOIN benutzer b ON b.id = a.benutzer_id
    WHERE a.referenz_tabelle = 'bestellungen' AND a.referenz_id = :id
    ORDER BY a.erstellt_am DESC LIMIT 20
");
$stmt->execute(['id' => $id]);
$aktivitaeten = $stmt->fetchAll();

$pageTitle    = $nr;
$activeModule = 'einkauf';
$actionBarContent = '<a href="/mealana/bestellungen/liste.php" class="btn btn-secondary btn-sm">← Liste</a>';
if (!$istAbgeschlossen) {
    $actionBarContent .= ' <a href="/mealana/bestellungen/bearbeiten.php?id=' . $id . '" class="btn btn-secondary btn-sm">Bearbeiten</a>';
    $actionBarContent .= ' <a href="/mealana/wareneingang/detail.php?bestellung_id=' . $id . '" class="btn btn-primary btn-sm">Wareneingang →</a>';
    $actionBarContent .= '<div class="actionbar-sep"></div><div class="actionbar-right"><form method="post" action="/mealana/bestellungen/stornieren.php" onsubmit="return confirm(\'Bestellung stornieren?\')"><input type="hidden" name="id" value="' . $id . '"><button type="submit" class="btn btn-danger btn-sm">Stornieren</button></form></div>';
}

require_once __DIR__ . '/../includes/shell_top.php';

$gesamtEk = array_sum(array_map(fn($p) => $p['menge_bestellt'] * ($p['ek_preis'] ?? 0), $positionen));
?>

<?php if ($erfolg): ?>
    <div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>

<!-- Kopfdaten -->
<div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <div style="font-size:18px;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($nr) ?></div>
            <div style="font-size:13px;color:var(--color-text-muted)"><?= htmlspecialchars($bestellung['lieferant_name']) ?></div>
        </div>
        <span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px;font-size:13px">
        <div><span style="color:var(--color-text-muted)">Bestellt:</span><br><?= date('d.m.Y', strtotime($bestellung['bestelldatum'])) ?></div>
        <div><span style="color:var(--color-text-muted)">Erwartet:</span><br><?= $bestellung['erwartet_am'] ? date('d.m.Y', strtotime($bestellung['erwartet_am'])) : ($bestellung['lieferzeit_text'] ? htmlspecialchars($bestellung['lieferzeit_text']) : '—') ?></div>
        <div><span style="color:var(--color-text-muted)">Zahlungsart:</span><br><?= htmlspecialchars($bestellung['zahlungsart'] ?? '—') ?></div>
        <div><span style="color:var(--color-text-muted)">AB-Nr.:</span><br><?= htmlspecialchars($bestellung['ab_nummer'] ?? '—') ?></div>
    </div>
    <?php if ($bestellung['notiz']): ?>
        <div style="margin-top:10px;font-size:13px;color:var(--color-text-muted)">Notiz: <?= htmlspecialchars($bestellung['notiz']) ?></div>
    <?php endif; ?>
    <?php if ($bestellung['gutschrift_betrag']): ?>
        <div style="margin-top:10px;padding:8px;background:#fff8e6;border-radius:4px;font-size:13px;color:#c0820a">
            Offene Gutschrift: <strong><?= number_format((float)$bestellung['gutschrift_betrag'], 2, ',', '.') ?> €</strong>
            <?= $bestellung['gutschrift_notiz'] ? ' — ' . htmlspecialchars($bestellung['gutschrift_notiz']) : '' ?>
        </div>
    <?php endif; ?>
</div>

<!-- Positionen -->
<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px;display:block;margin-bottom:10px">Positionen</strong>
    <table class="erp-table">
        <thead>
            <tr><th>Artikel</th><th>Bestellt</th><th>Eingeg.</th><th>Offen</th><th>EK-Preis</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($positionen as $p):
                $offen = (float)$p['menge_bestellt'] - (float)$p['menge_eingegangen'];
                $icon  = $p['gestrichen'] ? '✕' : ($offen <= 0 ? '✅' : ($p['menge_eingegangen'] > 0 ? '🔄' : '⬜'));
            ?>
                <tr <?= $p['gestrichen'] ? 'style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td>
                        <?= htmlspecialchars($p['artikel_name']) ?>
                        <?php if ($p['variante_name']): ?>
                            <span style="font-size:11px;color:var(--color-text-muted)"> — <?= htmlspecialchars($p['variante_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format((float)$p['menge_bestellt'], 0) ?></td>
                    <td><?= number_format((float)$p['menge_eingegangen'], 0) ?></td>
                    <td><?= $p['gestrichen'] ? 'gestrichen' : number_format($offen, 0) ?></td>
                    <td><?= $p['ek_preis'] ? number_format((float)$p['ek_preis'], 4, ',', '.') . ' €' : '—' ?></td>
                    <td><?= $icon ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;font-size:12px;color:var(--color-text-muted)">Gesamt EK (netto):</td>
                <td colspan="2"><strong><?= number_format($gesamtEk, 2, ',', '.') ?> €</strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Rechnung -->
<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px;display:block;margin-bottom:10px">Rechnung / Lieferschein</strong>
    <form method="post" action="/mealana/bestellungen/rechnung_speichern.php">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;align-items:end">
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">LS-Nummer</label>
                <input type="text" name="ls_nummer" class="erp-input" style="width:100%" value="<?= htmlspecialchars($bestellung['ls_nummer'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Rechnungs-Nr.</label>
                <input type="text" name="rechnung_nummer" class="erp-input" style="width:100%" value="<?= htmlspecialchars($bestellung['rechnung_nummer'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Rechnungsbetrag (€)</label>
                <input type="number" name="rechnung_betrag" step="0.01" class="erp-input" style="width:100%" value="<?= $bestellung['rechnung_betrag'] ? number_format((float)$bestellung['rechnung_betrag'], 2, '.', '') : '' ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Rechnungsdatum</label>
                <input type="date" name="rechnung_datum" class="erp-input" style="width:100%" value="<?= htmlspecialchars($bestellung['rechnung_datum'] ?? '') ?>">
            </div>
        </div>
        <div style="margin-top:10px;text-align:right">
            <button type="submit" class="btn btn-primary btn-sm">Rechnung speichern</button>
        </div>
    </form>
</div>

<!-- Aktivitäten -->
<?php if (!empty($aktivitaeten)): ?>
    <div class="card">
        <strong style="font-size:13px;display:block;margin-bottom:8px">Aktivitäten</strong>
        <?php foreach ($aktivitaeten as $a):
            $details = json_decode($a['details'] ?? '{}', true);
        ?>
            <div style="display:flex;gap:12px;padding:5px 0;border-bottom:1px solid var(--color-border);font-size:12px">
                <span style="color:var(--color-text-muted);white-space:nowrap"><?= date('d.m.Y H:i', strtotime($a['erstellt_am'])) ?></span>
                <span style="font-weight:500"><?= htmlspecialchars($a['formularname'] ?? '—') ?></span>
                <span style="color:var(--color-text-muted)"><?= htmlspecialchars($a['aktion']) ?></span>
                <?php if (!empty($details['menge'])): ?>
                    <span><?= htmlspecialchars($details['menge']) ?> Stk<?= !empty($details['charge']) ? ', Charge: ' . htmlspecialchars($details['charge']) : '' ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
