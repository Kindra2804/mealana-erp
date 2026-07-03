<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

$service   = new LieferantenService();
$lieferant = $service->findByIdMitVertretern($id);

if ($lieferant === false) {
    header('Location: liste.php');
    exit;
}

$activeTab = $_GET['tab'] ?? 'stammdaten';

// Tab-abhängige Daten laden
$lieferantArtikel    = [];
$lieferantBestellungen = [];
$zugaenge            = [];

if ($activeTab === 'artikel') {
    $lieferantArtikel = $service->getArtikel($id);
} elseif ($activeTab === 'bestellungen') {
    $lieferantBestellungen = $service->getBestellungen($id);
} elseif ($activeTab === 'zugaenge') {
    $zugaenge = $service->getZugaenge($id);
}

$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['erfolg']);

$pageTitle    = htmlspecialchars($lieferant['name']);
$activeModule = 'lieferanten';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lieferanten/bearbeiten.php?id={$id}" class="btn btn-secondary btn-sm">✏ Bearbeiten</a>
    <a href="{$basePath}/lieferanten/delete.php?id={$id}" class="btn btn-danger btn-sm"
       onclick="return confirm('Lieferant wirklich deaktivieren?')">Deaktivieren</a>
    <div class="actionbar-sep"></div>
    <div class="actionbar-right">
        <a href="{$basePath}/lieferanten/liste.php" class="btn btn-secondary btn-sm">← Liste</a>
    </div>
HTML;

$lieferbedingungLabels = [
    'frei_haus' => 'Frei Haus',
    'ab_werk'   => 'Ab Werk',
    'ab_lager'  => 'Ab Lager',
    'sonstige'  => 'Sonstige',
];

$steuerregelLabels = [
    'inland'            => 'Inland',
    'eu_igl'            => 'EU – Innergem. Erwerb (USt-frei)',
    'drittland_einfuhr' => 'Drittland – Einfuhr',
    'reverse_charge'    => 'Reverse-Charge (Dienstleistung)',
];

$anredeLabels = ['herr' => 'Herr', 'frau' => 'Frau', 'divers' => 'Divers'];

$statusLabels = [
    'entwurf'       => ['label' => 'Entwurf',       'class' => 'chip-inaktiv'],
    'offen'         => ['label' => 'Offen',          'class' => 'chip-aktiv'],
    'teilgeliefert' => ['label' => 'Teilgeliefert',  'class' => 'chip-auslauf'],
    'erledigt'      => ['label' => 'Erledigt',       'class' => 'chip-inaktiv'],
    'storniert'     => ['label' => 'Storniert',      'class' => 'chip-inaktiv'],
];

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
<div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px">
    <p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p>
</div>
<?php endif; ?>

<h2 style="margin:0 0 2px;font-size:20px;font-weight:700"><?= htmlspecialchars($lieferant['name']) ?></h2>
<?php if ($lieferant['firma']): ?>
<div style="color:var(--color-text-muted);font-size:13px;margin-bottom:12px">
    <?= htmlspecialchars($lieferant['firma']) ?><?= $lieferant['firmenzusatz'] ? ' · ' . htmlspecialchars($lieferant['firmenzusatz']) : '' ?>
</div>
<?php else: ?>
<div style="margin-bottom:12px"></div>
<?php endif; ?>

<!-- Tab-Navigation -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--color-border);margin-bottom:16px">
<?php
$tabs = [
    'stammdaten'  => 'Stammdaten',
    'vertreter'   => 'Vertreter (' . count($lieferant['vertreter']) . ')',
    'artikel'     => 'Artikel',
    'bestellungen'=> 'Bestellungen',
    'zugaenge'    => 'Zugänge',
];
foreach ($tabs as $key => $label):
    $active = $activeTab === $key;
?>
    <a href="?id=<?= $id ?>&tab=<?= $key ?>"
       style="padding:8px 18px;font-size:13px;font-weight:<?= $active ? '600' : '400' ?>;
              color:<?= $active ? 'var(--color-primary)' : 'var(--color-text-muted)' ?>;
              border-bottom:2px solid <?= $active ? 'var(--color-primary)' : 'transparent' ?>;
              margin-bottom:-2px;text-decoration:none;white-space:nowrap">
        <?= htmlspecialchars($label) ?>
    </a>
<?php endforeach; ?>
</div>

<?php if ($activeTab === 'stammdaten'): ?>

<!-- STAMMDATEN -->
<div class="card" style="margin-bottom:12px">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px 24px">

        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Lieferant-Nr.</div>
            <div><?= $lieferant['id'] ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Status</div>
            <div><?= $lieferant['aktiv'] ? '<span class="chip chip-aktiv">Aktiv</span>' : '<span class="chip chip-inaktiv">Inaktiv</span>' ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Währung</div>
            <div><?= htmlspecialchars($lieferant['waehrung'] ?? 'EUR') ?></div>
        </div>

        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Land</div>
            <div><?= htmlspecialchars($lieferant['land_name'] ?? $lieferant['land'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Kundennummer (bei Lieferant)</div>
            <div><?= htmlspecialchars($lieferant['kundennummer'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">UStID</div>
            <div><?= htmlspecialchars($lieferant['ustid'] ?? '–') ?></div>
        </div>

        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Steuerregel</div>
            <div><?= htmlspecialchars($steuerregelLabels[$lieferant['steuerregel'] ?? ''] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Lieferbedingung</div>
            <div><?= htmlspecialchars($lieferbedingungLabels[$lieferant['lieferbedingung'] ?? ''] ?? ($lieferant['lieferbedingung'] ?? '–')) ?></div>
        </div>

        <?php if ($lieferant['strasse'] || $lieferant['plz'] || $lieferant['ort']): ?>
        <div style="grid-column:1/-1">
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Adresse</div>
            <div>
                <?= htmlspecialchars(trim(($lieferant['strasse'] ?? '') . ' ' . ($lieferant['plz'] ?? '') . ' ' . ($lieferant['ort'] ?? ''))) ?: '–' ?>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Website</div>
            <div>
                <?php if ($lieferant['website']): ?>
                    <a href="<?= htmlspecialchars($lieferant['website']) ?>" target="_blank" style="color:var(--color-primary)"><?= htmlspecialchars($lieferant['website']) ?></a>
                <?php else: ?>–<?php endif; ?>
            </div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">E-Mail</div>
            <div><?= htmlspecialchars($lieferant['email'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Telefon</div>
            <div><?= htmlspecialchars($lieferant['telefon'] ?? '–') ?></div>
        </div>
    </div>
</div>

<!-- Konditionen -->
<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 12px">Konditionen</h3>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px 24px">
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Zahlungsziel</div>
            <div><?= $lieferant['zahlungsziel_tage'] !== null ? $lieferant['zahlungsziel_tage'] . ' Tage' : '–' ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Skonto</div>
            <div>
                <?php if ($lieferant['skonto_prozent'] !== null): ?>
                    <?= number_format((float)$lieferant['skonto_prozent'], 2) ?> %
                    <?= $lieferant['skonto_tage'] !== null ? 'bei ' . $lieferant['skonto_tage'] . ' Tagen' : '' ?>
                <?php else: ?>–<?php endif; ?>
            </div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Mindestbestellwert</div>
            <div><?= $lieferant['mindestbestellwert'] !== null ? '€ ' . number_format((float)$lieferant['mindestbestellwert'], 2, ',', '.') : '–' ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Standard-Lieferzeit</div>
            <div><?= $lieferant['lieferzeit_tage'] !== null ? $lieferant['lieferzeit_tage'] . ' Tage' : '–' ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Standard-Lieferkosten</div>
            <div><?= $lieferant['standard_lieferkosten'] !== null ? '€ ' . number_format((float)$lieferant['standard_lieferkosten'], 2, ',', '.') : '–' ?></div>
        </div>
    </div>
</div>

<?php if ($lieferant['iban'] || $lieferant['bic'] || $lieferant['bank_name'] || $lieferant['kontoinhaber']): ?>
<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 12px">Bankverbindung</h3>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px 24px">
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">IBAN</div>
            <div><?= htmlspecialchars($lieferant['iban'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">BIC</div>
            <div><?= htmlspecialchars($lieferant['bic'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Bank</div>
            <div><?= htmlspecialchars($lieferant['bank_name'] ?? '–') ?></div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:2px">Kontoinhaber</div>
            <div><?= htmlspecialchars($lieferant['kontoinhaber'] ?? $lieferant['firma'] ?? $lieferant['name']) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($lieferant['interne_notizen']): ?>
<div class="card">
    <h3 style="margin:0 0 8px">Interne Notizen</h3>
    <div style="white-space:pre-wrap;font-size:13px;line-height:1.5"><?= htmlspecialchars($lieferant['interne_notizen']) ?></div>
</div>
<?php endif; ?>

<?php elseif ($activeTab === 'vertreter'): ?>

<!-- VERTRETER -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">Vertreter</h3>
        <a href="vertreter_neu.php?lieferant_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Neuer Vertreter</a>
    </div>

    <?php if (empty($lieferant['vertreter'])): ?>
        <p style="color:var(--color-text-muted);margin:0">Noch keine Vertreter angelegt.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>E-MAIL</th>
                    <th>TELEFON</th>
                    <th>MOBIL</th>
                    <th>NOTIZEN</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lieferant['vertreter'] as $v): ?>
                <tr>
                    <td><strong><?= htmlspecialchars(trim(($anredeLabels[$v['anrede'] ?? ''] ?? '') . ' ' . $v['vorname'] . ' ' . $v['nachname'])) ?></strong></td>
                    <td><?= htmlspecialchars($v['email'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($v['telefon'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($v['mobil'] ?? '–') ?></td>
                    <td title="<?= htmlspecialchars($v['notizen'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($v['notizen'] ?? '', 0, 40, '…')) ?>
                    </td>
                    <td>
                        <a href="vertreter_bearbeiten.php?id=<?= $v['id'] ?>" style="text-decoration:none" title="Bearbeiten">✏</a>
                        <a href="vertreter_delete.php?lieferant_id=<?= $id ?>&id=<?= $v['id'] ?>"
                           style="text-decoration:none" title="Löschen"
                           onclick="return confirm('Vertreter wirklich löschen?')">🗑</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'artikel'): ?>

<!-- ARTIKEL -->
<div class="card">
    <h3 style="margin:0 0 12px">Artikel von diesem Lieferanten</h3>

    <?php if (empty($lieferantArtikel)): ?>
        <p style="color:var(--color-text-muted);margin:0">Keine Artikel mit diesem Lieferanten verknüpft.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>ARTIKELNR.</th>
                    <th>NAME</th>
                    <th>LIEF.-ARTIKELNR.</th>
                    <th>EK NETTO</th>
                    <th>VPE</th>
                    <th>LIEFERZEIT</th>
                    <th>STD.</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lieferantArtikel as $a): ?>
                <tr>
                    <td><a href="<?= BASE_PATH ?>/artikel/detail.php?id=<?= $a['id'] ?>" style="color:var(--color-primary)"><?= htmlspecialchars($a['artikelnummer']) ?></a></td>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td><?= htmlspecialchars($a['artikelnummer_lieferant'] ?? '–') ?></td>
                    <td><?= $a['netto_ek'] !== null ? '€ ' . number_format((float)$a['netto_ek'], 4, ',', '.') : '–' ?></td>
                    <td><?= $a['vpe_menge'] !== null ? $a['vpe_menge'] : '–' ?></td>
                    <td><?= $a['lieferzeit_tage'] !== null ? $a['lieferzeit_tage'] . ' Tage' : '–' ?></td>
                    <td><?= $a['standard_lieferant'] ? '<span class="chip chip-aktiv">Ja</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'bestellungen'): ?>

<!-- BESTELLUNGEN -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">Bestellungen</h3>
        <a href="<?= BASE_PATH ?>/bestellungen/neu.php?lieferant_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Neue Bestellung</a>
    </div>

    <?php if (empty($lieferantBestellungen)): ?>
        <p style="color:var(--color-text-muted);margin:0">Noch keine Bestellungen bei diesem Lieferanten.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>DATUM</th>
                    <th>STATUS</th>
                    <th>ERWARTET</th>
                    <th>AB-NR.</th>
                    <th>RECHNUNG</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lieferantBestellungen as $b):
                $sl = $statusLabels[$b['status']] ?? ['label' => $b['status'], 'class' => ''];
            ?>
                <tr>
                    <td><?= htmlspecialchars($b['bestelldatum']) ?></td>
                    <td><span class="chip <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
                    <td><?= $b['erwartet_am'] ? htmlspecialchars($b['erwartet_am']) : '–' ?></td>
                    <td><?= htmlspecialchars($b['ab_nummer'] ?? '–') ?></td>
                    <td><?= $b['rechnung_betrag'] !== null ? '€ ' . number_format((float)$b['rechnung_betrag'], 2, ',', '.') : '–' ?></td>
                    <td><a href="<?= BASE_PATH ?>/bestellungen/detail.php?id=<?= $b['id'] ?>" style="color:var(--color-primary);font-size:12px">→ Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'zugaenge'): ?>

<!-- ZUGÄNGE -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">Zugänge / Händlerportale</h3>
        <a href="zugang_neu.php?lieferant_id=<?= $id ?>" class="btn btn-primary btn-sm">+ Neuer Zugang</a>
    </div>

    <?php if (empty($zugaenge)): ?>
        <p style="color:var(--color-text-muted);margin:0">Noch keine Zugänge hinterlegt.</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>BEZEICHNUNG</th>
                    <th>URL</th>
                    <th>BENUTZERNAME</th>
                    <th>PASSWORT</th>
                    <th>NOTIZEN</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zugaenge as $z): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($z['bezeichnung']) ?></strong></td>
                    <td>
                        <?php if ($z['url']): ?>
                            <a href="<?= htmlspecialchars($z['url']) ?>" target="_blank"
                               style="color:var(--color-primary);font-size:12px">
                                <?= htmlspecialchars(mb_strimwidth($z['url'], 0, 40, '…')) ?>
                            </a>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($z['benutzername'] ?? '–') ?></td>
                    <td>
                        <?php if ($z['passwort'] !== null): ?>
                            <span class="pw-dots" style="font-family:monospace;letter-spacing:2px">••••••••</span>
                            <span class="pw-text" style="display:none;font-family:monospace"><?= htmlspecialchars($z['passwort']) ?></span>
                            <button type="button" onclick="togglePw(this)" style="background:none;border:none;cursor:pointer;color:var(--color-primary);font-size:11px;padding:0 4px">Zeigen</button>
                        <?php else: ?>–<?php endif; ?>
                    </td>
                    <td title="<?= htmlspecialchars($z['notizen'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($z['notizen'] ?? '', 0, 35, '…')) ?>
                    </td>
                    <td>
                        <a href="zugang_bearbeiten.php?id=<?= $z['id'] ?>" style="text-decoration:none" title="Bearbeiten">✏</a>
                        <a href="zugang_delete.php?lieferant_id=<?= $id ?>&id=<?= $z['id'] ?>"
                           style="text-decoration:none" title="Löschen"
                           onclick="return confirm('Zugang wirklich löschen?')">🗑</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
</style>
<script>
function togglePw(btn) {
    const td = btn.closest('td');
    const dots = td.querySelector('.pw-dots');
    const text = td.querySelector('.pw-text');
    if (text.style.display === 'none') {
        dots.style.display = 'none';
        text.style.display = 'inline';
        btn.textContent = 'Verstecken';
    } else {
        dots.style.display = 'inline';
        text.style.display = 'none';
        btn.textContent = 'Zeigen';
    }
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
