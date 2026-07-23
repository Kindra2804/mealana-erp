<?php

require_once __DIR__ . '/../../core/database.php';

/**
 * StatistikRepository – Auswertungen für Verkauf (Topseller/Umsatz-Zeitverlauf/Marge/Jahresvergleich).
 *
 * Läuft bewusst NUR über `auftraege`/`auftrag_positionen`, nicht zusätzlich über
 * `kassen_bons`/`kassen_bon_positionen` -- jeder Kassenverkauf wird beim Bon-Speichern
 * bereits 1:1 als eigener Auftrag (kanal='kasse') gespiegelt (siehe KassenService::erstelleBon()),
 * dadurch ist `auftraege` die einzige Quelle, die Kasse UND Online (kanal='woocommerce')
 * einheitlich abdeckt -- keine Doppelzählung, keine zwei Abfragen pro Auswertung.
 */
class StatistikRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function kanalBedingung(?string $kanal): string
    {
        return match ($kanal) {
            'kasse'   => "AND a.kanal = 'kasse'",
            'online'  => "AND a.kanal = 'woocommerce'",
            'manuell' => "AND a.kanal = 'manuell'",
            default   => '',
        };
    }

    /** Meistverkaufte Artikel im Zeitraum nach Menge (Umsatz als Zusatzinfo). */
    public function findTopseller(string $von, string $bis, ?string $kanal, int $limit = 15): array
    {
        $kanalSql = $this->kanalBedingung($kanal);
        $stmt = $this->db->prepare("
            SELECT
                art.id AS artikel_id,
                art.artikelnummer,
                art.name,
                SUM(p.menge) AS menge,
                SUM(p.gesamtpreis_netto * (1 + p.steuer_prozent / 100)) AS umsatz_brutto
            FROM auftrag_positionen p
            JOIN auftraege a ON a.id = p.auftrag_id
            JOIN artikel art ON art.id = p.artikel_id
            WHERE a.zahlungsstatus != 'storniert'
              AND a.erstellt_am BETWEEN :von AND :bis
              $kanalSql
            GROUP BY art.id, art.artikelnummer, art.name
            ORDER BY menge DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':von', $von);
        $stmt->bindValue(':bis', $bis);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Umsatz je Periode (Tag oder Monat, siehe $granularitaet), nach Kanal getrennt
     * für den Zeitverlauf-Chart. Nutzt auftraege.bruttobetrag direkt (Auftragsebene,
     * nicht Positionsebene) -- gleiche Basis wie dashboard.php's Umsatz-Kacheln.
     * Dritter Kanal 'manuell' (Telefon-/Laden-Bestellungen ohne Kassenbon) bewusst
     * separat ausgewiesen statt in Kasse/Online unterzumischen -- echter Fund beim
     * Testen: ohne diesen Kanal klaffte eine Lücke zwischen umsatz_gesamt und der
     * Summe der Einzelbalken.
     */
    public function findUmsatzZeitverlauf(string $von, string $bis, ?string $kanal, string $granularitaet): array
    {
        $format = $granularitaet === 'monat' ? '%Y-%m' : '%Y-%m-%d';
        $kanalSql = $this->kanalBedingung($kanal);
        $stmt = $this->db->prepare("
            SELECT
                DATE_FORMAT(a.erstellt_am, '$format') AS periode,
                SUM(CASE WHEN a.kanal = 'kasse' THEN a.bruttobetrag ELSE 0 END) AS umsatz_kasse,
                SUM(CASE WHEN a.kanal = 'woocommerce' THEN a.bruttobetrag ELSE 0 END) AS umsatz_online,
                SUM(CASE WHEN a.kanal = 'manuell' THEN a.bruttobetrag ELSE 0 END) AS umsatz_manuell,
                SUM(a.bruttobetrag) AS umsatz_gesamt
            FROM auftraege a
            WHERE a.zahlungsstatus != 'storniert'
              AND a.erstellt_am BETWEEN :von AND :bis
              $kanalSql
            GROUP BY periode
            ORDER BY periode
        ");
        $stmt->execute(['von' => $von, 'bis' => $bis]);
        return $stmt->fetchAll();
    }

    /**
     * Deckungsbeitrag je Artikelgruppe im Zeitraum. EK-Quelle: Standard-Lieferant
     * (artikel_lieferanten.standard_lieferant=1), gleiche Konvention wie die
     * Marge-Berechnung im Preise-Tab (project_preise.md) -- kein Standard-Lieferant
     * gesetzt → netto_ek fließt als 0 ein (Marge dann bewusst zu hoch, nicht "–",
     * weil hier über viele Artikel aggregiert wird statt einzeln angezeigt).
     */
    public function findMargeProGruppe(string $von, string $bis, ?string $kanal): array
    {
        $kanalSql = $this->kanalBedingung($kanal);
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(g.id, 0) AS gruppe_id,
                COALESCE(g.name, 'Ohne Artikelgruppe') AS gruppe,
                SUM(p.gesamtpreis_netto) AS umsatz_netto,
                SUM(p.menge * COALESCE(al.netto_ek, 0)) AS ek_gesamt
            FROM auftrag_positionen p
            JOIN auftraege a ON a.id = p.auftrag_id
            JOIN artikel art ON art.id = p.artikel_id
            LEFT JOIN artikel_gruppen g ON g.id = art.artikel_gruppe_id
            LEFT JOIN artikel_lieferanten al ON al.artikel_id = art.id AND al.standard_lieferant = 1
            WHERE a.zahlungsstatus != 'storniert'
              AND a.erstellt_am BETWEEN :von AND :bis
              $kanalSql
            GROUP BY gruppe_id, gruppe
            ORDER BY (SUM(p.gesamtpreis_netto) - SUM(p.menge * COALESCE(al.netto_ek, 0))) DESC
        ");
        $stmt->execute(['von' => $von, 'bis' => $bis]);
        return $stmt->fetchAll();
    }

    /** Aufträge-Anzahl + Umsatz je Jahr, für den Jahresvergleich. */
    public function findJahresvergleich(int $jahre = 3): array
    {
        $stmt = $this->db->prepare("
            SELECT
                YEAR(a.erstellt_am) AS jahr,
                COUNT(*) AS anzahl,
                SUM(a.bruttobetrag) AS umsatz
            FROM auftraege a
            WHERE a.zahlungsstatus != 'storniert'
              AND YEAR(a.erstellt_am) > YEAR(CURDATE()) - :jahre
            GROUP BY jahr
            ORDER BY jahr
        ");
        $stmt->execute(['jahre' => $jahre]);
        return $stmt->fetchAll();
    }
}
