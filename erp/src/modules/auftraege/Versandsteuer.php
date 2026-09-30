<?php

require_once __DIR__ . '/Positionsrechnung.php';

/**
 * Versandsteuer – Steuersatz und Netto/MwSt-Aufteilung der Versandkosten.
 *
 * Regel (Jacky 2026-09-30, AT: Versand als Nebenleistung teilt den Steuersatz der
 * überwiegenden Leistung): der Versand trägt den Steuersatz, auf den der größte
 * Bruttobetrag der Positionen entfällt -- also praktisch immer 20 %, außer die
 * 10-%-Artikel überwiegen. Bei Gleichstand gilt der höhere Satz. 0-%-Positionen
 * (z.B. Gutscheine) zählen nicht mit; gibt es nur solche, gelten 20 %.
 *
 * Versandkosten werden im ERP immer als BRUTTObetrag geführt (auftraege.versandkosten).
 * Einzige Stelle für diese Rechnung -- Auftrag (AuftragService) und Dokumente
 * (DokumentService) nutzen dieselbe Methode, damit Auftragsbetrag und Rechnung nie
 * auseinanderlaufen.
 */
class Versandsteuer
{
    public const STANDARD_SATZ = 20.0;

    /** @param array $positionen je mit steuer_prozent + gesamtpreis_netto */
    public static function satz(array $positionen): float
    {
        $bruttoProSatz = [];
        foreach ($positionen as $p) {
            $satz = (float)($p['steuer_prozent'] ?? 0);
            if ($satz <= 0) continue;
            $key = number_format($satz, 2, '.', '');
            $brutto = isset($p['einzelpreis_netto'], $p['menge'])
                ? Positionsrechnung::ausPosition($p)['brutto']
                : (float)($p['gesamtpreis_netto'] ?? 0) * (1 + $satz / 100);
            $bruttoProSatz[$key] = ($bruttoProSatz[$key] ?? 0) + $brutto;
        }
        if (!$bruttoProSatz) return self::STANDARD_SATZ;

        $besterSatz = null;
        $besterBetrag = -1.0;
        foreach ($bruttoProSatz as $key => $betrag) {
            $satz = (float)$key;
            if ($betrag > $besterBetrag + 0.004 || (abs($betrag - $besterBetrag) <= 0.004 && $satz > $besterSatz)) {
                $besterSatz = $satz;
                $besterBetrag = $betrag;
            }
        }
        return $besterSatz;
    }

    /** @return array{satz:float, netto:float, steuer:float, brutto:float} */
    public static function aufteilen(float $versandBrutto, array $positionen): array
    {
        $brutto = round($versandBrutto, 2);
        $satz   = self::satz($positionen);
        $netto  = round($brutto / (1 + $satz / 100), 2);
        return ['satz' => $satz, 'netto' => $netto, 'steuer' => round($brutto - $netto, 2), 'brutto' => $brutto];
    }
}
