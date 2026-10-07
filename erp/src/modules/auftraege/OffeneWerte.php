<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../dokumente/DokumentService.php';

/**
 * OffeneWerte – Kennzahlen für die Übersicht "offene Werte" (Belege-Umbau 2026-10-07,
 * Mockup docs/design/belege_spalte_uebersicht_mockup.svg):
 *
 *   bestand          bestellt, noch nicht ausgeliefert   (erwarteter Umsatz, NICHT Buchhaltung)
 *   nicht_verrechnet ausgeliefert, aber auf keinem Beleg (sollte immer 0 sein)
 *   rechnungen_offen verrechnet, noch nicht bezahlt      (offene Posten, davon überfällig)
 *   retoure_offen    Ware zurück, Gutschrift/Einlagerung ausständig
 *
 * Kasse-Spiegel und JTL-Archiv zählen nicht mit. Brutto je Zeile wie Positionsrechnung
 * (Einzel-Brutto auf Cent gerundet × Menge × (1 − Rabatt)).
 *
 * Die SQL-Bedingungen (BEDINGUNG_*) nutzt auch der Belege-Filter der Auftragsliste,
 * damit Kachel und Liste immer dieselben Aufträge meinen.
 */
class OffeneWerte
{
    /** Menge, die das Haus verlassen hat (Abholung: übergeben, ohne "will er nicht") */
    public const AUSGEGEBEN = "IF(a.lieferart = 'abholung', CAST(p.menge_abgeholt AS SIGNED) - CAST(p.menge_retourniert AS SIGNED), p.menge_geliefert)";
    private const BRUTTO_JE_STK = "ROUND(p.einzelpreis_netto * (1 + p.steuer_prozent / 100), 2) * (1 - p.rabatt_prozent / 100)";
    private const BASIS = "a.kanal NOT IN ('kasse', 'jtl_archiv') AND a.lieferstatus <> 'storniert' AND p.menge > 0";

    /** EXISTS-Bedingungen je Kachel (für den Filter in der Auftragsliste, Alias a = auftraege) */
    public const BEDINGUNG_BESTAND = "a.kanal NOT IN ('kasse','jtl_archiv') AND a.lieferstatus NOT IN ('storniert','abgeschlossen')
        AND EXISTS (SELECT 1 FROM auftrag_positionen p WHERE p.auftrag_id = a.id AND p.menge > 0
                    AND p.menge - IF(a.lieferart = 'abholung', p.menge_abgeholt, p.menge_geliefert) > 0)";
    public const BEDINGUNG_NICHT_VERRECHNET = "a.kanal NOT IN ('kasse','jtl_archiv') AND a.lieferstatus <> 'storniert'
        AND EXISTS (SELECT 1 FROM auftrag_positionen p WHERE p.auftrag_id = a.id AND p.menge > 0
                    AND IF(a.lieferart = 'abholung', CAST(p.menge_abgeholt AS SIGNED) - CAST(p.menge_retourniert AS SIGNED), p.menge_geliefert) > p.menge_verrechnet)";
    public const BEDINGUNG_RECHNUNG_OFFEN = "a.kanal NOT IN ('kasse','jtl_archiv') AND a.lieferstatus <> 'storniert'
        AND (SELECT COALESCE(SUM(r.bruttobetrag), 0) FROM rechnungen r WHERE r.auftrag_id = a.id)
          - (SELECT COALESCE(SUM(g.bruttobetrag), 0) FROM gutschriften g WHERE g.auftrag_id = a.id)
          - (SELECT COALESCE(SUM(z.betrag), 0) FROM auftrag_zahlungen z WHERE z.auftrag_id = a.id)
          - (SELECT COALESCE(-SUM(t.betrag), 0) FROM gutschein_transaktionen t WHERE t.auftrag_id = a.id AND t.betrag < 0) > 0.004";

    public static function berechnen(): array
    {
        $db = Database::getInstance();

        $bestand = $db->query("
            SELECT COUNT(DISTINCT a.id) AS anzahl,
                   COALESCE(SUM((p.menge - IF(a.lieferart = 'abholung', p.menge_abgeholt, p.menge_geliefert)) * " . self::BRUTTO_JE_STK . "), 0) AS betrag
            FROM auftrag_positionen p JOIN auftraege a ON a.id = p.auftrag_id
            WHERE " . self::BASIS . " AND a.lieferstatus <> 'abgeschlossen'
              AND p.menge - IF(a.lieferart = 'abholung', p.menge_abgeholt, p.menge_geliefert) > 0
        ")->fetch(PDO::FETCH_ASSOC);

        $nichtVerrechnet = $db->query("
            SELECT COUNT(DISTINCT a.id) AS anzahl,
                   COALESCE(SUM((" . self::AUSGEGEBEN . " - p.menge_verrechnet) * " . self::BRUTTO_JE_STK . "), 0) AS betrag
            FROM auftrag_positionen p JOIN auftraege a ON a.id = p.auftrag_id
            WHERE " . self::BASIS . " AND " . self::AUSGEGEBEN . " > p.menge_verrechnet
        ")->fetch(PDO::FETCH_ASSOC);

        $retoure = $db->query("
            SELECT COUNT(DISTINCT a.id) AS anzahl,
                   COALESCE(SUM(GREATEST(LEAST(p.menge_retourniert, p.menge_verrechnet) - p.menge_gutgeschrieben, 0) * " . self::BRUTTO_JE_STK . "), 0) AS betrag
            FROM auftraege a JOIN auftrag_positionen p ON p.auftrag_id = a.id
            WHERE a.lieferstatus = 'retoure_offen'
        ")->fetch(PDO::FETCH_ASSOC);

        // Offene Rechnungen: Zahlungen je Auftrag in Rechnungsreihenfolge verteilt
        // (wie auf der Rechnung selbst) -> Anzahl offener Rechnungen + überfällige
        $dok = new DokumentService();
        $rechnungen = $db->query("
            SELECT r.id, r.faellig_am
            FROM rechnungen r JOIN auftraege a ON a.id = r.auftrag_id
            WHERE r.storniert = 0 AND a.kanal NOT IN ('kasse', 'jtl_archiv') AND a.lieferstatus <> 'storniert'
              AND a.zahlungsstatus NOT IN ('bezahlt', 'erstattet')
        ")->fetchAll(PDO::FETCH_ASSOC);
        $offen = ['anzahl' => 0, 'betrag' => 0.0, 'ueberfaellig_anzahl' => 0, 'ueberfaellig_betrag' => 0.0];
        foreach ($rechnungen as $r) {
            $o = $dok->zahlungsInfo((int)$r['id'])['offen'];
            if ($o <= 0.004) continue;
            $offen['anzahl']++;
            $offen['betrag'] += $o;
            if ($r['faellig_am'] && $r['faellig_am'] < date('Y-m-d')) {
                $offen['ueberfaellig_anzahl']++;
                $offen['ueberfaellig_betrag'] += $o;
            }
        }

        return [
            'bestand'          => ['anzahl' => (int)$bestand['anzahl'], 'betrag' => round((float)$bestand['betrag'], 2)],
            'nicht_verrechnet' => ['anzahl' => (int)$nichtVerrechnet['anzahl'], 'betrag' => round((float)$nichtVerrechnet['betrag'], 2)],
            'rechnungen_offen' => array_map(fn($v) => is_float($v) ? round($v, 2) : $v, $offen),
            'retoure_offen'    => ['anzahl' => (int)$retoure['anzahl'], 'betrag' => round((float)$retoure['betrag'], 2)],
        ];
    }
}
