<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Encryption.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../kasse/PaperlessService.php';
require_once __DIR__ . '/NewsletterService.php';
require_once __DIR__ . '/NewsletterRenderer.php';
require_once __DIR__ . '/NewsletterPortalService.php';

/**
 * Newsletter-Versand (Schritt 4).
 *
 * starten():  Newsletter wird „eingefroren“ — einmal gerendert (mit Platzhalter-Vorname und ohne Vorname),
 *             alle Bilder auf die Belegseite (statt Anhang → kleine Mails), alle Links als anonyme Zähl-Links,
 *             Warteschlange = alle bestätigten Empfänger des Shops zu diesem Zeitpunkt.
 * senden():   Cron newsletter_versand.php (alle 5 Min.): höchstens newsletter_max_pro_stunde je Postfach
 *             (Shops mit eigenem SMTP zählen getrennt), gleichmäßig verteilt (je Lauf 1/12 der Stundenmenge).
 *             Wer inzwischen abgemeldet ist, wird übersprungen. Je Mail: persönlicher Abmeldelink +
 *             List-Unsubscribe (Ein-Klick-Abmeldung im Mailprogramm).
 * klicksHolen(): Klick-Summen je Link von der Belegseite (ohne Bezug zu Empfängern).
 */
class NewsletterVersandService
{
    private const M_VORNAME  = '%%NL_VORNAME%%';
    private const M_ABMELDEN = '%%NL_ABMELDEN%%';
    private const MAX_VERSUCHE = 3;
    private const LAEUFE_PRO_STUNDE = 12;          // Cron alle 5 Minuten

    private PDO $db;
    private ?int $systemId = null;   // Benutzer „system“ für Protokolleinträge aus dem Cron

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->systemId = (int)$this->db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn() ?: null;
    }

    public static function maxProStunde(): int
    {
        $w = Database::getInstance()->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'newsletter_max_pro_stunde'")->fetchColumn();
        return max(1, (int)($w ?: 100));
    }

    /** Für die Bestätigungsfrage: wie viele bekommen ihn, wie lange dauert es ungefähr */
    public function vorschau(int $id): array
    {
        $n = (new NewsletterService())->laden($id);
        if (!$n) return ['erfolg' => false, 'fehler' => 'Newsletter nicht gefunden.'];
        $st = $this->db->prepare("SELECT COUNT(*) FROM newsletter_empfaenger WHERE shop_id = ? AND status = 'aktiv'");
        $st->execute([$n['shop_id']]);
        $anzahl = (int)$st->fetchColumn();
        return ['erfolg' => true, 'anzahl' => $anzahl, 'stunden' => round($anzahl / self::maxProStunde(), 1)];
    }

    public function starten(int $id, int $benutzerId): array
    {
        $n = (new NewsletterService())->laden($id);
        if (!$n) return ['erfolg' => false, 'fehler' => 'Newsletter nicht gefunden.'];
        if ($n['status'] !== 'entwurf') return ['erfolg' => false, 'fehler' => 'Dieser Newsletter wurde schon verschickt.'];
        if (trim((string)$n['betreff']) === '') return ['erfolg' => false, 'fehler' => 'Bitte zuerst einen Betreff eintragen.'];
        if (!$n['testmail_am']) return ['erfolg' => false, 'fehler' => 'Bitte zuerst eine Testmail an dich selbst schicken und ansehen.'];
        $konf = PaperlessService::konfig();
        if ($konf['url'] === '' || $konf['schluessel'] === '') {
            return ['erfolg' => false, 'fehler' => 'Die Belegseite ist nicht eingerichtet (Einstellungen → System → Paperless) — ohne sie gibt es keine Abmeldelinks.'];
        }
        $portal = new NewsletterPortalService();
        $basis  = rtrim($konf['url'], '/');

        // 1. Link-Schlüssel aller Empfänger aktuell halten (Abmeldelinks müssen funktionieren)
        $portal->schluesselUebertragen($konf);

        // 2. Einfrieren: HTML/Text mit Platzhalter-Vorname und ohne Vorname
        $r = new NewsletterRenderer();
        $vorlage = [
            'html_mit'  => $r->html($n, ['vorname' => self::M_VORNAME, 'abmelde_link' => self::M_ABMELDEN]),
            'html_ohne' => $r->html($n, ['vorname' => '', 'abmelde_link' => self::M_ABMELDEN]),
            'text_mit'  => $r->text($n, ['vorname' => self::M_VORNAME, 'abmelde_link' => self::M_ABMELDEN]),
            'text_ohne' => $r->text($n, ['vorname' => '', 'abmelde_link' => self::M_ABMELDEN]),
        ];

        // 3. Bilder auf die Belegseite
        $bilder = [];
        try {
            foreach (['html_mit', 'html_ohne'] as $k) {
                $vorlage[$k] = preg_replace_callback('#src="data:image/(png|jpe?g|gif);base64,([A-Za-z0-9+/=]+)"#', function ($m) use (&$bilder, $portal, $konf, $basis) {
                    $daten = base64_decode($m[2]);
                    $name  = sha1($daten) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
                    if (!isset($bilder[$name])) {
                        $a = $portal->anfrage('nl_bild', ['name' => $name, 'daten' => $m[2]], $konf);
                        if (empty($a['erfolg'])) throw new RuntimeException('Bild konnte nicht auf die Belegseite geladen werden: ' . ($a['fehler'] ?? '?'));
                        $bilder[$name] = true;
                    }
                    return 'src="' . $basis . '/newsletter.php?i=' . $name . '"';
                }, $vorlage[$k]);
            }
        } catch (Throwable $e) {
            return ['erfolg' => false, 'fehler' => $e->getMessage()];
        }

        // 4. Links → Zähl-Links (gleiche Adresse = gleicher Schlüssel; Abmeldelink bleibt direkt)
        $links = [];
        foreach (['html_mit', 'html_ohne'] as $k) {
            $vorlage[$k] = preg_replace_callback('#href="(https?://[^"]+)"#i', function ($m) use (&$links, $basis) {
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                if (str_starts_with($url, $basis . '/newsletter.php')) return $m[0];
                $links[$url] ??= bin2hex(random_bytes(8));
                return 'href="' . $basis . '/newsletter.php?k=' . $links[$url] . '"';
            }, $vorlage[$k]);
        }
        if ($links) {
            $a = $portal->anfrage('nl_links_setzen', ['links' => array_map(fn($url, $k) => ['schluessel' => $k, 'url' => $url], array_keys($links), $links)], $konf);
            if (empty($a['erfolg'])) return ['erfolg' => false, 'fehler' => 'Links konnten nicht auf der Belegseite angelegt werden: ' . ($a['fehler'] ?? '?')];
        }

        // 5. Warteschlange + Status (in einer Transaktion; nur wenn noch Entwurf)
        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare("UPDATE newsletter SET status = 'versand_laeuft', vorlage = ?, versand_start_am = NOW(), versand_von = ?, versand_pausiert = 0
                                       WHERE id = ? AND status = 'entwurf'");
            $upd->execute([json_encode($vorlage, JSON_UNESCAPED_UNICODE), $benutzerId, $id]);
            if ($upd->rowCount() === 0) { $this->db->rollBack(); return ['erfolg' => false, 'fehler' => 'Der Newsletter wurde inzwischen schon gestartet.']; }
            $this->db->prepare("DELETE FROM newsletter_links WHERE newsletter_id = ?")->execute([$id]);
            $ins = $this->db->prepare("INSERT INTO newsletter_links (newsletter_id, schluessel, url) VALUES (?, ?, ?)");
            foreach ($links as $url => $k) $ins->execute([$id, $k, mb_substr($url, 0, 1000)]);
            $q = $this->db->prepare("INSERT IGNORE INTO newsletter_versand (newsletter_id, empfaenger_id)
                                     SELECT ?, id FROM newsletter_empfaenger WHERE shop_id = ? AND status = 'aktiv'");
            $q->execute([$id, $n['shop_id']]);
            $anzahl = $q->rowCount();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('newsletter.versand_gestartet', 'newsletter', $id, ['empfaenger' => $anzahl, 'bilder' => count($bilder), 'links' => count($links)], $benutzerId);
        return ['erfolg' => true, 'anzahl' => $anzahl];
    }

    public function pausieren(int $id, bool $pause, int $benutzerId): bool
    {
        $st = $this->db->prepare("UPDATE newsletter SET versand_pausiert = ? WHERE id = ? AND status = 'versand_laeuft'");
        $st->execute([$pause ? 1 : 0, $id]);
        if ($st->rowCount()) Logger::log($pause ? 'newsletter.versand_angehalten' : 'newsletter.versand_fortgesetzt', 'newsletter', $id, [], $benutzerId);
        return $st->rowCount() > 0;
    }

    /** Rest nicht mehr verschicken (wer ihn schon hat, hat ihn) */
    public function abbrechen(int $id, int $benutzerId): bool
    {
        $st = $this->db->prepare("UPDATE newsletter SET status = 'versendet', versand_ende_am = NOW(), versand_pausiert = 0 WHERE id = ? AND status = 'versand_laeuft'");
        $st->execute([$id]);
        if (!$st->rowCount()) return false;
        $q = $this->db->prepare("UPDATE newsletter_versand SET status = 'uebersprungen', fehler = 'Versand abgebrochen' WHERE newsletter_id = ? AND status = 'offen'");
        $q->execute([$id]);
        Logger::log('newsletter.versand_abgebrochen', 'newsletter', $id, ['nicht_verschickt' => $q->rowCount()], $benutzerId);
        return true;
    }

    /** @return array{gesamt:int, offen:int, gesendet:int, fehler:int, uebersprungen:int} */
    public function fortschritt(int $id): array
    {
        $st = $this->db->prepare("SELECT status, COUNT(*) FROM newsletter_versand WHERE newsletter_id = ? GROUP BY status");
        $st->execute([$id]);
        $z = $st->fetchAll(PDO::FETCH_KEY_PAIR) + ['offen' => 0, 'gesendet' => 0, 'fehler' => 0, 'uebersprungen' => 0];
        $z = array_map('intval', $z);
        return ['gesamt' => array_sum($z)] + $z;
    }

    public function links(int $id): array
    {
        $st = $this->db->prepare("SELECT url, klicks FROM newsletter_links WHERE newsletter_id = ? ORDER BY klicks DESC, id");
        $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cron: fällige Mails verschicken. */
    public function senden(?callable $log = null): array
    {
        $erg = ['gesendet' => 0, 'fehler' => 0, 'uebersprungen' => 0];
        $log ??= fn() => null;
        $mailAktiv = $this->db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'mail_aktiv'")->fetchColumn() === '1';
        $laufend = $this->db->query("SELECT n.id, n.shop_id, n.betreff, n.vorlage, s.mail_smtp_host, s.mail_smtp_user
                                     FROM newsletter n JOIN shops s ON s.id = n.shop_id
                                     WHERE n.status = 'versand_laeuft' AND n.versand_pausiert = 0 ORDER BY n.versand_start_am, n.id")->fetchAll(PDO::FETCH_ASSOC);
        if (!$laufend) return $erg;
        if (!$mailAktiv) { $log('Newsletter: Mailversand ist ausgeschaltet (Einstellungen → Mail) — nichts verschickt.'); return $erg; }
        $konf = PaperlessService::konfig();
        $basis = rtrim($konf['url'], '/');

        $max = self::maxProStunde();
        $proLauf = (int)ceil($max / self::LAEUFE_PRO_STUNDE);
        $budget = [];   // je Postfach: was in diesem Lauf noch geht

        foreach ($laufend as $n) {
            $postfach = $this->postfach($n);
            if (!isset($budget[$postfach])) {
                $budget[$postfach] = max(0, min($proLauf, $max - $this->gesendetLetzteStunde($postfach)));
            }
            if ($budget[$postfach] <= 0) continue;
            $vorlage = json_decode((string)$n['vorlage'], true);
            if (!$vorlage) continue;

            $st = $this->db->prepare("SELECT v.id, v.versuche, e.id AS empf_id, e.status, e.email_enc, e.vorname_enc, e.token
                                      FROM newsletter_versand v JOIN newsletter_empfaenger e ON e.id = v.empfaenger_id
                                      WHERE v.newsletter_id = ? AND v.status = 'offen' ORDER BY v.id LIMIT " . (int)$budget[$postfach]);
            $st->execute([$n['id']]);
            $mailer = (new Mailer())->fuerShop((int)$n['shop_id']);
            $fehlerInFolge = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
                if ($v['status'] !== 'aktiv') {
                    $this->db->prepare("UPDATE newsletter_versand SET status = 'uebersprungen', fehler = 'inzwischen abgemeldet' WHERE id = ?")->execute([$v['id']]);
                    $erg['uebersprungen']++;
                    continue;
                }
                $email   = (string)Encryption::decrypt($v['email_enc']);
                $vorname = trim((string)Encryption::decrypt($v['vorname_enc']));
                $abmelden = $basis . '/newsletter.php?a=' . $v['token'];
                $html = str_replace([self::M_VORNAME, self::M_ABMELDEN],
                                    [htmlspecialchars($vorname, ENT_QUOTES, 'UTF-8'), $abmelden], $vorname !== '' ? $vorlage['html_mit'] : $vorlage['html_ohne']);
                $text = str_replace([self::M_VORNAME, self::M_ABMELDEN], [$vorname, $abmelden], $vorname !== '' ? $vorlage['text_mit'] : $vorlage['text_ohne']);
                $betreff = $vorname !== '' ? str_replace('{vorname}', $vorname, $n['betreff']) : preg_replace('/\s*\{vorname\}/u', '', $n['betreff']);
                try {
                    $mailer->sende($email, $betreff, $html, $text, false, [], [
                        'List-Unsubscribe'      => '<' . $abmelden . '>',
                        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                    ]);
                    $this->db->prepare("UPDATE newsletter_versand SET status = 'gesendet', gesendet_am = NOW(), versuche = versuche + 1, fehler = NULL WHERE id = ?")->execute([$v['id']]);
                    $this->db->prepare("UPDATE newsletter_empfaenger SET letzter_newsletter_am = NOW(), geaendert_am = geaendert_am WHERE id = ?")->execute([$v['empf_id']]);
                    $erg['gesendet']++;
                    $budget[$postfach]--;
                    $fehlerInFolge = 0;
                } catch (Throwable $e) {
                    $endgueltig = $v['versuche'] + 1 >= self::MAX_VERSUCHE;
                    $this->db->prepare("UPDATE newsletter_versand SET versuche = versuche + 1, fehler = ?, status = ? WHERE id = ?")
                        ->execute([mb_substr($e->getMessage(), 0, 500), $endgueltig ? 'fehler' : 'offen', $v['id']]);
                    if ($endgueltig) $erg['fehler']++;
                    $budget[$postfach]--;   // Fehlversuch zählt (Server-Grenze)
                    if (++$fehlerInFolge >= 3) {
                        $log('Newsletter ' . $n['id'] . ': 3 Fehler hintereinander, Postfach pausiert bis zum nächsten Lauf: ' . $e->getMessage());
                        $budget[$postfach] = 0;
                        break;
                    }
                }
            }
            $this->fertigPruefen((int)$n['id']);
        }
        if ($erg['gesendet'] + $erg['fehler'] > 0) Logger::log('newsletter.versand_lauf', null, null, $erg, $this->systemId);
        return $erg;
    }

    /** Klick-Summen von der Belegseite (Newsletter der letzten 60 Tage) */
    public function klicksHolen(): int
    {
        $konf = PaperlessService::konfig();
        if ($konf['url'] === '' || $konf['schluessel'] === '') return 0;
        $links = $this->db->query("SELECT l.id, l.schluessel, l.newsletter_id FROM newsletter_links l JOIN newsletter n ON n.id = l.newsletter_id
                                   WHERE n.status <> 'entwurf' AND n.versand_start_am > NOW() - INTERVAL 60 DAY")->fetchAll(PDO::FETCH_ASSOC);
        if (!$links) return 0;
        $a = (new NewsletterPortalService())->anfrage('nl_klicks', ['schluessel' => array_column($links, 'schluessel')], $konf);
        if (empty($a['erfolg'])) return 0;
        $upd = $this->db->prepare("UPDATE newsletter_links SET klicks = ? WHERE id = ?");
        $nls = [];
        foreach ($links as $l) {
            $upd->execute([(int)($a['klicks'][$l['schluessel']] ?? 0), $l['id']]);
            $nls[$l['newsletter_id']] = true;
        }
        foreach (array_keys($nls) as $nid) {
            $this->db->prepare("UPDATE newsletter SET klicks = (SELECT COALESCE(SUM(klicks), 0) FROM newsletter_links WHERE newsletter_id = ?), klicks_am = NOW() WHERE id = ?")
                ->execute([$nid, $nid]);
        }
        return count($nls);
    }

    private function fertigPruefen(int $id): void
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM newsletter_versand WHERE newsletter_id = ? AND status = 'offen'");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() === 0) {
            $this->db->prepare("UPDATE newsletter SET status = 'versendet', versand_ende_am = NOW() WHERE id = ? AND status = 'versand_laeuft'")->execute([$id]);
            Logger::log('newsletter.versand_fertig', 'newsletter', $id, $this->fortschritt($id), $this->systemId);
        }
    }

    /** Postfach-Schlüssel: eigenes SMTP des Shops oder das allgemeine */
    private function postfach(array $shop): string
    {
        $host = trim((string)($shop['mail_smtp_host'] ?? ''));
        return $host === '' ? 'allgemein' : mb_strtolower($host . '|' . ($shop['mail_smtp_user'] ?? ''));
    }

    private function gesendetLetzteStunde(string $postfach): int
    {
        $zeilen = $this->db->query("SELECT s.mail_smtp_host, s.mail_smtp_user, COUNT(*) AS anzahl
                                    FROM newsletter_versand v JOIN newsletter n ON n.id = v.newsletter_id JOIN shops s ON s.id = n.shop_id
                                    WHERE v.gesendet_am > NOW() - INTERVAL 1 HOUR GROUP BY s.id")->fetchAll(PDO::FETCH_ASSOC);
        $summe = 0;
        foreach ($zeilen as $z) if ($this->postfach($z) === $postfach) $summe += (int)$z['anzahl'];
        return $summe;
    }
}
