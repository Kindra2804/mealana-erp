<?php
// Belegseite (Paperless) — Schnittstelle fürs ERP (nur mit API-Schlüssel im Header X-Nahtlos-Schluessel)
//   GET  api.php?aktion=test            → Verbindung + Datenbank prüfen
//   POST api.php?aktion=upload          → PDF + Bon-Nr./Betrag/Datum/Firma/Logo, Antwort: token + url
//   GET  api.php?aktion=status&t=TOKEN  → {abgerufen: true|false}
//   GET  api.php?aktion=verwerfen&t=TOKEN → nicht abgerufenen Beleg sofort löschen, {abgerufen: true|false}
//   Newsletter (Body JSON):
//   POST api.php?aktion=nl_setzen     {shops:[{slug,name,url,farbe}], tokens:[{token,shop,status}]} → Seiten/Link-Schlüssel
//   GET  api.php?aktion=nl_holen      → {ereignisse:[…]} (Anmeldung/Bestätigung/Abmeldung, älteste zuerst, max. 500)
//   POST api.php?aktion=nl_quittieren {ids:[…]} → verarbeitete Ereignisse hier löschen (Datensparsamkeit)
require __DIR__ . '/lib.php';
require __DIR__ . '/newsletter_lib.php';

bp_api_pruefen();
$aktion = $_GET['aktion'] ?? '';

try {
    switch ($aktion) {
        case 'test':
            $db = bp_db();
            bp_aufraeumen();
            $ordner = bp_config()['ordner'];
            if (!is_dir($ordner)) @mkdir($ordner, 0750, true);
            bp_json(['erfolg' => true, 'version' => BP_VERSION, 'datenbank' => bp_config()['db']['typ'],
                     'ordner_beschreibbar' => is_writable($ordner),
                     'anzahl' => (int)$db->query("SELECT COUNT(*) FROM " . bp_tabelle())->fetchColumn()]);

        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') bp_json(['erfolg' => false, 'fehler' => 'Nur POST.'], 405);
            $pdf = $_FILES['pdf']['tmp_name'] ?? '';
            if (!$pdf || !is_uploaded_file($pdf) || filesize($pdf) > 5 * 1024 * 1024
                || file_get_contents($pdf, false, null, 0, 5) !== '%PDF-') {
                bp_json(['erfolg' => false, 'fehler' => 'Keine gültige PDF (max. 5 MB).'], 400);
            }
            bp_aufraeumen();
            $token  = bin2hex(random_bytes(24));
            $ordner = bp_config()['ordner'];
            if (!is_dir($ordner)) mkdir($ordner, 0750, true);
            if (!move_uploaded_file($pdf, bp_pdf_pfad($token))) {
                bp_json(['erfolg' => false, 'fehler' => 'PDF konnte nicht gespeichert werden.'], 500);
            }
            $logo = (string)($_POST['logo'] ?? '');
            $logoOk = preg_match('#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$#', $logo) && strlen($logo) < 400000;
            bp_db()->prepare("INSERT INTO " . bp_tabelle() . " (token, bon_nr, betrag, beleg_datum, firma, logo, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$token,
                              mb_substr((string)($_POST['bon_nr'] ?? ''), 0, 40),
                              mb_substr((string)($_POST['betrag'] ?? ''), 0, 20),
                              mb_substr((string)($_POST['datum'] ?? ''), 0, 20),
                              mb_substr((string)($_POST['firma'] ?? ''), 0, 200),
                              $logoOk ? $logo : null,
                              date('Y-m-d H:i:s')]);
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $basis = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            bp_json(['erfolg' => true, 'token' => $token, 'url' => $basis . '/?t=' . $token]);

        case 'status':
            $b = bp_beleg((string)($_GET['t'] ?? ''));
            bp_json(['erfolg' => (bool)$b, 'abgerufen' => $b !== null && $b['abgerufen_am'] !== null, 'abgerufen_am' => $b['abgerufen_am'] ?? null]);

        case 'verwerfen':
            // Kunde hat nicht gescannt (60 s vorbei / nächster Kunde / doch gedruckt) → sofort löschen.
            // Nur wenn noch nicht abgerufen — sonst bleibt er (30 Tage) und die Antwort sagt das.
            $token = (string)($_GET['t'] ?? '');
            $b = bp_beleg($token);
            if ($b && $b['abgerufen_am'] === null) {
                $del = bp_db()->prepare("DELETE FROM " . bp_tabelle() . " WHERE token = ? AND abgerufen_am IS NULL");
                $del->execute([$token]);
                if ($del->rowCount() > 0) {
                    @unlink(bp_pdf_pfad($token));
                    bp_json(['erfolg' => true, 'abgerufen' => false]);
                }
                $b = bp_beleg($token);   // in genau diesem Moment abgerufen
            }
            bp_json(['erfolg' => true, 'abgerufen' => $b !== null && $b['abgerufen_am'] !== null]);

        case 'nl_setzen':
            $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
            $db = nl_db();
            $jetzt = date('Y-m-d H:i:s');
            $mysql = bp_config()['db']['typ'] === 'mysql';
            $upsert = fn(string $tab, string $cols, string $platz) => $mysql
                ? "INSERT INTO $tab ($cols) VALUES ($platz) ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($c) => "$c = VALUES($c)", explode(', ', $cols)))
                : "INSERT OR REPLACE INTO $tab ($cols) VALUES ($platz)";
            $db->beginTransaction();
            $sh = $db->prepare($upsert(nl_tab('nl_shops'), 'slug, name, url, farbe, aktualisiert', '?, ?, ?, ?, ?'));
            foreach (($in['shops'] ?? []) as $s) {
                $sh->execute([mb_substr((string)$s['slug'], 0, 50), mb_substr((string)$s['name'], 0, 200), mb_substr((string)($s['url'] ?? ''), 0, 255) ?: null,
                              mb_substr((string)($s['farbe'] ?? ''), 0, 20) ?: null, $jetzt]);
            }
            $tk = $db->prepare($upsert(nl_tab('nl_tokens'), 'token, shop, status, aktualisiert', '?, ?, ?, ?'));
            $n = 0;
            foreach (($in['tokens'] ?? []) as $t) {
                if (!preg_match('/^[a-f0-9]{32}$/', (string)($t['token'] ?? '')) || !in_array($t['status'] ?? '', ['doi', 'aktiv', 'abgemeldet'], true)) continue;
                $tk->execute([$t['token'], mb_substr((string)$t['shop'], 0, 50), $t['status'], $jetzt]);
                $n++;
            }
            $db->commit();
            bp_json(['erfolg' => true, 'tokens' => $n]);

        case 'nl_holen':
            $st = nl_db()->query("SELECT * FROM " . nl_tab('nl_ereignisse') . " ORDER BY id LIMIT 500");
            bp_json(['erfolg' => true, 'ereignisse' => $st->fetchAll()]);

        case 'nl_quittieren':
            $ids = array_values(array_filter(array_map('intval', (json_decode((string)file_get_contents('php://input'), true)['ids'] ?? []))));
            $n = 0;
            if ($ids) {
                $n = nl_db()->exec("DELETE FROM " . nl_tab('nl_ereignisse') . " WHERE id IN (" . implode(',', $ids) . ")");
            }
            bp_json(['erfolg' => true, 'geloescht' => (int)$n]);

        default:
            bp_json(['erfolg' => false, 'fehler' => 'Unbekannte Aktion.'], 400);
    }
} catch (Throwable $e) {
    error_log('[Belegseite] ' . $e->getMessage());
    bp_json(['erfolg' => false, 'fehler' => 'Serverfehler: ' . $e->getMessage()], 500);
}
