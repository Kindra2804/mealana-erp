<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../auftraege/AuftragService.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/../kunden/KundenService.php';
require_once __DIR__ . '/../kunden/KundenRepository.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * MietRechnungService – Fachmiete verrechnen (Partner-Abrechnung Schritt 4)
 *
 * Klärung Babsi/Jacky 2026-10-26: Fachmiete ist eine ganz normale eigene Rechnung an den
 * Partner, NIE mit Verkäufen verrechnet. Monatlich im Voraus; der Folgemonat wird ab
 * "fachmiete_vorlauf_tage" (2) Tage vor Monatsende verrechnet. Mietbeginn immer Monatserster,
 * Mietende immer Monatsletzter → keine Anteile (siehe MietfachService).
 *
 * Je Partner und Monat ein Auftrag (Kunde = partner.kunde_id, wird bei Bedarf aus den
 * Partnerdaten angelegt) mit einer Zeile je Fach (Artikel FACHMIETE, Gruppe Fachmiete =
 * Mieterlöse) + Vollrechnung. miet_rechnungen hält fest "Vertrag X, Monat Y verrechnet" —
 * UNIQUE(vertrag_id, periode) verhindert Doppelrechnungen. Verpasste Monate werden
 * beim nächsten Lauf nachgeholt.
 */
class MietRechnungService
{
    private PDO $db;

    private const MONATE = [1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                            'August', 'September', 'Oktober', 'November', 'Dezember'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Letzter Monat (JJJJ-MM), der am Stichtag schon verrechnet werden darf */
    public function zielMonat(?string $stichtag = null): string
    {
        $vorlauf = (int)($this->db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'fachmiete_vorlauf_tage'")->fetchColumn() ?: 2);
        $tag = new DateTimeImmutable($stichtag ?: 'today');
        // Vorlauf 2: ab dem 29.10. (31 − 2) zählt schon der November → Stichtag + 3 Tage
        return $tag->modify('+' . ($vorlauf + 1) . ' days')->format('Y-m');
    }

    /**
     * Was beim Lauf am Stichtag verrechnet würde: [['vertrag' => …, 'periode' => 'JJJJ-MM'], …]
     * Alle Monate ab Mietbeginn bis Zielmonat (bzw. Mietende), die noch keine Mietrechnung haben.
     */
    public function offene(?string $stichtag = null, ?int $partnerId = null): array
    {
        $ziel = $this->zielMonat($stichtag);
        $sql = "
            SELECT v.*, f.fach_bezeichnung, p.name AS partner_name
            FROM mietfach_mietvertraege v
            JOIN mietfaecher f ON f.id = v.mietfach_id
            JOIN partner p ON p.id = v.partner_id
            WHERE DATE_FORMAT(v.mietbeginn, '%Y-%m') <= ?";
        $p = [$ziel];
        if ($partnerId) { $sql .= " AND v.partner_id = ?"; $p[] = $partnerId; }
        $stmt = $this->db->prepare($sql . " ORDER BY p.name, f.fach_bezeichnung");
        $stmt->execute($p);
        $vertraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $schon = $this->db->prepare("SELECT periode FROM miet_rechnungen WHERE vertrag_id = ?");
        $offen = [];
        foreach ($vertraege as $v) {
            $schon->execute([$v['id']]);
            $erledigt = array_flip($schon->fetchAll(PDO::FETCH_COLUMN));
            $bis = $v['mietende'] ? min($ziel, substr($v['mietende'], 0, 7)) : $ziel;
            for ($m = substr($v['mietbeginn'], 0, 7); $m <= $bis; $m = date('Y-m', strtotime($m . '-01 +1 month'))) {
                if (!isset($erledigt[$m])) $offen[] = ['vertrag' => $v, 'periode' => $m];
            }
        }
        return $offen;
    }

    /**
     * Verrechnet alle offenen Monate (je Partner + Monat eine Rechnung).
     * Rückgabe: ['erfolg', 'rechnungen' => [[partner, periode, rechnung_nr, auftrag_id, brutto]], 'fehler' => [...]]
     */
    public function verrechnen(int $benutzerId, ?string $stichtag = null, ?int $partnerId = null): array
    {
        $gruppen = [];
        foreach ($this->offene($stichtag, $partnerId) as $o) {
            $gruppen[$o['vertrag']['partner_id'] . '|' . $o['periode']][] = $o['vertrag'];
        }
        $artikelId = (int)$this->db->query("SELECT id FROM artikel WHERE artikelnummer = 'FACHMIETE' LIMIT 1")->fetchColumn();
        if (!$artikelId) return ['erfolg' => false, 'fehler' => ['Artikel FACHMIETE fehlt (Migration 208).'], 'rechnungen' => []];

        $ergebnis = ['erfolg' => true, 'rechnungen' => [], 'fehler' => []];
        foreach ($gruppen as $schluessel => $vertraege) {
            [$pid, $periode] = explode('|', $schluessel);
            try {
                $ergebnis['rechnungen'][] = $this->rechnungFuerPartner((int)$pid, $periode, $vertraege, $artikelId, $benutzerId);
            } catch (Throwable $e) {
                $ergebnis['fehler'][] = $vertraege[0]['partner_name'] . ' ' . $periode . ': ' . $e->getMessage();
                Logger::log('partner.fachmiete_fehler', 'partner', (int)$pid, ['periode' => $periode, 'fehler' => $e->getMessage()], $benutzerId, 'error');
            }
        }
        $ergebnis['erfolg'] = !$ergebnis['fehler'];
        return $ergebnis;
    }

    private function rechnungFuerPartner(int $partnerId, string $periode, array $vertraege, int $artikelId, int $benutzerId): array
    {
        $partner = (new PartnerRepository())->findById($partnerId);
        $kundeId = $this->kundeSicherstellen($partner, $benutzerId);
        $monat   = self::MONATE[(int)substr($periode, 5, 2)] . ' ' . substr($periode, 0, 4);

        $positionen = [];
        foreach ($vertraege as $v) {
            $positionen[] = [
                'artikel_id'        => $artikelId,
                'bezeichnung'       => 'Fachmiete ' . $v['fach_bezeichnung'] . ' · ' . $monat,
                'menge'             => 1,
                'einzelpreis_netto' => (float)$v['mietbetrag_monatlich'],
                'steuer_prozent'    => (float)$v['mwst_satz'],
            ];
        }

        [$kundeSnapshot, $adresse] = $this->kundenSnapshot($kundeId);

        $this->db->beginTransaction();
        try {
            $auftragSvc = new AuftragService();
            $a = $auftragSvc->anlegen([
                'kunden_id'                 => $kundeId,
                'kunden_snapshot'           => $kundeSnapshot,
                'rechnungsadresse_snapshot' => $adresse,
                'kanal'                     => 'manuell',
                'zahlungsart'               => 'rechnung',
                'lieferart'                 => 'abholung',
                'notiz_intern'              => 'Fachmiete ' . $monat . ' (automatisch verrechnet)',
            ], $positionen, $benutzerId);
            if (!$a['erfolg']) throw new RuntimeException(implode(' ', $a['fehler']));
            $auftragId = (int)$a['id'];

            // Leistung, keine Ware: als erbracht markieren (sonst "Teilrechnung" + "noch ausständig"),
            // Miete ist ein Nettopreis, und nie in Packplatz/offene Lieferungen
            $this->db->prepare("UPDATE auftrag_positionen SET menge_geliefert = menge, menge_abgeholt = menge, preisbasis = 'netto' WHERE auftrag_id = ?")
                ->execute([$auftragId]);
            $auftragSvc->statusAktualisieren($auftragId, ['lieferstatus' => 'abgeschlossen'], 'Fachmiete — keine Lieferung', $benutzerId);

            $dokSvc = new DokumentService();
            $r = $dokSvc->erstelleVollrechnung($auftragId, $benutzerId);
            if (empty($r['erfolg'])) throw new RuntimeException($r['fehler'] ?? 'Rechnung konnte nicht erstellt werden.');
            // Leistungsdatum = Beginn des Mietmonats (nicht der Erstellungstag) → PDF neu rendern
            $this->db->prepare("UPDATE rechnungen SET leistungsdatum = ? WHERE id = ?")->execute([$periode . '-01', $r['rechnung_id']]);
            $dokSvc->renderRechnung((int)$r['rechnung_id']);

            $ins = $this->db->prepare("
                INSERT INTO miet_rechnungen (vertrag_id, fach_id, partner_id, auftrag_id, rechnung_id, periode,
                                             betrag_netto, mwst_satz, betrag_brutto, rechnungs_nr, erstellt_am, faellig_am)
                SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, r.rechnung_nr, CURDATE(), r.faellig_am FROM rechnungen r WHERE r.id = ?
            ");
            foreach ($vertraege as $v) {
                $netto = round((float)$v['mietbetrag_monatlich'], 2);
                $ins->execute([$v['id'], $v['mietfach_id'], $partnerId, $auftragId, $r['rechnung_id'], $periode,
                               $netto, (float)$v['mwst_satz'], round($netto * (1 + (float)$v['mwst_satz'] / 100), 2), $r['rechnung_id']]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        Logger::log('partner.fachmiete', 'auftraege', $auftragId, ['partner_id' => $partnerId, 'periode' => $periode, 'rechnung_nr' => $r['rechnung_nr']], $benutzerId);
        return [
            'partner'     => $partner['name'],
            'periode'     => $periode,
            'monat'       => $monat,
            'faecher'     => count($vertraege),
            'auftrag_id'  => $auftragId,
            'rechnung_nr' => $r['rechnung_nr'],
            'brutto'      => (float)$r['bruttobetrag'],
        ];
    }

    /**
     * Debitor für die Mietrechnung: verknüpfter Kunde, sonst bestehender Kunde mit derselben
     * E-Mail, sonst neu aus den Partnerdaten (Firma, Adresse, UID). Wird am Partner gemerkt.
     */
    private function kundeSicherstellen(array $partner, int $benutzerId): int
    {
        if (!empty($partner['kunde_id'])) return (int)$partner['kunde_id'];

        $kundeId = null;
        if (!empty($partner['email'])) {
            $vorh = (new KundenRepository())->findByEmailHash($partner['email']);
            if ($vorh) $kundeId = (int)$vorh['id'];
        }
        if (!$kundeId) {
            if (empty($partner['strasse']) || empty($partner['ort'])) {
                throw new RuntimeException('Adresse des Partners fehlt — bitte beim Partner eintragen (steht auf der Rechnung).');
            }
            $k = (new KundenService())->anlegen([
                'ist_firma'            => 1,
                'firmenname'           => $partner['name'],
                'email'                => $partner['email'],
                'telefon'              => $partner['telefon'],
                'uid_nummer'           => $partner['uid_nummer'],
                'strasse'              => $partner['strasse'],
                'plz'                  => $partner['plz'],
                'ort'                  => $partner['ort'],
                'land'                 => $partner['land'] ?: 'AT',
                'notiz'                => 'Automatisch angelegt für die Fachmiete (Partner #' . $partner['id'] . ')',
                'kundengruppe_id'      => null,
                'zahlungsbedingung_id' => null,
                'standardzahlungsart'  => 'rechnung',
                'kreditlimit'          => null,
            ], $benutzerId);
            if (!$k['erfolg']) throw new RuntimeException('Kunde: ' . implode(' ', $k['fehler']));
            $kundeId = (int)$k['id'];
        }
        $this->db->prepare("UPDATE partner SET kunde_id = ? WHERE id = ?")->execute([$kundeId, $partner['id']]);
        return $kundeId;
    }

    /**
     * Kunden- und Rechnungsadress-Snapshot für den Auftrag (wie HaendlerService) —
     * ohne Snapshot bliebe das Empfängerfeld der Rechnung leer.
     */
    private function kundenSnapshot(int $kundeId): array
    {
        $ks    = new KundenService();
        $kunde = $ks->getById($kundeId) ?: [];
        $name  = !empty($kunde['firmenname']) ? $kunde['firmenname'] : trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
        $snapshot = [
            'name' => $name, 'vorname' => $kunde['vorname'] ?? '', 'nachname' => $kunde['nachname'] ?? '',
            'firma' => $kunde['firmenname'] ?? '', 'email' => $kunde['email'] ?? '', 'uid_nummer' => $kunde['uid_nummer'] ?? '',
            'kundennummer' => $kunde['kundennummer'] ?? '',
        ];
        $adressen = $ks->getAdressen($kundeId);
        $ad = null;
        foreach ($adressen as $x) { if ($x['adresstyp'] === 'rechnung') { $ad = $x; break; } }
        $ad ??= $adressen[0] ?? null;
        $adresse = $ad ? [
            'firmenname' => $ad['firma'] ?: ($kunde['firmenname'] ?? ''), 'vorname' => $ad['vorname'] ?? '', 'nachname' => $ad['nachname'] ?? '',
            'strasse' => trim(($ad['strasse'] ?? '') . ' ' . ($ad['hausnummer'] ?? '')),
            'plz' => $ad['plz'] ?? '', 'ort' => $ad['ort'] ?? '', 'land' => $ad['land'] ?? 'AT',
        ] : null;
        return [$snapshot, $adresse];
    }

    /** Bisherige Mietrechnungen (für die Übersicht), neueste zuerst */
    public function getVerrechnet(?int $partnerId = null, int $limit = 50): array
    {
        $sql = "
            SELECT mr.periode, mr.rechnungs_nr, mr.auftrag_id, mr.betrag_brutto, mr.faellig_am,
                   f.fach_bezeichnung, p.name AS partner_name, p.id AS partner_id, a.zahlungsstatus
            FROM miet_rechnungen mr
            JOIN mietfaecher f ON f.id = mr.fach_id
            JOIN partner p ON p.id = mr.partner_id
            LEFT JOIN auftraege a ON a.id = mr.auftrag_id
            WHERE mr.vertrag_id IS NOT NULL" . ($partnerId ? " AND mr.partner_id = " . (int)$partnerId : '') . "
            ORDER BY mr.periode DESC, p.name, f.fach_bezeichnung
            LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
