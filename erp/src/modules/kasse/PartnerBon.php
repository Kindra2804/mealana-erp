<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../partner/PartnerService.php';

/**
 * PartnerBon – Partnerware auf dem Kassenbon (Partner-Abrechnung Schritt 2)
 *
 * Klärung Steuerberatung 2026-10-26: EIN Bon für Kunde + Zahlung, Partnerware in
 * eigenen Abschnitten. Daraus folgt pro Position (Snapshot in kassen_bon_positionen):
 *   kommission    → eigener Umsatz, normal RKSV, normale Erlösgruppe
 *   fremdrechnung → "im Namen und auf Rechnung von …": NICHT signiert, kein Erlös
 *   spende        → Durchlauf, NICHT signiert, Bargeld geht in die Spendenlade
 * "Fremd" heißt hier immer: fremdrechnung ODER spende (beides nicht unser Umsatz).
 *
 * Wird von KassenService (Bon/Storno/Abschluss), bon_speichern.php und den
 * Bon-Drucken benutzt, damit die Trennung überall gleich gerechnet wird.
 */
class PartnerBon
{
    /** SQL-Bedingung für "eigener Umsatz" auf kassen_bon_positionen (Alias bp) */
    public const SQL_EIGEN = "(bp.partner_modus IS NULL OR bp.partner_modus = 'kommission')";

    public static function istFremd(array $pos): bool
    {
        return in_array($pos['partner_modus'] ?? null, ['fremdrechnung', 'spende'], true);
    }

    public static function zeilenBrutto(array $pos): float
    {
        return (float)($pos['menge'] ?? 1)
            * (float)($pos['einzelpreis_brutto'] ?? 0)
            * (1 - (float)($pos['rabatt_prozent'] ?? 0) / 100);
    }

    /**
     * Setzt partner_id/partner_modus aus den Artikel-Stammdaten (serverseitig, nie vom
     * Client). Spende und Fremdrechnung eines Kleinunternehmers tragen 0 % — dort weist
     * niemand USt aus; bei Fremdrechnung sonst der Satz des Artikels (= USt des Partners).
     */
    public static function anreichern(array $positionen): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($p) => (int)($p['artikel_id'] ?? 0), $positionen))));
        $info = [];
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::getInstance()->prepare("
                SELECT a.id, a.partner_id, p.typ, p.kleinunternehmer
                FROM artikel a
                JOIN partner p ON p.id = a.partner_id
                WHERE a.id IN ($ph)
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $info[(int)$r['id']] = $r;
            }
        }
        foreach ($positionen as &$p) {
            $i = $info[(int)($p['artikel_id'] ?? 0)] ?? null;
            $p['partner_id']    = $i ? (int)$i['partner_id'] : null;
            $p['partner_modus'] = $i ? $i['typ'] : null;
            if ($i && ($i['typ'] === 'spende' || ($i['typ'] === 'fremdrechnung' && (int)$i['kleinunternehmer'] === 1))) {
                $p['steuer_prozent'] = 0;
            }
        }
        unset($p);
        return $positionen;
    }

    /** Nur die Positionen, die unser Umsatz sind (RKSV, Spiegel-Auftrag, Steuer) */
    public static function eigene(array $positionen): array
    {
        return array_values(array_filter($positionen, fn($p) => !self::istFremd($p)));
    }

    /** ['eigen' => …, 'fremd' => …, 'spende' => …] (Brutto, mit Vorzeichen) */
    public static function summen(array $positionen): array
    {
        $s = ['eigen' => 0.0, 'fremd' => 0.0, 'spende' => 0.0];
        foreach ($positionen as $p) {
            $art = match ($p['partner_modus'] ?? null) {
                'fremdrechnung' => 'fremd',
                'spende'        => 'spende',
                default         => 'eigen',
            };
            $s[$art] += self::zeilenBrutto($p);
        }
        return array_map(fn($v) => round($v, 2), $s);
    }

    /**
     * Bar-Anteil der Spende (geht in die Spendenlade, nicht in den Kassenstand).
     * Bar bezahlter Betrag nach Rückgeld je Zahlungsart wie in getKassenstand(); bei
     * Mischzahlung zuerst aus dem Bar-Anteil. Vorzeichen folgt der Spende (Retoure = −).
     */
    public static function spendeBar(float $spende, array $bonDaten, float $brutto): float
    {
        if (abs($spende) < 0.005) return 0.0;
        $bar = match ($bonDaten['zahlungsart'] ?? 'bar') {
            'bar'       => $brutto,
            'kombi'     => (float)($bonDaten['bar_betrag'] ?? 0) - (float)($bonDaten['rueckgeld'] ?? 0),
            'gutschein' => (float)($bonDaten['bar_betrag'] ?? 0),
            default     => 0.0,
        };
        if ($spende > 0) return round(max(0.0, min($spende, $bar)), 2);
        return round(min(0.0, max($spende, $bar)), 2);
    }

    /**
     * Abschnitte für den Druck: fremde Positionen je Partner, mit Kopfdaten.
     * Rückgabe: [['partner' => [...], 'modus' => 'fremdrechnung'|'spende',
     *            'ku_text' => ?string, 'positionen' => [...], 'summe' => float], …]
     */
    public static function abschnitte(array $positionen): array
    {
        $gruppen = [];
        foreach ($positionen as $p) {
            if (!self::istFremd($p) || empty($p['partner_id'])) continue;
            $gruppen[(int)$p['partner_id']][] = $p;
        }
        if (!$gruppen) return [];

        $ph = implode(',', array_fill(0, count($gruppen), '?'));
        $stmt = Database::getInstance()->prepare("SELECT * FROM partner WHERE id IN ($ph)");
        $stmt->execute(array_keys($gruppen));
        $partner = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $partner[(int)$r['id']] = $r;

        $abschnitte = [];
        foreach ($gruppen as $pid => $pos) {
            $pa = $partner[$pid] ?? ['name' => 'Partner #' . $pid, 'kleinunternehmer' => 0];
            $abschnitte[] = [
                'partner'    => $pa,
                'modus'      => $pos[0]['partner_modus'],
                'adresse'    => trim(implode(', ', array_filter([$pa['strasse'] ?? null, trim(($pa['plz'] ?? '') . ' ' . ($pa['ort'] ?? ''))]))),
                'ku_text'    => !empty($pa['kleinunternehmer']) ? ($pa['ku_text'] ?: PartnerService::KU_STANDARDTEXT) : null,
                'positionen' => $pos,
                'summe'      => round(array_sum(array_map([self::class, 'zeilenBrutto'], $pos)), 2),
            ];
        }
        return $abschnitte;
    }

    /** Überschrift eines Partner-Abschnitts */
    public static function abschnittTitel(array $abschnitt): string
    {
        return $abschnitt['modus'] === 'spende'
            ? 'Spende für'
            : 'Verkauft im Namen und auf Rechnung von';
    }
}
