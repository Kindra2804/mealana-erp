<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * BfrService – RKSV-Signatur über den BFR BONit Fiscal Recorder
 *
 * Signiert Kassenbons in TN-Reihenfolge (aufsteigend, wie von RKSV gefordert).
 * Ist der BFR beim Bon-Erstellen nicht erreichbar, bleibt der Bon
 * bfr_status='ausstehend' und wird beim nächsten erreichbaren Versuch
 * zusammen mit allen anderen offenen Belegen derselben Kasse (älteste zuerst)
 * nachsigniert. Mehr als ein offener Beleg → das wird als eigener
 * "Nachsignierungslauf" protokolliert (bfr_nachsignierungs_laeufe).
 */
class BfrService
{
    private PDO $db;

    private const TIMEOUT_SEKUNDEN            = 5;
    private const PAUSE_ZWISCHEN_SIGNATUREN_MS = 200;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt die ID des System-Users "Jarvis" zurück, für Logger::log() wenn
     * BfrService ohne Session läuft (Cronjob, Nachsignierung im Hintergrund).
     * Gleiches Muster wie LagerService::getJarvisId() — bewusst per Username
     * nachgeschlagen statt fixer ID, damit keine bestimmte benutzer.id bei der
     * Installation erzwungen werden muss.
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
     * Anlegen eines Bons aufgerufen, damit steuer_a..e sofort feststehen —
     * so kann auch Wochen später noch nachsigniert werden, ohne dass sich
     * an den Positionen zwischenzeitlich etwas geändert haben könnte.
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

    /**
     * Haupt-Einstiegspunkt: nach jeder Bon-Erstellung für die jeweilige Kasse
     * aufrufen. Signiert zuerst alle noch offenen Belege (älteste TN zuerst),
     * dann folgt der gerade erstellte Bon automatisch als letzter in der Liste.
     * Bricht bei der ersten fehlgeschlagenen Signatur ab — ein späterer Beleg
     * darf nie vor einem gescheiterten/offenen früheren Beleg signiert werden.
     */
    public function signiereAusstehende(int $kasseId, string $ausgeloestDurch = 'automatisch'): array
    {
        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return ['ausgefuehrt' => false, 'grund' => 'kein_bfr_konfiguriert'];
        }

        $verbindung = $this->pruefeVerbindung($kasse['bfr_url'], $kasse['rksv_kassen_id']);
        if (!$verbindung['erreichbar']) {
            return ['ausgefuehrt' => false, 'grund' => $verbindung['grund']];
        }

        // erstellt_am >= bfr_aktiv_seit: nur Belege ab der aktuell gültigen Kassen-ID/Registrierung
        // signieren. Historische Belege von vor der BFR-Einführung oder von einer alten Kassen-ID
        // (Hardware-Wechsel) gehören zu einer anderen bzw. gar keiner Signaturkette und dürfen nie
        // mit der jetzigen Registrierung nachsigniert werden.
        $stmt = $this->db->prepare("
            SELECT id, bon_nr, bruttobetrag, erstellt_am, steuer_a, steuer_b, steuer_c, steuer_d, steuer_e
            FROM kassen_bons
            WHERE kasse_id = :kid AND typ IN ('verkauf','storno') AND bfr_status = 'ausstehend'
                  AND erstellt_am >= :aktiv_seit
            ORDER BY id ASC
        ");
        $stmt->execute(['kid' => $kasseId, 'aktiv_seit' => $kasse['bfr_aktiv_seit']]);
        $belege = $stmt->fetchAll();

        if (empty($belege)) {
            return ['ausgefuehrt' => true, 'signiert' => 0, 'fehlgeschlagen' => 0];
        }

        $laufId = count($belege) > 1
            ? $this->starteNachsignierungslauf($kasseId, $ausgeloestDurch)
            : null;

        $zaehler        = (float)$kasse['bfr_umsatzzaehler'];
        $signiert       = 0;
        $fehlgeschlagen = 0;
        foreach ($belege as $i => $beleg) {
            $ergebnis = $this->signiereEinzelbeleg($beleg, $kasse['bfr_url'], $zaehler);
            if ($ergebnis['erfolg']) {
                $this->markiereSigniert((int)$beleg['id'], $ergebnis, $laufId);
                $zaehler += (float)$beleg['bruttobetrag'];
                $this->aktualisiereUmsatzzaehler($kasseId, $zaehler);
                $signiert++;
            } else {
                $this->markiereFehler((int)$beleg['id'], $ergebnis);
                $fehlgeschlagen++;
                break; // Reihenfolge einhalten: nicht am gescheiterten Beleg vorbei weitermachen
            }
            if ($i < count($belege) - 1) {
                usleep(self::PAUSE_ZWISCHEN_SIGNATUREN_MS * 1000);
            }
        }

        if ($laufId !== null) {
            $this->beendeNachsignierungslauf($laufId, $signiert, $fehlgeschlagen);
        }

        return ['ausgefuehrt' => true, 'signiert' => $signiert, 'fehlgeschlagen' => $fehlgeschlagen];
    }

    // -------------------------------------------------------------------------
    // Nacherfassungs-Seite — Übersicht offener Belege + Sammelbeleg-Historie
    // -------------------------------------------------------------------------

    /** Alle Verkaufs-/Storno-Bons, die noch auf eine Signatur warten oder fehlgeschlagen sind. */
    public function offeneBelege(): array
    {
        // Nur Belege ab k.bfr_aktiv_seit — historische/Kassen-ID-fremde Belege werden hier
        // bewusst nicht als "offen" gelistet, siehe Kommentar in signiereAusstehende().
        $stmt = $this->db->query("
            SELECT b.id, b.bon_nr, b.typ, b.bruttobetrag, b.erstellt_am,
                   b.bfr_status, b.bfr_fehlergrund, k.name AS kasse_name
            FROM kassen_bons b
            JOIN kassen k ON k.id = b.kasse_id
            WHERE b.bfr_status IN ('ausstehend','fehler')
                  AND k.bfr_aktiv_seit IS NOT NULL AND b.erstellt_am >= k.bfr_aktiv_seit
            ORDER BY b.id ASC
        ");
        return $stmt->fetchAll();
    }

    /** Alle Nullbelege, die noch auf eine Signatur warten oder fehlgeschlagen sind. */
    public function offeneNullbelege(): array
    {
        $stmt = $this->db->query("
            SELECT n.id, n.beleg_nr, n.monat, n.ausgeloest_durch, n.erstellt_am,
                   n.bfr_status, n.bfr_fehlergrund, k.name AS kasse_name
            FROM bfr_nullbelege n
            JOIN kassen k ON k.id = n.kasse_id
            WHERE n.bfr_status IN ('ausstehend','fehler')
            ORDER BY n.id ASC
        ");
        return $stmt->fetchAll();
    }

    /** Die letzten Nachsignierungsläufe (Sammelbeleg-Liste) über alle Kassen. */
    public function nachsignierungsLaeufe(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT l.id, l.kasse_id, k.name AS kasse_name, l.ausgeloest_durch,
                   l.gestartet_am, l.beendet_am, l.anzahl_signiert, l.anzahl_fehlgeschlagen
            FROM bfr_nachsignierungs_laeufe l
            JOIN kassen k ON k.id = l.kasse_id
            ORDER BY l.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Welche Belege gehören zu einem bestimmten Nachsignierungslauf (Drill-down). */
    public function laufBelege(int $laufId): array
    {
        $stmt = $this->db->prepare("
            SELECT bon_nr, typ, bruttobetrag, bfr_status, signiert_am
            FROM kassen_bons
            WHERE nachsignierungs_lauf_id = :lauf_id
            ORDER BY id ASC
        ");
        $stmt->execute(['lauf_id' => $laufId]);
        return $stmt->fetchAll();
    }

    /**
     * Setzt einen fehlgeschlagenen Beleg auf 'ausstehend' zurück und stößt die normale
     * Signierlogik für seine Kasse erneut an — die Reihenfolge-Regel greift dabei ganz
     * normal weiter (ältere offene Belege würden zuerst dran sein).
     */
    public function retryBeleg(int $bonId): array
    {
        $stmt = $this->db->prepare("SELECT kasse_id FROM kassen_bons WHERE id = :id");
        $stmt->execute(['id' => $bonId]);
        $kasseId = (int)($stmt->fetchColumn() ?: 0);
        if (!$kasseId) {
            return ['erfolg' => false, 'fehler' => 'Beleg nicht gefunden.'];
        }

        $this->db->prepare("UPDATE kassen_bons SET bfr_status = 'ausstehend', bfr_fehlergrund = NULL WHERE id = :id")
            ->execute(['id' => $bonId]);

        $ergebnis = $this->signiereAusstehende($kasseId, 'manuell');
        return ['erfolg' => true] + $ergebnis;
    }

    /** Gegenstück zu retryBeleg() für Nullbelege. */
    public function retryNullbeleg(int $nullbelegId): array
    {
        $stmt = $this->db->prepare("SELECT kasse_id, beleg_nr FROM bfr_nullbelege WHERE id = :id");
        $stmt->execute(['id' => $nullbelegId]);
        $nullbeleg = $stmt->fetch();
        if (!$nullbeleg) {
            return ['erfolg' => false, 'fehler' => 'Nullbeleg nicht gefunden.'];
        }

        $kasse = $this->ladeKasse((int)$nullbeleg['kasse_id']);
        if (empty($kasse['bfr_url'])) {
            return ['erfolg' => false, 'fehler' => 'Keine BFR-URL für diese Kasse konfiguriert.'];
        }

        return $this->versucheNullbelegSignatur($nullbelegId, $nullbeleg['beleg_nr'], $kasse);
    }

    // -------------------------------------------------------------------------
    // Kassen-Registrierung — Protokoll/Backup der FinanzOnline-Meldung
    // (die eigentliche Meldung + der Startbeleg passieren im BFR-Admin-Tool
    // selbst, nicht über unsere Software — das hier ist die zweite,
    // unabhängige Aufzeichnung "wann wurde was bestätigt")
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
     * aufrufen (KassenService::storniereBon) und den Vorgang bei true ganz verhindern,
     * statt einen Beleg mit dem irreführenden Hinweis "Sicherheitseinrichtung ausgefallen"
     * drucken zu lassen — das Gerät ist ja gar nicht ausgefallen.
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

    /**
     * POST /register für einen einzelnen Verkaufs- oder Stornobeleg.
     * Vorgabe des BFR-Herstellers: der Gesamtumsatzzähler darf durch kein
     * signiertes Storno negativ werden. Der Zähler steckt nur verschlüsselt
     * in der Signatur (nicht auslesbar) — wir führen ihn deshalb selbst in
     * kassen.bfr_umsatzzaehler mit und prüfen hier, BEVOR wir überhaupt an
     * den BFR senden.
     */
    private function signiereEinzelbeleg(array $beleg, string $bfrUrl, float $aktuellerZaehler): array
    {
        $betrag = (float)$beleg['bruttobetrag'];
        if ($betrag < 0 && ($aktuellerZaehler + $betrag) < 0) {
            return ['erfolg' => false, 'grund' => 'zaehler_negativ', 'meldung' => 'Storno würde Gesamtumsatzzähler negativ machen'];
        }

        $xml = $this->baueSignierXml($beleg);
        $antwort = $this->httpPost(rtrim($bfrUrl, '/') . '/register', $xml);
        return $this->parseRegisterAntwort($antwort);
    }

    /** Wertet die /register-Antwort aus — gemeinsam für Verkaufsbeleg und Nullbeleg. */
    private function parseRegisterAntwort(?string $antwort): array
    {
        if ($antwort === null) {
            return ['erfolg' => false, 'grund' => 'verbindung'];
        }

        $traC = @simplexml_load_string($antwort);
        if ($traC === false) {
            return ['erfolg' => false, 'grund' => 'abgelehnt', 'meldung' => 'Antwort nicht lesbar'];
        }

        $rc = (string)($traC->Result['RC'] ?? '');
        if ($rc !== 'OK') {
            return ['erfolg' => false, 'grund' => 'abgelehnt', 'meldung' => $rc ?: 'Unbekannter Fehler'];
        }

        return [
            'erfolg' => true,
            'code'   => (string)($traC->Fis->Code ?? ''),
            'link'   => (string)($traC->Fis->Link ?? ''),
        ];
    }

    /**
     * Erstellt einen Nullbeleg (kein Umsatz, keine Steuergruppen) — Pflicht am
     * Jahresende (31.12.) und als monatliche Absicherung, kann aber jederzeit
     * manuell ausgelöst werden. Eigener Belegnummernkreis ("NullbelegK1..."),
     * überschneidet sich nie mit normalen Bon-Nummern.
     */
    public function erstelleNullbeleg(int $kasseId, string $ausgeloestDurch, ?int $benutzerId = null): array
    {
        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return ['erfolg' => false, 'grund' => 'kein_bfr_konfiguriert'];
        }

        $belegNr = 'Nullbeleg' . $kasse['kasse_nr'] . date('YmdHis');
        $this->db->prepare("
            INSERT INTO bfr_nullbelege (kasse_id, monat, beleg_nr, ausgeloest_durch, benutzer_id)
            VALUES (:kasse_id, :monat, :beleg_nr, :ausgeloest_durch, :benutzer_id)
        ")->execute([
            'kasse_id'         => $kasseId,
            'monat'            => date('Y-m'),
            'beleg_nr'         => $belegNr,
            'ausgeloest_durch' => $ausgeloestDurch,
            'benutzer_id'      => $benutzerId,
        ]);
        $nullbelegId = (int)$this->db->lastInsertId();

        return $this->versucheNullbelegSignatur($nullbelegId, $belegNr, $kasse);
    }

    /**
     * Sorgt dafür, dass diese Kasse in diesem Kalendermonat mindestens einen
     * signierten Nullbeleg hat — vor der ersten echten Buchung des Monats
     * aufrufen (siehe KassenService::erstelleBon). Gibt es für diesen Monat
     * schon einen offenen/fehlgeschlagenen Versuch, wird der erneut versucht
     * statt einen weiteren Datensatz anzulegen.
     */
    public function sicherstelleMonatsNullbeleg(int $kasseId): void
    {
        $monat = date('Y-m');
        $stmt = $this->db->prepare("
            SELECT id, beleg_nr, bfr_status FROM bfr_nullbelege
            WHERE kasse_id = :kid AND monat = :monat
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['kid' => $kasseId, 'monat' => $monat]);
        $vorhanden = $stmt->fetch();

        if ($vorhanden && $vorhanden['bfr_status'] === 'signiert') {
            return; // für diesen Monat schon erledigt
        }

        $kasse = $this->ladeKasse($kasseId);
        if (empty($kasse['bfr_url'])) {
            return;
        }

        if ($vorhanden) {
            $this->versucheNullbelegSignatur((int)$vorhanden['id'], $vorhanden['beleg_nr'], $kasse);
        } else {
            $this->erstelleNullbeleg($kasseId, 'automatisch');
        }
    }

    private function versucheNullbelegSignatur(int $nullbelegId, string $belegNr, array $kasse): array
    {
        $verbindung = $this->pruefeVerbindung($kasse['bfr_url'], $kasse['rksv_kassen_id']);
        if (!$verbindung['erreichbar']) {
            return ['erfolg' => false, 'grund' => $verbindung['grund']];
        }

        $xml     = $this->baueNullbelegXml($belegNr, $kasse['rksv_kassen_id']);
        $antwort = $this->httpPost(rtrim($kasse['bfr_url'], '/') . '/register', $xml);
        $ergebnis = $this->parseRegisterAntwort($antwort);

        if ($ergebnis['erfolg']) {
            $this->db->prepare("
                UPDATE bfr_nullbelege SET
                    bfr_status = 'signiert', bfr_fehlergrund = NULL, signiert_am = NOW(),
                    rksv_signatur = :link, rksv_qr = :code
                WHERE id = :id
            ")->execute(['link' => $ergebnis['link'], 'code' => $ergebnis['code'], 'id' => $nullbelegId]);
            return ['erfolg' => true, 'beleg_nr' => $belegNr];
        }

        $status = $ergebnis['grund'] === 'verbindung' ? 'ausstehend' : 'fehler';
        $this->db->prepare("UPDATE bfr_nullbelege SET bfr_status = :status, bfr_fehlergrund = :grund WHERE id = :id")
            ->execute(['status' => $status, 'grund' => self::grundText($ergebnis['grund']), 'id' => $nullbelegId]);
        return ['erfolg' => false, 'grund' => $ergebnis['grund']];
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

    private function ladeKasse(int $kasseId): array
    {
        $stmt = $this->db->prepare("
            SELECT bfr_url, rksv_kassen_id, kasse_nr, bfr_umsatzzaehler, bfr_aktiv_seit FROM kassen WHERE id = :id
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

    /** Wandelt einen internen grund-Code in einen für die Nacherfassungs-Seite lesbaren Text. */
    public static function grundText(string $grund, ?string $meldung = null): string
    {
        $labels = [
            'verbindung'            => 'BFR nicht erreichbar',
            'abgelehnt'             => 'Vom BFR abgelehnt',
            'zaehler_negativ'       => 'Gesamtumsatzzähler würde negativ werden',
            'kein_bfr_konfiguriert' => 'Keine BFR-URL konfiguriert',
            'antwort_ungueltig'     => 'Antwort vom BFR war nicht lesbar',
            'rn_stimmt_nicht'       => 'RKSV-Kassen-ID stimmt nicht überein',
        ];
        $text = $labels[$grund] ?? $grund;
        return $meldung ? $text . ' (' . $meldung . ')' : $text;
    }

    private function markiereSigniert(int $bonId, array $ergebnis, ?int $laufId): void
    {
        $this->db->prepare("
            UPDATE kassen_bons SET
                bfr_status              = 'signiert',
                bfr_fehlergrund         = NULL,
                signiert_am             = NOW(),
                rksv_signatur           = :link,
                rksv_qr                 = :code,
                nachsignierungs_lauf_id = :lauf_id
            WHERE id = :id
        ")->execute([
            'link'    => $ergebnis['link'],
            'code'    => $ergebnis['code'],
            'lauf_id' => $laufId,
            'id'      => $bonId,
        ]);
    }

    private function markiereFehler(int $bonId, array $ergebnis): void
    {
        // Nur reiner Verbindungsfehler bleibt 'ausstehend' (automatischer Retry beim
        // nächsten Versuch). Alles andere (von BFR abgelehnt, Zähler würde negativ, ...)
        // → 'fehler', braucht manuellen Blick — ein bloßer Retry würde daran nichts ändern.
        $status = $ergebnis['grund'] === 'verbindung' ? 'ausstehend' : 'fehler';
        $this->db->prepare("UPDATE kassen_bons SET bfr_status = :status, bfr_fehlergrund = :grund WHERE id = :id")
            ->execute([
                'status' => $status,
                'grund'  => self::grundText($ergebnis['grund'], $ergebnis['meldung'] ?? null),
                'id'     => $bonId,
            ]);

        Logger::log('kasse.bfr.fehler', 'kassen_bons', $bonId, [
            'grund'   => $ergebnis['grund'],
            'meldung' => $ergebnis['meldung'] ?? null,
        ], $this->getJarvisId());
    }

    private function starteNachsignierungslauf(int $kasseId, string $ausgeloestDurch): int
    {
        $this->db->prepare("
            INSERT INTO bfr_nachsignierungs_laeufe (kasse_id, ausgeloest_durch)
            VALUES (:kasse_id, :ausgeloest_durch)
        ")->execute(['kasse_id' => $kasseId, 'ausgeloest_durch' => $ausgeloestDurch]);
        return (int)$this->db->lastInsertId();
    }

    private function beendeNachsignierungslauf(int $laufId, int $signiert, int $fehlgeschlagen): void
    {
        $this->db->prepare("
            UPDATE bfr_nachsignierungs_laeufe SET
                beendet_am            = NOW(),
                anzahl_signiert       = :signiert,
                anzahl_fehlgeschlagen = :fehlgeschlagen
            WHERE id = :id
        ")->execute(['signiert' => $signiert, 'fehlgeschlagen' => $fehlgeschlagen, 'id' => $laufId]);
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEKUNDEN,
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
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEKUNDEN,
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
