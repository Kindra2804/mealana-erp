<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: index.php?tab=kassen');
    exit;
}

$stmt = $db->prepare("SELECT * FROM kassen WHERE id = ?");
$stmt->execute([$id]);
$kasse = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$kasse) {
    header('Location: index.php?tab=kassen');
    exit;
}

$service = new BfrService();
$status  = $service->registrierungsStatus($id);
$entwurf = $status['entwurf'];
$abgeschlossen = $status['abgeschlossen'];

$erfolg = $_SESSION['erfolg'] ?? null; unset($_SESSION['erfolg']);
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$arbeitsplatzTokenSync = $_SESSION['arbeitsplatz_token_sync'] ?? null; unset($_SESSION['arbeitsplatz_token_sync']);

$pageTitle        = 'RKSV-Registrierung: ' . htmlspecialchars($kasse['name']);
$activeModule     = 'einstellungen';
$actionBarContent = '<a href="kasse_edit.php?id=' . $id . '" class="btn btn-secondary btn-sm">&larr; Zurück zur Kasse</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if ($abgeschlossen && !$entwurf): ?>
<!-- Aktive, abgeschlossene Registrierung — gesperrt -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header">Aktive RKSV-Registrierung</div>
    <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;font-size:13px">
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Kassen-ID</div><strong><?= htmlspecialchars($abgeschlossen['rksv_kassen_id']) ?></strong></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">BFR-URL</div><?= htmlspecialchars($abgeschlossen['bfr_url']) ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">UID-Nummer</div><?= htmlspecialchars($abgeschlossen['uid_nummer'] ?? '–') ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Vertrauensdiensteanbieter</div><?= htmlspecialchars($abgeschlossen['vertrauensdiensteanbieter'] ?? '–') ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Zertifikat-Seriennr.</div><?= htmlspecialchars($abgeschlossen['zertifikat_seriennr_dez'] ?? '–') ?> (Hex: <?= htmlspecialchars($abgeschlossen['zertifikat_seriennr_hex'] ?? '–') ?>)</div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Aktiv seit</div><?= $kasse['bfr_aktiv_seit'] ? date('d.m.Y H:i', strtotime($kasse['bfr_aktiv_seit'])) : '–' ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Zertifikat gemeldet</div><?= date('d.m.Y H:i', strtotime($abgeschlossen['zertifikat_gemeldet_am'])) ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Kasse gemeldet</div><?= date('d.m.Y H:i', strtotime($abgeschlossen['kasse_gemeldet_am'])) ?></div>
        <div><div style="color:var(--color-text-muted);font-size:11px;text-transform:uppercase">Startbeleg geprüft</div><?= date('d.m.Y H:i', strtotime($abgeschlossen['startbeleg_geprueft_am'])) ?></div>
    </div>
    <?php if ($abgeschlossen['startbeleg_inhalt']): ?>
    <div style="padding:0 16px 16px;font-size:11px;font-family:monospace;word-break:break-all;color:var(--color-text-muted)">
        Startbeleg: <?= htmlspecialchars($abgeschlossen['startbeleg_inhalt']) ?>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="padding:16px">
    <p style="font-size:13px;color:var(--color-text-muted);margin:0 0 12px">
        Die Kassen-ID ist gesperrt und kann im laufenden Betrieb nicht mehr geändert werden.
        Nur bei einem Hardware-Wechsel (neue Signaturkarte/BFR-Installation) eine neue Registrierung anfordern —
        der Gesamtumsatzzähler beginnt dann bei 0, ältere Belege bleiben der alten Kassen-ID zugeordnet.
    </p>
    <form method="post" action="kasse_registrierung_speichern.php" onsubmit="return confirm('Neue Kassen-ID wirklich anfordern? Das startet eine neue Registrierung (Hardware-Wechsel).');">
        <input type="hidden" name="kasse_id" value="<?= $id ?>">
        <input type="hidden" name="aktion" value="neu">
        <button type="submit" class="btn btn-secondary">Neue Kassen-ID anfordern (Hardware-Wechsel)</button>
    </form>
</div>

<?php else: ?>
<!-- Laufender Entwurf -->
<?php $e = $entwurf ?: ['rksv_kassen_id' => '', 'bfr_url' => '', 'uid_nummer' => '', 'vertrauensdiensteanbieter' => '',
                          'zertifikat_seriennr_dez' => '', 'zertifikat_seriennr_hex' => '',
                          'zertifikat_gemeldet_am' => null, 'kasse_gemeldet_am' => null, 'startbeleg_geprueft_am' => null,
                          'startbeleg_inhalt' => '']; ?>
<?php if (!$entwurf): $entwurfId = null; else: $entwurfId = (int)$entwurf['id']; endif; ?>

<div class="card" style="margin-bottom:16px;padding:12px 16px;color:var(--color-text-muted);font-size:13px">
    Die eigentliche Meldung bei FinanzOnline und der Startbeleg passieren im BFR-Admin-Tool selbst — hier wird das
    Ergebnis zusätzlich als zweite, unabhängige Aufzeichnung festgehalten.
</div>

<form method="post" action="kasse_registrierung_speichern.php" id="kasse-registrierung-form">
    <input type="hidden" name="kasse_id" value="<?= $id ?>">
    <input type="hidden" name="geraete_token" id="ap-geraete-token" value="">
    <?php if ($entwurfId): ?><input type="hidden" name="entwurf_id" value="<?= $entwurfId ?>"><?php endif; ?>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Stammdaten</div>
        <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
                <label class="form-label">RKSV-Kassen-ID (RN)</label>
                <input type="text" name="rksv_kassen_id" class="erp-input" value="<?= htmlspecialchars($e['rksv_kassen_id']) ?>" placeholder="z.B. BFR0226TEST" maxlength="12">
            </div>
            <div class="form-group">
                <label class="form-label">BFR-URL</label>
                <input type="text" name="bfr_url" class="erp-input" value="<?= htmlspecialchars($e['bfr_url']) ?>" placeholder="z.B. http://127.0.0.1:8787">
            </div>
            <div class="form-group">
                <label class="form-label">UID-Nummer</label>
                <input type="text" name="uid_nummer" class="erp-input" value="<?= htmlspecialchars($e['uid_nummer'] ?? '') ?>" placeholder="z.B. ATU65033000">
            </div>
            <div class="form-group">
                <label class="form-label">Vertrauensdiensteanbieter</label>
                <input type="text" name="vertrauensdiensteanbieter" class="erp-input" value="<?= htmlspecialchars($e['vertrauensdiensteanbieter'] ?? '') ?>" placeholder="z.B. AT1 A-TRUST">
            </div>
            <div class="form-group">
                <label class="form-label">Zertifikat-Seriennr. (Dez)</label>
                <input type="text" name="zertifikat_seriennr_dez" class="erp-input" value="<?= htmlspecialchars($e['zertifikat_seriennr_dez'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Zertifikat-Seriennr. (Hex)</label>
                <input type="text" name="zertifikat_seriennr_hex" class="erp-input" value="<?= htmlspecialchars($e['zertifikat_seriennr_hex'] ?? '') ?>">
            </div>
        </div>
        <div style="padding:0 16px 16px">
            <button type="submit" name="aktion" value="abrufen" class="btn btn-secondary btn-sm">🔄 Von /state abrufen (befüllt UID/Zertifikat automatisch)</button>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">Bestätigungen (im BFR-Admin-Tool geprüft)</div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px">
            <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="zertifikat_gemeldet" value="1" <?= $e['zertifikat_gemeldet_am'] ? 'checked' : '' ?>>
                <span>Zertifikat bei FinanzOnline gemeldet (Status Zertifikat: IN_BETRIEB)</span>
                <?php if ($e['zertifikat_gemeldet_am']): ?><span style="color:var(--color-text-muted);font-size:11px">— <?= date('d.m.Y H:i', strtotime($e['zertifikat_gemeldet_am'])) ?></span><?php endif; ?>
            </label>
            <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="kasse_gemeldet" value="1" <?= $e['kasse_gemeldet_am'] ? 'checked' : '' ?>>
                <span>Kasse bei FinanzOnline gemeldet (Status Kasse: REGISTRIERT/IN_BETRIEB)</span>
                <?php if ($e['kasse_gemeldet_am']): ?><span style="color:var(--color-text-muted);font-size:11px">— <?= date('d.m.Y H:i', strtotime($e['kasse_gemeldet_am'])) ?></span><?php endif; ?>
            </label>
            <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="startbeleg_geprueft" value="1" <?= $e['startbeleg_geprueft_am'] ? 'checked' : '' ?>>
                <span>Startbeleg erstellt und geprüft ("Alles OK.")</span>
                <?php if ($e['startbeleg_geprueft_am']): ?><span style="color:var(--color-text-muted);font-size:11px">— <?= date('d.m.Y H:i', strtotime($e['startbeleg_geprueft_am'])) ?></span><?php endif; ?>
            </label>
            <div class="form-group">
                <label class="form-label">Startbeleg-Inhalt (optional, zur Sicherheit einfügen)</label>
                <textarea name="startbeleg_inhalt" class="erp-input" rows="2" style="font-family:monospace;font-size:12px"><?= htmlspecialchars($e['startbeleg_inhalt'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px">
        <button type="submit" name="aktion" value="speichern" class="btn btn-secondary">Speichern</button>
        <button type="submit" name="aktion" value="abschliessen" class="btn btn-primary"
                onclick="return confirm('Registrierung abschließen? Die Kassen-ID wird danach gesperrt.');">
            Registrierung abschließen und sperren
        </button>
    </div>
</form>
<?php endif; ?>

<script>window.AP_TOKEN_SYNC = <?= json_encode($arbeitsplatzTokenSync) ?>;</script>
<script src="<?= BASE_PATH ?>/js/kasse_registrierung.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
