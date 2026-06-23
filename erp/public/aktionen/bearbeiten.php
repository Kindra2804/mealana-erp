<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service  = new AktionenService();
$artikelService = new ArtikelService();
$id       = (int)($_GET['id'] ?? 0);
$aktion   = $id ? $service->findById($id) : null;
$istNeu   = ($aktion === null && $id === 0);

if ($id && !$aktion) {
    header('Location: /mealana/aktionen/liste.php');
    exit;
}

$aktionsKategorien = $service->getAktionsKategorienFuerAuswahl();
$kategorienBaum = $artikelService->getKategorienBaum();

$kundengruppen  = $service->getAlleKundengruppen();
$standardKgId   = 1;
foreach ($kundengruppen as $kg) {
    if ($kg['ist_standard']) {
        $standardKgId = $kg['id'];
        break;
    }
}

$pageTitle    = $istNeu ? 'Neue Aktion' : 'Aktion: ' . htmlspecialchars($aktion['name']);
$activeModule = 'artikel';
$actionBarContent = $istNeu ? '' : match ($aktion['status']) {
    'entwurf', 'geplant' =>
    '<button onclick="aktionStarten()" class="btn btn-primary btn-sm">▶ Aktion starten</button>',
    'aktiv' =>
    '<span style="color:#107c10;font-weight:600;font-size:13px">⏰ Aktiv</span>'
        . '&nbsp;&nbsp;<button onclick="aktionStoppen()" class="btn btn-secondary btn-sm">⏸ Stoppen</button>',
    'abgelaufen' =>
    '<span style="color:#999;font-size:13px">✗ Abgelaufen</span>',
    default => ''
};

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($istNeu): ?>
    <!-- ── Neu-Formular ─────────────────────────────────────────────────── -->
    <div class="card" style="max-width:560px">
        <div style="padding:var(--space-md)">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:var(--space-md);color:var(--color-nav)">Neue Aktion anlegen</h2>
            <form method="POST" action="/mealana/aktionen/aktion_speichern.php">
                <input type="hidden" name="modus" value="neu">
                <div class="form-row">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="erp-input" style="width:100%" autofocus
                        placeholder="z.B. DROPS Frühjahr 2026">
                </div>
                <div class="form-row">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="beschreibung" class="erp-input" rows="2" style="width:100%;resize:vertical"
                        placeholder="Optionale Notiz zur Aktion"></textarea>
                </div>
                <div id="neu-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px"></div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:var(--space-md)">
                    <a href="/mealana/aktionen/liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
                    <button type="submit" class="btn btn-primary btn-sm">Anlegen & weiter</button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ── Bearbeiten-Seite ──────────────────────────────────────────────── -->

    <div id="banner" style="display:none;padding:8px 16px;border-radius:4px;margin-bottom:12px;font-size:13px"></div>

    <!-- Stammdaten -->
    <div class="card" style="margin-bottom:12px">
        <div style="padding:var(--space-md)">
            <div style="display:flex;gap:12px;align-items:flex-start">
                <div style="flex:1">
                    <div class="form-row" style="margin-bottom:8px">
                        <label class="form-label">Name *</label>
                        <input type="text" id="akt-name" class="erp-input" style="width:100%"
                            value="<?= htmlspecialchars($aktion['name']) ?>">
                    </div>
                    <div class="form-row" style="margin-bottom:0">
                        <label class="form-label">Beschreibung</label>
                        <textarea id="akt-beschreibung" class="erp-input" rows="2" style="width:100%;resize:vertical"><?= htmlspecialchars($aktion['beschreibung'] ?? '') ?></textarea>
                    </div>
                </div>
                <button onclick="stammdatenSpeichern()" class="btn btn-primary btn-sm" style="margin-top:22px;white-space:nowrap">
                    Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Aktions-Kategorien -->
    <div class="card" style="margin-bottom:12px">
        <div style="padding:var(--space-md)">
            <div style="font-size:13px;font-weight:700;color:var(--color-nav);margin-bottom:10px">
                AKTIONS-KATEGORIEN
            </div>
            <table class="erp-table" id="kat-tabelle" style="margin-bottom:10px">
                <thead>
                    <tr>
                        <th>KATEGORIE</th>
                        <th>AKTIV VON</th>
                        <th>AKTIV BIS</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="kat-tbody">
                    <?php foreach ($aktion['kategorien'] as $k): ?>
                        <tr data-akid="<?= $k['ak_id'] ?>">
                            <td style="font-weight:500"><?= htmlspecialchars($k['kat_name']) ?></td>
                            <td style="font-size:12px"><?= date('d.m.Y', strtotime($k['gueltig_ab'])) ?></td>
                            <td style="font-size:12px"><?= date('d.m.Y', strtotime($k['gueltig_bis'])) ?></td>
                            <td style="text-align:right">
                                <button onclick="katEntfernen(<?= $k['ak_id'] ?>, this)"
                                    class="btn btn-danger btn-xs">✕ Entfernen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($aktion['kategorien'])): ?>
                        <tr id="kat-leer-zeile">
                            <td colspan="4" style="color:var(--color-text-muted);font-size:12px">Noch keine Kategorien zugewiesen</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Hinzufügen-Zeile -->
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select id="kat-neu-id" class="erp-select" style="min-width:180px">
                    <option value="">— Kategorie wählen —</option>
                    <?php foreach ($aktionsKategorien as $ak): ?>
                        <option value="<?= $ak['id'] ?>"><?= htmlspecialchars($ak['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" id="kat-neu-von" class="erp-input" style="width:140px" title="Aktiv von">
                <span style="font-size:12px;color:var(--color-text-muted)">bis</span>
                <input type="date" id="kat-neu-bis" class="erp-input" style="width:140px" title="Aktiv bis">
                <button onclick="katHinzufuegen()" class="btn btn-secondary btn-sm">+ Hinzufügen</button>
            </div>
            <div id="kat-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:4px"></div>

            <?php if (empty($aktionsKategorien)): ?>
                <p style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
                    ⚠ Keine Aktions-Kategorien vorhanden. Bitte zuerst in der
                    <a href="/mealana/artikel/kategorien_verwalten.php">Kategorieverwaltung</a>
                    Kategorien als Aktions-Kategorie markieren.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preiseingabe -->
    <div class="card" id="preise-card" <?= empty($aktion['kategorien']) ? 'style="display:none"' : '' ?>>
        <div style="padding:var(--space-md)">
            <div style="font-size:13px;font-weight:700;color:var(--color-nav);margin-bottom:10px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                PREISEINGABE
                <div style="display:flex;gap:8px;align-items:center;font-weight:400">
                    <label style="font-size:12px;color:var(--color-text-muted)">Kategorie:</label>
                    <select id="preis-kat-id" class="erp-select" style="min-width:160px" onchange="artikelLaden()">
                        <option value="">— wählen —</option>
                        <?php foreach ($aktion['kategorien'] as $k): ?>
                            <option value="<?= $k['kategorie_id'] ?>"><?= htmlspecialchars($k['kat_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label style="font-size:12px;color:var(--color-text-muted)">Kundengruppe:</label>
                    <select id="preis-kg-id" class="erp-select" style="min-width:150px" onchange="artikelLaden()">
                        <?php foreach ($kundengruppen as $kg): ?>
                            <option value="<?= $kg['id'] ?>" <?= $kg['id'] == $standardKgId ? 'selected' : '' ?>>
                                <?= $kg['ist_standard'] ? '⭐ ' : '' ?><?= htmlspecialchars($kg['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="preis-inhalt">
                <p style="color:var(--color-text-muted);font-size:12px">
                    Bitte Kategorie wählen um Artikel anzuzeigen.
                </p>
            </div>
        </div>
    </div>

<?php endif; // !$istNeu 
?>

<script>var AKTION_ID = <?= $id ?>;</script>
<script src="/mealana/js/aktionen.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
