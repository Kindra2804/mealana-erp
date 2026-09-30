<?php

/**
 * Positionsrechnung – EINZIGE Stelle für Netto/MwSt/Brutto einer Auftragsposition.
 *
 * Preisbasis BRUTTO (B2C, Jacky 2026-09-30): Der Kunde zahlt Bruttopreise (Shop, Kasse,
 * Etikett). Deshalb wird die Zeile vom Brutto aus gerechnet:
 *   Einzel-Brutto = Einzel-Netto × (1 + Satz), auf Cent gerundet (= Verkaufspreis)
 *   Zeilen-Brutto = Einzel-Brutto × Menge × (1 − Rabatt), auf Cent gerundet
 *   Zeilen-Netto  = Zeilen-Brutto / (1 + Satz), auf Cent gerundet
 *   MwSt          = Zeilen-Brutto − Zeilen-Netto
 * Vorher wurde netto-basiert gerechnet (Netto runden, MwSt aufschlagen) -- 3 × 7,25 €
 * ergab dann 21,76 statt 21,75 € und Auftrag/Rechnung wichen vom Shop um Cents ab.
 *
 * Preisbasis NETTO ist für B2B vorbereitet (Händler bekommen Nettopreise, MwSt wird
 * aufgeschlagen), wird aber derzeit nirgends verwendet. Umschalten später pro Kunde bzw.
 * Auftrag -- dann den $basis-Parameter an den Aufrufstellen durchreichen.
 *
 * In der DB bleiben einzelpreis_netto (4 Nachkommastellen) und gesamtpreis_netto (Cent)
 * gespeichert; gesamtpreis_netto ist bei Brutto-Basis das aus dem Brutto abgeleitete Netto.
 * Brutto NIE aus gesamtpreis_netto × (1 + Satz) zurückrechnen (18,12 × 1,2 = 21,74 statt
 * 21,75) -- immer zeile()/ausPosition() verwenden.
 */
class Positionsrechnung
{
    public const BRUTTO = 'brutto';
    public const NETTO  = 'netto';
    public const STANDARD_BASIS = self::BRUTTO;

    /** Einzel-Bruttopreis (Verkaufspreis auf Cent) aus dem gespeicherten Einzel-Netto. */
    public static function einzelBrutto(float $einzelNetto, float $satz): float
    {
        return round($einzelNetto * (1 + $satz / 100), 2);
    }

    /** Einzel-Netto (4 Stellen) aus einem Bruttopreis -- zum Speichern in einzelpreis_netto. */
    public static function einzelNettoAusBrutto(float $einzelBrutto, float $satz): float
    {
        return round($einzelBrutto / (1 + $satz / 100), 4);
    }

    /** @return array{einzel_brutto: float, netto: float, steuer: float, brutto: float} */
    public static function zeile(float $einzelNetto, float $menge, float $rabattProzent, float $satz, string $basis = self::STANDARD_BASIS): array
    {
        $faktor = $menge * (1 - $rabattProzent / 100);
        $einzelBrutto = self::einzelBrutto($einzelNetto, $satz);

        if ($basis === self::NETTO) {
            $netto  = round($einzelNetto * $faktor, 2);
            $steuer = round($netto * $satz / 100, 2);
            $brutto = round($netto + $steuer, 2);
        } else {
            $brutto = round($einzelBrutto * $faktor, 2);
            $netto  = round($brutto / (1 + $satz / 100), 2);
            $steuer = round($brutto - $netto, 2);
        }
        return ['einzel_brutto' => $einzelBrutto, 'netto' => $netto, 'steuer' => $steuer, 'brutto' => $brutto];
    }

    /** zeile() für eine Positionszeile (auftrag_positionen o.ä.), optional mit abweichender Menge. */
    public static function ausPosition(array $p, ?float $menge = null, string $basis = self::STANDARD_BASIS): array
    {
        // Sonderfall Kasse-Retoure im Spiegel-Auftrag: Menge 0, aber negatives
        // gesamtpreis_netto (bon_speichern.php) -- dann gilt der gespeicherte Zeilenbetrag
        if ($menge === null && (float)($p['menge'] ?? 0) == 0 && abs((float)($p['gesamtpreis_netto'] ?? 0)) > 0.004) {
            $satz   = (float)($p['steuer_prozent'] ?? 0);
            $netto  = round((float)$p['gesamtpreis_netto'], 2);
            $brutto = round($netto * (1 + $satz / 100), 2);
            return ['einzel_brutto' => self::einzelBrutto((float)($p['einzelpreis_netto'] ?? 0), $satz),
                    'netto' => $netto, 'steuer' => round($brutto - $netto, 2), 'brutto' => $brutto];
        }
        return self::zeile(
            (float)($p['einzelpreis_netto'] ?? 0),
            $menge ?? (float)($p['menge'] ?? 0),
            (float)($p['rabatt_prozent'] ?? 0),
            (float)($p['steuer_prozent'] ?? 0),
            $basis
        );
    }
}
