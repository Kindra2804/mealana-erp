<?php
// Verkauf → Widerrufe: alle offenen Widerrufe aus den Shops an einem Ort (Jacky 2026-11-05)
//  1. Ohne Zuordnung: Gast hat über „Vertrag widerrufen“ widerrufen, Germanized fand keine passende
//     Bestellung (falsche/fehlende Nummer oder andere E-Mail) → hier einem Auftrag zuordnen oder ablehnen
//  2. Entscheidung offen: Aufträge mit „Widerruf beantragt“ → im Auftrag bestätigen/ablehnen
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/shop/WiderrufService.php';

$service = new WiderrufService();
$db      = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopId     = (int)($_POST['shop_id'] ?? 0);
    $antragId   = (int)($_POST['antrag_id'] ?? 0);
    $benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
    $erg = match ($_POST['aktion'] ?? '') {
        'zuordnen' => $service->zuordnen($shopId, $antragId, (string)($_POST['auftrag'] ?? ''), $benutzerId),
        'ablehnen' => $service->antragAblehnen($shopId, $antragId, (string)($_POST['grund'] ?? '')),
        default    => ['erfolg' => false, 'fehler' => 'Unbekannte Aktion.'],
    };
    if ($erg['erfolg']) {
        $_SESSION['erfolg'] = $erg['text'];
        header('Location: ' . (isset($erg['auftrag_id']) ? 'detail.php?id=' . $erg['auftrag_id'] : 'widerrufe.php'));
    } else {
        $_SESSION['fehler'] = [$erg['fehler']];
        header('Location: widerrufe.php');
    }
    exit;
}

['antraege' => $ungeklaert, 'fehler' => $ladeFehler] = $service->ungeklaerteAntraege();
$offen = $db->query("
    SELECT a.id, a.auftrag_nr, a.widerruf_am, a.lieferstatus, a.bruttobetrag, s.name AS shop_name,
           JSON_UNQUOTE(JSON_EXTRACT(a.kunden_snapshot, '$.name')) AS kunde
    FROM auftraege a LEFT JOIN shops s ON s.id = a.shop_id
    WHERE a.widerruf_status = 'beantragt'
    ORDER BY a.widerruf_am
")->fetchAll(PDO::FETCH_ASSOC);

$erfolg = $_SESSION['erfolg'] ?? null;
$fehler = array_merge($_SESSION['fehler'] ?? [], $ladeFehler);
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$datum = fn(?string $d) => $d ? date('d.m.Y, H:i', strtotime($d)) : '–';

$pageTitle    = 'Widerrufe';
$activeModule = 'verkauf';
$basePath     = BASE_PATH;
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
    <div class="banner banner-success" id="erfolg-banner"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px">
        <?php foreach ($fehler as $f): ?><p style="color:var(--color-danger);margin:4px 0"><?= htmlspecialchars($f) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:16px">
    <h3 style="margin:0 0 4px">Ohne Zuordnung <span class="badge"><?= count($ungeklaert) ?></span></h3>
    <p style="margin:0 0 12px;color:var(--color-text-muted)">
        Widerrufe über die Seite „Vertrag widerrufen“, bei denen der Shop keine passende Bestellung gefunden hat
        (Bestellnummer falsch oder fehlt, andere E-Mail). Der Kunde hat die Eingangsbestätigung schon bekommen.
        Bitte einem Auftrag zuordnen — danach im Auftrag bestätigen oder ablehnen — oder hier ablehnen, wenn er zu keiner Shop-Bestellung gehört.
    </p>
    <?php if (!$ungeklaert): ?>
        <p style="margin:0">Keine offenen Anträge ohne Zuordnung.</p>
    <?php endif; ?>
    <?php foreach ($ungeklaert as $a): ?>
        <div style="border:1px solid var(--color-border, #e2e8f0);border-radius:8px;padding:12px 14px;margin-bottom:10px">
            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:baseline">
                <strong><?= htmlspecialchars($a['name'] ?: '(ohne Namen)') ?></strong>
                <span><?= htmlspecialchars($a['email']) ?></span>
                <span class="chip"><?= htmlspecialchars($a['shop_name']) ?></span>
                <span style="color:var(--color-text-muted)">eingegangen <?= $datum($a['eingegangen']) ?></span>
            </div>
            <div style="margin-top:6px">
                Angaben zum Vertrag: <strong><?= htmlspecialchars($a['angaben'] ?: '—') ?></strong>
                <?php if (!empty($a['info'])): ?> · Zusatz: <?= htmlspecialchars($a['info']) ?><?php endif; ?>
                <?php if (!empty($a['bestellung'])): ?>
                    · Shop hat Bestellung #<?= (int)$a['bestellung'] ?> gefunden, aber die E-Mail passt nicht
                <?php endif; ?>
            </div>
            <?php if ($a['vorschlaege']): ?>
                <div style="margin-top:8px">Passende Aufträge:</div>
                <?php foreach ($a['vorschlaege'] as $v): ?>
                    <form method="post" style="display:flex;gap:10px;align-items:center;margin:4px 0"
                          onsubmit="return confirm('Widerruf dem Auftrag <?= htmlspecialchars($v['auftrag_nr']) ?> zuordnen?')">
                        <input type="hidden" name="aktion" value="zuordnen">
                        <input type="hidden" name="shop_id" value="<?= (int)$a['shop_id'] ?>">
                        <input type="hidden" name="antrag_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="auftrag" value="<?= htmlspecialchars($v['auftrag_nr']) ?>">
                        <a href="detail.php?id=<?= (int)$v['id'] ?>" target="_blank" style="font-weight:600"><?= htmlspecialchars($v['auftrag_nr']) ?></a>
                        <span><?= htmlspecialchars((string)$v['kunde']) ?> · <?= htmlspecialchars((string)$v['email']) ?></span>
                        <span style="color:var(--color-text-muted)"><?= date('d.m.Y', strtotime($v['erstellt_am'])) ?> · <?= number_format((float)$v['bruttobetrag'], 2, ',', '.') ?> € · Shop #<?= (int)$v['kanal_auftrag_id'] ?></span>
                        <span class="chip sc-aktion">passt: <?= htmlspecialchars(implode(', ', $v['gruende'])) ?></span>
                        <button type="submit" class="btn btn-primary btn-sm">Zuordnen</button>
                    </form>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="margin-top:8px;color:var(--color-text-muted)">Kein passender Auftrag gefunden (gleiche E-Mail, Name oder Nummer, letzte 120 Tage).</div>
            <?php endif; ?>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px">
                <form method="post" style="display:flex;gap:6px;align-items:center">
                    <input type="hidden" name="aktion" value="zuordnen">
                    <input type="hidden" name="shop_id" value="<?= (int)$a['shop_id'] ?>">
                    <input type="hidden" name="antrag_id" value="<?= (int)$a['id'] ?>">
                    <input type="text" name="auftrag" required placeholder="Auftragsnummer, z. B. A-IND-2026-00002" class="form-control" style="width:260px">
                    <button type="submit" class="btn btn-secondary btn-sm">Zuordnen</button>
                </form>
                <form method="post" style="display:flex;gap:6px;align-items:center"
                      onsubmit="return confirm('Antrag ablehnen? Der Grund geht per Mail an den Kunden.')">
                    <input type="hidden" name="aktion" value="ablehnen">
                    <input type="hidden" name="shop_id" value="<?= (int)$a['shop_id'] ?>">
                    <input type="hidden" name="antrag_id" value="<?= (int)$a['id'] ?>">
                    <input type="text" name="grund" required placeholder="Grund der Ablehnung (geht an den Kunden)" class="form-control" style="width:300px">
                    <button type="submit" class="btn btn-secondary btn-sm">Ablehnen</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="padding:16px">
    <h3 style="margin:0 0 4px">Entscheidung offen <span class="badge"><?= count($offen) ?></span></h3>
    <p style="margin:0 0 12px;color:var(--color-text-muted)">Aufträge mit „Widerruf beantragt“ — gesperrt für Packplatz und Abholung. Im Auftrag bestätigen oder ablehnen.</p>
    <?php if (!$offen): ?>
        <p style="margin:0">Keine offenen Widerrufe.</p>
    <?php else: ?>
        <table class="db-table">
            <tr><th>Auftrag</th><th>Shop</th><th>Kunde</th><th>Widerruf am</th><th style="text-align:right">Betrag</th><th></th></tr>
            <?php foreach ($offen as $o): ?>
                <tr>
                    <td><a href="detail.php?id=<?= (int)$o['id'] ?>" style="font-weight:600"><?= htmlspecialchars($o['auftrag_nr']) ?></a></td>
                    <td><?= htmlspecialchars((string)$o['shop_name']) ?></td>
                    <td><?= htmlspecialchars((string)$o['kunde']) ?></td>
                    <td><?= $datum($o['widerruf_am']) ?></td>
                    <td style="text-align:right"><?= number_format((float)$o['bruttobetrag'], 2, ',', '.') ?> €</td>
                    <td><a href="detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-secondary">Entscheiden</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
