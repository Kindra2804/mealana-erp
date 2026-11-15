<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Encryption.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../kasse/PaperlessService.php';

/**
 * Newsletter Schritt 2 — Abgleich mit der Belegseite (beleg.<domain>, gleiche Adresse + API-Schlüssel wie Paperless).
 *
 * Läuft im 15-Minuten-Cron (cron/shop_sync.php):
 *  1. Ereignisse vom Portal holen (Anmeldung mit E-Mail, Bestätigung, Abmeldung) und im ERP verarbeiten
 *  2. Shops (Name/Adresse/Farbe) + geänderte Link-Schlüssel ans Portal geben (nur Schlüssel + Status, keine E-Mail);
 *     „geändert“ = portal_stand IS NULL — JEDE Statusänderung setzt portal_stand = NULL (Zeitstempel reichen nicht,
 *     mehrere Änderungen in derselben Sekunde gingen sonst verloren)
 *  3. Bestätigungs-Mails (Double-Opt-in) über das Postfach des Shops schicken — erst NACH Schritt 2,
 *     damit der Link im Mail schon funktioniert
 *  4. Verarbeitete Ereignisse am Portal löschen lassen (dort liegen danach keine E-Mail-Adressen mehr)
 */
class NewsletterPortalService
{
    private PDO $db;
    private const STATUS_PORTAL = ['ausstehend' => 'doi', 'aktiv' => 'aktiv', 'abgemeldet' => 'abgemeldet'];
    private const DOI_WIEDERHOLEN_NACH_STUNDEN = 1;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array{erfolg:bool, fehler?:string, angemeldet:int, bestaetigt:int, abgemeldet:int, doi_mails:int, schluessel:int} */
    public function abgleichen(int $benutzerId, ?callable $log = null): array
    {
        $erg = ['erfolg' => true, 'angemeldet' => 0, 'bestaetigt' => 0, 'abgemeldet' => 0, 'doi_mails' => 0, 'schluessel' => 0];
        $konf = PaperlessService::konfig();
        if ($konf['url'] === '' || $konf['schluessel'] === '') {
            return ['erfolg' => false, 'fehler' => 'Belegseite nicht eingerichtet (Einstellungen → System → Paperless).'] + $erg;
        }

        // 1. holen + verarbeiten
        $antwort = $this->anfrage('nl_holen', null, $konf);
        if (empty($antwort['erfolg'])) return ['erfolg' => false, 'fehler' => $antwort['fehler'] ?? 'Portal antwortet nicht.'] + $erg;
        $erledigt = [];
        $doiFuer  = [];
        foreach ($antwort['ereignisse'] ?? [] as $e) {
            try {
                match ($e['typ']) {
                    'anmeldung'  => ($id = $this->anmeldung($e, $benutzerId)) && ($doiFuer[$id] = true) && $erg['angemeldet']++,
                    'bestaetigt' => $this->bestaetigt($e) && $erg['bestaetigt']++,
                    'abgemeldet' => $this->abgemeldet($e) && $erg['abgemeldet']++,
                    default      => null,
                };
                $erledigt[] = (int)$e['id'];
            } catch (Throwable $ex) {
                // bleibt am Portal liegen und wird beim nächsten Lauf erneut versucht
                if ($log) $log('Newsletter: Ereignis ' . $e['id'] . ' nicht verarbeitet: ' . $ex->getMessage());
            }
        }

        // 2. Shops + geänderte Schlüssel ans Portal
        $erg['schluessel'] = $this->schluesselUebertragen($konf);

        // 3. Bestätigungs-Mails (jetzt funktioniert der Link)
        foreach (array_keys($doiFuer) as $id) {
            if ($this->bestaetigungsMail((int)$id, $konf['url'])) $erg['doi_mails']++;
        }

        // 4. Portal aufräumen
        if ($erledigt) $this->anfrage('nl_quittieren', ['ids' => $erledigt], $konf);

        if ($erg['angemeldet'] + $erg['bestaetigt'] + $erg['abgemeldet'] > 0) {
            Logger::log('newsletter.portal_abgleich', null, null, $erg, $benutzerId);
        }
        return $erg;
    }

    /** Anmeldung aus dem Shop-Formular → Empfänger „wartet auf Bestätigung“. Rückgabe: id, wenn eine DOI-Mail fällig ist. */
    private function anmeldung(array $e, int $benutzerId): ?int
    {
        $st = $this->db->prepare("SELECT id FROM shops WHERE slug = ?");
        $st->execute([(string)$e['shop']]);
        $shopId = (int)$st->fetchColumn();
        $email = mb_strtolower(trim((string)$e['email']));
        if (!$shopId || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $hash = Encryption::hash($email);
        $info = json_decode((string)($e['info'] ?? ''), true) ?: [];
        $details = ['zustimmung' => $info['zustimmung'] ?? null, 'seite' => $info['seite'] ?? null];

        $st = $this->db->prepare("SELECT * FROM newsletter_empfaenger WHERE shop_id = ? AND email_hash = ?");
        $st->execute([$shopId, $hash]);
        $alt = $st->fetch(PDO::FETCH_ASSOC);

        if ($alt && $alt['status'] === 'aktiv') {
            $this->ereignis((int)$alt['id'], 'erneut_angemeldet', $e, $details + ['hinweis' => 'war schon bestätigt, keine Mail']);
            return null;
        }
        if ($alt) {
            $erneut = $alt['status'] === 'abgemeldet'
                || !$alt['doi_gesendet_am'] || strtotime($alt['doi_gesendet_am']) < time() - self::DOI_WIEDERHOLEN_NACH_STUNDEN * 3600;
            $this->db->prepare("UPDATE newsletter_empfaenger SET status = 'ausstehend', abgemeldet_am = NULL, anmelde_ip = ?, portal_stand = NULL,
                                vorname_enc = COALESCE(vorname_enc, ?) WHERE id = ?")
                ->execute([$e['ip'] ?: null, $e['vorname'] ? Encryption::encrypt((string)$e['vorname']) : null, $alt['id']]);
            $this->ereignis((int)$alt['id'], 'angemeldet', $e, $details);
            return $erneut ? (int)$alt['id'] : null;
        }

        $kunde = $this->db->prepare("SELECT id FROM kunden WHERE email_hash = ? LIMIT 1");
        $kunde->execute([$hash]);
        $this->db->prepare("INSERT INTO newsletter_empfaenger (shop_id, email_enc, email_hash, vorname_enc, kunden_id, status, quelle,
                                eingetragen_am, anmelde_ip, token)
                            VALUES (?, ?, ?, ?, ?, 'ausstehend', 'shop_formular', ?, ?, ?)")
            ->execute([$shopId, Encryption::encrypt($email), $hash, $e['vorname'] ? Encryption::encrypt((string)$e['vorname']) : null,
                       $kunde->fetchColumn() ?: null, $e['zeit'], $e['ip'] ?: null, bin2hex(random_bytes(16))]);
        $id = (int)$this->db->lastInsertId();
        $this->ereignis($id, 'angemeldet', $e, $details);
        return $id;
    }

    private function bestaetigt(array $e): bool
    {
        $r = $this->perToken((string)$e['token']);
        if (!$r || $r['status'] !== 'ausstehend') return false;
        $this->db->prepare("UPDATE newsletter_empfaenger SET status = 'aktiv', optin_am = ?, optin_ip = ?, portal_stand = NULL WHERE id = ?")
            ->execute([$e['zeit'], $e['ip'] ?: null, $r['id']]);
        $this->ereignis((int)$r['id'], 'bestaetigt', $e, ['laut' => 'Bestätigungslink (Double-Opt-in)']);
        return true;
    }

    private function abgemeldet(array $e): bool
    {
        $r = $this->perToken((string)$e['token']);
        if (!$r || $r['status'] === 'abgemeldet') return false;
        $this->db->prepare("UPDATE newsletter_empfaenger SET status = 'abgemeldet', abgemeldet_am = ?, portal_stand = NULL WHERE id = ?")
            ->execute([$e['zeit'], $r['id']]);
        $info = json_decode((string)($e['info'] ?? ''), true) ?: [];
        $this->ereignis((int)$r['id'], 'abgemeldet', $e, ['art' => $info['art'] ?? 'abmeldeseite']);
        return true;
    }

    private function perToken(string $token): ?array
    {
        $st = $this->db->prepare("SELECT id, status FROM newsletter_empfaenger WHERE token = ?");
        $st->execute([$token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ereignis(int $empfaengerId, string $was, array $e, array $details): void
    {
        $this->db->prepare("INSERT INTO newsletter_ereignisse (empfaenger_id, ereignis, zeitpunkt, ip, quelle, details) VALUES (?, ?, ?, ?, 'portal', ?)")
            ->execute([$empfaengerId, $was, $e['zeit'] ?: date('Y-m-d H:i:s'), $e['ip'] ?: null,
                       json_encode(array_filter($details, fn($v) => $v !== null), JSON_UNESCAPED_UNICODE)]);
    }

    /** Shops + alle seit dem letzten Mal geänderten Link-Schlüssel ans Portal (in Paketen). */
    private function schluesselUebertragen(array $konf): int
    {
        $shops = [];
        foreach ($this->db->query("SELECT slug, name, website_url, wc_url, design_farbe FROM shops WHERE ist_aktiv = 1")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $shops[] = ['slug' => $s['slug'], 'name' => $s['name'], 'url' => ($s['website_url'] ?: $s['wc_url']) ?: null, 'farbe' => $s['design_farbe']];
        }
        $anzahl = 0;
        $erstes = true;
        while (true) {
            $zeilen = $this->db->query("SELECT e.id, e.token, e.status, s.slug FROM newsletter_empfaenger e JOIN shops s ON s.id = e.shop_id
                                        WHERE e.portal_stand IS NULL   -- jede Statusänderung setzt portal_stand = NULL (fällig)
                                        ORDER BY e.id LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
            if (!$zeilen && !$erstes) break;
            $tokens = array_map(fn($z) => ['token' => $z['token'], 'shop' => $z['slug'], 'status' => self::STATUS_PORTAL[$z['status']]], $zeilen);
            $a = $this->anfrage('nl_setzen', ['shops' => $erstes ? $shops : [], 'tokens' => $tokens], $konf);
            if (empty($a['erfolg'])) break;
            if ($zeilen) {
                // geaendert_am bewusst unverändert lassen (sonst wäre die Zeile sofort wieder „geändert“)
                $this->db->exec("UPDATE newsletter_empfaenger SET portal_stand = NOW(), geaendert_am = geaendert_am
                                 WHERE id IN (" . implode(',', array_map(fn($z) => (int)$z['id'], $zeilen)) . ")");
            }
            $anzahl += count($zeilen);
            $erstes = false;
            if (count($zeilen) < 1000) break;
        }
        return $anzahl;
    }

    /** Double-Opt-in-Mail über das Postfach des Shops. */
    public function bestaetigungsMail(int $id, ?string $portalUrl = null): bool
    {
        $portalUrl ??= PaperlessService::konfig()['url'];
        $st = $this->db->prepare("SELECT e.*, s.name AS shop_name, s.design_farbe FROM newsletter_empfaenger e JOIN shops s ON s.id = e.shop_id WHERE e.id = ?");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['status'] !== 'ausstehend' || $portalUrl === '') return false;
        try {
            (new Mailer())->fuerShop((int)$r['shop_id'])->sendeTemplate(
                (string)Encryption::decrypt($r['email_enc']),
                'Bitte bestätige deine Newsletter-Anmeldung bei ' . $r['shop_name'],
                'mails/newsletter_bestaetigen.html.twig',
                ['vorname' => Encryption::decrypt($r['vorname_enc']), 'shop_name' => $r['shop_name'],
                 'link' => rtrim($portalUrl, '/') . '/newsletter.php?b=' . $r['token'],
                 'farbe' => preg_match('/^#[0-9a-f]{3,8}$/i', (string)$r['design_farbe']) ? $r['design_farbe'] : '#2c3e50']
            );
        } catch (Throwable $e) {
            Logger::log('newsletter.doi_fehler', 'newsletter_empfaenger', $id, ['fehler' => $e->getMessage()], null, 'warn');
            return false;
        }
        $this->db->prepare("UPDATE newsletter_empfaenger SET doi_gesendet_am = NOW(), geaendert_am = geaendert_am WHERE id = ?")->execute([$id]);
        $this->db->prepare("INSERT INTO newsletter_ereignisse (empfaenger_id, ereignis, zeitpunkt, quelle, details) VALUES (?, 'bestaetigung_gesendet', NOW(), 'erp', NULL)")
            ->execute([$id]);
        return true;
    }

    /** JSON an die Belegseite (gleicher Schlüssel-Header wie Paperless). */
    private function anfrage(string $aktion, ?array $json, array $konf): array
    {
        $ch = curl_init($konf['url'] . '/api.php?aktion=' . $aktion);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['X-Nahtlos-Schluessel: ' . $konf['schluessel'], 'Accept: application/json', 'Content-Type: application/json'],
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $antwort = curl_exec($ch);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($antwort === false) return ['erfolg' => false, 'fehler' => 'Belegseite nicht erreichbar: ' . $fehler];
        $d = json_decode($antwort, true);
        return is_array($d) ? $d : ['erfolg' => false, 'fehler' => 'Unerwartete Antwort der Belegseite.'];
    }
}
