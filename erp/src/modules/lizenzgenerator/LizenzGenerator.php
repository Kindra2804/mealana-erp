<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/LizenzSigner.php';

/**
 * Lizenzverwaltung / Generator (Ebene 2, Lizenz-Bau Schritt 4) — nur in Jackys eigener Installation.
 *
 * Aktiv nur, wenn system_einstellungen.lizenz_privat_schluessel_pfad auf eine vorhandene Datei
 * zeigt (setzen mit tools/lizenz/generator_aktivieren.php). Kunden = ERP-Kunden, Module = Artikel
 * mit artikel.lizenz_modul, Verkauf = Auftrag; daraus wird eine "Ausgabe" signiert.
 *
 * Lizenznummer L-PPNNNN-CC: PP Produkt (10 = NAHTLOS ERP), NNNN fortlaufend, CC Prüfziffer
 * mod 97 (wie IBAN). Bleibt je Kundeninstallation gleich, jede neue Datei = nächste Ausgabe.
 */
class LizenzGenerator
{
    public const PRODUKT_ERP = '10';
    /** Wartung im ersten Jahr inklusive (Annahme Preisvorschlag 2026-10-30) */
    public const ERSTES_JAHR_INKLUSIVE = true;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function schluesselPfad(): string
    {
        return (string)Database::getInstance()
            ->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'lizenz_privat_schluessel_pfad'")
            ->fetchColumn();
    }

    /** Generator sichtbar/benutzbar? (Schlüsseldatei vorhanden) */
    public static function aktiv(): bool
    {
        $pfad = self::schluesselPfad();
        return $pfad !== '' && is_file($pfad) && is_readable($pfad);
    }

    // ── Lizenznummer ──────────────────────────────────────────────────────────

    public static function lizenzNummer(int $laufnr, string $produkt = self::PRODUKT_ERP): string
    {
        $basis = $produkt . str_pad((string)$laufnr, 4, '0', STR_PAD_LEFT);
        return 'L-' . $basis . '-' . self::pruefziffer($basis);
    }

    /** Prüfziffer wie IBAN (ISO 7064 mod 97-10): erkennt jeden Einzelfehler und Zahlendreher */
    public static function pruefziffer(string $basis): string
    {
        return str_pad((string)(98 - ((int)($basis . '00') % 97)), 2, '0', STR_PAD_LEFT);
    }

    public static function nummerGueltig(string $nr): bool
    {
        if (!preg_match('/^L-(\d{6,})-(\d{2})$/', trim($nr), $m)) return false;
        return self::pruefziffer($m[1]) === $m[2];
    }

    // ── Lesen ─────────────────────────────────────────────────────────────────

    /** Lizenzkunden mit letzter Ausgabe */
    public function kunden(): array
    {
        return $this->db->query("
            SELECT lk.*, la.id AS ausgabe_id, la.ausgabe, la.module, la.wartung_bis, la.hauslizenz, la.erstellt_am AS ausgabe_am
            FROM lizenz_kunden lk
            LEFT JOIN lizenz_ausgaben la ON la.id = (SELECT MAX(id) FROM lizenz_ausgaben WHERE lizenz_kunde_id = lk.id)
            ORDER BY lk.laufnr DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function kunde(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM lizenz_kunden WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function ausgaben(int $lizenzKundeId): array
    {
        $stmt = $this->db->prepare("
            SELECT la.*, a.auftrag_nr, b.formularname
            FROM lizenz_ausgaben la
            LEFT JOIN auftraege a ON a.id = la.auftrag_id
            LEFT JOIN benutzer b ON b.id = la.benutzer_id
            WHERE la.lizenz_kunde_id = ? ORDER BY la.ausgabe DESC
        ");
        $stmt->execute([$lizenzKundeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ausgabe(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT la.*, lk.lizenz_nr FROM lizenz_ausgaben la JOIN lizenz_kunden lk ON lk.id = la.lizenz_kunde_id WHERE la.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function letzteAusgabe(int $lizenzKundeId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM lizenz_ausgaben WHERE lizenz_kunde_id = ? ORDER BY ausgabe DESC LIMIT 1");
        $stmt->execute([$lizenzKundeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Hat der Auftrag Lizenz-Positionen? (für den Button im Auftrag) */
    public function auftragHatLizenz(int $auftragId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM auftrag_positionen p JOIN artikel a ON a.id = p.artikel_id
            WHERE p.auftrag_id = ? AND a.lizenz_modul IS NOT NULL LIMIT 1
        ");
        $stmt->execute([$auftragId]);
        return (bool)$stmt->fetchColumn();
    }

    // ── Vorschlag fürs Formular ───────────────────────────────────────────────

    /**
     * Formular-Vorbelegung: aus Auftrag (Module aus den Positionen, Wartung verlängert) und/oder
     * bestehendem Lizenzkunden (Module + Restlaufzeit der letzten Ausgabe übernehmen).
     * Rückgabe: lizenz_kunde_id, kunde_id, firma, uid, steuernummer, module, wartung_bis, hauslizenz, auftrag_id, hinweise[]
     */
    public function vorschlag(?int $auftragId, ?int $lizenzKundeId): array
    {
        $v = ['lizenz_kunde_id' => null, 'kunde_id' => null, 'firma' => '', 'uid' => '', 'steuernummer' => '',
              'module' => ['grundmodul' => 1], 'wartung_bis' => null, 'hauslizenz' => false,
              'auftrag_id' => $auftragId, 'hinweise' => []];

        $auftrag = null;
        if ($auftragId) {
            $stmt = $this->db->prepare("SELECT id, auftrag_nr, kunden_id, kunden_snapshot FROM auftraege WHERE id = ?");
            $stmt->execute([$auftragId]);
            $auftrag = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($auftrag) {
                $v['kunde_id'] = $auftrag['kunden_id'] ? (int)$auftrag['kunden_id'] : null;
                $snap = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
                $v['firma'] = $snap['firma'] ?? '';
                $v['uid']   = $snap['uid_nummer'] ?? '';
                if ($v['kunde_id']) {
                    $k = (new KundenRepository())->findById($v['kunde_id']);
                    if ($k) {
                        $v['firma'] = $k['firmenname'] ?: $v['firma'] ?: trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
                        $v['uid']   = $k['uid_nummer'] ?: $v['uid'];
                    }
                    // bestehende Lizenz dieses Kunden automatisch vorschlagen (nur wenn genau eine)
                    if (!$lizenzKundeId) {
                        $stmt = $this->db->prepare("SELECT id FROM lizenz_kunden WHERE kunde_id = ?");
                        $stmt->execute([$v['kunde_id']]);
                        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        if (count($ids) === 1) $lizenzKundeId = (int)$ids[0];
                        if (count($ids) > 1) $v['hinweise'][] = 'Dieser Kunde hat mehrere Lizenzen — bitte die richtige wählen.';
                    }
                }
            }
        }

        $heute = date('Y-m-d');
        $basisWartung = null;
        if ($lizenzKundeId && ($lk = $this->kunde($lizenzKundeId))) {
            $v['lizenz_kunde_id'] = (int)$lk['id'];
            $v['kunde_id']      ??= $lk['kunde_id'] ? (int)$lk['kunde_id'] : null;
            $v['firma']           = $v['firma'] ?: $lk['firma'];
            $v['uid']             = $v['uid'] ?: (string)$lk['uid'];
            $v['steuernummer']    = (string)$lk['steuernummer'];
            if ($letzte = $this->letzteAusgabe((int)$lk['id'])) {
                $v['module']     = json_decode($letzte['module'], true) ?: $v['module'];
                $v['hauslizenz'] = (bool)$letzte['hauslizenz'];
                $basisWartung    = $letzte['wartung_bis'];
                $v['wartung_bis'] = $letzte['wartung_bis'];
                if ($letzte['wartung_bis'] === null && !$letzte['hauslizenz']) $v['wartung_bis'] = $heute;
            }
            if ($lk['firma'] !== $v['firma'] || (string)$lk['uid'] !== (string)$v['uid']) {
                $v['hinweise'][] = 'Firmendaten weichen von der bisherigen Lizenz ab (' . $lk['firma'] . ' / ' . ($lk['uid'] ?: '–')
                    . ') — bei Umfirmierung so lassen, sonst korrigieren.';
            }
        }

        // Positionen des Auftrags: Module dazu, Wartung verlängern
        if ($auftrag) {
            $stmt = $this->db->prepare("
                SELECT a.lizenz_modul, SUM(p.menge) AS menge
                FROM auftrag_positionen p JOIN artikel a ON a.id = p.artikel_id
                WHERE p.auftrag_id = ? AND a.lizenz_modul IS NOT NULL
                GROUP BY a.lizenz_modul
            ");
            $stmt->execute([$auftragId]);
            $jahre = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $modul => $menge) {
                $menge = max(1, (int)$menge);
                if ($modul === 'wartung') {
                    $jahre += $menge;
                } elseif ($modul === 'kasse+' || $modul === 'shop+') {
                    $basis = rtrim($modul, '+');
                    $v['module'][$basis] = max(1, (int)($v['module'][$basis] ?? 1)) + $menge;
                } elseif (isset(Lizenz::MODULE[$modul])) {
                    $v['module'][$modul] = max(1, (int)($v['module'][$modul] ?? 0));
                }
            }
            if (!$v['hauslizenz']) {
                // Startpunkt: noch laufende Wartung weiterführen, sonst ab heute (neue Lizenz: 1. Jahr inklusive)
                $start = ($basisWartung && $basisWartung > $heute) ? $basisWartung : $heute;
                if (!$v['lizenz_kunde_id'] && self::ERSTES_JAHR_INKLUSIVE) $jahre++;
                if ($jahre > 0) $v['wartung_bis'] = date('Y-m-d', strtotime($start . " +$jahre years"));
            }
        }
        if (!$v['lizenz_kunde_id'] && !$v['wartung_bis'] && !$v['hauslizenz']) {
            $v['wartung_bis'] = date('Y-m-d', strtotime('+1 year'));
        }
        return $v;
    }

    // ── Ausstellen ────────────────────────────────────────────────────────────

    /**
     * $e: lizenz_kunde_id (leer = neue Lizenz), kunde_id, firma, uid, steuernummer, module {code: anzahl},
     *     wartung_bis (Y-m-d|null), hauslizenz, auftrag_id, grund
     * Rückgabe: erfolg, ausgabe_id, lizenz_nr, ausgabe | fehler
     */
    public function ausstellen(array $e, int $benutzerId): array
    {
        if (!self::aktiv()) return ['erfolg' => false, 'fehler' => 'Generator nicht aktiv (Schlüsseldatei fehlt).'];
        $firma = trim($e['firma'] ?? '');
        $uid   = strtoupper(preg_replace('/\s+/', '', (string)($e['uid'] ?? '')));
        $stnr  = trim($e['steuernummer'] ?? '');
        if ($firma === '')               return ['erfolg' => false, 'fehler' => 'Firmenname fehlt.'];
        if ($uid === '' && $stnr === '') return ['erfolg' => false, 'fehler' => 'UID oder Steuernummer ist Pflicht (Bindung der Lizenz).'];

        $haus   = !empty($e['hauslizenz']);
        $module = [];
        foreach (Lizenz::MODULE as $code => $m) {
            $anz = $haus ? ($m['menge'] ? 99 : 1) : (int)($e['module'][$code] ?? 0);
            if ($anz > 0) $module[$code] = $m['menge'] ? min(99, $anz) : 1;
        }
        $module['grundmodul'] = 1;
        $wartung = $haus ? null : (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['wartung_bis'] ?? '')) ? $e['wartung_bis'] : null);
        if (!$haus && $wartung === null) return ['erfolg' => false, 'fehler' => 'Bitte „Wartung bis“ angeben (unbegrenzt nur bei Hauslizenz).'];

        $this->db->beginTransaction();
        try {
            $lkId = (int)($e['lizenz_kunde_id'] ?? 0);
            if ($lkId) {
                $lk = $this->kunde($lkId);
                if (!$lk) throw new RuntimeException('Lizenzkunde nicht gefunden.');
                $this->db->prepare("UPDATE lizenz_kunden SET firma = ?, uid = ?, steuernummer = ?, kunde_id = COALESCE(?, kunde_id) WHERE id = ?")
                         ->execute([$firma, $uid ?: null, $stnr ?: null, ($e['kunde_id'] ?? null) ?: null, $lkId]);
                $lizenzNr = $lk['lizenz_nr'];
            } else {
                $laufnr   = (int)$this->db->query("SELECT COALESCE(MAX(laufnr), 0) + 1 FROM lizenz_kunden")->fetchColumn();
                $lizenzNr = self::lizenzNummer($laufnr);
                $this->db->prepare("INSERT INTO lizenz_kunden (lizenz_nr, produkt, laufnr, kunde_id, firma, uid, steuernummer) VALUES (?, ?, ?, ?, ?, ?, ?)")
                         ->execute([$lizenzNr, self::PRODUKT_ERP, $laufnr, ($e['kunde_id'] ?? null) ?: null, $firma, $uid ?: null, $stnr ?: null]);
                $lkId = (int)$this->db->lastInsertId();
            }
            $stmt = $this->db->prepare("SELECT COALESCE(MAX(ausgabe), 0) + 1 FROM lizenz_ausgaben WHERE lizenz_kunde_id = ?");
            $stmt->execute([$lkId]);
            $ausgabe = (int)$stmt->fetchColumn();

            $inhalt = LizenzSigner::signiere([
                'lizenz_nr'    => $lizenzNr,
                'ausgabe'      => $ausgabe,
                'firma'        => $firma,
                'uid'          => $uid,
                'steuernummer' => $stnr,
                'module'       => $module,
                'wartung_bis'  => $wartung,
                'hauslizenz'   => $haus,
            ], self::schluesselPfad());

            $this->db->prepare("
                INSERT INTO lizenz_ausgaben (lizenz_kunde_id, ausgabe, auftrag_id, module, wartung_bis, hauslizenz, grund, inhalt, benutzer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$lkId, $ausgabe, ($e['auftrag_id'] ?? null) ?: null, json_encode($module), $wartung, $haus ? 1 : 0,
                         trim($e['grund'] ?? '') ?: null, $inhalt, $benutzerId ?: null]);
            $ausgabeId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $ex) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Ausstellen fehlgeschlagen: ' . $ex->getMessage()];
        }

        Logger::log('lizenz.ausgestellt', 'lizenz_ausgaben', $ausgabeId, [
            'lizenz_nr' => $lizenzNr, 'ausgabe' => $ausgabe, 'firma' => $firma, 'module' => $module, 'wartung_bis' => $wartung,
        ], $benutzerId ?: null);
        return ['erfolg' => true, 'ausgabe_id' => $ausgabeId, 'lizenz_nr' => $lizenzNr, 'ausgabe' => $ausgabe];
    }

    /** Dateiname für den Download, z.B. L-100001-42_A2.lic */
    public static function dateiname(array $ausgabe): string
    {
        return $ausgabe['lizenz_nr'] . '_A' . $ausgabe['ausgabe'] . '.lic';
    }
}
