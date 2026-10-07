<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/AuftragRepository.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';

/**
 * MahnwesenService – Erinnerung/Stornierung für überfällige Aufträge.
 *
 * Vorkasse: Erinnerung nach 14 Tagen, Auto-Storno nach 30 Tagen (ab Bestellung).
 * Rechnung (seit 2026-10-02): ab Fälligkeit der Rechnung Erinnerung (automatisch), dann
 * 1. und 2. Mahnung als Vorschlag (mahnungen.status 'vorgeschlagen') — Freigabe auf
 * Verkauf → Mahnwesen erzeugt PDF + Mail und schreibt die Mahngebühr fest. Offene
 * Gebühren zählen zum zu zahlenden Betrag (AuftragRepository::getOffeneMahngebuehren).
 *
 * Wird sowohl vom Cronjob (cron/mahnwesen.php, taeglich 06:00) als auch vom
 * manuellen "Erinnerung senden"/"Stornieren?"-Button im Dashboard aufgerufen
 * (public/auftraege/mahnung_manuell_ajax.php) — dieselbe Logik, damit beide
 * Wege exakt gleich buchen/mailen/loggen. $ausloeser landet in
 * mahnungen.erstellt_von ('cronjob'|'manuell').
 */
class MahnwesenService
{
    private PDO $db;
    private AuftragRepository $auftragRepo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auftragRepo = new AuftragRepository();
    }

    private function ladeAuftrag(int $auftragId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, auftrag_nr, erstellt_am, bruttobetrag, zahlungsart, zahlungsstatus, lieferstatus, kunden_snapshot
            FROM auftraege WHERE id = ?
        ");
        $stmt->execute([$auftragId]);
        $auftrag = $stmt->fetch(PDO::FETCH_ASSOC);
        return $auftrag ?: null;
    }

    private function kundenDaten(array $auftrag): array
    {
        $snapshot  = !empty($auftrag['kunden_snapshot']) ? json_decode($auftrag['kunden_snapshot'], true) : [];
        $kundeName = trim(($snapshot['vorname'] ?? '') . ' ' . ($snapshot['nachname'] ?? ''));
        if (!$kundeName) $kundeName = $snapshot['firma'] ?? 'Kunde';
        return ['name' => $kundeName, 'email' => $snapshot['email'] ?? ''];
    }

    /** Fristen/Gebühren aus Einstellungen → Mahnwesen (Vorgaben = Migration 193). */
    public function einstellungen(): array
    {
        $e = $this->db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel LIKE 'mahnung\\_%'")
                      ->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'erinnerung_tage' => (int)($e['mahnung_erinnerung_tage'] ?? 7),
            'stufe1_tage'     => (int)($e['mahnung_stufe1_tage'] ?? 14),
            'stufe2_tage'     => (int)($e['mahnung_stufe2_tage'] ?? 14),
            'gebuehr1'        => (float)($e['mahnung_gebuehr_stufe1'] ?? 5),
            'gebuehr2'        => (float)($e['mahnung_gebuehr_stufe2'] ?? 10),
        ];
    }

    /**
     * Stand einer Rechnung: offener Rechnungsbetrag, offene Mahngebühren, Mahnhistorie.
     * null, wenn es zum Auftrag keine (nicht stornierte) Rechnung gibt — ohne Rechnung
     * wird nicht gemahnt.
     */
    public function rechnungsStand(int $auftragId): ?array
    {
        // Teilrechnungen (Belege-Umbau 2026-10-07): maßgeblich ist die älteste noch nicht
        // (voll) bezahlte Rechnung -- Zahlungen werden in Rechnungsreihenfolge angerechnet.
        $r = $this->db->prepare("SELECT * FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 ORDER BY id");
        $r->execute([$auftragId]);
        $alle = $r->fetchAll(PDO::FETCH_ASSOC);
        if (!$alle) return null;
        $dok = new DokumentService();
        $rechnung = end($alle);
        foreach ($alle as $re) {
            if ($dok->zahlungsInfo((int)$re['id'])['offen'] > 0.004) { $rechnung = $re; break; }
        }

        $gebuehren = $this->auftragRepo->getOffeneMahngebuehren($auftragId);
        // Offen = was auf Belegen steht (Rechnungen − Gutschriften − Zahlungen − Gutscheine),
        // NICHT der Auftragsbetrag -- noch nicht gelieferte Ware wird nicht gemahnt
        $offenBelege = $dok->offenerRechnungsbetrag($auftragId) - $gebuehren;

        $m = $this->db->prepare("SELECT * FROM mahnungen WHERE auftrag_id = ? ORDER BY id");
        $m->execute([$auftragId]);
        $stufen = [];
        foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stufen[$row['typ']] = $row; // letzte Zeile je Typ
        }

        return [
            'rechnung'        => $rechnung,
            'faellig_am'      => $rechnung['faellig_am'] ?: date('Y-m-d', strtotime($rechnung['erstellt_am'] . ' +14 days')),
            'offen'           => round(max(0, $offenBelege), 2),
            'gebuehren_offen' => $gebuehren,
            'stufen'          => $stufen,
        ];
    }

    /**
     * Cron-Schritt für Rechnungskunden: Erinnerung automatisch, Mahnungen nur als Vorschlag.
     * Rechnet ab Fälligkeit der Rechnung bzw. ab dem Versand der Vorstufe.
     * @return array{erinnerungen:int, vorschlaege:int}
     */
    public function pruefeRechnungen(int $benutzerId): array
    {
        $e = $this->einstellungen();
        $ergebnis = ['erinnerungen' => 0, 'vorschlaege' => 0];

        $ids = $this->db->query("
            SELECT a.id FROM auftraege a
            WHERE a.zahlungsart = 'rechnung'
              AND a.zahlungsstatus IN ('ausstehend', 'teilbezahlt')
              AND a.lieferstatus != 'storniert'
              AND a.kanal != 'jtl_archiv'
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $stand = $this->rechnungsStand((int)$id);
            if (!$stand || $stand['offen'] + $stand['gebuehren_offen'] <= 0.004) continue;
            $s = $stand['stufen'];

            $tageSeit = fn(?string $datum) => $datum ? (int)floor((time() - strtotime($datum)) / 86400) : -1;
            $versendet = fn(string $typ) => isset($s[$typ]) && $s[$typ]['status'] === 'versendet';

            if (!isset($s['erinnerung'])) {
                if ($tageSeit($stand['faellig_am']) >= $e['erinnerung_tage']) {
                    $r = $this->sendeErinnerung((int)$id, $benutzerId, 'cronjob');
                    if ($r['erfolg']) $ergebnis['erinnerungen']++;
                }
            } elseif (!isset($s['mahnung1'])) {
                if ($tageSeit($s['erinnerung']['gesendet_am']) >= $e['stufe1_tage']) {
                    $this->schlageVor((int)$id, 'mahnung1');
                    $ergebnis['vorschlaege']++;
                }
            } elseif ($versendet('mahnung1') && !isset($s['mahnung2'])) {
                if ($tageSeit($s['mahnung1']['gesendet_am']) >= $e['stufe2_tage']) {
                    $this->schlageVor((int)$id, 'mahnung2');
                    $ergebnis['vorschlaege']++;
                }
            }
            // Nach der 2. Mahnung: keine weitere Stufe — erscheint auf der Mahnwesen-Seite
            // unter "manuell klären" (findManuellKlaeren). Verworfene Vorschläge werden nicht
            // erneut vorgeschlagen.
        }
        return $ergebnis;
    }

    private function schlageVor(int $auftragId, string $typ): void
    {
        ['email' => $email] = $this->kundenDaten($this->ladeAuftrag($auftragId));
        $this->db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, status, vorgeschlagen_am, gesendet_am, mail_an, erstellt_von)
            VALUES (?, ?, 'vorgeschlagen', NOW(), NULL, ?, 'cronjob')
        ")->execute([$auftragId, $typ, $email]);
    }

    /**
     * Gibt eine vorgeschlagene Mahnung frei: Gebühr festschreiben, PDF erzeugen, Mail mit PDF
     * an den Kunden. Offener Betrag und Gebühr werden jetzt (nicht beim Vorschlag) ermittelt.
     */
    public function freigeben(int $mahnungId, int $benutzerId): array
    {
        $m = $this->ladeMahnung($mahnungId);
        if (!$m || $m['status'] !== 'vorgeschlagen') {
            return ['erfolg' => false, 'fehler' => 'Mahnung ist nicht (mehr) zur Freigabe offen'];
        }
        $auftrag = $this->ladeAuftrag((int)$m['auftrag_id']);
        $stand   = $this->rechnungsStand((int)$m['auftrag_id']);
        if (!$stand) return ['erfolg' => false, 'fehler' => 'Zum Auftrag gibt es keine Rechnung'];
        if (!in_array($auftrag['zahlungsstatus'], ['ausstehend', 'teilbezahlt'], true) || $auftrag['lieferstatus'] === 'storniert') {
            $this->setzeStatus($mahnungId, 'verworfen', $benutzerId);
            return ['erfolg' => false, 'fehler' => 'Auftrag ist inzwischen bezahlt oder storniert — Vorschlag verworfen'];
        }

        $e       = $this->einstellungen();
        $gebuehr = $m['typ'] === 'mahnung2' ? $e['gebuehr2'] : $e['gebuehr1'];
        $frist   = date('Y-m-d', strtotime('+' . $e['stufe2_tage'] . ' days'));

        require_once __DIR__ . '/../dokumente/DokumentService.php';
        $pdf = (new DokumentService())->erstelleMahnung((int)$m['auftrag_id'], [
            'typ'              => $m['typ'],
            'offen_betrag'     => $stand['offen'],
            'gebuehren_vorher' => $stand['gebuehren_offen'],
            'gebuehr'          => $gebuehr,
            'neue_frist'       => $frist,
        ], $benutzerId);
        if (!$pdf['erfolg']) return $pdf;

        ['name' => $kundeName, 'email' => $email] = $this->kundenDaten($auftrag);

        $this->db->prepare("
            UPDATE mahnungen
            SET status = 'versendet', gesendet_am = NOW(), mail_an = ?, offen_betrag = ?, gebuehr = ?,
                neue_frist = ?, dateiname = ?, bearbeitet_von = ?
            WHERE id = ?
        ")->execute([$email, $stand['offen'], $gebuehr, $frist, $pdf['dateiname'], $benutzerId, $mahnungId]);
        $this->db->prepare("UPDATE auftraege SET mahnung_stufe = ?, mahnung_gesendet_am = NOW() WHERE id = ?")
                 ->execute([$m['typ'] === 'mahnung2' ? 2 : 1, $m['auftrag_id']]);

        $titel = $m['typ'] === 'mahnung2' ? '2. Mahnung' : '1. Mahnung';
        $this->auftragRepo->logStatus((int)$m['auftrag_id'], [],
            "$titel versendet (Mahngebühr " . number_format($gebuehr, 2, ',', '.') . ' €, Frist ' . date('d.m.Y', strtotime($frist)) . ')',
            $benutzerId);

        $mailFehler = null;
        if ($email) {
            try {
                (new Mailer())->sendeTemplate(
                    empfaenger:   $email,
                    betreff:      "$titel zu Rechnung " . $stand['rechnung']['rechnung_nr'],
                    templatePfad: 'mails/mahnwesen/mahnung.html.twig',
                    variablen: [
                        'kunde_name'  => $kundeName,
                        'titel'       => $titel,
                        'stufe'       => $m['typ'] === 'mahnung2' ? 2 : 1,
                        'rechnung_nr' => $stand['rechnung']['rechnung_nr'],
                        'gesamt'      => number_format($stand['offen'] + $stand['gebuehren_offen'] + $gebuehr, 2, ',', '.'),
                        'frist'       => date('d.m.Y', strtotime($frist)),
                    ],
                    anhaenge: [['pfad' => $pdf['pfad'], 'name' => $pdf['dateiname']]]
                );
            } catch (Throwable $ex) {
                $mailFehler = $ex->getMessage();
            }
        }

        Logger::log('mahnwesen.' . $m['typ'], 'auftraege', (int)$m['auftrag_id'],
            ['nummer' => $auftrag['auftrag_nr'], 'gebuehr' => $gebuehr, 'mail' => $email && !$mailFehler], $benutzerId);

        return ['erfolg' => true, 'mail_gesendet' => $email && !$mailFehler, 'mail_fehler' => $mailFehler,
                'ohne_email' => !$email, 'dateiname' => $pdf['dateiname'], 'auftrag_id' => (int)$m['auftrag_id']];
    }

    public function verwerfen(int $mahnungId, int $benutzerId): array
    {
        $m = $this->ladeMahnung($mahnungId);
        if (!$m || $m['status'] !== 'vorgeschlagen') {
            return ['erfolg' => false, 'fehler' => 'Mahnung ist nicht (mehr) zur Freigabe offen'];
        }
        $this->setzeStatus($mahnungId, 'verworfen', $benutzerId);
        Logger::log('mahnwesen.verworfen', 'auftraege', (int)$m['auftrag_id'], ['typ' => $m['typ']], $benutzerId);
        return ['erfolg' => true];
    }

    /** Erlässt die Mahngebühr einer versendeten Mahnung (Kulanz) — Buchhaltung bucht sie zurück. */
    public function gebuehrErlassen(int $mahnungId, int $benutzerId): array
    {
        $m = $this->ladeMahnung($mahnungId);
        if (!$m || $m['status'] !== 'versendet' || (float)$m['gebuehr'] <= 0 || $m['gebuehr_erlassen_am']) {
            return ['erfolg' => false, 'fehler' => 'Keine offene Mahngebühr zu dieser Mahnung'];
        }
        $this->db->prepare("UPDATE mahnungen SET gebuehr_erlassen_am = NOW(), bearbeitet_von = ? WHERE id = ?")
                 ->execute([$benutzerId, $mahnungId]);
        $this->auftragRepo->logStatus((int)$m['auftrag_id'], [],
            'Mahngebühr ' . number_format((float)$m['gebuehr'], 2, ',', '.') . ' € erlassen', $benutzerId);

        require_once __DIR__ . '/AuftragService.php';
        (new AuftragService())->zahlungsstatusNachGebuehrErlass((int)$m['auftrag_id'], $benutzerId);

        Logger::log('mahnwesen.gebuehr_erlassen', 'auftraege', (int)$m['auftrag_id'], ['gebuehr' => (float)$m['gebuehr']], $benutzerId);
        return ['erfolg' => true];
    }

    private function ladeMahnung(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM mahnungen WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function setzeStatus(int $mahnungId, string $status, int $benutzerId): void
    {
        $this->db->prepare("UPDATE mahnungen SET status = ?, bearbeitet_von = ? WHERE id = ?")
                 ->execute([$status, $benutzerId, $mahnungId]);
    }

    // ── Listen für Verkauf → Mahnwesen ─────────────────────────────────────

    /** Basisabfrage: Mahnungen mit Auftrag/Rechnung; Bedingung kommt vom Aufrufer. */
    private function mahnungsliste(string $where): array
    {
        $rows = $this->db->query("
            SELECT m.*, a.auftrag_nr, a.kunden_snapshot, a.bruttobetrag, a.zahlungsstatus,
                   r.rechnung_nr, r.faellig_am
            FROM mahnungen m
            JOIN auftraege a ON a.id = m.auftrag_id
            -- bei Teilrechnungen nur eine Zeile je Mahnung (älteste Rechnung)
            LEFT JOIN rechnungen r ON r.id = (SELECT MIN(r2.id) FROM rechnungen r2 WHERE r2.auftrag_id = a.id AND r2.storniert = 0)
            WHERE $where
            ORDER BY COALESCE(r.faellig_am, a.erstellt_am), m.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        $dok = new DokumentService();
        foreach ($rows as &$r) {
            $r['kunde_name'] = $this->kundenDaten($r)['name'];
            $r['offen']      = round(max(0, $dok->offenerRechnungsbetrag((int)$r['auftrag_id'])
                                        - $this->auftragRepo->getOffeneMahngebuehren((int)$r['auftrag_id'])), 2);
        }
        return $rows;
    }

    public function findVorschlaege(): array
    {
        return $this->mahnungsliste("m.status = 'vorgeschlagen'");
    }

    public function anzahlVorschlaege(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM mahnungen WHERE status = 'vorgeschlagen'")->fetchColumn();
    }

    /** Versendete Mahnungen mit noch offener Gebühr (Auftrag nicht bezahlt/storniert) — "erlassen" möglich. */
    public function findOffeneGebuehren(): array
    {
        return $this->mahnungsliste("m.status = 'versendet' AND m.gebuehr > 0 AND m.gebuehr_erlassen_am IS NULL
                                     AND a.zahlungsstatus IN ('ausstehend','teilbezahlt') AND a.lieferstatus != 'storniert'");
    }

    /** 2. Mahnung versendet, Frist abgelaufen, immer noch offen → Inkasso/Anwalt/Telefon. */
    public function findManuellKlaeren(): array
    {
        return $this->mahnungsliste("m.typ = 'mahnung2' AND m.status = 'versendet' AND m.neue_frist < CURDATE()
                                     AND a.zahlungsstatus IN ('ausstehend','teilbezahlt') AND a.lieferstatus != 'storniert'");
    }

    /** Zuletzt versendete Erinnerungen/Mahnungen (Verlauf). */
    public function findVerlauf(int $limit = 30): array
    {
        return array_slice(array_reverse($this->mahnungsliste("m.status IN ('versendet','verworfen') AND m.typ IN ('erinnerung','mahnung1','mahnung2')")), 0, $limit);
    }

    /**
     * Sendet die Zahlungserinnerung (einmal pro Auftrag). Gilt für Vorkasse + Rechnung.
     */
    public function sendeErinnerung(int $auftragId, int $benutzerId, string $ausloeser = 'cronjob'): array
    {
        $auftrag = $this->ladeAuftrag($auftragId);
        if (!$auftrag) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];

        $schon = $this->db->prepare("SELECT COUNT(*) FROM mahnungen WHERE auftrag_id = ? AND typ = 'erinnerung'");
        $schon->execute([$auftragId]);
        if ((int)$schon->fetchColumn() > 0) {
            return ['erfolg' => false, 'fehler' => 'Erinnerung wurde bereits gesendet'];
        }

        ['name' => $kundeName, 'email' => $email] = $this->kundenDaten($auftrag);
        $firma = $this->db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Rechnung: offener Betrag + Frist bis zum Vorschlag der 1. Mahnung.
        // Vorkasse: voller Betrag, Frist = Auto-Storno nach 30 Tagen.
        $istRechnung = $auftrag['zahlungsart'] === 'rechnung';
        $stand       = $istRechnung ? $this->rechnungsStand($auftragId) : null;
        $betrag      = $stand ? $stand['offen'] + $stand['gebuehren_offen'] : (float)$auftrag['bruttobetrag'];
        $frist       = $istRechnung
            ? date('d.m.Y', strtotime('+' . $this->einstellungen()['stufe1_tage'] . ' days'))
            : date('d.m.Y', strtotime($auftrag['erstellt_am'] . ' +30 days'));

        $this->db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, status, mail_an, offen_betrag, erstellt_von)
            VALUES (?, 'erinnerung', 'versendet', ?, ?, ?)
        ")->execute([$auftragId, $email, $betrag, $ausloeser]);

        $mailFehler = null;
        if ($email) {
            try {
                (new Mailer())->sendeTemplate(
                    empfaenger:  $email,
                    betreff:     'Zahlungserinnerung: Auftrag ' . $auftrag['auftrag_nr'],
                    templatePfad: 'mails/mahnwesen/erinnerung.html.twig',
                    variablen: [
                        'kunde_name'    => $kundeName,
                        'auftrag_nummer'=> $auftrag['auftrag_nr'],
                        'auftrag_datum' => date('d.m.Y', strtotime($auftrag['erstellt_am'])),
                        'betrag'        => number_format($betrag, 2, ',', '.'),
                        'zahlungsart'   => $istRechnung ? 'Rechnung' : 'Vorkasse',
                        'faellig_am'    => $frist,
                        'firma_email'   => $firma['email'] ?? '',
                    ]
                );
            } catch (Throwable $e) {
                $mailFehler = $e->getMessage();
            }
        }

        Logger::log('mahnwesen.erinnerung', 'auftraege', $auftragId,
            ['nummer' => $auftrag['auftrag_nr'], 'ausloeser' => $ausloeser], $benutzerId);

        return ['erfolg' => true, 'mail_gesendet' => $email && !$mailFehler, 'mail_fehler' => $mailFehler];
    }

    /**
     * Storniert den Auftrag + bucht Lagerbestand zurück (einmal pro Auftrag).
     * Beim Cronjob nur für Vorkasse automatisch aufgerufen (Rechnung könnte schon
     * versendet sein!). Beim manuellen Trigger bewusst für beide Zahlungsarten
     * erlaubt — das ist ja die menschliche Einzelfall-Entscheidung, die der
     * Cronjob bei Rechnung absichtlich nicht automatisch trifft.
     */
    public function storniere(int $auftragId, int $benutzerId, string $ausloeser = 'cronjob'): array
    {
        $auftrag = $this->ladeAuftrag($auftragId);
        if (!$auftrag) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];
        if ($auftrag['lieferstatus'] === 'storniert') {
            return ['erfolg' => false, 'fehler' => 'Auftrag ist bereits storniert'];
        }
        // Gleiche Regel wie AuftragService::stornieren(): Ware ist schon beim Kunden
        if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'retoure_offen'], true)) {
            return ['erfolg' => false, 'fehler' => 'Bereits versendete oder abgeschlossene Aufträge können nicht storniert werden'];
        }

        ['name' => $kundeName, 'email' => $email] = $this->kundenDaten($auftrag);
        $firma = $this->db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE auftraege
                SET lieferstatus = 'storniert', zahlungsstatus = 'storniert', aktualisiert_am = NOW()
                WHERE id = ?
            ")->execute([$auftragId]);

            // Kein Lager-Rückbuchen: vor dem Versand ist nur reserviert, nicht abgebucht
            // (wie AuftragService::stornieren). Früher stand hier ein Bestand-Plus auf Lager 1
            // mit nicht existierenden Spalten — jede Stornierung mit Positionen brach ab.
            $this->auftragRepo->schliesseReservierungen($auftragId);

            $this->db->prepare("
                INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
                VALUES (?, 'stornierung', ?, ?)
            ")->execute([$auftragId, $email, $ausloeser]);

            $this->db->commit();

            $grundText = $ausloeser === 'cronjob'
                ? 'Automatisch storniert (30 Tage unbezahlt — Mahnwesen-Cronjob)'
                : 'Manuell storniert (Mahnwesen-Dashboard)';
            $this->auftragRepo->logStatus($auftragId, [
                'lieferstatus'   => [$auftrag['lieferstatus'], 'storniert'],
                'zahlungsstatus' => [$auftrag['zahlungsstatus'], 'storniert'],
            ], $grundText, $benutzerId);
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => 'Stornierung fehlgeschlagen: ' . $e->getMessage()];
        }

        $mailFehler = null;
        if ($email) {
            try {
                (new Mailer())->sendeTemplate(
                    empfaenger:  $email,
                    betreff:     'Ihr Auftrag ' . $auftrag['auftrag_nr'] . ' wurde storniert',
                    templatePfad: 'mails/mahnwesen/stornierung.html.twig',
                    variablen: [
                        'kunde_name'    => $kundeName,
                        'auftrag_nummer'=> $auftrag['auftrag_nr'],
                        'auftrag_datum' => date('d.m.Y', strtotime($auftrag['erstellt_am'])),
                        'betrag'        => number_format((float)$auftrag['bruttobetrag'], 2, ',', '.'),
                        'firma_email'   => $firma['email'] ?? '',
                    ]
                );
            } catch (Throwable $e) {
                $mailFehler = $e->getMessage();
            }
        }

        Logger::log('mahnwesen.stornierung', 'auftraege', $auftragId,
            ['nummer' => $auftrag['auftrag_nr'], 'ausloeser' => $ausloeser], $benutzerId);

        return ['erfolg' => true, 'mail_gesendet' => $email && !$mailFehler, 'mail_fehler' => $mailFehler];
    }

}
