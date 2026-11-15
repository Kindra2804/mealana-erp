<?php
// Newsletter verfassen: Formular-Aktionen (neu, kopieren, löschen) + Ajax (speichern, bild, artikel_suche, artikel_info, testmail)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterService.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterRenderer.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterVersandService.php';

$svc = new NewsletterService();
$uid = (int)$_SESSION['benutzer']['id'];
$aktion = $_POST['aktion'] ?? $_GET['aktion'] ?? '';
$json = fn(array $d) => (header('Content-Type: application/json') || true) && exit(json_encode($d, JSON_UNESCAPED_UNICODE));

switch ($aktion) {
    case 'neu':
        $id = $svc->anlegen((int)($_POST['shop_id'] ?? 0), (string)($_POST['titel'] ?? ''), $uid);
        header('Location: editor.php?id=' . $id);
        exit;
    case 'kopieren':
        $id = $svc->kopieren((int)($_POST['id'] ?? 0), $uid);
        $_SESSION[$id ? 'erfolg' : 'fehler'] = $id ? 'Kopie angelegt.' : 'Kopieren nicht möglich.';
        header('Location: ' . ($id ? 'editor.php?id=' . $id : 'liste.php'));
        exit;
    case 'loeschen':
        $_SESSION[$svc->loeschen((int)($_POST['id'] ?? 0), $uid) ? 'erfolg' : 'fehler'] = 'Newsletter gelöscht.';
        header('Location: liste.php');
        exit;

    case 'pausieren':
    case 'fortsetzen':
        $id = (int)($_POST['id'] ?? 0);
        (new NewsletterVersandService())->pausieren($id, $aktion === 'pausieren', $uid);
        $_SESSION['erfolg'] = $aktion === 'pausieren' ? 'Versand angehalten.' : 'Versand läuft weiter.';
        header('Location: editor.php?id=' . $id);
        exit;
    case 'abbrechen':
        $id = (int)($_POST['id'] ?? 0);
        $_SESSION[(new NewsletterVersandService())->abbrechen($id, $uid) ? 'erfolg' : 'fehler'] = 'Versand beendet — der Rest bekommt diesen Newsletter nicht.';
        header('Location: editor.php?id=' . $id);
        exit;

    // ── Ajax ──
    case 'versand_info':
        $json((new NewsletterVersandService())->vorschau((int)($_GET['id'] ?? 0)) + ['max' => NewsletterVersandService::maxProStunde()]);
    case 'versand_starten':
        $erg = (new NewsletterVersandService())->starten((int)($_POST['id'] ?? 0), $uid);
        if ($erg['erfolg']) $_SESSION['erfolg'] = 'Versand gestartet: ' . $erg['anzahl'] . ' Empfänger. Die Mails gehen in den nächsten Stunden nach und nach raus.';
        $json($erg);
    case 'speichern':
        $daten = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $json($svc->speichern((int)($daten['id'] ?? 0), $daten));
    case 'bild':
        $json($svc->bildHochladen((int)($_POST['id'] ?? 0), $_FILES['bild'] ?? []));
    case 'artikel_suche':
        $json(['erfolg' => true, 'artikel' => $svc->artikelSuche((string)($_GET['q'] ?? ''))]);
    case 'artikel_info':
        $ids = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))));
        $erg = [];
        if ($ids) {
            $st = Database::getInstance()->query("SELECT id, artikelnummer, name FROM artikel WHERE id IN (" . implode(',', $ids) . ")");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $erg[(int)$a['id']] = $a + $svc->preis((int)$a['id']);
        }
        $json(['erfolg' => true, 'artikel' => $erg]);
    case 'testmail':
        $id = (int)($_POST['id'] ?? 0);
        $an = trim((string)($_POST['an'] ?? ''));
        $n  = $svc->laden($id);
        if (!$n) $json(['erfolg' => false, 'fehler' => 'Newsletter nicht gefunden.']);
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) $json(['erfolg' => false, 'fehler' => 'Bitte eine gültige E-Mail-Adresse.']);
        $r = new NewsletterRenderer();
        $empf = ['vorname' => $_SESSION['benutzer']['formularname'] ?? '', 'abmelde_link' => '#test'];
        try {
            // erzwingen: Testmail auch, wenn der allgemeine Mailversand (noch) aus ist — geht nur an die eingegebene Adresse
            (new Mailer())->fuerShop((int)$n['shop_id'])->sende($an, '[TEST] ' . (($n['betreff'] ?? '') ?: $n['titel']), $r->html($n, $empf), $r->text($n, $empf), true);
        } catch (Throwable $e) {
            $json(['erfolg' => false, 'fehler' => 'Versand fehlgeschlagen: ' . $e->getMessage()]);
        }
        Database::getInstance()->prepare("UPDATE newsletter SET testmail_am = NOW() WHERE id = ?")->execute([$id]);
        Logger::log('newsletter.testmail', 'newsletter', $id, ['an' => $an], $uid);
        $json(['erfolg' => true]);
}
header('Location: liste.php');
