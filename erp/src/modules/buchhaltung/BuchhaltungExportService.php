<?php

require_once __DIR__ . '/../../core/Database.php';

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
 * Gutschein/gemischt/kombi werden NICHT automatisch gebucht (zu selten bisher,
 * Fehlerrisiko höher als Nutzen) — tauchen stattdessen als "manuell_pruefen"
 * Hinweise auf, die im Export sichtbar bleiben statt still falsch zu buchen.
 */
class BuchhaltungExportService
{
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

        $rows = $this->db->query("
            SELECT DATE(b.erstellt_am) AS datum, b.zahlungsart,
                   ag.konto_nr, ag.name AS gruppe_name, bp.steuer_prozent,
                   SUM(ABS(bp.menge) * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)) AS brutto
            FROM kassen_bon_positionen bp
            INNER JOIN kassen_bons b ON b.id = bp.bon_id
            LEFT JOIN artikel a       ON a.id  = bp.artikel_id
            LEFT JOIN artikel_gruppen ag ON ag.id = COALESCE(a.artikel_gruppe_id, {$diversesGruppeId})
            WHERE b.typ = 'verkauf' AND b.storniert = 0
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
    }

    /** Gemeinsame Erlös+USt-Zeilen-Erzeugung für Kasse und "einfache" Aufträge. */
    private function erloesZeilenAnhaengen(
        array &$buchungen, array &$hinweise,
        string $datum, string $belegnr, ?string $erloesKonto, string $gruppeName,
        float $satz, float $brutto, string $zahlungsart, string $quelle
    ): void {
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
            'text' => "Erlös $gruppeName $quelle ($zahlungsart)",
        ];

        if ($steuer > 0) {
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
            SELECT a.id, a.auftrag_nr, DATE(a.erstellt_am) AS datum, k.debitorennummer
            FROM auftraege a
            LEFT JOIN kunden k ON k.id = a.kunden_id
            WHERE a.zahlungsart = 'rechnung' AND a.lieferstatus != 'storniert'
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

                if ($steuer > 0) {
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
}
