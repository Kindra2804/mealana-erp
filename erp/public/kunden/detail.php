<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

$id = (int) ($_GET['id'] ?? 0);
$service = new KundenService();
$kunde = $service->getById($id);

if (!$kunde) {
    echo 'Kunde nicht gefunden.';
    exit;
}

$adressen = $service->getAdressen($id);
$consent  = $service->getConsent($id);

$flashErfolg = $_SESSION['erfolg'] ?? null;
$flashFehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

// Anzeigename
if ($kunde['ist_firma'] && $kunde['firmenname']) {
    $anzeigename = $kunde['firmenname'];
    $unterzeile  = trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
} else {
    $anzeigename = trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
    $unterzeile  = '';
}

$activeTab = $_GET['tab'] ?? 'stammdaten';

$statusChip = match($kunde['status']) {
    'aktiv'     => '<span class="chip chip-aktiv">Aktiv</span>',
    'gesperrt'  => '<span class="chip" style="background:#fff3cd;color:#856404">Gesperrt</span>',
    'geloescht' => '<span class="chip chip-inaktiv">Gelöscht</span>',
    default     => '',
};

$pageTitle        = $anzeigename;
$activeModule     = 'kunden';
$actionBarContent = <<<HTML
    <a href="bearbeiten.php?id={$id}" class="btn btn-primary btn-sm">✏ Bearbeiten</a>
    <a href="liste.php" class="btn btn-secondary btn-sm">← Liste</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($flashErfolg): ?>
<div class="banner-erfolg" id="flash-banner" style="background:#d1fae5;border-left:3px solid #10b981;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:13px">
    <?= htmlspecialchars($flashErfolg) ?>
</div>
<script>setTimeout(() => { const b = document.getElementById('flash-banner'); if (b) b.style.display='none'; }, 3000);</script>
<?php endif; ?>

<!-- Kunden-Header -->
<div class="card" style="margin-bottom:var(--space-md);display:flex;align-items:center;gap:var(--space-md)">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--color-nav);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0">
        <?= $kunde['ist_firma'] ? '🏢' : '👤' ?>
    </div>
    <div style="flex:1;min-width:0">
        <div style="font-size:17px;font-weight:700;color:var(--color-nav)">
            <?= htmlspecialchars($anzeigename) ?>
            <?php if ($kunde['ist_firma']): ?>
                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);margin-left:6px">B2B</span>
            <?php endif; ?>
        </div>
        <?php if ($unterzeile): ?>
            <div style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($unterzeile) ?></div>
        <?php endif; ?>
        <div style="margin-top:4px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span style="font-family:monospace;font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($kunde['kundennummer']) ?></span>
            <?= $statusChip ?>
            <?php if ($kunde['kundengruppe']): ?>
                <span class="chip"><?= htmlspecialchars($kunde['kundengruppe']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <!-- Schnellinfo rechts -->
    <div style="display:flex;gap:var(--space-lg);font-size:12px;color:var(--color-text-muted);flex-shrink:0">
        <?php if ($kunde['email']): ?>
            <div><div style="font-weight:600;color:var(--color-text)">E-Mail</div><?= htmlspecialchars($kunde['email']) ?></div>
        <?php endif; ?>
        <?php if ($kunde['telefon'] || $kunde['mobil']): ?>
            <div><div style="font-weight:600;color:var(--color-text)">Telefon</div><?= htmlspecialchars($kunde['telefon'] ?? $kunde['mobil'] ?? '') ?></div>
        <?php endif; ?>
        <div><div style="font-weight:600;color:var(--color-text)">Seit</div><?= date('d.m.Y', strtotime($kunde['erstellt_am'])) ?></div>
    </div>
</div>

<!-- Tab-Navigation -->
<div style="display:flex;gap:4px;margin-bottom:var(--space-md);border-bottom:2px solid var(--color-border)">
    <?php
    $tabs = [
        'stammdaten' => 'Stammdaten',
        'adressen'   => 'Adressen (' . count($adressen) . ')',
        'dsgvo'      => 'DSGVO / Consent',
        'bestellungen' => 'Bestellungen',
    ];
    foreach ($tabs as $key => $label):
        $isActive = $activeTab === $key;
        $disabled = in_array($key, ['bestellungen']) ? 'style="opacity:.4;pointer-events:none"' : '';
    ?>
        <a href="?id=<?= $id ?>&tab=<?= $key ?>"
           <?= $disabled ?>
           style="padding:8px 14px;font-size:13px;font-weight:<?= $isActive ? '600' : '400' ?>;
                  color:<?= $isActive ? 'var(--color-nav)' : 'var(--color-text-muted)' ?>;
                  border-bottom:2px solid <?= $isActive ? 'var(--color-nav)' : 'transparent' ?>;
                  margin-bottom:-2px;text-decoration:none">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ── Tab: Stammdaten ─────────────────────────────────────────────────── -->
<?php if ($activeTab === 'stammdaten'): ?>
<div class="card">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md)">

        <?php
        function zeile(string $label, ?string $wert): void {
            if ($wert === null || $wert === '') return;
            echo '<div>';
            echo '<div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">' . htmlspecialchars($label) . '</div>';
            echo '<div style="font-size:13px">' . htmlspecialchars($wert) . '</div>';
            echo '</div>';
        }
        ?>

        <?php if ($kunde['ist_firma'] && $kunde['firmenname']): zeile('Firma', $kunde['firmenname']); endif; ?>
        <?php zeile('Vorname', $kunde['vorname']); ?>
        <?php zeile('Nachname', $kunde['nachname']); ?>
        <?php zeile('E-Mail', $kunde['email']); ?>
        <?php zeile('Telefon', $kunde['telefon']); ?>
        <?php zeile('Mobil', $kunde['mobil']); ?>
        <?php zeile('Geburtsdatum', $kunde['geburtsdatum'] ? date('d.m.Y', strtotime($kunde['geburtsdatum'])) : null); ?>
        <?php zeile('UID-Nummer', $kunde['uid_nummer']); ?>
        <?php zeile('Kundengruppe', $kunde['kundengruppe']); ?>
        <?php zeile('Zahlungsbedingung', $kunde['zahlungsbedingung_name']); ?>
        <?php zeile('Standard-Zahlungsart', $kunde['standardzahlungsart']); ?>
        <?php if ($kunde['kreditlimit']): zeile('Kreditlimit', number_format((float)$kunde['kreditlimit'], 2, ',', '.') . ' €'); endif; ?>
        <?php zeile('Sprache', strtoupper($kunde['sprache'] ?? 'DE')); ?>
        <?php zeile('Herkunft', ucfirst($kunde['kundenherkunft'] ?? '')); ?>

        <?php if ($kunde['notiz']): ?>
        <div style="grid-column:1/-1">
            <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Interne Notiz</div>
            <div style="font-size:13px;background:var(--color-bg);padding:8px 12px;border-radius:4px;border:1px solid var(--color-border)">
                <?= nl2br(htmlspecialchars($kunde['notiz'])) ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Tab: Adressen ──────────────────────────────────────────────────── -->
<?php elseif ($activeTab === 'adressen'): ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:var(--space-sm)">
    <button class="btn btn-secondary btn-sm" onclick="adresseNeuOeffnen()">+ Adresse hinzufügen</button>
</div>

<?php if (empty($adressen)): ?>
    <div class="card" style="text-align:center;padding:32px;color:var(--color-text-muted)">
        Noch keine Adressen hinterlegt.
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--space-md)">
<?php foreach ($adressen as $adr): ?>
    <div class="card" style="position:relative"
         data-adr-id="<?= $adr['id'] ?>"
         data-adr-typ="<?= htmlspecialchars($adr['adresstyp']) ?>"
         data-adr-standard="<?= $adr['ist_standard'] ?>"
         data-adr-firma="<?= htmlspecialchars($adr['firma'] ?? '') ?>"
         data-adr-vorname="<?= htmlspecialchars($adr['vorname'] ?? '') ?>"
         data-adr-nachname="<?= htmlspecialchars($adr['nachname'] ?? '') ?>"
         data-adr-strasse="<?= htmlspecialchars($adr['strasse'] ?? '') ?>"
         data-adr-hausnummer="<?= htmlspecialchars($adr['hausnummer'] ?? '') ?>"
         data-adr-plz="<?= htmlspecialchars($adr['plz'] ?? '') ?>"
         data-adr-ort="<?= htmlspecialchars($adr['ort'] ?? '') ?>"
         data-adr-land="<?= htmlspecialchars($adr['land'] ?? 'AT') ?>"
         data-adr-zusatz="<?= htmlspecialchars($adr['zusatz'] ?? '') ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase">
                <?= ucfirst($adr['adresstyp']) ?>
                <?php if ($adr['ist_standard']): ?>
                    <span style="color:var(--color-nav);margin-left:4px">★ Standard</span>
                <?php endif; ?>
            </span>
            <div style="display:flex;gap:8px;align-items:center">
                <button onclick="adresseEditOeffnen(this.closest('[data-adr-id]'))"
                        class="btn btn-secondary btn-sm" style="padding:2px 8px">✏</button>
                <a href="adresse_loeschen.php?id=<?= $adr['id'] ?>&kunde_id=<?= $id ?>"
                   onclick="return confirm('Adresse löschen?')"
                   style="font-size:12px;color:var(--color-danger);text-decoration:none">✕</a>
            </div>
        </div>
        <div style="font-size:13px;line-height:1.6">
            <?php if ($adr['firma']): ?>
                <div style="font-weight:600"><?= htmlspecialchars($adr['firma']) ?></div>
            <?php endif; ?>
            <?= htmlspecialchars(trim(($adr['vorname'] ?? '') . ' ' . ($adr['nachname'] ?? ''))) ?><br>
            <?= htmlspecialchars(($adr['strasse'] ?? '') . ' ' . ($adr['hausnummer'] ?? '')) ?><br>
            <?php if ($adr['zusatz']): ?>
                <?= htmlspecialchars($adr['zusatz']) ?><br>
            <?php endif; ?>
            <?= htmlspecialchars(($adr['plz'] ?? '') . ' ' . ($adr['ort'] ?? '')) ?><br>
            <span style="color:var(--color-text-muted)"><?= htmlspecialchars($adr['land']) ?></span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Adresse-Edit-Modal -->
<div id="adresse-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:24px;width:420px;box-shadow:0 4px 24px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
        <div style="font-weight:700;font-size:14px;color:var(--color-nav);margin-bottom:16px">Adresse bearbeiten</div>
        <form method="POST" action="adresse_aktualisieren.php">
            <input type="hidden" name="id"       id="edit-adr-id">
            <input type="hidden" name="kunde_id" value="<?= $id ?>">

            <label class="erp-label">Typ</label>
            <select name="adresstyp" id="edit-adr-typ" class="erp-select" style="width:100%;margin-bottom:10px">
                <option value="haupt">Hauptadresse</option>
                <option value="rechnung">Rechnungsadresse</option>
                <option value="lieferung">Lieferadresse</option>
            </select>

            <label class="erp-label">Firma (optional)</label>
            <input type="text" name="firma" id="edit-adr-firma" class="erp-input" style="width:100%;margin-bottom:10px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">Vorname</label>
                <input type="text" name="vorname" id="edit-adr-vorname" class="erp-input" style="width:100%"></div>
                <div><label class="erp-label">Nachname</label>
                <input type="text" name="nachname" id="edit-adr-nachname" class="erp-input" style="width:100%"></div>
            </div>

            <label class="erp-label">Zusatz (c/o, Stiege, …)</label>
            <input type="text" name="zusatz" id="edit-adr-zusatz" class="erp-input" style="width:100%;margin-bottom:10px">

            <div style="display:grid;grid-template-columns:1fr 80px;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">Straße *</label>
                <input type="text" name="strasse" id="edit-adr-strasse" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Nr. *</label>
                <input type="text" name="hausnummer" id="edit-adr-hausnummer" class="erp-input" style="width:100%" required></div>
            </div>

            <div style="display:grid;grid-template-columns:90px 1fr 70px;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">PLZ *</label>
                <input type="text" name="plz" id="edit-adr-plz" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Ort *</label>
                <input type="text" name="ort" id="edit-adr-ort" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Land</label>
                <select name="land" id="edit-adr-land" class="erp-select" style="width:100%">
                    <option value="AT">AT</option>
                    <option value="DE">DE</option>
                    <option value="CH">CH</option>
                </select></div>
            </div>

            <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:16px;cursor:pointer">
                <input type="checkbox" name="ist_standard" id="edit-adr-standard" value="1"> Als Standardadresse setzen
            </label>

            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" onclick="adresseEditSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>
<script>
function adresseEditOeffnen(card) {
    var d = card.dataset;
    document.getElementById('edit-adr-id').value         = d.adrId;
    document.getElementById('edit-adr-typ').value        = d.adrTyp;
    document.getElementById('edit-adr-firma').value      = d.adrFirma;
    document.getElementById('edit-adr-vorname').value    = d.adrVorname;
    document.getElementById('edit-adr-nachname').value   = d.adrNachname;
    document.getElementById('edit-adr-strasse').value    = d.adrStrasse;
    document.getElementById('edit-adr-hausnummer').value = d.adrHausnummer;
    document.getElementById('edit-adr-plz').value        = d.adrPlz;
    document.getElementById('edit-adr-ort').value        = d.adrOrt;
    document.getElementById('edit-adr-land').value       = d.adrLand;
    document.getElementById('edit-adr-zusatz').value     = d.adrZusatz;
    document.getElementById('edit-adr-standard').checked = d.adrStandard === '1';
    document.getElementById('adresse-edit-modal').style.display = 'flex';
}
function adresseEditSchliessen() {
    document.getElementById('adresse-edit-modal').style.display = 'none';
}
</script>

<!-- Adresse-Neu-Modal -->
<div id="adresse-neu-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:24px;width:420px;box-shadow:0 4px 24px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
        <div style="font-weight:700;font-size:14px;color:var(--color-nav);margin-bottom:16px">Neue Adresse</div>
        <form method="POST" action="adresse_speichern.php">
            <input type="hidden" name="kunde_id" value="<?= $id ?>">

            <label class="erp-label">Typ</label>
            <select name="adresstyp" class="erp-select" style="width:100%;margin-bottom:10px">
                <option value="haupt">Hauptadresse</option>
                <option value="rechnung">Rechnungsadresse</option>
                <option value="lieferung">Lieferadresse</option>
            </select>

            <label class="erp-label">Firma (optional)</label>
            <input type="text" name="firma" class="erp-input" style="width:100%;margin-bottom:10px">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">Vorname</label>
                <input type="text" name="vorname" class="erp-input" style="width:100%"></div>
                <div><label class="erp-label">Nachname</label>
                <input type="text" name="nachname" class="erp-input" style="width:100%"></div>
            </div>

            <label class="erp-label">Zusatz (c/o, Stiege, …)</label>
            <input type="text" name="zusatz" class="erp-input" style="width:100%;margin-bottom:10px">

            <div style="display:grid;grid-template-columns:1fr 80px;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">Straße *</label>
                <input type="text" name="strasse" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Nr. *</label>
                <input type="text" name="hausnummer" class="erp-input" style="width:100%" required></div>
            </div>

            <div style="display:grid;grid-template-columns:90px 1fr 70px;gap:8px;margin-bottom:10px">
                <div><label class="erp-label">PLZ *</label>
                <input type="text" name="plz" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Ort *</label>
                <input type="text" name="ort" class="erp-input" style="width:100%" required></div>
                <div><label class="erp-label">Land</label>
                <select name="land" class="erp-select" style="width:100%">
                    <option value="AT">AT</option>
                    <option value="DE">DE</option>
                    <option value="CH">CH</option>
                </select></div>
            </div>

            <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:16px;cursor:pointer">
                <input type="checkbox" name="ist_standard" value="1"> Als Standardadresse setzen
            </label>

            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" onclick="adresseNeuSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </form>
    </div>
</div>
<script>
function adresseNeuOeffnen()    { document.getElementById('adresse-neu-modal').style.display = 'flex'; }
function adresseNeuSchliessen() { document.getElementById('adresse-neu-modal').style.display = 'none'; }
</script>

<!-- ── Tab: DSGVO / Consent ───────────────────────────────────────────── -->
<?php elseif ($activeTab === 'dsgvo'): ?>
<div class="card">
    <div style="font-weight:600;font-size:13px;color:var(--color-nav);margin-bottom:var(--space-md);padding-bottom:var(--space-xs);border-bottom:1px solid var(--color-border)">
        Einwilligungen (Consent-Log)
    </div>

    <?php if (empty($consent)): ?>
        <p style="color:var(--color-text-muted);font-size:13px">Noch keine Einwilligungen erfasst.</p>
    <?php else: ?>
        <table class="erp-table" style="font-size:12px">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Status</th>
                    <th>Datum</th>
                    <th>Quelle</th>
                    <th>Widerrufen am</th>
                    <th>Kommentar</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($consent as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($c['consent_typ'])) ?></td>
                    <td>
                        <?php if ($c['eingewilligt']): ?>
                            <span class="chip chip-aktiv">Eingewilligt</span>
                        <?php else: ?>
                            <span class="chip chip-inaktiv">Abgelehnt</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d.m.Y H:i', strtotime($c['eingewilligt_am'])) ?></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['quelle']) ?></td>
                    <td style="color:var(--color-text-muted)"><?= $c['widerrufen_am'] ? date('d.m.Y', strtotime($c['widerrufen_am'])) : '–' ?></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['kommentar'] ?? '–') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Neue Einwilligung eintragen -->
    <div style="margin-top:var(--space-md);padding-top:var(--space-md);border-top:1px solid var(--color-border)">
        <div style="font-weight:600;font-size:12px;color:var(--color-text-muted);margin-bottom:var(--space-sm)">EINWILLIGUNG EINTRAGEN</div>
        <form method="POST" action="consent_speichern.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="kunde_id" value="<?= $id ?>">
            <div>
                <label class="erp-label">Typ</label>
                <select name="consent_typ" class="erp-select">
                    <option value="newsletter">Newsletter</option>
                    <option value="marketing">Marketing</option>
                    <option value="profiling">Profiling</option>
                </select>
            </div>
            <div>
                <label class="erp-label">Status</label>
                <select name="eingewilligt" class="erp-select">
                    <option value="1">Eingewilligt</option>
                    <option value="0">Abgelehnt / Widerrufen</option>
                </select>
            </div>
            <div>
                <label class="erp-label">Quelle</label>
                <select name="quelle" class="erp-select">
                    <option value="erp_manuell">ERP manuell</option>
                    <option value="telefon">Telefon</option>
                    <option value="shop">Shop</option>
                    <option value="messe">Messe</option>
                </select>
            </div>
            <div>
                <label class="erp-label">Kommentar</label>
                <input type="text" name="kommentar" class="erp-input" placeholder="z.B. Kunde hat angerufen" style="width:200px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Eintragen</button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'bestellungen'): ?>
<div class="card" style="text-align:center;padding:32px;color:var(--color-text-muted)">
    Bestellhistorie folgt mit dem Verkaufsmodul.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
