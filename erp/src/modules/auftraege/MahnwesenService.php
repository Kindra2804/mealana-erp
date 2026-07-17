<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/../../core/logger.php';
require_once __DIR__ . '/AuftragRepository.php';

/**
 * MahnwesenService – Erinnerung/Stornierung für überfällige Aufträge.
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

        $this->db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
            VALUES (?, 'erinnerung', ?, ?)
        ")->execute([$auftragId, $email, $ausloeser]);

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
                        'betrag'        => number_format((float)$auftrag['bruttobetrag'], 2, ',', '.'),
                        'zahlungsart'   => 'Rechnung',
                        'faellig_am'    => date('d.m.Y', strtotime($auftrag['erstellt_am'] . ' +30 days')),
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

        ['name' => $kundeName, 'email' => $email] = $this->kundenDaten($auftrag);
        $firma = $this->db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE auftraege
                SET lieferstatus = 'storniert', zahlungsstatus = 'storniert', aktualisiert_am = NOW()
                WHERE id = ?
            ")->execute([$auftragId]);

            $positionen = $this->db->prepare("SELECT artikel_id, menge FROM auftrag_positionen WHERE auftrag_id = ? AND artikel_id IS NOT NULL");
            $positionen->execute([$auftragId]);
            foreach ($positionen->fetchAll(PDO::FETCH_ASSOC) as $pos) {
                $this->db->prepare("
                    UPDATE lagerbestand SET bestand = bestand + ? WHERE artikel_id = ? AND lager_id = 1
                ")->execute([$pos['menge'], $pos['artikel_id']]);
                $this->db->prepare("
                    INSERT INTO lager_bewegungen (artikel_id, lager_id, typ, menge, referenz_typ, referenz_id, erstellt_am)
                    VALUES (?, 1, 'eingang', ?, 'auftrag', ?, NOW())
                ")->execute([$pos['artikel_id'], $pos['menge'], $auftragId]);
            }

            $this->db->prepare("
                INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
                VALUES (?, 'stornierung', ?, ?)
            ")->execute([$auftragId, $email, $ausloeser]);

            $this->db->commit();

            $grundText = $ausloeser === 'cronjob'
                ? 'Automatisch storniert (30 Tage unbezahlt — Mahnwesen-Cronjob)'
                : 'Manuell storniert (Mahnwesen-Dashboard)';
            $this->auftragRepo->logStatus($auftragId,
                ['zahlungsstatus' => 'storniert', 'lieferstatus' => 'storniert'],
                $grundText, $benutzerId
            );
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

    /**
     * Nur der Hinweis-Log-Eintrag für Rechnung 30+ Tage (kein Storno) — bleibt
     * Cronjob-exklusiv, dafuer gibt es keinen manuellen Button (der Mensch
     * entscheidet in diesem Fall direkt ueber "Stornieren?" oder Nichtstun).
     */
    public function rechnungHinweis(int $auftragId, int $benutzerId): array
    {
        $auftrag = $this->ladeAuftrag($auftragId);
        if (!$auftrag) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden'];

        ['email' => $email] = $this->kundenDaten($auftrag);

        $this->db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
            VALUES (?, 'hinweis', ?, 'cronjob')
        ")->execute([$auftragId, $email]);

        $this->auftragRepo->logStatus($auftragId, [],
            'Rechnung 30+ Tage unbezahlt — bitte manuell prüfen (kein Auto-Storno bei Rechnungszahlern)',
            $benutzerId
        );

        Logger::log('mahnwesen.rechnung_ueberfaellig', 'auftraege', $auftragId,
            ['nummer' => $auftrag['auftrag_nr']], $benutzerId);

        return ['erfolg' => true];
    }
}
