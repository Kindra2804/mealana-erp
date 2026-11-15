<?php
// Newsletter — öffentliche Seiten auf der Belegseite:
//   POST (Formular aus dem Shop): shop, email, vorname?, zustimmung, website (Honigtopf), zurueck?
//        → Anmeldung in die Warteschlange; das ERP schickt die Bestätigungs-Mail (Double-Opt-in)
//   GET  ?b=TOKEN  → Anmeldung bestätigen
//   GET  ?a=TOKEN  → Abmelden-Seite mit Knopf; POST a=TOKEN (auch „List-Unsubscribe=One-Click“) → abgemeldet
//   GET  ?i=NAME   → Bild eines Newsletters
//   GET  ?k=SCHLÜSSEL → Zähl-Link: Klick +1 (nur Summe, ohne Empfänger), Weiterleitung zur hinterlegten Adresse
require __DIR__ . '/newsletter_lib.php';

if (isset($_GET['i'])) {
    $name = (string)$_GET['i'];
    $datei = nl_bild_name_ok($name) ? nl_bild_ordner() . '/' . $name : '';
    if (!$datei || !is_file($datei)) { http_response_code(404); exit; }
    header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'][pathinfo($name, PATHINFO_EXTENSION)]);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($datei));
    readfile($datei);
    exit;
}
if (isset($_GET['k'])) {
    $ziel = null;
    try {
        $k = (string)$_GET['k'];
        if (preg_match('/^[a-f0-9]{16}$/', $k)) {
            $st = nl_db()->prepare("SELECT url FROM " . nl_tab('nl_links') . " WHERE schluessel = ?");
            $st->execute([$k]);
            $ziel = $st->fetchColumn() ?: null;
            if ($ziel) nl_db()->prepare("UPDATE " . nl_tab('nl_links') . " SET klicks = klicks + 1 WHERE schluessel = ?")->execute([$k]);
        }
    } catch (Throwable $e) {
        error_log('[Belegseite Newsletter] ' . $e->getMessage());
    }
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    if ($ziel) { header('Location: ' . $ziel, true, 302); exit; }
    http_response_code(404);
    exit('Link nicht gefunden.');
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$titel = 'Newsletter';
$text  = '';
$shop  = null;
$knopf = null;   // Abmelde-Formular

try {
    $methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($methode === 'POST' && isset($_POST['email'])) {
        // ── Anmeldung ──
        $shop  = nl_shop((string)($_POST['shop'] ?? ''));
        $email = mb_strtolower(trim((string)$_POST['email']));
        $zurueck = (string)($_POST['zurueck'] ?? '');
        if (!$shop) {
            $titel = 'Anmeldung nicht möglich'; $text = 'Dieses Anmeldeformular ist nicht (mehr) gültig.';
        } elseif (trim((string)($_POST['website'] ?? '')) !== '') {
            $titel = 'Fast geschafft'; $text = 'Bitte bestätige deine Anmeldung über den Link in der E-Mail.';   // Bot: still verwerfen
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) {
            $titel = 'Bitte prüfen'; $text = 'Die E-Mail-Adresse scheint nicht zu stimmen. Bitte gehe zurück und versuche es noch einmal.';
        } elseif (empty($_POST['zustimmung'])) {
            $titel = 'Bitte prüfen'; $text = 'Für die Anmeldung brauchen wir deine Zustimmung (Häkchen). Bitte gehe zurück und versuche es noch einmal.';
        } elseif (nl_zu_viele_anmeldungen()) {
            $titel = 'Bitte später noch einmal'; $text = 'Von deinem Anschluss kamen gerade sehr viele Anmeldungen. Bitte versuche es in einer Stunde wieder.';
        } else {
            nl_ereignis('anmeldung', $shop['slug'], null, $email, mb_substr(trim((string)($_POST['vorname'] ?? '')), 0, 100),
                        ['zustimmung' => mb_substr((string)$_POST['zustimmung'], 0, 300), 'seite' => mb_substr($zurueck, 0, 255)]);
            if (nl_ruecksprung_ok($shop, $zurueck)) {
                header('Location: ' . $zurueck . (str_contains($zurueck, '?') ? '&' : '?') . 'newsletter=danke', true, 303);
                exit;
            }
            $titel = 'Fast geschafft!';
            $text  = 'Danke für deine Anmeldung. In den nächsten Minuten bekommst du eine E-Mail — bitte klicke dort auf den Link, '
                   . 'um die Anmeldung zu bestätigen. Erst dann bist du eingetragen. (Nichts bekommen? Bitte auch im Spam-Ordner schauen.)';
        }

    } elseif (isset($_GET['b'])) {
        // ── Bestätigen (Double-Opt-in) ──
        $t = nl_token((string)$_GET['b']);
        $shop = $t ? nl_shop($t['shop']) : null;
        if (!$t) {
            $titel = 'Link nicht gültig'; $text = 'Dieser Bestätigungslink ist nicht (mehr) gültig. Bitte melde dich einfach noch einmal an.';
        } elseif ($t['status'] === 'aktiv') {
            $titel = 'Schon bestätigt'; $text = 'Deine Anmeldung ist bereits bestätigt — danke!';
        } elseif ($t['status'] === 'abgemeldet') {
            $titel = 'Abgemeldet'; $text = 'Diese Adresse ist vom Newsletter abgemeldet. Für eine neue Anmeldung bitte das Formular im Shop verwenden.';
        } else {
            nl_ereignis('bestaetigt', $t['shop'], $t['token']);
            nl_token_status($t['token'], 'aktiv');
            $titel = 'Danke, du bist dabei!'; $text = 'Deine Anmeldung zum Newsletter ist bestätigt. Abmelden kannst du dich jederzeit über den Link in jedem Newsletter.';
        }

    } elseif (isset($_GET['a']) || isset($_POST['a'])) {
        // ── Abmelden (Seite mit Knopf; POST = abmelden, auch Ein-Klick-Abmeldung aus dem Mailprogramm) ──
        $tok = (string)($_POST['a'] ?? $_GET['a']);
        $t = nl_token($tok);
        $shop = $t ? nl_shop($t['shop']) : null;
        if (!$t) {
            $titel = 'Link nicht gültig'; $text = 'Dieser Abmeldelink ist nicht (mehr) gültig. Bitte wende dich direkt an uns.';
        } elseif ($t['status'] === 'abgemeldet') {
            $titel = 'Abgemeldet'; $text = 'Du bist bereits vom Newsletter abgemeldet.';
        } elseif ($methode === 'POST') {
            nl_ereignis('abgemeldet', $t['shop'], $t['token'], null, null,
                        ['art' => isset($_POST['List-Unsubscribe']) ? 'ein-klick (Mailprogramm)' : 'abmeldeseite']);
            nl_token_status($t['token'], 'abgemeldet');
            $titel = 'Abgemeldet'; $text = 'Du bekommst unseren Newsletter ab sofort nicht mehr. Schade — und danke, dass du dabei warst!';
        } else {
            $titel = 'Newsletter abbestellen'; $text = 'Möchtest du unseren Newsletter wirklich nicht mehr bekommen?';
            $knopf = $t['token'];
        }
    } else {
        $titel = 'Newsletter'; $text = 'Diese Seite verwaltet die Anmeldung zu unseren Newslettern.';
    }
} catch (Throwable $e) {
    error_log('[Belegseite Newsletter] ' . $e->getMessage());
    $titel = 'Kurze Störung'; $text = 'Gerade ist etwas schiefgelaufen. Bitte versuche es in ein paar Minuten noch einmal.';
}

$farbe = ($shop && preg_match('/^#[0-9a-f]{3,8}$/i', (string)$shop['farbe'])) ? $shop['farbe'] : '#1e3a5f';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $h($titel) ?><?= $shop ? ' — ' . $h($shop['name']) : '' ?></title>
    <style>
        body { margin: 0; background: #f3f4f6; font-family: -apple-system, 'Segoe UI', Arial, sans-serif; color: #1f2937; }
        .karte { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 14px; padding: 28px 24px;
                 box-shadow: 0 4px 20px rgba(0,0,0,.06); border-top: 6px solid <?= $h($farbe) ?>; }
        .shop { font-size: 13px; color: #6b7280; margin-bottom: 6px; }
        h1 { font-size: 22px; margin: 0 0 12px; color: <?= $h($farbe) ?>; }
        p { line-height: 1.6; margin: 0 0 14px; }
        button, .link { display: inline-block; background: <?= $h($farbe) ?>; color: #fff; border: 0; border-radius: 8px;
                        padding: 12px 20px; font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media (max-width: 520px) { .karte { margin: 16px; } }
    </style>
</head>
<body>
<div class="karte">
    <?php if ($shop): ?><div class="shop"><?= $h($shop['name']) ?></div><?php endif; ?>
    <h1><?= $h($titel) ?></h1>
    <p><?= $h($text) ?></p>
    <?php if ($knopf): ?>
        <form method="post"><input type="hidden" name="a" value="<?= $h($knopf) ?>"><button>Ja, abmelden</button></form>
    <?php elseif ($shop && !empty($shop['url'])): ?>
        <a class="link" href="<?= $h($shop['url']) ?>">Zum Shop</a>
    <?php endif; ?>
</div>
</body>
</html>
