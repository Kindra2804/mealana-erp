<?php
/**
 * Mengen-Aktionen „X Stück für Y €“ / „Kauf X, zahl Y“ / „Paketpreise“ (MengenAktionService, Migration 241, 242)
 *   mengen.php          Liste
 *   mengen.php?neu=1    Anlegen
 *   mengen.php?id=X     Bearbeiten (Kategorien, Shops, einzeln zugeordnete Artikel)
 * POST aktion=speichern | loeschen | artikel_entfernen (Formular, danach Weiterleitung)
 * Einzelne Artikel kommen über die Artikelliste dazu: Aktion „Zu Mengen-Aktion hinzufügen“ (mengen_ajax.php).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/MengenAktionService.php';

$svc = new MengenAktionService();
$url = BASE_PATH . '/aktionen/mengen.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if (!Auth::kann($aktion === 'loeschen' ? 'artikel.loeschen' : 'artikel.bearbeiten')) throw new RuntimeException('Dafür fehlt die Berechtigung');
        if ($aktion === 'speichern') {
            $id = $svc->speichern($_POST);
            $svc->uebertrageAlle((int) $_SESSION['benutzer']['id']);   // gleich an die Shops (nur bei Änderung)
            header("Location: $url?id=$id&ok=gespeichert");
        } elseif ($aktion === 'loeschen') {
            $svc->loeschen($id);
            $svc->uebertrageAlle((int) $_SESSION['benutzer']['id']);
            header("Location: $url?ok=geloescht");
        } elseif ($aktion === 'artikel_entfernen') {
            $svc->artikelEntfernen($id, (int) ($_POST['artikel_id'] ?? 0));
            $svc->uebertrageAlle((int) $_SESSION['benutzer']['id']);
            header("Location: $url?id=$id#artikel");
        }
        exit;
    } catch (Throwable $e) {
        $fehler = $e->getMessage();
        $formular = $_POST;   // Eingaben wieder anzeigen
    }
}

$id    = (int) ($_GET['id'] ?? ($formular['id'] ?? 0));
$neu   = !$id && (isset($_GET['neu']) || isset($formular));
$akt   = $id ? $svc->finde($id) : null;
if ($id && !$akt) { header("Location: $url"); exit; }

$pageTitle    = $akt ? 'Mengen-Aktion: ' . $akt['name'] : ($neu ? 'Neue Mengen-Aktion' : 'Mengen-Aktionen');
$activeModule = 'artikel';
$actionBarContent = '<a href="' . BASE_PATH . '/aktionen/liste.php" class="btn btn-secondary btn-sm">← Preis-Aktionen</a> '
                  . (!$akt && !$neu ? '<a href="' . $url . '?neu=1" class="btn btn-primary btn-sm">+ Neue Mengen-Aktion</a>' : '');
require_once __DIR__ . '/../includes/shell_top.php';

$status = [
    'aktiv'      => ['Aktiv', '#107c10', '#e8f5e8'],
    'geplant'    => ['Geplant', '#0078d4', '#e8f3fc'],
    'pausiert'   => ['Pausiert', '#888', '#f5f5f5'],
    'abgelaufen' => ['Abgelaufen', '#999', '#f0f0f0'],
];
$h = fn($s) => htmlspecialchars((string) $s);
$datum = fn($d) => $d ? date('d.m.Y', strtotime($d)) : '';
?>
<div style="padding:var(--space-md);max-width:1000px">

<?php if (isset($_GET['ok'])): /* Erfolgsmeldung, blendet sich nach 3 s aus */ ?>
    <div id="ma-ok" class="success-banner" style="margin-bottom:12px">✓ Mengen-Aktion <?= $_GET['ok'] === 'geloescht' ? 'gelöscht' : 'gespeichert' ?></div>
<?php endif; ?>
<?php if (!empty($fehler)): ?>
    <div style="background:#fdecea;color:var(--color-danger);padding:8px 12px;border-radius:4px;margin-bottom:12px;font-size:13px"><?= $h($fehler) ?></div>
<?php endif; ?>

<?php if (!$akt && !$neu): /* ── Liste ── */ $alle = $svc->alle(); ?>
    <p style="font-size:13px;color:var(--color-text-muted);margin:0 0 12px">
        Mengenrabatt über gemischte Artikel, z.B. <b>4 für 12 €</b> oder <b>3 für 2</b> (das günstigste Stück gratis).
        Der Rabatt wird gleichmäßig auf alle Stücke im Paket verteilt. Gilt nur für Endkunden und nicht für Artikel, die schon reduziert sind.
    </p>
    <div class="card">
    <?php if (!$alle): ?>
        <p style="padding:var(--space-md);color:var(--color-text-muted)">Noch keine Mengen-Aktion angelegt.
            <a href="<?= $url ?>?neu=1" class="btn btn-primary btn-sm" style="margin-left:8px">Erste anlegen</a></p>
    <?php else: ?>
        <table class="erp-table">
            <thead><tr><th>NAME</th><th>REGEL</th><th>GILT FÜR</th><th>KANÄLE</th><th>ZEITRAUM</th><th>STATUS</th></tr></thead>
            <tbody>
            <?php foreach ($alle as $a): [$st, $farbe, $bg] = $status[$a['status']]; ?>
                <tr>
                    <td><a href="<?= $url ?>?id=<?= $a['id'] ?>" style="font-weight:600;color:var(--color-nav);text-decoration:none"><?= $h($a['name']) ?></a></td>
                    <td><span style="background:#fff3cd;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:600;white-space:nowrap"><?= $h($a['badge_text']) ?></span>
                        <?php if ($a['pakete']): ?><div style="font-size:11px;color:var(--color-text-muted);margin-top:2px"><?= $h(implode(' · ', array_map(fn($p) => $p['menge'] . ' für ' . MengenAktionService::euro($p['preis']) . ' €', $a['pakete']))) ?></div><?php endif; ?></td>
                    <td style="font-size:12px"><?= (int) $a['anzahl_artikel'] ?> Artikel
                        <?php if ($a['kategorien']): ?><div style="color:var(--color-text-muted)"><?= $h(implode(', ', array_column($a['kategorien'], 'name'))) ?></div><?php endif; ?></td>
                    <td style="font-size:12px"><?= $a['an_kasse'] ? 'Kasse' : '' ?><?= $a['an_kasse'] && $a['shops'] ? ', ' : '' ?><?= $h(implode(', ', array_column($a['shops'], 'name'))) ?: ($a['an_kasse'] ? '' : '<span style="color:#ccc">—</span>') ?></td>
                    <td style="font-size:12px;white-space:nowrap"><?= $datum($a['gueltig_ab']) ?> – <?= $a['gueltig_bis'] ? $datum($a['gueltig_bis']) : 'ohne Ende' ?></td>
                    <td><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:<?= $farbe ?>;background:<?= $bg ?>"><?= $st ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>

    <?php $shopStand = Database::getInstance()->query("SELECT name, sync_pausiert, mengen_uebertragen_am, mengen_fehler FROM shops WHERE ist_aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); ?>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:10px">
        Übertragung an die Shops (automatisch beim Speichern und alle paar Minuten, wenn sich etwas geändert hat):
        <?php foreach ($shopStand as $ss): ?>
            <div>· <?= $h($ss['name']) ?>:
                <?php if ($ss['mengen_fehler']): ?><span style="color:var(--color-danger)">Fehler – <?= $h($ss['mengen_fehler']) ?></span>
                <?php elseif ($ss['sync_pausiert']): ?>Sync pausiert
                <?php elseif ($ss['mengen_uebertragen_am']): ?>zuletzt <?= date('d.m.Y H:i', strtotime($ss['mengen_uebertragen_am'])) ?>
                <?php else: ?>noch nie<?php endif; ?></div>
        <?php endforeach; ?>
    </div>

<?php else: /* ── Anlegen / Bearbeiten ── */
    $f = $formular ?? $akt ?? ['art' => 'x_fuer_y', 'menge' => 4, 'zahl_menge' => '', 'paketpreis' => '', 'badge_text' => '',
                                'gueltig_ab' => date('Y-m-d'), 'gueltig_bis' => '', 'aktiv' => 1, 'an_kasse' => 1, 'name' => ''];
    $katGewaehlt  = isset($formular) ? array_map('intval', (array) ($formular['kategorie_ids'] ?? [])) : array_map(fn($k) => (int) $k['id'], $akt['kategorien'] ?? []);
    $shopGewaehlt = isset($formular) ? array_map('intval', (array) ($formular['shop_ids'] ?? [])) : array_map(fn($s) => (int) $s['id'], $akt['shops'] ?? []);
    if (!$akt && !isset($formular)) $shopGewaehlt = array_map(fn($s) => (int) $s['id'], $svc->shops());   // neu: alle Shops vorbelegt
?>
    <form method="post" id="ma-form">
        <input type="hidden" name="aktion" value="speichern">
        <input type="hidden" name="id" value="<?= (int) ($akt['id'] ?? 0) ?>">

        <div class="card" style="padding:var(--space-md);margin-bottom:12px">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
                <label class="form-label">Name *<input type="text" name="name" class="erp-input" style="width:100%" value="<?= $h($f['name']) ?>" placeholder="z.B. Weihnachtsanhänger 4 für 12"></label>
                <label class="form-label">Art
                    <select name="art" id="ma-art" class="erp-select" style="width:100%">
                        <option value="x_fuer_y" <?= $f['art'] === 'x_fuer_y' ? 'selected' : '' ?>>X Stück für Y € (z.B. 4 für 12 €)</option>
                        <option value="kauf_x_zahl_y" <?= $f['art'] === 'kauf_x_zahl_y' ? 'selected' : '' ?>>Kauf X, zahl Y (z.B. 3 für 2)</option>
                        <option value="paketpreise" <?= $f['art'] === 'paketpreise' ? 'selected' : '' ?>>Paketpreise, mehrere Größen (z.B. 2 für 4,50 · 4 für 8 …)</option>
                    </select></label>
            </div>
            <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-top:10px">
                <label class="form-label ma-nicht-paket"><span class="ma-x">Stück je Paket</span><input type="number" min="2" name="menge" id="ma-menge" class="erp-input" style="width:90px" value="<?= $h($f['menge']) ?>"></label>
                <label class="form-label ma-nur-x"><span>Paketpreis € (brutto)</span><input type="text" name="paketpreis" id="ma-preis" class="erp-input" style="width:110px" inputmode="decimal" value="<?= $f['paketpreis'] !== '' && $f['paketpreis'] !== null ? $h(number_format((float) str_replace(',', '.', (string) $f['paketpreis']), 2, ',', '')) : '' ?>"></label>
                <label class="form-label ma-nur-kauf"><span>davon bezahlt</span><input type="number" min="1" name="zahl_menge" id="ma-zahl" class="erp-input" style="width:90px" value="<?= $h($f['zahl_menge']) ?>"></label>
                <label class="form-label">Badge / Hinweis<input type="text" name="badge_text" id="ma-badge" maxlength="40" class="erp-input" style="width:160px" value="<?= $h($f['badge_text']) ?>" placeholder="automatisch"></label>
                <div style="font-size:12px;color:var(--color-text-muted);padding-bottom:8px" id="ma-erklaerung"></div>
            </div>
            <?php
                // Paketpreise: vorhandene Zeilen + leere bis 6 Zeilen
                $pz = [];
                if (isset($formular)) foreach ((array) ($formular['paket_menge'] ?? []) as $i => $m) $pz[] = ['menge' => $m, 'preis' => $formular['paket_preis'][$i] ?? ''];
                else foreach ($akt['pakete'] ?? [] as $pk) $pz[] = ['menge' => $pk['menge'], 'preis' => number_format($pk['preis'], 2, ',', '')];
                $pz = array_values(array_filter($pz, fn($r) => $r['menge'] !== '' || $r['preis'] !== ''));
                while (count($pz) < 6) $pz[] = ['menge' => '', 'preis' => ''];
            ?>
            <div class="ma-nur-paket" style="margin-top:10px">
                <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:4px">
                    Paketgrößen (1 Stück = normaler Preis des Artikels). Die Menge wird so aus Paketen zusammengesetzt, dass es am günstigsten ist, der Rest einzeln – z.B. 5 Stück = 4er-Paket + 1 Stück.
                </div>
                <table style="border-collapse:collapse;font-size:13px">
                    <tr><th style="text-align:left;padding:2px 8px 2px 0">Stück</th><th style="text-align:left;padding:2px 8px 2px 0">Paketpreis € (brutto)</th><th></th></tr>
                    <?php foreach ($pz as $r): ?>
                        <tr>
                            <td style="padding:2px 8px 2px 0"><input type="number" min="2" name="paket_menge[]" class="erp-input ma-pk-menge" style="width:80px" value="<?= $h($r['menge']) ?>"></td>
                            <td style="padding:2px 8px 2px 0"><input type="text" name="paket_preis[]" class="erp-input ma-pk-preis" style="width:100px" inputmode="decimal" value="<?= $h($r['preis']) ?>"></td>
                            <td class="ma-pk-stueck" style="font-size:12px;color:var(--color-text-muted)"></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-top:10px">
                <label class="form-label">gültig ab *<input type="date" name="gueltig_ab" class="erp-input" value="<?= $h($f['gueltig_ab']) ?>"></label>
                <label class="form-label">gültig bis <span style="color:var(--color-text-muted)">(leer = ohne Ende)</span><input type="date" name="gueltig_bis" class="erp-input" value="<?= $h($f['gueltig_bis']) ?>"></label>
                <label style="font-size:13px;padding-bottom:8px"><input type="checkbox" name="aktiv" value="1" <?= !empty($f['aktiv']) ? 'checked' : '' ?>> aktiv (aus = pausiert)</label>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px">
            <div class="card" style="padding:var(--space-md)">
                <div style="font-weight:700;font-size:13px;color:var(--color-nav);margin-bottom:6px">KATEGORIEN <span style="font-weight:400;color:var(--color-text-muted)">(Unterkategorien zählen mit)</span></div>
                <input type="text" id="ma-katsuche" class="erp-input" style="width:100%;margin-bottom:6px" placeholder="Kategorie suchen…">
                <div style="max-height:260px;overflow-y:auto;border:1px solid var(--color-border);border-radius:4px;padding:4px 8px">
                    <?php foreach ($svc->kategorienFlach() as $k): $an = in_array($k['id'], $katGewaehlt, true); ?>
                        <label class="ma-kat" data-text="<?= $h(mb_strtolower($k['name'])) ?>" style="display:<?= $an ? 'flex' : 'flex' ?>;gap:6px;align-items:center;font-size:13px;padding:2px 0 2px <?= $k['tiefe'] * 16 ?>px<?= $an ? ';font-weight:600' : '' ?>">
                            <input type="checkbox" name="kategorie_ids[]" value="<?= $k['id'] ?>" <?= $an ? 'checked' : '' ?>> <?= $h($k['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" style="padding:var(--space-md)">
                <div style="font-weight:700;font-size:13px;color:var(--color-nav);margin-bottom:6px">GILT IN</div>
                <label style="display:block;font-size:13px;padding:3px 0"><input type="checkbox" name="an_kasse" value="1" <?= !empty($f['an_kasse']) ? 'checked' : '' ?>> Kasse</label>
                <?php foreach ($svc->shops() as $s): ?>
                    <label style="display:block;font-size:13px;padding:3px 0"><input type="checkbox" name="shop_ids[]" value="<?= $s['id'] ?>" <?= in_array((int) $s['id'], $shopGewaehlt, true) ? 'checked' : '' ?>> <?= $h($s['name']) ?></label>
                <?php endforeach; ?>
                <div style="font-size:11px;color:var(--color-text-muted);margin-top:8px">Nur für Endkunden (Standard-Kundengruppe).</div>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:space-between;margin-bottom:var(--space-md)">
            <div>
                <?php if ($akt): ?><button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Mengen-Aktion „<?= $h(addslashes($akt['name'])) ?>“ löschen?')){document.getElementById('ma-loeschen').submit()}">Löschen</button><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px">
                <a href="<?= $url ?>" class="btn btn-secondary btn-sm">Zurück</a>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </div>
    </form>
    <?php if ($akt): ?>
        <form method="post" id="ma-loeschen"><input type="hidden" name="aktion" value="loeschen"><input type="hidden" name="id" value="<?= $akt['id'] ?>"></form>

        <div class="card" style="padding:var(--space-md)" id="artikel">
            <div style="font-weight:700;font-size:13px;color:var(--color-nav);margin-bottom:6px">EINZELN ZUGEORDNETE ARTIKEL</div>
            <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px">
                Zusätzlich zu den Kategorien. Dazu in der Artikelliste Artikel anhaken → Aktion „🛒 Zu Mengen-Aktion hinzufügen“. Väter nehmen ihre Kinder mit.
                Insgesamt gilt die Aktion für <b><?= (int) $akt['anzahl_artikel'] ?> Artikel</b>.
            </div>
            <?php if ($akt['artikel']): ?>
                <table class="erp-table" style="width:100%">
                    <?php foreach ($akt['artikel'] as $ar): ?>
                        <tr>
                            <td><?= $h($ar['artikelnummer']) ?> · <?= $h($ar['name']) ?><?= $ar['kinder'] ? ' <span style="color:var(--color-text-muted);font-size:12px">(+ ' . (int) $ar['kinder'] . ' Kinder)</span>' : '' ?><?= $ar['aktiv'] ? '' : ' <span style="color:var(--color-text-muted)">(inaktiv)</span>' ?></td>
                            <td style="text-align:right;width:90px">
                                <form method="post" style="margin:0"><input type="hidden" name="aktion" value="artikel_entfernen"><input type="hidden" name="id" value="<?= $akt['id'] ?>"><input type="hidden" name="artikel_id" value="<?= $ar['id'] ?>">
                                    <button class="btn btn-secondary btn-xs">Entfernen</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div style="font-size:13px;color:var(--color-text-muted)">Keine.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<script src="<?= BASE_PATH ?>/js/mengen_aktionen.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
