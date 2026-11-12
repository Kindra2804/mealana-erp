<?php
/**
 * GPSR-Texte aus Beschreibungen entfernen: erst prüfen (Zahlen + Liste), dann per Knopf entfernen.
 * Logik: GpsrTextBereinigung. Einmal-Werkzeug nach dem JTL-Import (2026-11-12).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/GpsrTextBereinigung.php';

$service  = new GpsrTextBereinigung();
$ergebnis = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'entfernen') {
    $ergebnis = $service->entferne();
}
$p = $service->pruefe();

$pageTitle    = 'GPSR-Texte bereinigen';
$activeModule = 'artikel';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<style>
.gpsr-zahlen { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px }
.gpsr-zahl { background:var(--color-surface,#fff); border:1px solid var(--color-border,#e2e8f0); border-radius:8px; padding:10px 16px; min-width:150px }
.gpsr-zahl b { display:block; font-size:22px }
.gpsr-zahl span { font-size:12px; color:var(--color-text-muted) }
.gpsr-tab { width:100%; border-collapse:collapse; font-size:13px }
.gpsr-tab th { text-align:left; font-size:12px; color:var(--color-text-muted); padding:6px; border-bottom:2px solid var(--color-border,#e2e8f0) }
.gpsr-tab td { padding:5px 6px; border-bottom:1px solid var(--color-border,#eef1f5) }
.gpsr-hinweis { background:#eef4fb; border:1px solid #c9dcf2; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:14px }
.gpsr-hinweis b.i { display:inline-block; width:18px; height:18px; border-radius:50%; background:#2563eb; color:#fff; text-align:center; font-size:12px; line-height:18px; margin-right:6px }
</style>

<?php if ($ergebnis): ?>
    <div class="gpsr-hinweis" style="<?= $ergebnis['erfolg'] ? 'background:#ecfdf3;border-color:#a7e3bf' : 'background:#fdecec;border-color:#f3b4b4' ?>">
        <?= $ergebnis['erfolg']
            ? '✓ ' . (int)$ergebnis['geaendert'] . ' Artikel bereinigt und für den Shop-Abgleich vorgemerkt.' . ($ergebnis['sicherung'] ? ' Sicherung der alten Texte: <code>' . htmlspecialchars($ergebnis['sicherung']) . '</code>' : '')
            : htmlspecialchars($ergebnis['fehler']) ?>
    </div>
<?php endif; ?>

<p style="font-size:13px;color:var(--color-text-muted);margin:0 0 12px">
    Sucht in Lang- und Kurzbeschreibungen nach GPSR-Blöcken aus JTL („Kontaktinformation / Verantwortliche Person gem. Art. 19 EU GPSR“
    bzw. HTML des JTL-Plugins „xo-gpsr“). Ab der ersten solchen Überschrift wird der Text abgeschnitten. Die Pflichtangaben zeigt der Shop
    weiterhin im Reiter „Produktsicherheit“ – aus den Hersteller-Stammdaten.
</p>

<?php if (!$p['gesamt']): ?>
    <div class="card"><p style="padding:var(--space-md)">✓ Keine Artikel mit GPSR-Text in der Beschreibung.</p></div>
<?php else: ?>
    <div class="gpsr-zahlen">
        <div class="gpsr-zahl"><b><?= number_format($p['gesamt'], 0, ',', '.') ?></b><span>Artikel betroffen</span></div>
        <div class="gpsr-zahl"><b><?= number_format($p['vaeter'], 0, ',', '.') ?></b><span>Väter / Einzelartikel</span></div>
        <div class="gpsr-zahl"><b><?= number_format($p['kinder'], 0, ',', '.') ?></b><span>Kinder (Varianten)</span></div>
        <div class="gpsr-zahl"><b><?= number_format($p['leer_danach'], 0, ',', '.') ?></b><span>danach ohne Beschreibung</span></div>
        <div class="gpsr-zahl"><b><?= $p['felder']['kurzbeschreibung'] ?></b><span>davon in der Kurzbeschreibung</span></div>
    </div>

    <?php if ($p['ohne_adresse']): ?>
        <div class="gpsr-hinweis"><b class="i">!</b>Bei diesen Herstellern fehlt im ERP die Adresse – bitte unter Artikel → Hersteller nachtragen,
            sonst fehlen im Shop die GPSR-Angaben: <?= htmlspecialchars(implode(', ', array_map(fn($h, $n) => "$h ($n)", array_keys($p['ohne_adresse']), $p['ohne_adresse']))) ?></div>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('GPSR-Texte bei <?= (int)$p['gesamt'] ?> Artikeln entfernen?\n\nDie alten Texte werden vorher gesichert (storage/gpsr_sicherung).')" style="margin-bottom:16px">
        <input type="hidden" name="aktion" value="entfernen">
        <button type="submit" class="btn btn-primary btn-sm">GPSR-Texte bei <?= number_format($p['gesamt'], 0, ',', '.') ?> Artikeln entfernen</button>
    </form>

    <h3 style="font-size:14px;margin:0 0 6px">Nach Hersteller (Väter / Einzelartikel)</h3>
    <p style="font-size:13px;margin:0 0 14px"><?= htmlspecialchars(implode(' · ', array_map(fn($h, $n) => "$h $n", array_keys($p['hersteller']), $p['hersteller']))) ?></p>

    <table class="gpsr-tab">
        <thead><tr><th>Artikel</th><th>Hersteller</th><th>Nach dem Entfernen</th></tr></thead>
        <tbody>
        <?php foreach ($p['liste'] as $z): ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/artikel/detail.php?id=<?= $z['id'] ?>" target="_blank"><?= htmlspecialchars($z['artikelnummer']) ?></a> <?= htmlspecialchars($z['name']) ?></td>
                <td><?= htmlspecialchars($z['hersteller']) ?></td>
                <td style="color:var(--color-text-muted)"><?= $z['bleibt_text'] ? 'eigener Text bleibt' : 'Beschreibung leer' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
