<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../auftraege/AuftragService.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';
require_once __DIR__ . '/MietRechnungService.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * PartnerVersandService – Versandkosten an Online-Partner weiterverrechnen (Migration 238)
 *
 * Jacky 2026-11-13: Der Kunde zahlt den Versand auf der Rechnung des Partners (z.B. R-INDRA-…),
 * Versand/Porto gehört aber der KG. Keine Gegenverrechnung mit dem Partner-Konto, sondern —
 * wie die Fachmiete — eine ganz normale KG-Rechnung an den Partner (R-Kreis, 20 %):
 * monatlich, für den Vormonat. Betrag = Versand brutto aller Partner-Rechnungen des Monats
 * minus Versand auf Gutschriften (Rechnungskorrekturen) desselben Monats.
 *
 * Je Partner und Monat ein Auftrag (Kunde = partner.kunde_id, siehe MietRechnungService::
 * kundeSicherstellen) mit einer Zeile je Partner-Rechnung bzw. -Gutschrift (Artikel
 * VERSAND-PARTNER, Gruppe Versandkosten). partner_versand_rechnungen UNIQUE(partner, periode)
 * verhindert Doppelrechnungen; verpasste Monate werden beim nächsten Lauf nachgeholt.
 * Monate ohne Versand (oder mit Summe ≤ 0) bekommen keine Rechnung.
 */
class PartnerVersandService
{
    private PDO $db;

    private const MONATE = [1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                            'August', 'September', 'Oktober', 'November', 'Dezember'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Offene Monate (nur abgeschlossene Monate vor dem Stichtag-Monat), noch ohne Versandrechnung:
     * [['partner_id', 'partner_name', 'periode' => 'JJJJ-MM', 'betrag' => brutto, 'zeilen' => [...]], …]
     */
    public function offene(?string $stichtag = null, ?int $partnerId = null): array
    {
        $bisExkl = (new DateTimeImmutable($stichtag ?: 'today'))->format('Y-m'); // laufender Monat zählt noch nicht
        $sql = "
            SELECT 'rechnung' AS art, a.partner_id, pa.name AS partner_name, DATE_FORMAT(r.erstellt_am, '%Y-%m') AS periode,
                   r.rechnung_nr AS nr, a.auftrag_nr, r.versandkosten_brutto AS betrag
            FROM rechnungen r
            JOIN auftraege a ON a.id = r.auftrag_id
            JOIN partner pa ON pa.id = a.partner_id
            WHERE r.versandkosten_brutto <> 0 AND DATE_FORMAT(r.erstellt_am, '%Y-%m') < ?" . ($partnerId ? " AND a.partner_id = ?" : '') . "
            UNION ALL
            SELECT 'gutschrift', a.partner_id, pa.name, DATE_FORMAT(g.erstellt_am, '%Y-%m'),
                   g.gutschrift_nr, a.auftrag_nr, -g.versandkosten_brutto
            FROM gutschriften g
            JOIN auftraege a ON a.id = g.auftrag_id
            JOIN partner pa ON pa.id = a.partner_id
            WHERE g.versandkosten_brutto <> 0 AND DATE_FORMAT(g.erstellt_am, '%Y-%m') < ?" . ($partnerId ? " AND a.partner_id = ?" : '') . "
            ORDER BY partner_name, periode, nr";
        $p = $partnerId ? [$bisExkl, $partnerId, $bisExkl, $partnerId] : [$bisExkl, $bisExkl];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($p);

        $erledigt = array_flip($this->db->query("SELECT CONCAT(partner_id, '|', periode) FROM partner_versand_rechnungen")->fetchAll(PDO::FETCH_COLUMN));
        $gruppen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $key = $z['partner_id'] . '|' . $z['periode'];
            if (isset($erledigt[$key])) continue;
            $gruppen[$key] ??= ['partner_id' => (int)$z['partner_id'], 'partner_name' => $z['partner_name'],
                                'periode' => $z['periode'], 'betrag' => 0.0, 'zeilen' => []];
            $gruppen[$key]['zeilen'][] = $z;
            $gruppen[$key]['betrag']   = round($gruppen[$key]['betrag'] + (float)$z['betrag'], 2);
        }
        return array_values(array_filter($gruppen, fn($g) => $g['betrag'] > 0));
    }

    /**
     * Verrechnet alle offenen Monate (je Partner + Monat eine Rechnung).
     * Rückgabe: ['erfolg', 'rechnungen' => [[partner, periode, monat, auftrag_id, rechnung_nr, brutto]], 'fehler' => [...]]
     */
    public function verrechnen(int $benutzerId, ?string $stichtag = null, ?int $partnerId = null): array
    {
        $artikel = $this->db->query("
            SELECT a.id, COALESCE(s.satz, 20) AS satz FROM artikel a
            LEFT JOIN steuerklassen s ON s.id = a.steuerklasse_id
            WHERE a.artikelnummer = 'VERSAND-PARTNER' LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
        if (!$artikel) return ['erfolg' => false, 'fehler' => ['Artikel VERSAND-PARTNER fehlt (Migration 238).'], 'rechnungen' => []];

        $ergebnis = ['erfolg' => true, 'rechnungen' => [], 'fehler' => []];
        foreach ($this->offene($stichtag, $partnerId) as $o) {
            try {
                $ergebnis['rechnungen'][] = $this->rechnungFuerPartner($o, (int)$artikel['id'], (float)$artikel['satz'], $benutzerId);
            } catch (Throwable $e) {
                $ergebnis['fehler'][] = $o['partner_name'] . ' ' . $o['periode'] . ': ' . $e->getMessage();
                Logger::log('partner.versand_fehler', 'partner', $o['partner_id'], ['periode' => $o['periode'], 'fehler' => $e->getMessage()], $benutzerId, 'error');
            }
        }
        $ergebnis['erfolg'] = !$ergebnis['fehler'];
        return $ergebnis;
    }

    private function rechnungFuerPartner(array $o, int $artikelId, float $satz, int $benutzerId): array
    {
        $partner = (new PartnerRepository())->findById($o['partner_id']);
        $miete   = new MietRechnungService();
        $kundeId = $miete->kundeSicherstellen($partner, $benutzerId);
        [$kundeSnapshot, $adresse] = $miete->kundenSnapshot($kundeId);
        $monat   = self::MONATE[(int)substr($o['periode'], 5, 2)] . ' ' . substr($o['periode'], 0, 4);

        // Bruttobetrag des Kunden = Bruttobetrag der KG-Rechnung → Netto mit 4 Stellen zurückrechnen
        $positionen = [];
        foreach ($o['zeilen'] as $z) {
            $positionen[] = [
                'artikel_id'        => $artikelId,
                'bezeichnung'       => ($z['art'] === 'gutschrift' ? 'Korrektur Versandkosten ' : 'Versandkosten ')
                                       . $z['nr'] . ' (Auftrag ' . $z['auftrag_nr'] . ')',
                'menge'             => 1,
                'einzelpreis_netto' => round((float)$z['betrag'] / (1 + $satz / 100), 4),
                'steuer_prozent'    => $satz,
            ];
        }

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
                'notiz_intern'              => 'Versandkosten ' . $monat . ' an ' . $partner['name'] . ' (automatisch verrechnet)',
            ], $positionen, $benutzerId);
            if (!$a['erfolg']) throw new RuntimeException(implode(' ', $a['fehler']));
            $auftragId = (int)$a['id'];

            // Leistung, keine Ware (wie Fachmiete): erbracht, Nettopreis, nie in Packplatz/offene Lieferungen
            $this->db->prepare("UPDATE auftrag_positionen SET menge_geliefert = menge, menge_abgeholt = menge, preisbasis = 'netto' WHERE auftrag_id = ?")
                ->execute([$auftragId]);
            $auftragSvc->statusAktualisieren($auftragId, ['lieferstatus' => 'abgeschlossen'], 'Versandkosten-Weiterverrechnung — keine Lieferung', $benutzerId);

            $dokSvc = new DokumentService();
            $r = $dokSvc->erstelleVollrechnung($auftragId, $benutzerId); // normaler R-Kreis der KG
            if (empty($r['erfolg'])) throw new RuntimeException($r['fehler'] ?? 'Rechnung konnte nicht erstellt werden.');

            $this->db->prepare("
                INSERT INTO partner_versand_rechnungen (partner_id, periode, betrag_brutto, auftrag_id, rechnung_id)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$o['partner_id'], $o['periode'], (float)$r['bruttobetrag'], $auftragId, $r['rechnung_id']]);

            // Leistungsdatum = Monatsende des verrechneten Monats → PDF neu rendern (erst jetzt erkennt
            // DokumentService die Rechnung als Partner-Leistung: ohne Versandart/Abholhinweis)
            $this->db->prepare("UPDATE rechnungen SET leistungsdatum = LAST_DAY(?) WHERE id = ?")->execute([$o['periode'] . '-01', $r['rechnung_id']]);
            $dokSvc->renderRechnung((int)$r['rechnung_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        Logger::log('partner.versand_rechnung', 'auftraege', $auftragId, ['partner_id' => $o['partner_id'], 'periode' => $o['periode'], 'rechnung_nr' => $r['rechnung_nr']], $benutzerId);
        return [
            'partner'     => $partner['name'],
            'periode'     => $o['periode'],
            'monat'       => $monat,
            'auftrag_id'  => $auftragId,
            'rechnung_nr' => $r['rechnung_nr'],
            'brutto'      => (float)$r['bruttobetrag'],
        ];
    }

    /** Bisherige Versandrechnungen an den Partner, neueste zuerst */
    public function getVerrechnet(int $partnerId, int $limit = 24): array
    {
        $stmt = $this->db->prepare("
            SELECT v.periode, v.betrag_brutto, v.auftrag_id, r.rechnung_nr, r.faellig_am, a.zahlungsstatus
            FROM partner_versand_rechnungen v
            JOIN rechnungen r ON r.id = v.rechnung_id
            LEFT JOIN auftraege a ON a.id = v.auftrag_id
            WHERE v.partner_id = ?
            ORDER BY v.periode DESC
            LIMIT " . (int)$limit);
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
