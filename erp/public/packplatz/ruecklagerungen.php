<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/packplatz/RuecklagerungRepository.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$repo      = new RuecklagerungRepository();
$offene    = $repo->findOffene();
$alleLager = (new LagerService())->getAlleLager();

$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);

$pageTitle = 'Offene Rücklagerungen';
$backUrl   = BASE_PATH . '/packplatz/index.php';
$headerSub = 'Rücklagerungen';
require_once __DIR__ . '/shell_top.php';
?>

<?php if ($erfolg): ?>
<div style="background:#0d2d0d;border:1px solid #4caf50;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#4caf50">
    ✓ <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if ($fehler): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef5350">
    <?= htmlspecialchars($fehler) ?>
</div>
<?php endif; ?>

<div style="max-width:1000px;margin:0 auto">

    <div style="color:#aaa;font-size:13px;margin-bottom:16px;line-height:1.6">
        Ware, die an der Kasse als Retoure zurückgenommen wurde (finanziell schon erledigt —
        Erstattung ist bereits gebucht) und physisch am Tresen liegt, aber noch nicht wieder
        im Lagerbestand ist. Bitte Zustand prüfen und einbuchen.
    </div>

    <?php if (empty($offene)): ?>
        <div style="color:#555;font-size:14px;text-align:center;padding:40px">
            ✓ Keine offenen Rücklagerungen
        </div>
    <?php else: ?>
        <table class="pp-table">
            <thead>
                <tr>
                    <th>Artikel</th>
                    <th style="width:70px">Menge</th>
                    <th>Charge</th>
                    <th>Herkunft</th>
                    <th>Kasse</th>
                    <th style="width:120px">Erstellt</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($offene as $r): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($r['bezeichnung']) ?></td>
                    <td><?= (int)$r['menge'] ?></td>
                    <td style="color:#aaa">
                        <?php if ($r['charge']): ?>
                            <?= htmlspecialchars($r['charge']) ?>
                        <?php elseif ($r['charge_pflicht']): ?>
                            <span style="color:#ff9800">⚠ fehlt (Pflicht)</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td style="color:#aaa">
                        Bon <?= htmlspecialchars($r['bon_nr']) ?>
                        <?php if ($r['auftrag_nr']): ?>
                            <br><span style="font-size:12px">zu <?= htmlspecialchars($r['auftrag_nr']) ?></span>
                        <?php else: ?>
                            <br><span style="font-size:12px;color:#e65100">Freitext-Retour (kein Auftrag)</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#aaa"><?= htmlspecialchars($r['kasse_name']) ?></td>
                    <td style="color:#aaa;font-size:12px"><?= date('d.m.Y H:i', strtotime($r['erstellt_am'])) ?></td>
                    <td>
                        <button type="button" class="pp-btn pp-btn-primary"
                                onclick="rlEinbuchenOeffnen(<?= $r['id'] ?>, '<?= htmlspecialchars($r['bezeichnung'], ENT_QUOTES) ?>', <?= (int)$r['menge'] ?>, <?= $r['charge_pflicht'] ? 'true' : 'false' ?>, '<?= htmlspecialchars($r['charge'] ?? '', ENT_QUOTES) ?>')">
                            Einbuchen
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<!-- OVERLAY: Einbuchen -->
<div class="pp-overlay" id="overlay-einbuchen">
    <div class="pp-overlay-box" style="min-width:420px">
        <div class="pp-overlay-titel" id="rl-titel">Einbuchen</div>
        <form method="post" action="ruecklagerungen_speichern.php">
            <input type="hidden" name="id" id="rl-id">

            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Ziel-Lager:</label>
            <select name="lager_id" class="pp-overlay-input" style="margin-bottom:16px;cursor:pointer">
                <?php foreach ($alleLager as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">Zustand der Ware:</label>
            <select name="zustand" class="pp-overlay-input" style="margin-bottom:16px;cursor:pointer">
                <option value="neu">Neu</option>
                <option value="gebraucht">Gebraucht</option>
                <option value="beschaedigt">Beschädigt</option>
                <option value="defekt">Defekt</option>
            </select>

            <div id="rl-charge-block" style="display:none;margin-bottom:20px">
                <label style="display:block;font-size:13px;color:#aaa;margin-bottom:6px">
                    Charge/Los (Chargenpflicht — vor dem Einbuchen prüfen/eintragen!):
                </label>
                <input type="text" name="charge" id="rl-charge" class="pp-overlay-input" style="width:100%" placeholder="z.B. LOT-2024-007">
            </div>

            <div style="display:flex;gap:12px;justify-content:center">
                <button type="button" class="pp-btn pp-btn-secondary" onclick="rlEinbuchenSchliessen()">Abbrechen</button>
                <button type="submit" class="pp-btn pp-btn-primary" onclick="return rlEinbuchenPruefen()">✓ Einbuchen</button>
            </div>
        </form>
    </div>
</div>

<script>
var rlChargePflicht = false;

function rlEinbuchenOeffnen(id, bezeichnung, menge, chargePflicht, vorhandeneCharge) {
    document.getElementById('rl-id').value = id;
    document.getElementById('rl-titel').textContent = menge + '× ' + bezeichnung + ' einbuchen';
    rlChargePflicht = chargePflicht;
    document.getElementById('rl-charge-block').style.display = chargePflicht ? 'block' : 'none';
    document.getElementById('rl-charge').value = vorhandeneCharge || '';
    document.getElementById('overlay-einbuchen').classList.add('aktiv');
}
function rlEinbuchenSchliessen() {
    document.getElementById('overlay-einbuchen').classList.remove('aktiv');
}
function rlEinbuchenPruefen() {
    if (rlChargePflicht && document.getElementById('rl-charge').value.trim() === '') {
        alert('Dieser Artikel ist chargenpflichtig — bitte Charge eintragen, bevor eingebucht wird.');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/shell_bottom.php'; ?>
