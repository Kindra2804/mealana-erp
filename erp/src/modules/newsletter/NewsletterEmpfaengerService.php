<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Encryption.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * Newsletter-Empfänger je Shop + Einwilligungsnachweis (Newsletter Schritt 1, Migration 249).
 *
 * E-Mail und Name verschlüsselt (wie Kunden), Dubletten über email_hash. Jede Änderung landet
 * unveränderlich in newsletter_ereignisse (Nachweis DSGVO/TKG § 174). Abgemeldete bleiben als
 * Zeile stehen, damit ein späterer Import sie nie wieder aktiv setzt.
 */
class NewsletterEmpfaengerService
{
    private PDO $db;

    /** Spalten der JTL-Export-Abfrage (siehe docs/handbuch) */
    public const JTL_SPALTEN = ['email', 'anrede', 'vorname', 'nachname', 'eingetragen', 'anmelde_ip', 'optin_ip', 'optin_datum', 'letzter_newsletter', 'gesperrt'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Lesen ────────────────────────────────────────────────────────────────

    /** Anzahl je Status für einen Shop: ['aktiv' => n, 'ausstehend' => n, 'abgemeldet' => n] */
    public function zaehler(int $shopId): array
    {
        $st = $this->db->prepare("SELECT status, COUNT(*) FROM newsletter_empfaenger WHERE shop_id = ? GROUP BY status");
        $st->execute([$shopId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR)) + ['aktiv' => 0, 'ausstehend' => 0, 'abgemeldet' => 0];
    }

    /**
     * Empfänger eines Shops (entschlüsselt). Suche in E-Mail/Name nach dem Entschlüsseln
     * (bei einigen tausend Zeilen unkritisch).
     */
    public function liste(int $shopId, ?string $status, string $suche = '', int $limit = 200): array
    {
        $sql = "SELECT * FROM newsletter_empfaenger WHERE shop_id = ?";
        $p = [$shopId];
        if ($status) { $sql .= " AND status = ?"; $p[] = $status; }
        $st = $this->db->prepare($sql . " ORDER BY eingetragen_am DESC, id DESC");
        $st->execute($p);
        $suche = mb_strtolower(trim($suche));
        $erg = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $r['email']    = Encryption::decrypt($r['email_enc']);
            $r['vorname']  = Encryption::decrypt($r['vorname_enc']);
            $r['nachname'] = Encryption::decrypt($r['nachname_enc']);
            unset($r['email_enc'], $r['vorname_enc'], $r['nachname_enc']);
            if ($suche !== '' && !str_contains(mb_strtolower($r['email'] . ' ' . $r['vorname'] . ' ' . $r['nachname']), $suche)) continue;
            $erg[] = $r;
            if (count($erg) >= $limit) break;
        }
        return $erg;
    }

    public function ereignisse(int $empfaengerId): array
    {
        $st = $this->db->prepare("SELECT e.*, b.formularname FROM newsletter_ereignisse e LEFT JOIN benutzer b ON b.id = e.benutzer_id
                                  WHERE e.empfaenger_id = ? ORDER BY e.zeitpunkt, e.id");
        $st->execute([$empfaengerId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Ändern ───────────────────────────────────────────────────────────────

    /** Von Hand abmelden (z.B. Kunde ruft an). Bleibt als „abgemeldet“ stehen. */
    public function abmelden(int $id, int $benutzerId, string $grund = ''): array
    {
        $st = $this->db->prepare("UPDATE newsletter_empfaenger SET status = 'abgemeldet', abgemeldet_am = NOW() WHERE id = ? AND status <> 'abgemeldet'");
        $st->execute([$id]);
        if (!$st->rowCount()) return ['erfolg' => false, 'fehler' => 'Empfänger nicht gefunden oder schon abgemeldet.'];
        $this->ereignis($id, 'abgemeldet', 'erp', null, $benutzerId, ['grund' => $grund ?: 'von Hand im ERP']);
        Logger::log('newsletter.abgemeldet', 'newsletter_empfaenger', $id, ['grund' => $grund], $benutzerId);
        return ['erfolg' => true];
    }

    private function ereignis(int $empfaengerId, string $ereignis, ?string $quelle, ?string $ip, ?int $benutzerId, array $details = [], ?string $zeitpunkt = null): void
    {
        $this->db->prepare("INSERT INTO newsletter_ereignisse (empfaenger_id, ereignis, zeitpunkt, ip, quelle, benutzer_id, details)
                            VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$empfaengerId, $ereignis, $zeitpunkt ?? date('Y-m-d H:i:s'), $ip, $quelle, $benutzerId,
                       $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
    }

    // ── Import aus dem JTL-Shop (CSV aus phpMyAdmin) ─────────────────────────

    /**
     * CSV mit den Spalten aus JTL_SPALTEN (Kopfzeile, Trennzeichen ; oder , automatisch).
     * gesperrt=1 (JTL-Blacklist) → status 'abgemeldet'; sonst 'aktiv' mit Opt-in-Nachweis aus JTL.
     * Schon vorhandene Adressen (gleicher Shop) bleiben unverändert — außer eine Sperre: die gilt immer.
     *
     * @return array{erfolg:bool, fehler?:string, neu_aktiv:int, neu_gesperrt:int, gesperrt_gesetzt:int,
     *               vorhanden:int, ungueltig:int, ungueltige:string[], ohne_optin:int}
     */
    public function importJtlCsv(int $shopId, string $pfad, bool $probelauf, int $benutzerId): array
    {
        $erg = ['erfolg' => true, 'neu_aktiv' => 0, 'neu_gesperrt' => 0, 'gesperrt_gesetzt' => 0, 'vorhanden' => 0,
                'ungueltig' => 0, 'ungueltige' => [], 'ohne_optin' => 0];
        $zeilen = $this->leseCsv($pfad);
        if (isset($zeilen['fehler'])) return ['erfolg' => false, 'fehler' => $zeilen['fehler']] + $erg;

        $vorhanden = $this->db->prepare("SELECT id, status FROM newsletter_empfaenger WHERE shop_id = ? AND email_hash = ?");
        $kunde     = $this->db->prepare("SELECT id FROM kunden WHERE email_hash = ? LIMIT 1");
        $ins       = $this->db->prepare("
            INSERT INTO newsletter_empfaenger (shop_id, email_enc, email_hash, anrede, vorname_enc, nachname_enc, kunden_id, status, quelle,
                                               eingetragen_am, anmelde_ip, optin_am, optin_ip, abgemeldet_am, letzter_newsletter_am, token)
            VALUES (:shop, :email, :hash, :anrede, :vorname, :nachname, :kunde, :status, 'jtl_import',
                    :eingetragen, :anmelde_ip, :optin_am, :optin_ip, :abgemeldet, :letzter, :token)");

        if (!$probelauf) $this->db->beginTransaction();
        try {
            $gesehen = [];
            foreach ($zeilen as $nr => $z) {
                $email = mb_strtolower(trim((string)($z['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $erg['ungueltig']++;
                    if (count($erg['ungueltige']) < 20) $erg['ungueltige'][] = 'Zeile ' . ($nr + 2) . ': „' . ($z['email'] ?? '') . '“';
                    continue;
                }
                $hash = Encryption::hash($email);
                $gesperrt = in_array(trim((string)($z['gesperrt'] ?? '0')), ['1', 'ja', 'true'], true);
                if (isset($gesehen[$hash])) {               // doppelt in der Datei: Sperre gewinnt
                    if ($gesperrt) $gesehen[$hash] = true;
                    continue;
                }
                $gesehen[$hash] = $gesperrt;
            }
            foreach ($zeilen as $z) {
                $email = mb_strtolower(trim((string)($z['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $hash = Encryption::hash($email);
                if (!array_key_exists($hash, $gesehen)) continue;        // schon verarbeitet
                $gesperrt = $gesehen[$hash];
                unset($gesehen[$hash]);

                $vorhanden->execute([$shopId, $hash]);
                $alt = $vorhanden->fetch(PDO::FETCH_ASSOC);
                if ($alt) {
                    if ($gesperrt && $alt['status'] !== 'abgemeldet') {
                        $erg['gesperrt_gesetzt']++;
                        if (!$probelauf) {
                            $this->db->prepare("UPDATE newsletter_empfaenger SET status = 'abgemeldet', abgemeldet_am = NOW() WHERE id = ?")->execute([$alt['id']]);
                            $this->ereignis((int)$alt['id'], 'abgemeldet', 'jtl_import', null, $benutzerId, ['grund' => 'JTL-Blacklist']);
                        }
                    } else {
                        $erg['vorhanden']++;
                    }
                    continue;
                }

                $eingetragen = $this->datum($z['eingetragen'] ?? null) ?? date('Y-m-d H:i:s');
                $optinAm     = $this->datum($z['optin_datum'] ?? null);
                $optinIp     = $this->ip($z['optin_ip'] ?? null);
                if (!$gesperrt && !$optinAm) $erg['ohne_optin']++;
                $gesperrt ? $erg['neu_gesperrt']++ : $erg['neu_aktiv']++;
                if ($probelauf) continue;

                $kunde->execute([$hash]);
                $ins->execute([
                    'shop' => $shopId, 'email' => Encryption::encrypt($email), 'hash' => $hash,
                    'anrede' => $this->kurz($z['anrede'] ?? null, 30),
                    'vorname' => Encryption::encrypt($this->kurz($z['vorname'] ?? null, 255)),
                    'nachname' => Encryption::encrypt($this->kurz($z['nachname'] ?? null, 255)),
                    'kunde' => $kunde->fetchColumn() ?: null,
                    'status' => $gesperrt ? 'abgemeldet' : 'aktiv',
                    'eingetragen' => $eingetragen,
                    'anmelde_ip' => $this->ip($z['anmelde_ip'] ?? null),
                    'optin_am' => $gesperrt ? null : ($optinAm ?? $eingetragen),
                    'optin_ip' => $gesperrt ? null : $optinIp,
                    'abgemeldet' => $gesperrt ? ($this->datum($z['eingetragen'] ?? null) ?? date('Y-m-d H:i:s')) : null,
                    'letzter' => $this->datum($z['letzter_newsletter'] ?? null),
                    'token' => bin2hex(random_bytes(16)),
                ]);
                $id = (int)$this->db->lastInsertId();
                $this->ereignis($id, 'importiert', 'jtl_import', null, $benutzerId, [
                    'aus' => 'JTL-Shop', 'eingetragen' => $eingetragen, 'optin_datum' => $optinAm,
                    'optin_ip' => $optinIp, 'gesperrt' => $gesperrt,
                ]);
                if (!$gesperrt) {
                    // Opt-in zum ursprünglichen Zeitpunkt nachweisen (aus JTL übernommen)
                    $this->ereignis($id, 'bestaetigt', 'jtl_import', $optinIp, null, ['laut' => 'JTL-Shop'], $optinAm ?? $eingetragen);
                }
            }
            if (!$probelauf) $this->db->commit();
        } catch (Throwable $e) {
            if (!$probelauf && $this->db->inTransaction()) $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Import abgebrochen: ' . $e->getMessage()] + $erg;
        }
        if (!$probelauf) {
            Logger::log('newsletter.import_jtl', 'shops', $shopId, array_diff_key($erg, ['ungueltige' => 1]), $benutzerId);
        }
        return $erg;
    }

    /** CSV lesen: Kopfzeile, Trennzeichen ; , oder TAB automatisch, UTF-8 (BOM wird entfernt). */
    private function leseCsv(string $pfad): array
    {
        $roh = @file_get_contents($pfad);
        if ($roh === false || trim($roh) === '') return ['fehler' => 'Die Datei ist leer oder nicht lesbar.'];
        $roh = preg_replace('/^\xEF\xBB\xBF/', '', $roh);
        if (!mb_check_encoding($roh, 'UTF-8')) $roh = mb_convert_encoding($roh, 'UTF-8', 'Windows-1252');
        $ersteZeile = strtok($roh, "\n");
        $trenner = substr_count($ersteZeile, ';') >= substr_count($ersteZeile, ',') ? ';' : ',';
        if (substr_count($ersteZeile, "\t") > substr_count($ersteZeile, $trenner)) $trenner = "\t";

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $roh);
        rewind($fh);
        $kopf = array_map(fn($k) => mb_strtolower(trim((string)$k, " \"\r\n")), fgetcsv($fh, 0, $trenner, '"', '\\') ?: []);
        if (!in_array('email', $kopf, true)) {
            fclose($fh);
            return ['fehler' => 'Spalte „email“ fehlt. Bitte die Export-Abfrage aus der Anleitung verwenden und beim CSV-Export „Spaltennamen in die erste Zeile“ anhaken.'];
        }
        $zeilen = [];
        while (($r = fgetcsv($fh, 0, $trenner, '"', '\\')) !== false) {
            if ($r === [null] || $r === ['']) continue;
            $z = [];
            foreach ($kopf as $i => $k) {
                $v = $r[$i] ?? null;
                $z[$k] = ($v === null || strtoupper(trim($v)) === 'NULL') ? null : trim($v);
            }
            $zeilen[] = $z;
        }
        fclose($fh);
        return $zeilen;
    }

    private function datum(?string $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '' || str_starts_with($v, '0000')) return null;
        $t = strtotime($v);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private function ip(?string $v): ?string
    {
        $v = trim((string)$v);
        return filter_var($v, FILTER_VALIDATE_IP) ? $v : null;
    }

    private function kurz(?string $v, int $max): ?string
    {
        $v = trim((string)$v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
