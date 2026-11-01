<?php
// Belegseite (Paperless) — Abrufseite für den Kunden: ?t=TOKEN (Seite), ?t=TOKEN&pdf=1 (Download)
require __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$token = (string)($_GET['t'] ?? '');
try {
    bp_aufraeumen();
    $beleg = bp_beleg($token);
} catch (Throwable $e) {
    error_log('[Belegseite] ' . $e->getMessage());
    $beleg = null;
}

if ($beleg) {
    bp_als_abgerufen_markieren($token);
    if (!empty($_GET['pdf'])) {
        $name = 'Beleg_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $beleg['bon_nr']) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (!empty($_GET['ansehen']) ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        header('Content-Length: ' . filesize(bp_pdf_pfad($token)));
        readfile(bp_pdf_pfad($token));
        exit;
    }
}
$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$tage = (int)bp_config()['abgerufen_tage'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $beleg ? 'Ihr Beleg ' . $h($beleg['bon_nr']) : 'Beleg nicht verfügbar' ?></title>
    <style>
        :root { --bg: #f3f4f6; --karte: #fff; --text: #1f2937; --leise: #6b7280; --akzent: #1e3a5f; --knopf-text: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #111827; --karte: #1f2937; --text: #f3f4f6; --leise: #9ca3af; --akzent: #93c5fd; --knopf-text: #111827; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text);
               font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
               display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .karte { background: var(--karte); border-radius: 14px; padding: 28px 24px; width: 100%; max-width: 420px;
                 text-align: center; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
        .logo { max-width: 200px; max-height: 80px; margin-bottom: 14px; background: #fff; border-radius: 8px; padding: 6px 10px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        .betrag { font-size: 30px; font-weight: 700; margin: 14px 0 4px; font-variant-numeric: tabular-nums; }
        .meta { color: var(--leise); font-size: 14px; }
        .knopf { display: block; margin-top: 22px; padding: 14px; border-radius: 10px; background: var(--akzent);
                 color: var(--knopf-text); font-weight: 600; text-decoration: none; font-size: 16px; }
        .knopf:focus-visible, .zweit:focus-visible { outline: 3px solid var(--akzent); outline-offset: 3px; }
        .zweit { display: inline-block; margin-top: 12px; color: var(--akzent); font-size: 14px; }
        .hinweis { margin-top: 20px; font-size: 12px; color: var(--leise); line-height: 1.5; }
    </style>
</head>
<body>
<div class="karte">
<?php if ($beleg): ?>
    <?php if ($beleg['logo']): ?><img class="logo" src="<?= $h($beleg['logo']) ?>" alt="<?= $h($beleg['firma']) ?>"><?php endif; ?>
    <h1><?= $h($beleg['firma']) ?></h1>
    <div class="meta">Beleg <?= $h($beleg['bon_nr']) ?> · <?= $h($beleg['beleg_datum']) ?></div>
    <div class="betrag">€ <?= $h($beleg['betrag']) ?></div>
    <a class="knopf" href="?t=<?= $h($token) ?>&amp;pdf=1">Beleg herunterladen (PDF)</a>
    <a class="zweit" href="?t=<?= $h($token) ?>&amp;pdf=1&amp;ansehen=1">Im Browser ansehen</a>
    <div class="hinweis">Bitte speichern Sie den Beleg — er ist hier nur <?= $tage ?> Tage abrufbar.<br>Vielen Dank für Ihren Einkauf!</div>
<?php else: ?>
    <h1>Beleg nicht verfügbar</h1>
    <div class="hinweis">Dieser Beleg ist abgelaufen oder der Link ist unvollständig.<br>
        Bei Fragen wenden Sie sich bitte an das Geschäft, in dem Sie eingekauft haben.</div>
<?php endif; ?>
</div>
</body>
</html>
