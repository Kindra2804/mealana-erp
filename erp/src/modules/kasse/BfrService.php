<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * BfrService – RKSV-Signatur über den BFR BONit Fiscal Recorder
 *
 * Modell (seit 2026-07-06, nach echtem Hardware-Test): VOR jeder Bon-/Storno-/
 * Nullbeleg-Erstellung wird /state geprüft. Ist der Dienst nicht erreichbar,
 * wird die Buchung gar nicht erst zugelassen ("kein Dienst, keine Kasse" —
 * Empfehlung des BFR-Herstellers). Ist er erreichbar, kommt von /register laut
 * Hersteller-API IMMER eine Antwort (RC ist immer "OK", nur <Link> unterscheidet
 * "echte Signatur" von "Sicherheitseinrichtung ausgefallen") — ein Beleg bleibt
 * also nie mehr unsigniert/unentschieden hängen. Die frühere Nachsignierungs-
 * Warteschlange (mehrere offene Belege in einem "Lauf" nachholen) entfällt
 * damit komplett.
 *
 * Störungen (Dienst nicht erreichbar ODER "Sicherheitseinrichtung ausgefallen")
 * werden stattdessen episodenweise in bfr_ausfaelle/bfr_ausfall_ereignisse
 * protokolliert — für die 24h/48h-FON-Meldepflicht-Warnung.
 */
class BfrService
{
    private PDO $db;

    private const TIMEOUT_SEKUNDEN        = 5;
    private const CONNECT_TIMEOUT_MS      = 400;  // BFR ist immer lokal/LAN — Verbindungsaufbau muss schnell scheitern
    private const KURZ_RETRY_ANZAHL       = 2;
    private const KURZ_RETRY_PAUSE_MS     = 300;
    private const AUSFALL_WARNUNG_STUNDEN = 24;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt die ID des System-Users "Jarvis" zurück, für Logger::log() wenn
     * BfrService ohne Session läuft (Cronjob, Kassenstart-Recovery).
     */
    private function getJarvisId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM benutzer WHERE username = 'system'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ordnet die Positionen eines Bons den 5 BFR-Steuergruppen zu und
     * summiert den Brutto-Anteil je Gruppe. Wird von KassenService beim
     * Anlegen eines Bons aufgerufen, damit steuer_a..e sofort feststehen.
     */
    public static function steuerGruppenAusPositionen(array $positionen): array
    {
        $summen = ['a' => 0.0, 'b' => 0.0, 'c' => 0.0, 'd' => 0.0, 'e' => 0.0];
        foreach ($positionen as $p) {
            $brutto = (float)($p['menge'] ?? 1)
                * (float)($p['einzelpreis_brutto'] ?? 0)
                * (1 - (float)($p['rabatt_prozent'] ?? 0) / 100);
            $gruppe = match ((float)($p['steuer_prozent'] ?? 20)) {
                20.0    => 'a',
                10.0    => 'b',
                13.0    => 'c',
                0.0     => 'd',
                default => 'e',
            };
            $summen[$gruppe] += $brutto;
        }
        return array_map(fn($v) => round($v, 2), $summen);
    }

    // -------------------------------------------------------------------------
    // State-Check-Gate — VOR jeder Buchung aufrufen
    // -------------------------------------------------------------------------

    /**
     * Vorab-Check mit stillen Kurz-Retries (für den ersten, automatischen Versuch
     * beim Bezahlen-Klick bzw. Kassenstart — im Normalfall < 1 Sekunde, unsichtbar).
     * Protokolliert bei endgültigem Fehlschlag eine Ausfall-Episode.
     */
    public function pruefeVorBuchung(int $kasseId): array
    {
        return $this->pruefeVorBuchungIntern($kasseId, self::KURZ_RETRY_ANZAHL);
    }

    /**
     * Einzel-Check ohne Kurz-Retries — für die beiden Popup-Buttons ("Erneut
     * versuchen" / "Überprüft, Dienst sollte wieder laufen"). Der Klick selbst
     * repräsentiert bereits verstrichene Zeit, ein weiterer Kurz-Retry-Burst
     * ist dort nicht nötig.
     */
    public function pruefeVorBuchungEinzeln(int $kasseId): array
    {
        return $this->pruefeVorBuchungIntern($kasseId, 1);
    }

    private function pruefeVorBuchungIntern(int $kasseId, int $versuche): array
    {
        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return ['erreichbar' => true, 'bfr_konfiguriert' => false];
        }

        $verbindung = null;
        for ($i = 0; $i < $versuche; $i++) {
            $verbindung = $this->pruefeVerbindung($kasse['bfr_url'], $kasse['rksv_kassen_id']);
            if ($verbindung['erreichbar']) {
                return ['erreichbar' => true, 'bfr_konfiguriert' => true];
            }
            if ($i < $versuche - 1) {
                usleep(self::KURZ_RETRY_PAUSE_MS * 1000);
            }
        }

        $this->protokolliereAusfall($kasseId, 'dienst_nicht_erreichbar', null);
        return ['erreichbar' => false, 'bfr_konfiguriert' => true, 'grund' => $verbindung['grund'] ?? 'bfr_nicht_erreichbar'];
    }

    /**
     * Beim Öffnen der Kasse aufrufen (bon.php-Seitenaufruf). Zusätzlich zum
     * normalen Erreichbarkeits-Check: ist eine Ausfall-Episode dieser Kasse noch
     * offen, wird im Hintergrund ein Nullbeleg versucht — klappt er (echte
     * Signatur), schließt das die Episode automatisch; kommt wieder "ausgefallen",
     * läuft die Episode einfach weiter. Kein eigenes Popup dafür, die Kasse
     * startet in beiden Fällen normal.
     */
    public function pruefeKassenstart(int $kasseId): array
    {
        $ergebnis = $this->pruefeVorBuchungEinzeln($kasseId);
        if (!$ergebnis['erreichbar'] || !$ergebnis['bfr_konfiguriert']) {
            return $ergebnis;
        }

        if ($this->offeneEpisode($kasseId)) {
            try {
                $this->versucheRecoveryNullbeleg($kasseId);
            } catch (Throwable $e) {
                error_log('[BFR-Recovery] ' . $e->getMessage());
            }
        }

        return $ergebnis;
    }

    private function versucheRecoveryNullbeleg(int $kasseId): void
    {
        $kasse   = $this->ladeKasse($kasseId);
        $belegNr = 'Nullbeleg' . $kasse['kasse_nr'] . date('YmdHis');
        $xml     = $this->baueNullbelegXml($belegNr, $kasse['rksv_kassen_id']);
        $antwort = $this->httpPost(rtrim($kasse['bfr_url'], '/') . '/register', $xml);
        $reg     = $this->parseRegisterAntwort($antwort);

        $this->db->prepare("
            INSERT INTO bfr_nullbelege (kasse_id, monat, beleg_nr, ausgeloest_durch, rksv_signatur, rksv_qr, signiert_am)
            VALUES (:kid, :monat, :beleg_nr, 'automatisch', :sig, :qr, NOW())
        ")->execute([
            'kid'     => $kasseId,
            'monat'   => date('Y-m'),
            'beleg_nr'=> $belegNr,
            'sig'     => $reg['link'],
            'qr'      => $reg['code'],
        ]);

        $this->verarbeiteRegisterErgebnis($kasseId, $belegNr, $reg);
    }

    /** GET /state — prüft Erreichbarkeit und ob die Kassen-ID (RN) übereinstimmt. */
    public function pruefeVerbindung(string $bfrUrl, ?string $erwarteteRn = null): array
    {
        $antwort = $this->httpGet(rtrim($bfrUrl, '/') . '/state');
        if ($antwort === null) {
            return ['erreichbar' => false, 'grund' => 'bfr_nicht_erreichbar'];
        }

        $state = @simplexml_load_string($antwort);
        if ($state === false) {
            return ['erreichbar' => false, 'grund' => 'antwort_ungueltig'];
        }

        $rn = (string)($state->RN ?? '');
        if ($erwarteteRn && $rn !== $erwarteteRn) {
            return ['erreichbar' => false, 'grund' => 'rn_stimmt_nicht', 'rn' => $rn];
        }

        return ['erreichbar' => true, 'rn' => $rn];
    }

    // -------------------------------------------------------------------------
    // Beleg signieren — wird erst NACH erfolgreichem pruefeVorBuchung() gerufen
    // -------------------------------------------------------------------------

    /**
     * Signiert einen einzelnen Verkaufs-/Stornobeleg synchron und speichert das
     * Ergebnis sofort am Bon. Da /state kurz zuvor erfolgreich war, kommt von
     * /register laut Hersteller-API garantiert eine Antwort — bleibt sie
     * trotzdem aus (das enge Zeitfenster zwischen State-Check und Register-Call),
     * wird das wie "Sicherheitseinrichtung ausgefallen" behandelt: der bereits
     * abgeschlossene Verkauf darf dadurch nie mehr rückgängig gemacht werden.
     */
    public function signiereBeleg(int $bonId, array $beleg, array $kasse): array
    {
        $betrag = (float)$beleg['bruttobetrag'];
        $zaehler = (float)$kasse['bfr_umsatzzaehler'];
        if ($betrag < 0 && ($zaehler + $betrag) < 0) {
            // Sicherheitsnetz — der eigentliche Check läuft schon vorab in
            // KassenService::storniereBon() über wuerdeUmsatzzaehlerNegativWerden().
            return ['erfolg' => false, 'grund' => 'zaehler_negativ'];
        }

        $xml     = $this->baueSignierXml($beleg);
        $antwort = $this->httpPost(rtrim($kasse['bfr_url'], '/') . '/register', $xml);
        $reg     = $this->parseRegisterAntwort($antwort);

        $this->db->prepare("
            UPDATE kassen_bons SET rksv_signatur = :sig, rksv_qr = :qr, signiert_am = NOW() WHERE id = :id
        ")->execute(['sig' => $reg['link'], 'qr' => $reg['code'], 'id' => $bonId]);

        // Zähler zählt IMMER mit, auch bei "Sicherheitseinrichtung ausgefallen": der Umsatz
        // wird trotzdem an den BFR übermittelt und dort für den späteren Sammelbeleg
        // gespeichert/summiert — nur die eigentliche Signatur fehlt, der Betrag selbst ist
        // beim BFR genauso angekommen wie bei einer normalen Signatur.
        $this->aktualisiereUmsatzzaehler((int)$kasse['id'], $zaehler + $betrag);
        $this->verarbeiteRegisterErgebnis((int)$kasse['id'], $beleg['bon_nr'], $reg);

        return ['erfolg' => true, 'ausgefallen' => $reg['ausgefallen']];
    }

    /**
     * Wertet die /register-Antwort aus. Laut Hersteller-API ist RC immer "OK" —
     * das einzige Unterscheidungsmerkmal ist <Link>: entweder die Steuerkennung
     * (echte Signatur) oder der Text "Sicherheitseinrichtung ausgefallen". Kommt
     * trotz erfolgreichem State-Check keine/keine lesbare Antwort, wird das
     * defensiv genauso behandelt wie "ausgefallen" — RKSV-seitig die sichere Wahl,
     * da der Verkauf so oder so nicht mehr zurückgenommen werden kann.
     */
    private function parseRegisterAntwort(?string $antwort): array
    {
        if ($antwort !== null) {
            $traC = @simplexml_load_string($antwort);
            if ($traC !== false) {
                $link = (string)($traC->Fis->Link ?? '');
                $code = (string)($traC->Fis->Code ?? '');
                if ($link !== '' && $link !== 'Sicherheitseinrichtung ausgefallen') {
                    return ['ausgefallen' => false, 'link' => $link, 'code' => $code];
                }
                return ['ausgefallen' => true, 'link' => 'Sicherheitseinrichtung ausgefallen', 'code' => $code ?: null];
            }
        }
        return ['ausgefallen' => true, 'link' => 'Sicherheitseinrichtung ausgefallen', 'code' => null];
    }

    /** Gemeinsame Nachbearbeitung für Bons und Nullbelege: Episode öffnen/schließen. */
    private function verarbeiteRegisterErgebnis(int $kasseId, ?string $bonNr, array $reg): void
    {
        if ($reg['ausgefallen']) {
            $this->protokolliereAusfall($kasseId, 'sicherheitseinrichtung_ausgefallen', $bonNr);
        } else {
            $this->schliesseOffeneAusfallEpisode($kasseId);
        }
    }

    // -------------------------------------------------------------------------
    // Ausfall-Episoden — Buchführung für die 24h/48h-FON-Meldepflicht
    // -------------------------------------------------------------------------

    private function protokolliereAusfall(int $kasseId, string $typ, ?string $bonNr): void
    {
        $episode = $this->offeneEpisode($kasseId);
        if ($episode) {
            $ausfallId = (int)$episode['id'];
            $this->db->prepare("
                UPDATE bfr_ausfaelle SET letzte_erkennung_am = NOW(), anzahl_ereignisse = anzahl_ereignisse + 1
                WHERE id = :id
            ")->execute(['id' => $ausfallId]);
        } else {
            $this->db->prepare("
                INSERT INTO bfr_ausfaelle (kasse_id, erste_erkennung_am, letzte_erkennung_am)
                VALUES (:kid, NOW(), NOW())
            ")->execute(['kid' => $kasseId]);
            $ausfallId = (int)$this->db->lastInsertId();

            Logger::log('kasse.bfr.ausfall_begonnen', 'bfr_ausfaelle', $ausfallId, [
                'kasse_id' => $kasseId, 'typ' => $typ,
            ], $this->getJarvisId());
        }

        $this->db->prepare("
            INSERT INTO bfr_ausfall_ereignisse (ausfall_id, typ, bon_nr)
            VALUES (:aid, :typ, :bon_nr)
        ")->execute(['aid' => $ausfallId, 'typ' => $typ, 'bon_nr' => $bonNr]);
    }

    private function schliesseOffeneAusfallEpisode(int $kasseId): void
    {
        $episode = $this->offeneEpisode($kasseId);
        if (!$episode) return;

        $this->db->prepare("UPDATE bfr_ausfaelle SET geloest_am = NOW() WHERE id = :id")
            ->execute(['id' => $episode['id']]);

        Logger::log('kasse.bfr.ausfall_geloest', 'bfr_ausfaelle', (int)$episode['id'], [
            'kasse_id' => $kasseId,
            'dauer_minuten' => round((strtotime('now') - strtotime($episode['erste_erkennung_am'])) / 60),
        ], $this->getJarvisId());
    }

    public function offeneEpisode(int $kasseId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bfr_ausfaelle WHERE kasse_id = :kid AND geloest_am IS NULL LIMIT 1
        ");
        $stmt->execute(['kid' => $kasseId]);
        return $stmt->fetch() ?: null;
    }

    /** Für Dashboard/Kassen-Header: alle offenen Episoden, mit "über 24h"-Flag. */
    public function offeneEpisodenMitWarnung(): array
    {
        $stmt = $this->db->query("
            SELECT a.*, k.name AS kasse_name,
                   TIMESTAMPDIFF(HOUR, a.erste_erkennung_am, NOW()) AS dauer_stunden
            FROM bfr_ausfaelle a
            JOIN kassen k ON k.id = a.kasse_id
            WHERE a.geloest_am IS NULL
            ORDER BY a.erste_erkennung_am ASC
        ");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['warnung_24h'] = (int)$r['dauer_stunden'] >= self::AUSFALL_WARNUNG_STUNDEN;
        }
        return $rows;
    }

    /** Ausfall-Historie (offen + gelöst) für die Übersichtsseite. */
    public function ausfallHistorie(int $limit = 100): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, k.name AS kasse_name
            FROM bfr_ausfaelle a
            JOIN kassen k ON k.id = a.kasse_id
            ORDER BY a.erste_erkennung_am DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Einzelvorfälle einer Episode (Drill-down). */
    public function episodeEreignisse(int $ausfallId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bfr_ausfall_ereignisse WHERE ausfall_id = :aid ORDER BY id ASC
        ");
        $stmt->execute(['aid' => $ausfallId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Nullbeleg
    // -------------------------------------------------------------------------

    /**
     * Manueller Trigger (Klick auf "RKSV aktiv" in der Kassen-Kopfzeile).
     * Prüft Erreichbarkeit selbst (Einzel-Check), damit der Aufrufer nur das
     * Ergebnis behandeln muss.
     */
    public function erstelleNullbeleg(int $kasseId, string $ausgeloestDurch, ?int $benutzerId = null): array
    {
        $ergebnis = $this->pruefeVorBuchungEinzeln($kasseId);
        if (!$ergebnis['bfr_konfiguriert']) {
            return ['erfolg' => false, 'grund' => 'kein_bfr_konfiguriert'];
        }
        if (!$ergebnis['erreichbar']) {
            return ['erfolg' => false, 'grund' => $ergebnis['grund'] ?? 'bfr_nicht_erreichbar'];
        }

        $kasse   = $this->ladeKasse($kasseId);
        $belegNr = 'Nullbeleg' . $kasse['kasse_nr'] . date('YmdHis');
        $xml     = $this->baueNullbelegXml($belegNr, $kasse['rksv_kassen_id']);
        $antwort = $this->httpPost(rtrim($kasse['bfr_url'], '/') . '/register', $xml);
        $reg     = $this->parseRegisterAntwort($antwort);

        $this->db->prepare("
            INSERT INTO bfr_nullbelege (kasse_id, monat, beleg_nr, ausgeloest_durch, rksv_signatur, rksv_qr, benutzer_id, signiert_am)
            VALUES (:kid, :monat, :beleg_nr, :ausgeloest_durch, :sig, :qr, :benutzer_id, NOW())
        ")->execute([
            'kid'              => $kasseId,
            'monat'            => date('Y-m'),
            'beleg_nr'         => $belegNr,
            'ausgeloest_durch' => $ausgeloestDurch,
            'sig'              => $reg['link'],
            'qr'               => $reg['code'],
            'benutzer_id'      => $benutzerId,
        ]);

        $this->verarbeiteRegisterErgebnis($kasseId, $belegNr, $reg);

        return ['erfolg' => true, 'ausgefallen' => $reg['ausgefallen'], 'beleg_nr' => $belegNr];
    }

    /**
     * Sorgt dafür, dass diese Kasse in diesem Kalendermonat mindestens einen
     * signierten Nullbeleg hat. Vor der ersten echten Buchung des Monats
     * aufrufen. Ist BFR gerade nicht erreichbar, wird still übersprungen —
     * der nächste Verkauf (der ja ohnehin sein eigenes State-Gate hat) oder
     * der Cronjob holt das nach. Kein Platzhalter-Datensatz nötig: schlägt
     * ein Versuch fehl, bleibt einfach nichts zurück, der nächste Versuch
     * bekommt automatisch eine neue Belegnummer.
     */
    public function sicherstelleMonatsNullbeleg(int $kasseId): void
    {
        $monat = date('Y-m');
        $stmt = $this->db->prepare("
            SELECT 1 FROM bfr_nullbelege
            WHERE kasse_id = :kid AND monat = :monat AND rksv_signatur IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute(['kid' => $kasseId, 'monat' => $monat]);
        if ($stmt->fetchColumn()) {
            return; // für diesen Monat schon erledigt
        }

        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return;
        }

        $verbindung = $this->pruefeVerbindung($kasse['bfr_url'], $kasse['rksv_kassen_id']);
        if (!$verbindung['erreichbar']) {
            return; // still überspringen, nächster Versuch holt's nach
        }

        $this->erstelleNullbeleg($kasseId, 'automatisch');
    }

    private function baueNullbelegXml(string $belegNr, ?string $rksvKassenId): string
    {
        $tra = new SimpleXMLElement('<Tra/>');
        $esr = $tra->addChild('ESR');
        $esr->addAttribute('D', date('Y-m-d\TH:i:s'));
        $esr->addAttribute('TT', (string)$rksvKassenId);
        $esr->addAttribute('TN', $belegNr);
        return $tra->asXML();
    }

    // -------------------------------------------------------------------------
    // Kassen-Registrierung — Protokoll/Backup der FinanzOnline-Meldung
    // (unverändert — die eigentliche Meldung + der Startbeleg passieren im
    // BFR-Admin-Tool selbst, nicht über unsere Software)
    // -------------------------------------------------------------------------

    /** Liest /state und zerlegt das SC-Feld (z.B. "ATU65033000:AT1:5619064c") in seine Teile. */
    public function leseZertifikatInfo(string $bfrUrl): array
    {
        $antwort = $this->httpGet(rtrim($bfrUrl, '/') . '/state');
        if ($antwort === null) {
            return ['erfolg' => false, 'grund' => 'bfr_nicht_erreichbar'];
        }
        $state = @simplexml_load_string($antwort);
        if ($state === false) {
            return ['erfolg' => false, 'grund' => 'antwort_ungueltig'];
        }

        $teile = explode(':', (string)($state->SC ?? ''));
        $hex   = $teile[2] ?? '';

        return [
            'erfolg'                    => true,
            'rksv_kassen_id'            => (string)($state->RN ?? ''),
            'uid_nummer'                => $teile[0] ?: (string)($state->Company ?? ''),
            'vertrauensdiensteanbieter' => $teile[1] ?? '',
            'zertifikat_seriennr_hex'   => $hex,
            'zertifikat_seriennr_dez'   => $hex !== '' ? (string)hexdec($hex) : '',
        ];
    }

    /** Laufender Entwurf + zuletzt abgeschlossene Registrierung einer Kasse. */
    public function registrierungsStatus(int $kasseId): array
    {
        $entwurf = $this->db->prepare("
            SELECT * FROM bfr_kassen_registrierungen
            WHERE kasse_id = :kid AND abgeschlossen_am IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $entwurf->execute(['kid' => $kasseId]);

        $abgeschlossen = $this->db->prepare("
            SELECT * FROM bfr_kassen_registrierungen
            WHERE kasse_id = :kid AND abgeschlossen_am IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
        $abgeschlossen->execute(['kid' => $kasseId]);

        return ['entwurf' => $entwurf->fetch() ?: null, 'abgeschlossen' => $abgeschlossen->fetch() ?: null];
    }

    /** Legt einen neuen Registrierungs-Entwurf an (Erst-Setup oder Hardware-Wechsel). */
    public function starteRegistrierung(int $kasseId, int $benutzerId): int
    {
        $this->db->prepare("
            INSERT INTO bfr_kassen_registrierungen (kasse_id, rksv_kassen_id, bfr_url, benutzer_id)
            VALUES (:kasse_id, '', '', :benutzer_id)
        ")->execute(['kasse_id' => $kasseId, 'benutzer_id' => $benutzerId]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Speichert Stammdaten + Bestätigungs-Checkboxen eines Entwurfs. Ein einmal
     * gesetzter Zeitstempel bleibt beim erneuten Speichern erhalten (nicht auf
     * "jetzt" zurückgesetzt) — wird die Checkbox wieder abgehakt, wird er gelöscht.
     */
    public function aktualisiereRegistrierung(int $id, array $daten): void
    {
        $stmt = $this->db->prepare("SELECT * FROM bfr_kassen_registrierungen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $bisher = $stmt->fetch();
        if (!$bisher) {
            return;
        }

        $jetzt = date('Y-m-d H:i:s');
        $this->db->prepare("
            UPDATE bfr_kassen_registrierungen SET
                rksv_kassen_id            = :rksv_kassen_id,
                bfr_url                   = :bfr_url,
                uid_nummer                = :uid_nummer,
                vertrauensdiensteanbieter = :vertrauensdiensteanbieter,
                zertifikat_seriennr_dez   = :zertifikat_seriennr_dez,
                zertifikat_seriennr_hex   = :zertifikat_seriennr_hex,
                zertifikat_gemeldet_am    = :zertifikat_gemeldet_am,
                kasse_gemeldet_am         = :kasse_gemeldet_am,
                startbeleg_geprueft_am    = :startbeleg_geprueft_am,
                startbeleg_inhalt         = :startbeleg_inhalt
            WHERE id = :id
        ")->execute([
            'rksv_kassen_id'            => $daten['rksv_kassen_id'] ?? '',
            'bfr_url'                   => $daten['bfr_url'] ?? '',
            'uid_nummer'                => $daten['uid_nummer'] ?: null,
            'vertrauensdiensteanbieter' => $daten['vertrauensdiensteanbieter'] ?: null,
            'zertifikat_seriennr_dez'   => $daten['zertifikat_seriennr_dez'] ?: null,
            'zertifikat_seriennr_hex'   => $daten['zertifikat_seriennr_hex'] ?: null,
            'zertifikat_gemeldet_am'    => !empty($daten['zertifikat_gemeldet']) ? ($bisher['zertifikat_gemeldet_am'] ?: $jetzt) : null,
            'kasse_gemeldet_am'         => !empty($daten['kasse_gemeldet'])      ? ($bisher['kasse_gemeldet_am']      ?: $jetzt) : null,
            'startbeleg_geprueft_am'    => !empty($daten['startbeleg_geprueft'])  ? ($bisher['startbeleg_geprueft_am']  ?: $jetzt) : null,
            'startbeleg_inhalt'         => $daten['startbeleg_inhalt'] ?: null,
            'id'                        => $id,
        ]);
    }

    /**
     * Schließt eine Registrierung ab: überträgt Kassen-ID/BFR-URL auf die Kasse,
     * setzt bfr_aktiv_seit auf jetzt (= Stichtag für alle Signier-Abfragen) und
     * den Umsatzzähler auf 0 zurück (neue Kassen-ID = neue Signaturkette).
     */
    public function schliesseRegistrierungAb(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM bfr_kassen_registrierungen WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $reg = $stmt->fetch();
        if (!$reg) {
            return ['erfolg' => false, 'fehler' => 'Registrierung nicht gefunden.'];
        }
        if (empty($reg['rksv_kassen_id']) || empty($reg['bfr_url'])) {
            return ['erfolg' => false, 'fehler' => 'Kassen-ID und BFR-URL müssen gesetzt sein.'];
        }
        if (!$reg['zertifikat_gemeldet_am'] || !$reg['kasse_gemeldet_am'] || !$reg['startbeleg_geprueft_am']) {
            return ['erfolg' => false, 'fehler' => 'Alle drei Bestätigungen müssen abgehakt sein, bevor abgeschlossen werden kann.'];
        }

        $this->db->prepare("UPDATE bfr_kassen_registrierungen SET abgeschlossen_am = NOW() WHERE id = :id")
            ->execute(['id' => $id]);

        $this->db->prepare("
            UPDATE kassen SET
                rksv_kassen_id    = :rksv_kassen_id,
                bfr_url           = :bfr_url,
                bfr_aktiv_seit    = NOW(),
                bfr_umsatzzaehler = 0.00
            WHERE id = :kasse_id
        ")->execute([
            'rksv_kassen_id' => $reg['rksv_kassen_id'],
            'bfr_url'        => $reg['bfr_url'],
            'kasse_id'       => $reg['kasse_id'],
        ]);

        Logger::log('kasse.bfr.registrierung_abgeschlossen', 'kassen', (int)$reg['kasse_id'], [
            'rksv_kassen_id' => $reg['rksv_kassen_id'],
        ], $this->getJarvisId());

        return ['erfolg' => true];
    }

    /**
     * Vorab-Check für Stornos: würde dieser (negative) Betrag den Gesamtumsatzzähler
     * unter Null drücken? Der BFR selbst ist dabei völlig funktionsfähig — wir lehnen
     * hier rein aus eigener Business-Logik ab. Deshalb VOR dem Anlegen des Storno-Belegs
     * aufrufen (KassenService::storniereBon) und den Vorgang bei true ganz verhindern.
     */
    public function wuerdeUmsatzzaehlerNegativWerden(int $kasseId, float $betrag): bool
    {
        if ($betrag >= 0) {
            return false;
        }
        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return false; // keine BFR-Anbindung für diese Kasse, keine Sperre nötig
        }
        return ((float)$kasse['bfr_umsatzzaehler'] + $betrag) < 0;
    }

    private function ladeKasse(int $kasseId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, bfr_url, rksv_kassen_id, kasse_nr, bfr_umsatzzaehler, bfr_aktiv_seit FROM kassen WHERE id = :id
        ");
        $stmt->execute(['id' => $kasseId]);
        return $stmt->fetch() ?: [];
    }

    private function aktualisiereUmsatzzaehler(int $kasseId, float $neuerZaehler): void
    {
        $this->db->prepare("UPDATE kassen SET bfr_umsatzzaehler = :zaehler WHERE id = :id")
            ->execute(['zaehler' => $neuerZaehler, 'id' => $kasseId]);
    }

    private function baueSignierXml(array $beleg): string
    {
        $datum = date('Y-m-d\TH:i:s', strtotime($beleg['erstellt_am']));

        $tra = new SimpleXMLElement('<Tra/>');
        $esr = $tra->addChild('ESR');
        $esr->addAttribute('D', $datum);
        $esr->addAttribute('TN', $beleg['bon_nr']);
        $esr->addAttribute('T', number_format((float)$beleg['bruttobetrag'], 2, '.', ''));

        $taxA = $esr->addChild('TaxA');
        foreach (['A' => 'steuer_a', 'B' => 'steuer_b', 'C' => 'steuer_c', 'D' => 'steuer_d', 'E' => 'steuer_e'] as $gruppe => $spalte) {
            $tax = $taxA->addChild('Tax');
            $tax->addAttribute('TaxG', $gruppe);
            $tax->addAttribute('Amt', number_format((float)($beleg[$spalte] ?? 0), 2, '.', ''));
        }

        return $tra->asXML();
    }

    /** Wandelt einen internen grund-Code in einen für die UI lesbaren Text. */
    public static function grundText(string $grund): string
    {
        $labels = [
            'bfr_nicht_erreichbar'  => 'BFR-Dienst nicht erreichbar',
            'antwort_ungueltig'     => 'Antwort vom BFR war nicht lesbar',
            'rn_stimmt_nicht'       => 'RKSV-Kassen-ID stimmt nicht überein',
            'zaehler_negativ'       => 'Gesamtumsatzzähler würde negativ werden',
            'kein_bfr_konfiguriert' => 'Keine BFR-URL konfiguriert',
        ];
        return $labels[$grund] ?? $grund;
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => self::TIMEOUT_SEKUNDEN,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
        ]);
        $antwort = curl_exec($ch);
        $fehler  = curl_errno($ch);
        curl_close($ch);
        return ($fehler || $antwort === false) ? null : $antwort;
    }

    private function httpPost(string $url, string $xmlBody): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => self::TIMEOUT_SEKUNDEN,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xmlBody,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml'],
        ]);
        $antwort = curl_exec($ch);
        $fehler  = curl_errno($ch);
        curl_close($ch);
        return ($fehler || $antwort === false) ? null : $antwort;
    }
}
