<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../auftraege/Versandsteuer.php';

/**
 * BuchhaltungExportService – sammelt Umsatzbuchungen für einen Zeitraum und liefert
 * sie als flache Liste von Buchungszeilen (Basis für CSV- und DATEV-Export).
 *
 * Buchungskonvention (durchgängig): 'konto' ist das Sachkonto der Zeile
 * (Erlöskonto, USt-Konto oder Debitorenkonto), 'gegenkonto' das Zahlungsmittel-
 * oder Debitorenkonto, 'soll_haben' bezieht sich auf 'konto'.
 *
 * Grundsatz (Belege-Umbau, Jacky 2026-10-07): Erlös kommt NUR aus Belegen -- Kassenbon,
 * Rechnung, Gutschrift (Gutschein-Verkauf = Anzahlung 3230). Aufträge selbst sind nur
 * "erwarteter Umsatz" und werden nicht mehr gebucht. MEALANA ist eine KG ->
 * Soll-Versteuerung, die USt entsteht mit der Rechnung.
 *
 * Buchungsblöcke:
 * 1. Kassenbons: Umsatz UND Zahlung fallen zusammen → Erlös+USt sofort gegen
 *    Zahlungsmittel, pro Tag × Warengruppe × Steuersatz × Zahlungsart (ohne Zahlungszeilen).
 * 2. Zahlbelege an der Kasse (Zahlung auf Rechnung / Rückzahlung Anzahlung, 0 %):
 *    Kassa an Kundenkonto.
 * 3. Rechnungen + Gutschriften: Erlös+USt am Rechnungs-/Gutschriftsdatum gegen Kundenkonto.
 * 4. Zahlungseingänge außerhalb der Kasse (alle Zahlarten) + Online-Gutschein-Einlösungen:
 *    Bank/PayPal/3230 an Kundenkonto, am Buchungsdatum.
 * 5. Mahngebühren.
 *
 * Kassenbons mit mehreren Zahlungsmitteln (kombi, Gutschein + Rest) werden seit
 * 2026-09-30 anteilig auf Kassa/Bank/3230 aufgeteilt (gemischteBonsAufteilen). Zum
 * Gegenprüfen: kontrollListe() -> Seite buchhaltung/zahlungskontrolle.php.
 *
 * Seit 2026-09-30 zusätzlich: Versandkosten als Erlös (Gruppe "Versandkosten",
 * Steuersatz der überwiegenden Leistung, siehe Versandsteuer), Gutscheine über das
 * Anzahlungskonto 3230 (Verkauf = Artikelgruppe Gutscheine, Einlösung = Zahlungsart
 * "gutschein" bzw. bei Online-Aufträgen 3230 an Kundenkonto). Aufträge der Kanäle
 * AUSGESCHLOSSENE_KANAELE werden nicht exportiert (siehe dort).
 */
class BuchhaltungExportService
{
    /**
     * Kasse: jeder Bon legt zusätzlich einen Auftrag an -- der Umsatz steckt schon in
     * kassenbonUmsaetze(), sonst doppelt. jtl_archiv: importierte JTL-Altaufträge,
     * damals in JTL gebucht -- dürfen nicht nochmal in den Export (Fund 2026-09-30).
     */
    private const AUSGESCHLOSSENE_KANAELE = "'kasse', 'jtl_archiv'";

    /** auftrag_zahlungen.zahlungsweg -> Schlüssel in zahlungsart_konten */
    private const ZAHLUNGSWEG_ZU_ZAHLUNGSART = ['ueberweisung' => 'vorkasse', 'paypal' => 'paypal', 'nachnahme' => 'nachnahme',
                                               'bar' => 'bar', 'karte' => 'karte_extern', 'gutschein' => 'gutschein'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return array{buchungen: array<int, array>, hinweise: array<int, string>}
     */
    public function sammleZeitraum(string $von, string $bis): array
    {
        $buchungen = [];
        $hinweise  = [];

        $this->kassenbonUmsaetze($von, $bis, $buchungen, $hinweise);
        $this->kassenZahlbelege($von, $bis, $buchungen, $hinweise);
        $this->rechnungenUndGutschriften($von, $bis, $buchungen, $hinweise);
        $this->zahlungseingaenge($von, $bis, $buchungen, $hinweise);
        $this->mahngebuehren($von, $bis, $buchungen, $hinweise);

        // DATEV & übliche Buchungsformate erwarten immer einen POSITIVEN Betrag —
        // die Soll/Haben-Kennung trägt das Vorzeichen. Retouren/Gutschriften können
        // hier zu negativen Zwischensummen führen (z.B. Rückerstattung am selben Tag
        // wie andere Verkäufe derselben Warengruppe) -> Vorzeichen umdrehen + S/H tauschen.
        foreach ($buchungen as &$b) {
            if ($b['betrag'] < 0) {
                $b['betrag']     = round(abs($b['betrag']), 2);
                $b['soll_haben'] = $b['soll_haben'] === 'H' ? 'S' : 'H';
            }
        }
        unset($b);

        usort($buchungen, fn($a, $b) => $a['datum'] <=> $b['datum']);

        return ['buchungen' => $buchungen, 'hinweise' => $hinweise];
    }

    /** Konto-Zeile aus zahlungsart_konten, oder null + Hinweis wenn nicht direkt buchbar. */
    private function zahlungsartKonto(string $zahlungsart): ?array
    {
        $stmt = $this->db->prepare("
            SELECT zk.hinweis, k.kontonummer, k.name
            FROM zahlungsart_konten zk
            LEFT JOIN kontenplan k ON k.id = zk.konto_id
            WHERE zk.zahlungsart = :z
        ");
        $stmt->execute([':z' => $zahlungsart]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Krumme Altsätze (z.B. 20,03 % aus dem Shop-Import vor Migration 184, per Abholung
     * auch auf Kassenbons kopiert) auf den echten Steuersatz einrasten (max. 0,5 %-Punkte).
     * Die signierten Bons selbst bleiben unverändert (RKSV).
     */
    private function echterSatz(float $satz): float
    {
        static $saetze = null;
        $saetze ??= array_map("floatval", $this->db->query("SELECT DISTINCT satz FROM steuerklassen")->fetchAll(PDO::FETCH_COLUMN));
        foreach ($saetze as $s) {
            if (abs($s - $satz) <= 0.5) return $s;
        }
        return $satz;
    }

    private function ustKonto(float $satz): ?string
    {
        $stmt = $this->db->prepare("
            SELECT k.kontonummer FROM steuerklassen_konten sk
            JOIN steuerklassen s ON s.id = sk.steuerklasse_id
            LEFT JOIN kontenplan k ON k.id = sk.steuer_konto_id
            WHERE s.satz = :satz
            LIMIT 1
        ");
        $stmt->execute([':satz' => $satz]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Freitext-Positionen ("99-9999 Diverses") bekommen nur beim Spiegeln nach
     * auftrag_positionen eine echte artikel_id (siehe KassenService::getDiversArtikelId()) —
     * direkt in kassen_bon_positionen bleibt artikel_id NULL. Für den Export fallen
     * solche Positionen auf dieselbe Artikelgruppe zurück wie der 99-9999-Artikel selbst.
     *
     * Reihenfolge seit Migration 201 (2026-10-08): an der Kasse gewählte Gruppe
     * (bp.artikel_gruppe_id) → Gruppe des Artikels → NUR für Divers ohne Gruppe
     * (Altbestand) dieser Fallback. Echte Artikel ohne Gruppe fallen NICHT mehr
     * still hierher, sondern erzeugen einen Hinweis (kein Erlöskonto).
     */
    private function diversesGruppeId(): ?int
    {
        $stmt = $this->db->query("
            SELECT art.artikel_gruppe_id FROM artikel art WHERE art.artikelnummer = '99-9999' LIMIT 1
        ");
        return (int)$stmt->fetchColumn() ?: null;
    }

    // ── Block 1a: Kassenbons ─────────────────────────────────────────────────

    private function kassenbonUmsaetze(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $diversesGruppeId = $this->diversesGruppeId() ?? 0;

        // Menge MIT Vorzeichen: Retour-Positionen (negative Menge) mindern den Umsatz.
        // Bis 2026-09-30 stand hier ABS(menge) -- eine Kassen-Retoure über -5,00 wurde
        // als +10,00 Umsatz exportiert.
        // Datum: bei nacherfassten Messe-Belegen (Papier-Messe, Migration 202) zählt das
        // Datum des händischen Belegs, nicht der Signaturzeitpunkt — richtiger USt-Monat.
        $rows = $this->db->query("
            SELECT COALESCE(b.handbeleg_datum, DATE(b.erstellt_am)) AS datum, b.zahlungsart,
                   ag.konto_nr, ag.name AS gruppe_name, bp.steuer_prozent,
                   SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)) AS brutto
            FROM kassen_bon_positionen bp
            INNER JOIN kassen_bons b ON b.id = bp.bon_id
            LEFT JOIN artikel a       ON a.id  = bp.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = COALESCE(bp.artikel_gruppe_id, a.artikel_gruppe_id, CASE WHEN bp.artikel_id IS NULL THEN {$diversesGruppeId} END)
            WHERE b.typ = 'verkauf' AND b.storniert = 0 AND COALESCE(bp.block, '') <> 'zahlung'
              AND NOT (" . self::GEMISCHT_BEDINGUNG . ")
              AND COALESCE(b.handbeleg_datum, DATE(b.erstellt_am)) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
            GROUP BY datum, b.zahlungsart, ag.id, ag.konto_nr, ag.name, bp.steuer_prozent
        ")->fetchAll();

        foreach ($rows as $r) {
            $this->erloesZeilenAnhaengen(
                $buchungen, $hinweise,
                datum: $r['datum'], belegnr: 'Kasse-' . $r['datum'],
                erloesKonto: $r['konto_nr'], gruppeName: $r['gruppe_name'] ?? 'ohne Gruppe',
                satz: (float)$r['steuer_prozent'], brutto: (float)$r['brutto'],
                zahlungsart: $r['zahlungsart'], quelle: 'Kasse'
            );
        }

        $this->gemischteBonsAufteilen($von, $bis, $diversesGruppeId, $buchungen, $hinweise);
    }

    /** Kassenbon mit mehreren Zahlungsmitteln: Bar+Karte (kombi) oder Gutschein + Rest bar/Karte. */
    private const GEMISCHT_BEDINGUNG = "b.zahlungsart = 'kombi' OR (b.zahlungsart = 'gutschein' AND COALESCE(b.bar_betrag, 0) + COALESCE(b.karten_betrag, 0) > 0)";

    /**
     * Zahlungsanteile eines Kassenbons (Kassa/Bank/Gutschein), Summe = Bon-Betrag.
     * kombi speichert den GEGEBENEN Barbetrag (Rückgeld separat) -> Rückgeld abziehen;
     * Gutschein + Rest bar speichert den Rest schon netto. Eine Differenz zum Bon-Betrag
     * (z.B. Rundung, Altdaten) wird dem Baranteil zugeschlagen und als 'differenz' gemeldet.
     *
     * @return array{anteile: array<string,float>, differenz: float}  Schlüssel = Zahlungsart für zahlungsart_konten
     */
    public function zahlungsAnteile(array $bon): array
    {
        $bar = (float)($bon['bar_betrag'] ?? 0) - ($bon['zahlungsart'] === 'kombi' ? (float)($bon['rueckgeld'] ?? 0) : 0);
        $anteile = array_filter([
            'bar'          => round($bar, 2),
            'karte_extern' => round((float)($bon['karten_betrag'] ?? 0), 2),
            'gutschein'    => $bon['zahlungsart'] === 'gutschein' ? round((float)($bon['gutschein_betrag'] ?? 0), 2) : 0.0,
        ], fn($v) => abs($v) > 0.004);
        $differenz = round((float)$bon['bruttobetrag'] - array_sum($anteile), 2);
        if (abs($differenz) > 0.004) {
            $anteile['bar'] = round(($anteile['bar'] ?? 0) + $differenz, 2);
        }
        return ['anteile' => $anteile, 'differenz' => $differenz];
    }

    /**
     * Gemischte Kassenbons anteilig buchen: jede Warengruppe × Steuersatz des Bons wird im
     * Verhältnis der Zahlungsanteile auf Kassa / Bank / Gutschein-Konto 3230 verteilt (letzter
     * Anteil bekommt den Rundungsrest, damit die Summe exakt stimmt), dann wie normale Bons
     * pro Tag × Gruppe × Satz × Zahlungsart zusammengefasst. Vorher: nur Hinweis "manuell buchen".
     */
    private function gemischteBonsAufteilen(string $von, string $bis, int $diversesGruppeId, array &$buchungen, array &$hinweise): void
    {
        $bons = $this->db->query("
            SELECT b.id, b.bon_nr, COALESCE(b.handbeleg_datum, DATE(b.erstellt_am)) AS datum, b.zahlungsart, b.bruttobetrag,
                   b.bar_betrag, b.karten_betrag, b.gutschein_betrag, b.rueckgeld
            FROM kassen_bons b
            WHERE b.typ = 'verkauf' AND b.storniert = 0 AND (" . self::GEMISCHT_BEDINGUNG . ")
              AND COALESCE(b.handbeleg_datum, DATE(b.erstellt_am)) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
        ")->fetchAll(PDO::FETCH_ASSOC);

        $posStmt = $this->db->prepare("
            SELECT ag.konto_nr, ag.name AS gruppe_name, bp.steuer_prozent,
                   SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)) AS brutto
            FROM kassen_bon_positionen bp
            LEFT JOIN artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = COALESCE(bp.artikel_gruppe_id, a.artikel_gruppe_id, CASE WHEN bp.artikel_id IS NULL THEN {$diversesGruppeId} END)
            WHERE bp.bon_id = ? AND COALESCE(bp.block, '') <> 'zahlung'
            GROUP BY ag.id, ag.konto_nr, ag.name, bp.steuer_prozent
        ");

        $summen = []; // datum|konto|gruppe|satz|zahlungsart => brutto
        foreach ($bons as $bon) {
            $bonBrutto = (float)$bon['bruttobetrag'];
            $anteile   = $this->zahlungsAnteile($bon)['anteile'];
            if (abs($bonBrutto) < 0.005 || !$anteile) continue;

            $posStmt->execute([(int)$bon['id']]);
            foreach ($posStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $gruppenBrutto = round((float)$p['brutto'], 2);
                $rest = $gruppenBrutto;
                $schluessel = array_keys($anteile);
                foreach ($schluessel as $i => $za) {
                    $teil = $i === count($schluessel) - 1
                        ? $rest
                        : round($gruppenBrutto * $anteile[$za] / $bonBrutto, 2);
                    $rest = round($rest - $teil, 2);
                    $k = implode('|', [$bon['datum'], $p['konto_nr'], $p['gruppe_name'] ?? 'ohne Gruppe', (float)$p['steuer_prozent'], $za]);
                    $summen[$k] = ($summen[$k] ?? 0) + $teil;
                }
            }
        }

        foreach ($summen as $k => $brutto) {
            [$datum, $konto, $gruppe, $satz, $za] = explode('|', $k);
            $this->erloesZeilenAnhaengen(
                $buchungen, $hinweise,
                datum: $datum, belegnr: 'Kasse-' . $datum,
                erloesKonto: $konto !== '' ? $konto : null, gruppeName: $gruppe,
                satz: (float)$satz, brutto: round($brutto, 2),
                zahlungsart: $za, quelle: 'Kasse (Kombi)'
            );
        }
    }

    /** Erlöskonto für Versandkosten = Konto der Artikelgruppe "Versandkosten" (Standard 4090). */
    private function versandKonto(): ?string
    {
        static $konto = false;
        if ($konto === false) {
            $konto = $this->db->query("SELECT konto_nr FROM artikel_gruppen WHERE name = 'Versandkosten' AND aktiv = 1 LIMIT 1")->fetchColumn() ?: null;
        }
        return $konto;
    }

    /** Gemeinsame Erlös+USt-Zeilen-Erzeugung für Kasse und "einfache" Aufträge. */
    private function erloesZeilenAnhaengen(
        array &$buchungen, array &$hinweise,
        string $datum, string $belegnr, ?string $erloesKonto, string $gruppeName,
        float $satz, float $brutto, string $zahlungsart, string $quelle
    ): void {
        $satz = $this->echterSatz($satz);
        if (!$erloesKonto) {
            $hinweise[] = "$quelle $datum: Position(en) ohne Artikelgruppe (kein Erlöskonto) — € " . number_format($brutto, 2, ',', '.') . " manuell prüfen";
            return;
        }

        $zk = $this->zahlungsartKonto($zahlungsart);
        if (!$zk || !$zk['kontonummer']) {
            $hinweise[] = "$quelle $datum: Zahlungsart '$zahlungsart' ohne Konto (" . ($zk['hinweis'] ?? 'nicht gemappt') . ") — € " . number_format($brutto, 2, ',', '.') . " manuell buchen";
            return;
        }

        $netto  = $satz > 0 ? round($brutto / (1 + $satz / 100), 2) : $brutto;
        $steuer = round($brutto - $netto, 2);
        $gegenkonto = $zk['kontonummer'];

        $buchungen[] = [
            'datum' => $datum, 'belegnr' => $belegnr, 'konto' => $erloesKonto, 'gegenkonto' => $gegenkonto,
            'betrag' => $netto, 'soll_haben' => 'H', 'satz' => $satz,
            // Gutschein-Verkauf ist kein Erlös, sondern erhaltene Anzahlung (Konto 3230)
            'text' => ($erloesKonto === ($this->zahlungsartKonto('gutschein')['kontonummer'] ?? null)
                ? "Gutschein-Verkauf (Anzahlung) $quelle ($zahlungsart)"
                : "Erlös $gruppeName $quelle ($zahlungsart)"),
        ];

        if (abs($steuer) > 0.004) { // auch negativ (Retoure mindert USt)
            $ustKonto = $this->ustKonto($satz);
            if (!$ustKonto) {
                $hinweise[] = "$quelle $datum: Kein USt-Konto für $satz% hinterlegt — Steuerbetrag € " . number_format($steuer, 2, ',', '.') . " manuell buchen";
            } else {
                $buchungen[] = [
                    'datum' => $datum, 'belegnr' => $belegnr, 'konto' => $ustKonto, 'gegenkonto' => $gegenkonto,
                    'betrag' => $steuer, 'soll_haben' => 'H', 'satz' => $satz,
                    'text' => "USt $satz% $gruppeName $quelle",
                ];
            }
        }
    }

    // ── Block 2: Zahlbelege / Rückzahlungen an der Kasse ─────────────────────

    /**
     * Bon-Zeilen block 'zahlung': Zahlung auf eine Rechnung (positiv) bzw. Rückzahlung einer
     * nie verrechneten Anzahlung (negativ). Kein Erlös -- Kassa/Bank an Kundenkonto.
     */
    private function kassenZahlbelege(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $rows = $this->db->query("
            SELECT DATE(b.erstellt_am) AS datum, b.bon_nr, b.zahlungsart,
                   SUM(bp.menge * bp.einzelpreis_brutto) AS betrag,
                   a.auftrag_nr, k.debitorennummer
            FROM kassen_bon_positionen bp
            JOIN kassen_bons b ON b.id = bp.bon_id
            LEFT JOIN auftraege a ON a.id = bp.web_auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE bp.block = 'zahlung' AND b.typ = 'verkauf' AND b.storniert = 0
              AND DATE(b.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
            GROUP BY b.id, bp.web_auftrag_id
        ")->fetchAll();

        foreach ($rows as $r) {
            $betrag = round((float)$r['betrag'], 2);
            $za     = in_array($r['zahlungsart'], ['bar', 'karte_extern', 'gutschein'], true) ? $r['zahlungsart'] : 'bar';
            if ($za !== $r['zahlungsart']) {
                $hinweise[] = "Zahlbeleg {$r['bon_nr']}: Bon mit mehreren Zahlungsmitteln — Zahlung zu {$r['auftrag_nr']} als Kassa gebucht, bitte prüfen";
            }
            $zk = $this->zahlungsartKonto($za)['kontonummer'] ?? null;
            if (!$r['debitorennummer'] || !$zk) {
                $hinweise[] = "Zahlbeleg {$r['bon_nr']} ({$r['auftrag_nr']}): " . (!$zk ? 'Zahlungskonto fehlt' : 'Kunde ohne Debitorennummer')
                    . " — € " . number_format($betrag, 2, ',', '.') . " manuell buchen";
                continue;
            }
            $buchungen[] = [
                'datum' => $r['datum'], 'belegnr' => $r['bon_nr'], 'konto' => $r['debitorennummer'],
                'gegenkonto' => $zk, 'betrag' => $betrag, 'soll_haben' => 'H', 'satz' => null,
                'text' => ($betrag >= 0 ? 'Zahlung Kasse' : 'Rückzahlung Kasse') . " Auftrag {$r['auftrag_nr']}",
            ];
        }
    }

    // ── Block 3: Rechnungen und Gutschriften (Soll-Versteuerung, KG) ───────────

    /**
     * Erlös entsteht mit der (Teil-)Rechnung: Kundenkonto an Erlös je Warengruppe × Steuersatz
     * + USt, am Rechnungsdatum (Belege-Umbau 2026-10-07; vorher Auftragsdatum und nur für
     * Zahlart "Rechnung"). Gutschriften mindern den Erlös gleich, mit umgekehrtem Vorzeichen.
     * Gutschein-Verkauf läuft über die Artikelgruppe Gutscheine (Konto 3230, 0 %).
     */
    private function rechnungenUndGutschriften(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $zeitraum = " BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis);
        $belege = $this->db->query("
            SELECT 'rechnung' AS art, r.id, r.rechnung_nr AS nr, DATE(r.erstellt_am) AS datum,
                   r.versandkosten_brutto, a.auftrag_nr, k.debitorennummer, a.id AS auftrag_id
            FROM rechnungen r
            JOIN auftraege a ON a.id = r.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ") AND DATE(r.erstellt_am) $zeitraum
            UNION ALL
            SELECT 'gutschrift', g.id, g.gutschrift_nr, DATE(g.erstellt_am), g.versandkosten_brutto, a.auftrag_nr, k.debitorennummer, a.id
            FROM gutschriften g
            JOIN auftraege a ON a.id = g.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ") AND DATE(g.erstellt_am) $zeitraum
        ")->fetchAll();

        $posStmt = [
            'rechnung' => $this->db->prepare("
                SELECT ag.konto_nr, ag.name AS gruppe_name, rp.steuer_prozent,
                       SUM(rp.netto) AS netto, SUM(rp.steuer) AS steuer, SUM(rp.brutto) AS brutto
                FROM rechnung_positionen rp
                LEFT JOIN artikel art ON art.id = rp.artikel_id
                LEFT JOIN artikel_gruppen ag ON ag.id = art.artikel_gruppe_id
                WHERE rp.rechnung_id = ?
                GROUP BY ag.id, ag.konto_nr, ag.name, rp.steuer_prozent
            "),
            'gutschrift' => $this->db->prepare("
                SELECT ag.konto_nr, ag.name AS gruppe_name, gp.steuer_prozent,
                       SUM(gp.netto) AS netto, SUM(gp.steuer) AS steuer, SUM(gp.brutto) AS brutto
                FROM gutschrift_positionen gp
                LEFT JOIN artikel art ON art.id = gp.artikel_id
                LEFT JOIN artikel_gruppen ag ON ag.id = art.artikel_gruppe_id
                WHERE gp.gutschrift_id = ?
                GROUP BY ag.id, ag.konto_nr, ag.name, gp.steuer_prozent
            "),
        ];
        $gsKonto = $this->zahlungsartKonto('gutschein')['kontonummer'] ?? null;

        foreach ($belege as $b) {
            $vz    = $b['art'] === 'gutschrift' ? -1 : 1;
            $label = $b['art'] === 'gutschrift' ? 'Gutschrift' : 'Rechnung';
            if (!$b['debitorennummer']) {
                $hinweise[] = "$label {$b['nr']} ({$b['datum']}, Auftrag {$b['auftrag_nr']}): Kunde ohne Debitorennummer — manuell buchen";
                continue;
            }
            $posStmt[$b['art']]->execute([(int)$b['id']]);
            $zeilen = $posStmt[$b['art']]->fetchAll();

            foreach ($zeilen as $p) {
                $this->belegZeile($buchungen, $hinweise, $b, $label, $p['konto_nr'], $p['gruppe_name'] ?? 'ohne Gruppe',
                    $this->echterSatz((float)$p['steuer_prozent']), $vz * (float)$p['netto'], $vz * (float)$p['steuer'], $gsKonto);
            }

            if ((float)$b['versandkosten_brutto'] > 0) {
                // Satz wie auf der Rechnung mit den Versandkosten (bei einer Korrektur deren Original)
                $basis = $b['art'] === 'gutschrift' ? $this->versandBasisRechnung((int)$b['auftrag_id']) : null;
                $v = Versandsteuer::aufteilen((float)$b['versandkosten_brutto'], $basis ??
                    array_map(fn($p) => ['steuer_prozent' => $p['steuer_prozent'], 'gesamtpreis_netto' => $p['netto']], $zeilen));
                $this->belegZeile($buchungen, $hinweise, $b, $label, $this->versandKonto(), 'Versandkosten',
                    (float)$v['satz'], $vz * $v['netto'], $vz * $v['steuer'], $gsKonto);
            }
        }
    }

    /** Positionen der Rechnung, auf der die Versandkosten des Auftrags standen. */
    private function versandBasisRechnung(int $auftragId): ?array
    {
        $s = $this->db->prepare("
            SELECT steuer_prozent, netto AS gesamtpreis_netto FROM rechnung_positionen
            WHERE rechnung_id = (SELECT id FROM rechnungen WHERE auftrag_id = ? AND versandkosten_brutto > 0 ORDER BY id LIMIT 1)
        ");
        $s->execute([$auftragId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: null;
    }

    /** Erlös- und USt-Zeile eines Rechnungs-/Gutschrift-Belegs gegen das Kundenkonto. */
    private function belegZeile(array &$buchungen, array &$hinweise, array $b, string $label, ?string $konto,
                                string $gruppe, float $satz, float $netto, float $steuer, ?string $gsKonto): void
    {
        $netto = round($netto, 2); $steuer = round($steuer, 2);
        if (!$konto) {
            $hinweise[] = "$label {$b['nr']}: $gruppe ohne Erlöskonto — € " . number_format($netto + $steuer, 2, ',', '.') . " manuell buchen";
            return;
        }
        $buchungen[] = [
            'datum' => $b['datum'], 'belegnr' => $b['nr'], 'konto' => $konto, 'gegenkonto' => $b['debitorennummer'],
            'betrag' => $netto, 'soll_haben' => 'H', 'satz' => $satz,
            'text' => ($konto === $gsKonto ? 'Gutschein-Verkauf (Anzahlung)' : "Erlös $gruppe") . " $label {$b['nr']}",
        ];
        if (abs($steuer) > 0.004) {
            $ustKonto = $this->ustKonto($satz);
            if (!$ustKonto) {
                $hinweise[] = "$label {$b['nr']}: Kein USt-Konto für $satz% — Steuer € " . number_format($steuer, 2, ',', '.') . " manuell buchen";
                return;
            }
            $buchungen[] = [
                'datum' => $b['datum'], 'belegnr' => $b['nr'], 'konto' => $ustKonto, 'gegenkonto' => $b['debitorennummer'],
                'betrag' => $steuer, 'soll_haben' => 'H', 'satz' => $satz,
                'text' => "USt $satz% $gruppe $label {$b['nr']}",
            ];
        }
    }

    // ── Block 4: Zahlungseingänge + Gutschein-Einlösungen (außerhalb der Kasse) ──

    /**
     * Jede Zahlung auf einen Auftrag: Bank/PayPal/... an Kundenkonto, am Buchungsdatum --
     * für ALLE Zahlarten (Vorkasse wird so zur Anzahlung, die Rechnung gleicht sie später aus).
     * An der Kasse gebuchte Zahlungen (kassen_bon_id gesetzt) laufen über den Bon und werden
     * hier übersprungen. Online eingelöste Gutscheine: Anzahlungskonto 3230 an Kundenkonto.
     */
    private function zahlungseingaenge(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $zeitraum = " BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis);
        $rows = $this->db->query("
            SELECT z.buchungsdatum AS datum, z.betrag, z.zahlungsweg, a.zahlungsart, a.auftrag_nr, k.debitorennummer,
                   'zahlung' AS quelle
            FROM auftrag_zahlungen z
            JOIN auftraege a ON a.id = z.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE z.kassen_bon_id IS NULL AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND z.buchungsdatum $zeitraum
            UNION ALL
            SELECT DATE(t.erstellt_am), -t.betrag, 'gutschein', a.zahlungsart, a.auftrag_nr, k.debitorennummer, 'gutschein'
            FROM gutschein_transaktionen t
            JOIN auftraege a ON a.id = t.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE t.betrag < 0 AND t.kassen_bon_id IS NULL AND t.kanal <> 'kasse'
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND DATE(t.erstellt_am) $zeitraum
        ")->fetchAll();

        foreach ($rows as $r) {
            $za = self::ZAHLUNGSWEG_ZU_ZAHLUNGSART[$r['zahlungsweg'] ?? ''] ?? (in_array($r['zahlungsart'], ['rechnung', 'gemischt'], true) ? 'vorkasse' : $r['zahlungsart']);
            $zk = $this->zahlungsartKonto($za)['kontonummer'] ?? null;
            $betrag = round((float)$r['betrag'], 2);
            if (!$r['debitorennummer'] || !$zk) {
                $hinweise[] = "Zahlung {$r['auftrag_nr']} ({$r['datum']}): " . (!$zk ? "kein Konto für '$za'" : 'Kunde ohne Debitorennummer')
                    . " — € " . number_format($betrag, 2, ',', '.') . " manuell buchen";
                continue;
            }
            $buchungen[] = [
                'datum' => $r['datum'], 'belegnr' => $r['auftrag_nr'], 'konto' => $r['debitorennummer'],
                'gegenkonto' => $zk, 'betrag' => $betrag, 'soll_haben' => 'H', 'satz' => null,
                'text' => ($r['quelle'] === 'gutschein' ? 'Gutschein-Einlösung' : ($betrag < 0 ? 'Rückerstattung' : 'Zahlungseingang'))
                    . " Auftrag {$r['auftrag_nr']}",
            ];
        }
    }

    // ── Block 5: Mahngebühren (Rechnungskunden) ─────────────────────────────

    /**
     * Mahngebühr wird mit dem Versand der Mahnung zur Forderung: Kundenkonto an Erlöskonto
     * Mahngebühren (Artikelgruppe "Mahngebühren", nicht umsatzsteuerbar). Erlassene Gebühr
     * am Erlassdatum zurück. Die Zahlung selbst läuft über Block 4 (Bank an Kunde).
     */
    private function mahngebuehren(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $zeitraum = " BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis);
        $rows = $this->db->query("
            SELECT m.typ, m.gebuehr, DATE(m.gesendet_am) AS gesendet, DATE(m.gebuehr_erlassen_am) AS erlassen,
                   a.auftrag_nr, k.debitorennummer
            FROM mahnungen m
            JOIN auftraege a ON a.id = m.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE m.status = 'versendet' AND m.gebuehr > 0
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND (DATE(m.gesendet_am) $zeitraum OR DATE(m.gebuehr_erlassen_am) $zeitraum)
        ")->fetchAll();
        if (!$rows) return;

        $konto = $this->db->query("SELECT konto_nr FROM artikel_gruppen WHERE name = 'Mahngebühren' AND aktiv = 1 LIMIT 1")->fetchColumn() ?: null;

        foreach ($rows as $r) {
            $stufe = $r['typ'] === 'mahnung2' ? '2. Mahnung' : '1. Mahnung';
            if (!$r['debitorennummer'] || !$konto) {
                $hinweise[] = "Mahngebühr {$stufe} {$r['auftrag_nr']}: " . (!$konto ? 'keine Artikelgruppe "Mahngebühren"' : 'Kunde ohne Debitorennummer') . " — manuell buchen";
                continue;
            }
            $betrag = round((float)$r['gebuehr'], 2);
            if ($r['gesendet'] >= $von && $r['gesendet'] <= $bis) {
                $buchungen[] = [
                    'datum' => $r['gesendet'], 'belegnr' => $r['auftrag_nr'], 'konto' => $konto,
                    'gegenkonto' => $r['debitorennummer'], 'betrag' => $betrag, 'soll_haben' => 'H', 'satz' => 0.0,
                    'text' => "Mahngebühr {$stufe} {$r['auftrag_nr']}",
                ];
            }
            if ($r['erlassen'] && $r['erlassen'] >= $von && $r['erlassen'] <= $bis) {
                $buchungen[] = [
                    'datum' => $r['erlassen'], 'belegnr' => $r['auftrag_nr'], 'konto' => $konto,
                    'gegenkonto' => $r['debitorennummer'], 'betrag' => $betrag, 'soll_haben' => 'S', 'satz' => 0.0,
                    'text' => "Mahngebühr {$stufe} {$r['auftrag_nr']} erlassen",
                ];
            }
        }
    }

    /** Wert der zurückgenommenen Ware (wie auftraege/detail.php, solange noch nicht alles verrechnet ist). */
    private function retourWert(int $auftragId): float
    {
        require_once __DIR__ . '/../auftraege/Positionsrechnung.php';
        $s = $this->db->prepare("SELECT * FROM auftrag_positionen WHERE auftrag_id = ? AND menge_retourniert > 0");
        $s->execute([$auftragId]);
        $summe = 0.0;
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $summe += Positionsrechnung::ausPosition($p, (float)$p['menge_retourniert'])['brutto'];
        }
        return $summe;
    }

    // ── Zahlungs-Kontrollliste (Seite buchhaltung/zahlungskontrolle.php) ─────

    /**
     * Kassenbons und Aufträge mit ihren Zahlungen zum Gegenprüfen des Exports.
     * Standard: nur "interessante" Belege -- gemischte Zahlung, Gutschein beteiligt oder
     * Differenz (Zahlungen ≠ Betrag bzw. Positionen ≠ Bon-Betrag). $alle = true zeigt
     * alle Belege des Zeitraums. 'konten' = wie der Export den Betrag verteilt.
     *
     * @return array{bons: array, auftraege: array, konten: array<string,float>}
     */
    public function kontrollListe(string $von, string $bis, bool $alle = false): array
    {
        $zeitraum = " BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis);
        $kontoSummen = [];
        $kontoVon = function (string $zahlungsart): string {
            return $this->zahlungsartKonto($zahlungsart)['kontonummer'] ?? '?';
        };

        // Kassenbons
        $bons = $this->db->query("
            SELECT b.id, b.bon_nr, b.erstellt_am, b.zahlungsart, b.bruttobetrag, b.gegeben, b.rueckgeld,
                   b.bar_betrag, b.karten_betrag, b.gutschein_betrag, b.gutschein_code,
                   (SELECT ROUND(SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)), 2)
                      FROM kassen_bon_positionen bp WHERE bp.bon_id = b.id) AS positionen_summe
            FROM kassen_bons b
            WHERE b.typ = 'verkauf' AND b.storniert = 0 AND DATE(b.erstellt_am) $zeitraum
            ORDER BY b.erstellt_am
        ")->fetchAll(PDO::FETCH_ASSOC);

        $bonZeilen = [];
        foreach ($bons as $b) {
            $gemischt = $b['zahlungsart'] === 'kombi'
                || ($b['zahlungsart'] === 'gutschein' && (float)$b['bar_betrag'] + (float)$b['karten_betrag'] > 0);
            if ($gemischt) {
                ['anteile' => $anteile, 'differenz' => $differenz] = $this->zahlungsAnteile($b);
            } else {
                $anteile   = [$b['zahlungsart'] => round((float)$b['bruttobetrag'], 2)];
                $differenz = 0.0;
            }
            $posDifferenz = round((float)$b['bruttobetrag'] - (float)$b['positionen_summe'], 2);
            $auffaellig   = abs($differenz) > 0.004 || abs($posDifferenz) > 0.004;

            if (!$alle && !$gemischt && $b['zahlungsart'] !== 'gutschein' && !$auffaellig) continue;

            $konten = [];
            foreach ($anteile as $za => $betrag) {
                $k = $kontoVon($za);
                $konten[$k] = ($konten[$k] ?? 0) + $betrag;
                $kontoSummen[$k] = ($kontoSummen[$k] ?? 0) + $betrag;
            }
            $bonZeilen[] = $b + [
                'gemischt'      => $gemischt,
                'bar_netto'     => $anteile['bar'] ?? 0.0,
                'karte'         => $anteile['karte_extern'] ?? 0.0,
                'gutschein'     => $anteile['gutschein'] ?? 0.0,
                'differenz'     => $differenz,
                'pos_differenz' => $posDifferenz,
                'konten'        => $konten,
                'auffaellig'    => $auffaellig,
            ];
        }

        // Aufträge (ohne Kasse-Spiegel und JTL-Archiv, wie im Export)
        $auftraege = $this->db->query("
            SELECT a.id, a.auftrag_nr, a.erstellt_am, a.kanal, a.zahlungsart, a.zahlungsstatus,
                   a.bruttobetrag, a.versandkosten, a.gutschein_betrag,
                   (SELECT COALESCE(SUM(m.gebuehr), 0) FROM mahnungen m
                     WHERE m.auftrag_id = a.id AND m.status = 'versendet' AND m.gebuehr_erlassen_am IS NULL) AS mahngebuehren,
                   (SELECT GROUP_CONCAT(DISTINCT g.code SEPARATOR ', ') FROM gutschein_transaktionen t
                      JOIN gutscheine g ON g.id = t.gutschein_id
                     WHERE t.auftrag_id = a.id AND t.betrag < 0) AS gutschein_codes
            FROM auftraege a
            WHERE a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND a.lieferstatus != 'storniert' AND DATE(a.erstellt_am) $zeitraum
            ORDER BY a.erstellt_am
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Belege-basiert wie der Export (Belege-Umbau 2026-10-07): Rechnungen + Bons − Korrekturen
        // gegen Zahlungen. Vorher Auftragsbetrag − Zahlungen -- zeigte z.B. bei Teilabholung mit
        // Erstattung "offen 5,-" (Klicktest B7/47, A-2026-00068).
        require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';
        $zStmt = $this->db->prepare("SELECT buchungsdatum, betrag, notiz, zahlungsweg, kassen_bon_id FROM auftrag_zahlungen WHERE auftrag_id = ? ORDER BY buchungsdatum, id");
        $belegStmt = $this->db->prepare("
            SELECT 'Rechnung' COLLATE utf8mb4_unicode_ci AS art, rechnung_nr COLLATE utf8mb4_unicode_ci AS nr, bruttobetrag AS betrag FROM rechnungen WHERE auftrag_id = :a1
            UNION ALL
            SELECT 'Bon' COLLATE utf8mb4_unicode_ci, b.bon_nr COLLATE utf8mb4_unicode_ci, SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100))
              FROM kassen_bon_positionen bp JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
             WHERE bp.web_auftrag_id = :a2 AND bp.block IN ('auftrag', 'retour') GROUP BY b.id, b.bon_nr
            UNION ALL
            SELECT 'Korrektur' COLLATE utf8mb4_unicode_ci, gutschrift_nr COLLATE utf8mb4_unicode_ci, -bruttobetrag FROM gutschriften WHERE auftrag_id = :a3
        ");

        $auftragZeilen = [];
        foreach ($auftraege as $a) {
            $id = (int)$a['id'];
            $zStmt->execute([$id]);
            $zahlungen = $zStmt->fetchAll(PDO::FETCH_ASSOC);
            $belegStmt->execute([':a1' => $id, ':a2' => $id, ':a3' => $id]);
            $belege    = $belegStmt->fetchAll(PDO::FETCH_ASSOC);
            $gezahlt   = round(array_sum(array_column($zahlungen, 'betrag')), 2);
            $gutschein = round((float)$a['gutschein_betrag'], 2);

            $k = AuftragAbschluss::kriterien($id);
            $aussagekraeftig = $belege && $k['belegt'];
            $offen = $aussagekraeftig
                ? round(AuftragAbschluss::saldo($id), 2)
                : round((float)$a['bruttobetrag'] - $this->retourWert($id) + (float)$a['mahngebuehren'] - $gutschein - $gezahlt, 2);
            $nichtVerrechnet = !$k['belegt'];

            // Auffällig: geliefert ohne Beleg, als bezahlt markiert aber nicht ausgeglichen,
            // oder dem Kunden ist Geld zurückzuzahlen
            $auffaellig = $nichtVerrechnet
                || (in_array($a['zahlungsstatus'], ['bezahlt', 'erstattet'], true) && abs($offen) > 0.004)
                || $offen < -0.004;
            $interessant = $gutschein > 0 || count($zahlungen) > 1 || $auffaellig
                || in_array('Korrektur', array_column($belege, 'art'), true);
            if (!$alle && !$interessant) continue;

            // Buchung wie im Export: Zahlungen ohne Bon auf das Konto ihres Zahlungswegs,
            // Kassen-Zahlungen laufen über den Bon (oben), Online-Gutschein über 3230
            $konten = [];
            $ueberBon = 0.0;
            foreach ($zahlungen as $z) {
                if (!empty($z['kassen_bon_id'])) { $ueberBon += (float)$z['betrag']; continue; }
                $za = self::ZAHLUNGSWEG_ZU_ZAHLUNGSART[$z['zahlungsweg'] ?? ''] ?? (in_array($a['zahlungsart'], ['rechnung', 'gemischt'], true) ? 'vorkasse' : $a['zahlungsart']);
                $kn = $kontoVon($za);
                $konten[$kn] = round(($konten[$kn] ?? 0) + (float)$z['betrag'], 2);
            }
            if ($gutschein > 0) {
                $kn = $kontoVon('gutschein');
                $konten[$kn] = round(($konten[$kn] ?? 0) + $gutschein, 2);
            }
            foreach ($konten as $kn => $betrag) {
                $kontoSummen[$kn] = ($kontoSummen[$kn] ?? 0) + $betrag;
            }
            $auftragZeilen[] = $a + [
                'zahlungen'        => $zahlungen,
                'belege'           => $belege,
                'beleg_summe'      => round(array_sum(array_column($belege, 'betrag')), 2),
                'gezahlt'          => $gezahlt,
                'offen'            => $offen,
                'nicht_verrechnet' => $nichtVerrechnet,
                'konten'           => $konten,
                'ueber_bon'        => round($ueberBon, 2),
                'auffaellig'       => $auffaellig,
            ];
        }

        ksort($kontoSummen);
        return [
            'bons'      => $bonZeilen,
            'auftraege' => $auftragZeilen,
            'konten'    => array_map(fn($v) => round($v, 2), $kontoSummen),
        ];
    }
}
