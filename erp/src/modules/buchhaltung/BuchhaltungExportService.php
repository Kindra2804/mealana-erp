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
 * Drei Buchungsblöcke:
 * 1. "Einfache" Zahlarten (Kassenbons + Aufträge außer Rechnung/gemischt/kombi):
 *    Umsatz UND Zahlung fallen zusammen → Erlös+USt sofort gegen Zahlungsmittel,
 *    aggregiert pro Tag × Warengruppe × Steuersatz × Zahlungsart.
 * 2. Rechnung (Soll-Versteuerung, pro Auftrag einzeln wegen individuellem
 *    Debitorenkonto): Erlös+USt bei Auftragsdatum gegen Kundenkonto.
 * 3. Zahlungseingänge auf Rechnung (auftrag_zahlungen): Bank gegen Kundenkonto,
 *    zeitlich unabhängig von Block 2.
 *
 * Kassenbons mit mehreren Zahlungsmitteln (kombi, Gutschein + Rest) werden seit
 * 2026-09-30 anteilig auf Kassa/Bank/3230 aufgeteilt (gemischteBonsAufteilen). Zum
 * Gegenprüfen: kontrollListe() -> Seite buchhaltung/zahlungskontrolle.php.
 *
 * Seit 2026-09-30 zusätzlich: Versandkosten als Erlös (Gruppe "Versandkosten",
 * Steuersatz der überwiegenden Leistung, siehe Versandsteuer), Gutscheine über das
 * Anzahlungskonto 3230 (Verkauf = Artikelgruppe Gutscheine, Einlösung = Zahlungsart
 * "gutschein" bzw. bei Online-Aufträgen Umbuchung Bank → 3230). Aufträge der Kanäle
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
        $this->auftragUmsaetzeEinfach($von, $bis, $buchungen, $hinweise);
        $this->auftragUmsaetzeRechnung($von, $bis, $buchungen, $hinweise);
        $this->rechnungZahlungseingaenge($von, $bis, $buchungen, $hinweise);
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
        $rows = $this->db->query("
            SELECT DATE(b.erstellt_am) AS datum, b.zahlungsart,
                   ag.konto_nr, ag.name AS gruppe_name, bp.steuer_prozent,
                   SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)) AS brutto
            FROM kassen_bon_positionen bp
            INNER JOIN kassen_bons b ON b.id = bp.bon_id
            LEFT JOIN artikel a       ON a.id  = bp.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = COALESCE(a.artikel_gruppe_id, {$diversesGruppeId})
            WHERE b.typ = 'verkauf' AND b.storniert = 0
              AND NOT (" . self::GEMISCHT_BEDINGUNG . ")
              AND DATE(b.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
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
            SELECT b.id, b.bon_nr, DATE(b.erstellt_am) AS datum, b.zahlungsart, b.bruttobetrag,
                   b.bar_betrag, b.karten_betrag, b.gutschein_betrag, b.rueckgeld
            FROM kassen_bons b
            WHERE b.typ = 'verkauf' AND b.storniert = 0 AND (" . self::GEMISCHT_BEDINGUNG . ")
              AND DATE(b.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
        ")->fetchAll(PDO::FETCH_ASSOC);

        $posStmt = $this->db->prepare("
            SELECT ag.konto_nr, ag.name AS gruppe_name, bp.steuer_prozent,
                   SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)) AS brutto
            FROM kassen_bon_positionen bp
            LEFT JOIN artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = COALESCE(a.artikel_gruppe_id, {$diversesGruppeId})
            WHERE bp.bon_id = ?
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

    // ── Block 1b: Auftrags-Positionen, alle Zahlarten außer Rechnung/gemischt ──

    private function auftragUmsaetzeEinfach(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $rows = $this->db->query("
            SELECT DATE(a.erstellt_am) AS datum, a.zahlungsart, a.auftrag_nr,
                   ag.konto_nr, ag.name AS gruppe_name, ap.steuer_prozent,
                   SUM(ap.gesamtpreis_netto) AS netto
            FROM auftrag_positionen ap
            INNER JOIN auftraege a ON a.id = ap.auftrag_id
            LEFT JOIN artikel art       ON art.id = ap.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = art.artikel_gruppe_id
            WHERE a.zahlungsart NOT IN ('rechnung', 'gemischt')
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND a.lieferstatus != 'storniert'
              AND DATE(a.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
            GROUP BY datum, a.zahlungsart, ag.id, ag.konto_nr, ag.name, ap.steuer_prozent
        ")->fetchAll();

        foreach ($rows as $r) {
            $netto  = (float)$r['netto'];
            $satz   = (float)$r['steuer_prozent'];
            $brutto = round($netto * (1 + $satz / 100), 2);
            $this->erloesZeilenAnhaengen(
                $buchungen, $hinweise,
                datum: $r['datum'], belegnr: 'Auftrag-' . $r['datum'],
                erloesKonto: $r['konto_nr'], gruppeName: $r['gruppe_name'] ?? 'ohne Gruppe',
                satz: $satz, brutto: $brutto,
                zahlungsart: $r['zahlungsart'], quelle: 'Auftrag'
            );
        }

        // Versand + Gutschein-Einlösung pro Auftrag (Versandsteuersatz hängt an den
        // Positionen des einzelnen Auftrags, deshalb nicht in der Aggregation oben)
        $auftraege = $this->db->query("
            SELECT a.id, a.auftrag_nr, DATE(a.erstellt_am) AS datum, a.zahlungsart, a.versandkosten, a.gutschein_betrag
            FROM auftraege a
            WHERE a.zahlungsart NOT IN ('rechnung', 'gemischt')
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND a.lieferstatus != 'storniert'
              AND (a.versandkosten > 0 OR a.gutschein_betrag > 0)
              AND DATE(a.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
        ")->fetchAll();

        foreach ($auftraege as $a) {
            if ((float)$a['versandkosten'] > 0) {
                $v = Versandsteuer::aufteilen((float)$a['versandkosten'], $this->positionenFuerSteuer((int)$a['id']));
                $this->erloesZeilenAnhaengen(
                    $buchungen, $hinweise,
                    datum: $a['datum'], belegnr: 'Auftrag-' . $a['datum'],
                    erloesKonto: $this->versandKonto(), gruppeName: 'Versandkosten',
                    satz: $v['satz'], brutto: $v['brutto'],
                    zahlungsart: $a['zahlungsart'], quelle: 'Auftrag'
                );
            }
            if ((float)$a['gutschein_betrag'] > 0) {
                $this->gutscheinEinloesungAnhaengen($buchungen, $hinweise, $a, $this->zahlungsartKonto($a['zahlungsart'])['kontonummer'] ?? null);
            }
        }
    }

    /**
     * Online mit Gutschein bezahlt: Erlös wurde oben voll gegen das Zahlungsmittel
     * (z.B. Bank) gebucht, tatsächlich kam der Gutschein-Anteil aber aus dem
     * Anzahlungskonto 3230 → Umbuchung Zahlungsmittel (Haben) an 3230 (Soll).
     */
    private function gutscheinEinloesungAnhaengen(array &$buchungen, array &$hinweise, array $a, ?string $vonKonto): void
    {
        $gsKonto = $this->zahlungsartKonto('gutschein')['kontonummer'] ?? null;
        $betrag  = round((float)$a['gutschein_betrag'], 2);
        if (!$gsKonto || !$vonKonto) {
            $hinweise[] = "Auftrag {$a['auftrag_nr']}: Gutschein-Einlösung € " . number_format($betrag, 2, ',', '.') . " — Konto fehlt, manuell buchen";
            return;
        }
        $buchungen[] = [
            'datum' => $a['datum'], 'belegnr' => $a['auftrag_nr'], 'konto' => $vonKonto,
            'gegenkonto' => $gsKonto, 'betrag' => $betrag, 'soll_haben' => 'H', 'satz' => null,
            'text' => "Gutschein-Einlösung Auftrag {$a['auftrag_nr']}",
        ];
    }

    /** Positionen eines Auftrags (für den Versandsteuersatz der überwiegenden Leistung). */
    private function positionenFuerSteuer(int $auftragId): array
    {
        $stmt = $this->db->prepare("SELECT steuer_prozent, gesamtpreis_netto FROM auftrag_positionen WHERE auftrag_id = ?");
        $stmt->execute([$auftragId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    // ── Block 2: Rechnung (Soll-Versteuerung, pro Auftrag einzeln) ─────────────

    private function auftragUmsaetzeRechnung(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $auftraege = $this->db->query("
            SELECT a.id, a.auftrag_nr, DATE(a.erstellt_am) AS datum, k.debitorennummer,
                   a.versandkosten, a.gutschein_betrag
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.zahlungsart = 'rechnung' AND a.lieferstatus != 'storniert'
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND DATE(a.erstellt_am) BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
        ")->fetchAll();

        foreach ($auftraege as $auf) {
            if (!$auf['debitorennummer']) {
                $hinweise[] = "Rechnung {$auf['auftrag_nr']} ({$auf['datum']}): Kunde ohne Debitorennummer — manuell buchen";
                continue;
            }

            $positionen = $this->db->prepare("
                SELECT ag.konto_nr, ag.name AS gruppe_name, ap.steuer_prozent, SUM(ap.gesamtpreis_netto) AS netto
                FROM auftrag_positionen ap
                LEFT JOIN artikel art       ON art.id = ap.artikel_id
                LEFT JOIN artikel_gruppen ag ON ag.id = art.artikel_gruppe_id
                WHERE ap.auftrag_id = :id
                GROUP BY ag.id, ag.konto_nr, ag.name, ap.steuer_prozent
            ");
            $positionen->execute([':id' => $auf['id']]);

            foreach ($positionen->fetchAll() as $p) {
                if (!$p['konto_nr']) {
                    $hinweise[] = "Rechnung {$auf['auftrag_nr']}: Position ohne Artikelgruppe — manuell prüfen";
                    continue;
                }
                $netto  = round((float)$p['netto'], 2);
                $satz   = (float)$p['steuer_prozent'];
                $steuer = round($netto * $satz / 100, 2);

                $buchungen[] = [
                    'datum' => $auf['datum'], 'belegnr' => $auf['auftrag_nr'], 'konto' => $p['konto_nr'],
                    'gegenkonto' => $auf['debitorennummer'], 'betrag' => $netto, 'soll_haben' => 'H', 'satz' => $satz,
                    'text' => "Erlös {$p['gruppe_name']} Rechnung {$auf['auftrag_nr']}",
                ];

                if (abs($steuer) > 0.004) {
                    $ustKonto = $this->ustKonto($satz);
                    if (!$ustKonto) {
                        $hinweise[] = "Rechnung {$auf['auftrag_nr']}: Kein USt-Konto für $satz% — Steuerbetrag € " . number_format($steuer, 2, ',', '.') . " manuell buchen";
                    } else {
                        $buchungen[] = [
                            'datum' => $auf['datum'], 'belegnr' => $auf['auftrag_nr'], 'konto' => $ustKonto,
                            'gegenkonto' => $auf['debitorennummer'], 'betrag' => $steuer, 'soll_haben' => 'H', 'satz' => $satz,
                            'text' => "USt $satz% Rechnung {$auf['auftrag_nr']}",
                        ];
                    }
                }
            }

            // Versandkosten der Rechnung: Erlös (Gruppe Versandkosten) + USt gegen Kundenkonto
            if ((float)$auf['versandkosten'] > 0) {
                $v = Versandsteuer::aufteilen((float)$auf['versandkosten'], $this->positionenFuerSteuer((int)$auf['id']));
                if (!$this->versandKonto()) {
                    $hinweise[] = "Rechnung {$auf['auftrag_nr']}: keine Artikelgruppe \"Versandkosten\" — Versand € " . number_format($v['brutto'], 2, ',', '.') . " manuell buchen";
                } else {
                    $buchungen[] = [
                        'datum' => $auf['datum'], 'belegnr' => $auf['auftrag_nr'], 'konto' => $this->versandKonto(),
                        'gegenkonto' => $auf['debitorennummer'], 'betrag' => $v['netto'], 'soll_haben' => 'H', 'satz' => $v['satz'],
                        'text' => "Erlös Versandkosten Rechnung {$auf['auftrag_nr']}",
                    ];
                    $ustKonto = $v['steuer'] > 0 ? $this->ustKonto($v['satz']) : null;
                    if ($v['steuer'] > 0 && !$ustKonto) {
                        $hinweise[] = "Rechnung {$auf['auftrag_nr']}: Kein USt-Konto für {$v['satz']}% — Versand-Steuer € " . number_format($v['steuer'], 2, ',', '.') . " manuell buchen";
                    } elseif ($ustKonto) {
                        $buchungen[] = [
                            'datum' => $auf['datum'], 'belegnr' => $auf['auftrag_nr'], 'konto' => $ustKonto,
                            'gegenkonto' => $auf['debitorennummer'], 'betrag' => $v['steuer'], 'soll_haben' => 'H', 'satz' => $v['satz'],
                            'text' => "USt {$v['satz']}% Versand Rechnung {$auf['auftrag_nr']}",
                        ];
                    }
                }
            }

            // Mit Gutschein bezahlter Teil: Kundenforderung (Haben) an Anzahlungskonto 3230
            if ((float)$auf['gutschein_betrag'] > 0) {
                $this->gutscheinEinloesungAnhaengen($buchungen, $hinweise, $auf, $auf['debitorennummer']);
            }
        }
    }

    // ── Block 3: Zahlungseingänge auf Rechnung ──────────────────────────────

    private function rechnungZahlungseingaenge(string $von, string $bis, array &$buchungen, array &$hinweise): void
    {
        $rows = $this->db->query("
            SELECT z.buchungsdatum, z.betrag, a.auftrag_nr, k.debitorennummer
            FROM auftrag_zahlungen z
            INNER JOIN auftraege a ON a.id = z.auftrag_id
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.zahlungsart = 'rechnung'
              AND a.kanal NOT IN (" . self::AUSGESCHLOSSENE_KANAELE . ")
              AND z.buchungsdatum BETWEEN " . $this->db->quote($von) . " AND " . $this->db->quote($bis) . "
        ")->fetchAll();

        $bankKonto = $this->zahlungsartKonto('vorkasse')['kontonummer'] ?? null; // Rechnung wird i.d.R. per Überweisung beglichen -> Bank-Konto

        foreach ($rows as $r) {
            if (!$r['debitorennummer']) {
                $hinweise[] = "Zahlungseingang {$r['auftrag_nr']} ({$r['buchungsdatum']}): Kunde ohne Debitorennummer — manuell buchen";
                continue;
            }
            if (!$bankKonto) {
                $hinweise[] = "Zahlungseingang {$r['auftrag_nr']}: Kein Bank-Konto gemappt — manuell buchen";
                continue;
            }
            $buchungen[] = [
                'datum' => $r['buchungsdatum'], 'belegnr' => $r['auftrag_nr'], 'konto' => $r['debitorennummer'],
                'gegenkonto' => $bankKonto, 'betrag' => round((float)$r['betrag'], 2), 'soll_haben' => 'H', 'satz' => null,
                'text' => "Zahlungseingang Rechnung {$r['auftrag_nr']}",
            ];
        }
    }

    // ── Block 4: Mahngebühren (Rechnungskunden) ─────────────────────────────

    /**
     * Mahngebühr wird mit dem Versand der Mahnung zur Forderung: Kundenkonto an Erlöskonto
     * Mahngebühren (Artikelgruppe "Mahngebühren", nicht umsatzsteuerbar). Erlassene Gebühr
     * am Erlassdatum zurück. Die Zahlung selbst läuft unverändert über Block 3 (Bank an Kunde).
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

        $zStmt = $this->db->prepare("SELECT buchungsdatum, betrag, notiz FROM auftrag_zahlungen WHERE auftrag_id = ? ORDER BY buchungsdatum, id");
        $bankKonto = $kontoVon('vorkasse');

        $auftragZeilen = [];
        foreach ($auftraege as $a) {
            $zStmt->execute([(int)$a['id']]);
            $zahlungen = $zStmt->fetchAll(PDO::FETCH_ASSOC);
            $gezahlt   = round(array_sum(array_column($zahlungen, 'betrag')), 2);
            $gutschein = round((float)$a['gutschein_betrag'], 2);
            // Offene Mahngebühren gehören zum zu zahlenden Betrag (MahnwesenService)
            $offen     = round((float)$a['bruttobetrag'] + (float)$a['mahngebuehren'] - $gutschein - $gezahlt, 2);
            // Differenz nur relevant, wenn der Auftrag als bezahlt gilt (ausstehend = offen ist normal)
            $auffaellig = ($a['zahlungsstatus'] === 'bezahlt' && abs($offen) > 0.004) || $offen < -0.004;
            $interessant = $gutschein > 0 || count($zahlungen) > 1 || $auffaellig;

            if (!$alle && !$interessant) continue;

            $konten = [];
            if ($gezahlt != 0) {
                $k = $a['zahlungsart'] === 'rechnung' ? $bankKonto : $kontoVon($a['zahlungsart']);
                $konten[$k] = $gezahlt;
            }
            if ($gutschein > 0) {
                $k = $kontoVon('gutschein');
                $konten[$k] = ($konten[$k] ?? 0) + $gutschein;
            }
            foreach ($konten as $k => $betrag) {
                $kontoSummen[$k] = ($kontoSummen[$k] ?? 0) + $betrag;
            }
            $auftragZeilen[] = $a + [
                'zahlungen'  => $zahlungen,
                'gezahlt'    => $gezahlt,
                'offen'      => $offen,
                'konten'     => $konten,
                'auffaellig' => $auffaellig,
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
