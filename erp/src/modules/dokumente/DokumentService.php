<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/DokumentRepository.php';
require_once __DIR__ . '/PdfGenerator.php';
require_once __DIR__ . '/../konfigurator/KonfiguratorRepository.php';
require_once __DIR__ . '/../gutscheine/GutscheinRepository.php';
require_once __DIR__ . '/../auftraege/Versandsteuer.php';

/**
 * DokumentService – Erzeugt PDF-Dokumente für Aufträge.
 *
 * Lädt alle nötigen Daten (Auftrag, Positionen, Firma, Snapshots),
 * berechnet Steuer-Aufschlüsselung, und delegiert das Rendern an PdfGenerator.
 *
 * B2C (kein uid_nummer beim Kunden): Positionstabelle zeigt Brutto-Preise.
 * B2B (uid_nummer vorhanden):        Positionstabelle zeigt Netto-Preise.
 * Steuerblock im Footer: immer Netto + MwSt + Brutto, AT UStG §11.
 */
class DokumentService
{
    private PDO               $db;
    private DokumentRepository $repo;
    private PdfGenerator      $pdf;
    private KonfiguratorRepository $konfiguratorRepo;
    private GutscheinRepository $gutscheinRepo;

    private string $storagePfad;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->repo        = new DokumentRepository();
        $this->pdf         = new PdfGenerator();
        $this->konfiguratorRepo = new KonfiguratorRepository();
        $this->gutscheinRepo = new GutscheinRepository();
        $this->storagePfad = __DIR__ . '/../../../storage/dokumente';
    }

    // ──────────────────────────────────────────────────────────────
    // Öffentliche Methoden
    // ──────────────────────────────────────────────────────────────

    /**
     * Erzeugt eine (Teil-)Rechnung als PDF und speichert sie (Belege-Umbau 2026-10-07).
     *
     * Grundregel: jede Ware, die das Haus verlässt, steht auf genau einem Beleg. Eine
     * Rechnung nimmt deshalb pro Position nur, was noch NICHT verrechnet ist
     * (menge - menge_verrechnet; den Zähler erhöhen Rechnungen UND Kassenbons).
     *
     * $mengen: [auftrag_position_id => menge]. null = automatisch alles, was ausgegeben
     * (versendet bzw. abgeholt), aber noch nicht verrechnet ist -- der Normalfall für
     * Packplatz, Kasse und den manuellen Button. Versandkosten kommen auf die erste
     * Rechnung des Auftrags.
     *
     * Rückgabe: erfolg, rechnung_id, rechnung_nr, dateiname, pfad, bruttobetrag, offen.
     * erfolg=false + nichts_offen=true heißt: es gab nichts zu verrechnen (kein Fehler).
     */
    public function erstelleRechnung(int $auftragId, int $benutzerId, ?array $mengen = null, ?int $lieferungId = null): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];
        $auftrag = $daten['auftrag'];

        if ($auftrag['kanal'] === 'kasse') {
            return ['erfolg' => false, 'fehler' => 'Kassen-Auftrag: der Kassenbon ist bereits der Beleg.'];
        }
        if ($auftrag['lieferstatus'] === 'storniert') {
            return ['erfolg' => false, 'fehler' => 'Der Auftrag ist storniert.'];
        }

        $mengen ??= $this->unverrechneteMengen($auftrag, $daten['positionen']);

        $rePos = [];
        foreach ($daten['positionen'] as $p) {
            $frei = (int)$p['menge'] - (int)$p['menge_verrechnet'];
            $m    = (int)min((float)($mengen[(int)$p['id']] ?? 0), $frei);
            if ($m <= 0) continue;
            $z = Positionsrechnung::ausPosition($p, (float)$m);
            $rePos[] = array_merge($p, [
                'menge'              => $m,
                'gesamtpreis_netto'  => $z['netto'],
                'gesamtpreis_brutto' => $z['brutto'],
                'zeile_steuer'       => $z['steuer'],
            ]);
        }
        if (!$rePos) {
            return ['erfolg' => false, 'nichts_offen' => true,
                    'fehler' => 'Keine ausgelieferte, noch nicht verrechnete Ware — es gibt nichts zu verrechnen.'];
        }

        $versand = $this->versandNochOffen($auftrag) ? (float)$auftrag['versandkosten'] : 0.0;
        $summen  = $this->berechneSummen($rePos, $versand);
        $faellig = $this->berechneFaelligkeit($auftrag);

        $eigeneTx = !$this->db->inTransaction();
        if ($eigeneTx) $this->db->beginTransaction();
        try {
            $rechnungsNr = $this->repo->naechsteNummer('rechnung', (int)date('Y'));
            $dateiname   = 'R-' . $auftrag['auftrag_nr'] . '_' . $rechnungsNr . '.pdf';

            // faellig_am ist die Basis fürs Mahnwesen (Erinnerung/Mahnstufen ab Fälligkeit)
            $this->db->prepare("
                INSERT INTO rechnungen (auftrag_id, lieferung_id, rechnung_nr, nettobetrag, steuerbetrag, bruttobetrag,
                                        versandkosten_brutto, faellig_am, leistungsdatum, dateiname, erstellt_von, erstellt_am)
                VALUES (:aid, :lid, :nr, :netto, :steuer, :brutto, :versand, :faellig, CURDATE(), :datei, :buid, NOW())
            ")->execute([
                ':aid'     => $auftragId,
                ':lid'     => $lieferungId,
                ':nr'      => $rechnungsNr,
                ':netto'   => $summen['netto_gesamt'],
                ':steuer'  => $summen['steuer_gesamt'],
                ':brutto'  => $summen['brutto_gesamt'],
                ':versand' => $versand,
                ':faellig' => $faellig['datum'],
                ':datei'   => $dateiname,
                ':buid'    => $benutzerId,
            ]);
            $rechnungId = (int)$this->db->lastInsertId();

            $posStmt = $this->db->prepare("
                INSERT INTO rechnung_positionen
                    (rechnung_id, auftrag_position_id, artikel_id, bezeichnung, menge, einzelpreis_netto,
                     steuer_prozent, rabatt_prozent, preisbasis, netto, steuer, brutto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $verrechnetStmt = $this->db->prepare("UPDATE auftrag_positionen SET menge_verrechnet = menge_verrechnet + ? WHERE id = ?");
            foreach ($rePos as $p) {
                $posStmt->execute([
                    $rechnungId, $p['id'], $p['artikel_id'] ?: null, $p['bezeichnung'], $p['menge'],
                    $p['einzelpreis_netto'], $p['steuer_prozent'], $p['rabatt_prozent'] ?? 0,
                    $p['preisbasis'] ?? 'brutto', $p['gesamtpreis_netto'], $p['zeile_steuer'], $p['gesamtpreis_brutto'],
                ]);
                $verrechnetStmt->execute([$p['menge'], $p['id']]);
            }

            // "Noch ausständig" zum Rechnungszeitpunkt einfrieren (Nachdruck zeigt denselben Stand)
            $ausstaendig = $auftrag['kanal'] === 'haendler' ? [] : $this->ausstaendigePositionen($auftragId);
            $this->db->prepare("UPDATE rechnungen SET ausstaendig_json = ? WHERE id = ?")
                ->execute([$ausstaendig ? json_encode($ausstaendig, JSON_UNESCAPED_UNICODE) : null, $rechnungId]);

            if ($eigeneTx) $this->db->commit();
        } catch (Throwable $e) {
            if ($eigeneTx && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $pfad = $this->renderRechnung($rechnungId);
        $this->repo->speichern($auftragId, 'rechnung', $dateiname, $benutzerId, $rechnungsNr);
        $info = $this->zahlungsInfo($rechnungId);

        return [
            'erfolg'       => true,
            'rechnung_id'  => $rechnungId,
            'rechnung_nr'  => $rechnungsNr,
            'dateiname'    => $dateiname,
            'pfad'         => $pfad,
            'auftrag_id'   => $auftragId,
            'bruttobetrag' => $summen['brutto_gesamt'],
            'offen'        => $info['offen'],
        ];
    }

    /**
     * Vollrechnung über alle noch nicht verrechneten Mengen, unabhängig von der
     * Auslieferung -- für Händler-Verkaufsmeldungen und reine Gutschein-Bestellungen.
     */
    public function erstelleVollrechnung(int $auftragId, int $benutzerId): array
    {
        $stmt = $this->db->prepare("SELECT id, menge - menge_verrechnet FROM auftrag_positionen WHERE auftrag_id = ?");
        $stmt->execute([$auftragId]);
        return $this->erstelleRechnung($auftragId, $benutzerId, $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * Ausgegeben, aber noch nicht verrechnet -- pro Position.
     * Versand: menge_geliefert. Abholung: menge_abgeholt abzüglich Zurückgenommenem
     * (menge_abgeholt enthält auch "will er nicht", das als Retoure läuft).
     */
    private function unverrechneteMengen(array $auftrag, array $positionen): array
    {
        $istAbholung = ($auftrag['lieferart'] ?? '') === 'abholung';
        $mengen = [];
        foreach ($positionen as $p) {
            $ausgegeben = $istAbholung
                ? (int)$p['menge_abgeholt'] - (int)$p['menge_retourniert']
                : (int)$p['menge_geliefert'];
            $offen = $ausgegeben - (int)$p['menge_verrechnet'];
            if ($offen > 0) $mengen[(int)$p['id']] = $offen;
        }
        return $mengen;
    }

    /**
     * Kassenbons, auf denen Ware dieses (Web-)Auftrags kassiert wurde -- Originalbeleg für eine
     * Rechnungskorrektur, wenn es keine Rechnung gibt (Abholung an der Kasse bezahlt).
     * @return array<int, array{nr:string, datum:string}>
     */
    public function kassenbonBelege(int $auftragId): array
    {
        $s = $this->db->prepare("
            SELECT DISTINCT b.bon_nr AS nr, DATE_FORMAT(b.erstellt_am, '%d.%m.%Y') AS datum
            FROM kassen_bon_positionen bp
            JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
            WHERE bp.web_auftrag_id = ? AND bp.block = 'auftrag'
            ORDER BY b.id
        ");
        $s->execute([$auftragId]);
        return array_map(fn($r) => ['nr' => 'Kassenbon ' . $r['nr'], 'datum' => $r['datum']], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Versandkosten, die verrechnet und noch nicht per Rechnungskorrektur erstattet sind. */
    public function versandErstattbar(int $auftragId): float
    {
        $v = $this->db->prepare("
            SELECT (SELECT COALESCE(SUM(versandkosten_brutto), 0) FROM rechnungen WHERE auftrag_id = :a1)
                 - (SELECT COALESCE(SUM(versandkosten_brutto), 0) FROM gutschriften WHERE auftrag_id = :a2)
        ");
        $v->execute([':a1' => $auftragId, ':a2' => $auftragId]);
        return max(0.0, round((float)$v->fetchColumn(), 2));
    }

    /** Positionen der Rechnung mit den Versandkosten -- für deren Steuersatz bei einer Korrektur. */
    private function versandSteuerBasis(int $auftragId): ?array
    {
        $s = $this->db->prepare("
            SELECT rp.steuer_prozent, rp.netto AS gesamtpreis_netto
            FROM rechnung_positionen rp
            WHERE rp.rechnung_id = (SELECT id FROM rechnungen WHERE auftrag_id = ? AND versandkosten_brutto > 0 ORDER BY id LIMIT 1)
        ");
        $s->execute([$auftragId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: null;
    }

    /** Versandkosten stehen noch auf keiner (nicht stornierten) Rechnung dieses Auftrags. */
    private function versandNochOffen(array $auftrag): bool
    {
        if ((float)($auftrag['versandkosten'] ?? 0) <= 0) return false;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 AND versandkosten_brutto > 0");
        $stmt->execute([(int)$auftrag['id']]);
        return (int)$stmt->fetchColumn() === 0;
    }

    /** Positionen, die noch nicht ausgeliefert/abgeholt sind (für "Noch ausständig", ohne Preise). */
    private function ausstaendigePositionen(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.bezeichnung, a.artikelnummer,
                   p.menge - IF(au.lieferart = 'abholung', p.menge_abgeholt, p.menge_geliefert) AS rest
            FROM auftrag_positionen p
            JOIN auftraege au ON au.id = p.auftrag_id
            LEFT JOIN artikel a ON a.id = p.artikel_id
            WHERE p.auftrag_id = ?
            ORDER BY p.sort_order, p.id
        ");
        $stmt->execute([$auftragId]);
        return array_values(array_filter(
            array_map(fn($r) => ['bezeichnung' => $r['bezeichnung'], 'artikelnummer' => $r['artikelnummer'] ?? '', 'menge' => (int)$r['rest']],
                $stmt->fetchAll(PDO::FETCH_ASSOC)),
            fn($r) => $r['menge'] > 0
        ));
    }

    /**
     * Rendert eine gespeicherte Rechnung (neu) als PDF -- aus den eingefrorenen
     * rechnung_positionen, mit der AKTUELLEN Zahlungsinfo. Wird beim Erstellen und für
     * einen Nachdruck verwendet. Gibt den absoluten Dateipfad zurück.
     */
    public function renderRechnung(int $rechnungId, ?string $zielDateiname = null): string
    {
        $stmt = $this->db->prepare("SELECT * FROM rechnungen WHERE id = ?");
        $stmt->execute([$rechnungId]);
        $rechnung = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rechnung) throw new RuntimeException("Rechnung $rechnungId nicht gefunden.");

        $daten = $this->ladeDaten((int)$rechnung['auftrag_id']);

        // Anzeige-Felder (Artikelnummer, Konfiguration) von der Auftragsposition übernehmen
        $auftragPos = [];
        foreach ($daten['positionen'] as $p) $auftragPos[(int)$p['id']] = $p;

        $posStmt = $this->db->prepare("SELECT * FROM rechnung_positionen WHERE rechnung_id = ? ORDER BY id");
        $posStmt->execute([$rechnungId]);
        $positionen = [];
        foreach ($posStmt->fetchAll(PDO::FETCH_ASSOC) as $rp) {
            $ap = $auftragPos[(int)$rp['auftrag_position_id']] ?? [];
            $z  = Positionsrechnung::ausPosition($rp, (float)$rp['menge']);
            $positionen[] = [
                'bezeichnung'        => $rp['bezeichnung'],
                'artikelnummer'      => $ap['artikelnummer'] ?? '',
                'konfig_anzeige'     => $ap['konfig_anzeige'] ?? null,
                'menge'              => (int)$rp['menge'],
                'einzelpreis_netto'  => (float)$rp['einzelpreis_netto'],
                'einzelpreis_brutto' => $z['einzel_brutto'],
                'gesamtpreis_netto'  => (float)$rp['netto'],
                'gesamtpreis_brutto' => (float)$rp['brutto'],
                'steuer_prozent'     => (float)$rp['steuer_prozent'],
                'rabatt_prozent'     => (float)$rp['rabatt_prozent'],
                'preisbasis'         => $rp['preisbasis'],
            ];
        }

        $anzahlRechnungen = $this->db->prepare("SELECT COUNT(*) FROM rechnungen WHERE auftrag_id = ? AND storniert = 0");
        $anzahlRechnungen->execute([(int)$rechnung['auftrag_id']]);
        $ausstaendig = json_decode((string)($rechnung['ausstaendig_json'] ?? ''), true) ?: [];

        $daten['positionen'] = $positionen;
        $daten['summen']     = $this->berechneSummen($positionen, (float)$rechnung['versandkosten_brutto']);
        // Gutschein-Zahlungen stehen in der Zahlungsinfo (unten), nicht im Steuerblock
        $daten['gutschein_zahlungen'] = [];
        $daten['gutschein_summe']     = 0.0;
        $faelligText = $rechnung['faellig_am']
            ? max(0, (int)round((strtotime($rechnung['faellig_am']) - strtotime(substr($rechnung['erstellt_am'], 0, 10))) / 86400))
            : null;
        $daten['rechnung'] = [
            'nr'              => $rechnung['rechnung_nr'],
            'datum'           => date('d.m.Y', strtotime($rechnung['erstellt_am'])),
            'leistungsdatum'  => date('d.m.Y', strtotime($rechnung['leistungsdatum'] ?: $rechnung['erstellt_am'])),
            'faellig'         => $rechnung['faellig_am'] ? date('d.m.Y', strtotime($rechnung['faellig_am'])) : '',
            'faellig_text'    => $faelligText === null ? '' : ($faelligText === 0 ? 'sofort' : $faelligText . ' Tage'),
            'ist_teilrechnung'=> $ausstaendig || (int)$anzahlRechnungen->fetchColumn() > 1,
            'ausstaendig'     => $ausstaendig,
        ];
        $daten['zahlungsinfo'] = $this->zahlungsInfo($rechnungId);

        $dateiname = $zielDateiname ?? ($rechnung['dateiname'] ?: 'R-' . $daten['auftrag']['auftrag_nr'] . '_' . $rechnung['rechnung_nr'] . '.pdf');
        $dateipfad = $this->storagePfad . '/' . $rechnung['auftrag_id'] . '/' . $dateiname;
        $this->pdf->generiere('rechnung/standard.html.twig', $daten, $dateipfad);
        return $dateipfad;
    }

    /**
     * Zahlungen des Auftrags + wie viel davon auf DIESE Rechnung entfällt.
     * Zahlungen gelten pro Auftrag; auf mehrere Teilrechnungen werden sie in
     * Rechnungsreihenfolge verteilt (ältere Rechnung zuerst).
     *
     * @return array{zahlungen: array, angerechnet: float, offen: float, brutto: float}
     */
    public function zahlungsInfo(int $rechnungId): array
    {
        $stmt = $this->db->prepare("SELECT id, auftrag_id, bruttobetrag FROM rechnungen WHERE id = ?");
        $stmt->execute([$rechnungId]);
        $rechnung  = $stmt->fetch(PDO::FETCH_ASSOC);
        $auftragId = (int)$rechnung['auftrag_id'];
        $brutto    = (float)$rechnung['bruttobetrag'];

        $zahlungen = $this->zahlungenFuerAnzeige($auftragId);
        $summe     = array_sum(array_column($zahlungen, 'betrag'));

        $vorher = $this->db->prepare("SELECT COALESCE(SUM(bruttobetrag), 0) FROM rechnungen WHERE auftrag_id = ? AND storniert = 0 AND id < ?");
        $vorher->execute([$auftragId, $rechnungId]);
        $verfuegbar  = $summe - (float)$vorher->fetchColumn();
        $angerechnet = round(max(0.0, min($brutto, $verfuegbar)), 2);

        return [
            'zahlungen'   => $zahlungen,
            'angerechnet' => $angerechnet,
            'offen'       => round($brutto - $angerechnet, 2),
            'brutto'      => $brutto,
        ];
    }

    /**
     * Alle Zahlungen eines Auftrags mit lesbarem Text, z.B.
     * "per Überweisung" / "bar an Kasse 1 (Zahlbeleg K1-2026-000012)" / "mit Gutschein ABC".
     */
    public function zahlungenFuerAnzeige(int $auftragId): array
    {
        $auftrag = $this->db->prepare("SELECT zahlungsart FROM auftraege WHERE id = ?");
        $auftrag->execute([$auftragId]);
        $zahlungsart = (string)$auftrag->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT z.betrag, z.buchungsdatum, z.zahlungsweg, z.notiz, b.bon_nr, k.name AS kasse_name,
                   EXISTS(SELECT 1 FROM kassen_bon_positionen bp WHERE bp.bon_id = b.id AND bp.block = 'zahlung') AS ist_zahlbeleg
            FROM auftrag_zahlungen z
            LEFT JOIN kassen_bons b ON b.id = z.kassen_bon_id
            LEFT JOIN kassen k ON k.id = b.kasse_id
            WHERE z.auftrag_id = ?
            ORDER BY z.buchungsdatum, z.id
        ");
        $stmt->execute([$auftragId]);
        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $liste[] = [
                'datum'  => date('d.m.Y', strtotime($z['buchungsdatum'])),
                'betrag' => (float)$z['betrag'],
                'text'   => self::zahlungsText($z, $zahlungsart),
            ];
        }

        $gs = $this->db->prepare("
            SELECT g.code, -t.betrag AS betrag, t.erstellt_am
            FROM gutschein_transaktionen t
            JOIN gutscheine g ON g.id = t.gutschein_id
            WHERE t.auftrag_id = ? AND t.betrag < 0
            ORDER BY t.id
        ");
        $gs->execute([$auftragId]);
        foreach ($gs->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $liste[] = [
                'datum'  => date('d.m.Y', strtotime($g['erstellt_am'])),
                'betrag' => (float)$g['betrag'],
                'text'   => 'mit Gutschein ' . $g['code'],
            ];
        }
        return $liste;
    }

    /**
     * Saldo aus den BELEGEN eines Auftrags: Rechnungen + an der Kasse kassierte Auftragsware
     * (Bon-Zeilen inkl. Kassen-Retouren) − Rechnungskorrekturen − Zahlungen (inkl.
     * Rückerstattungen) − Gutschein-Einlösungen + offene Mahngebühren.
     * > 0 = Kunde schuldet noch, < 0 = wir schulden dem Kunden (Rückerstattung offen).
     * Basis für Zahlbeleg, Mahnwesen, Abschluss-Prüfung und Anzeige im Auftrag.
     */
    public function offenerRechnungsbetrag(int $auftragId): float
    {
        $q = fn(string $sql) => (function () use ($sql, $auftragId) {
            $s = $this->db->prepare($sql);
            $s->execute([$auftragId]);
            return (float)$s->fetchColumn();
        })();
        $bons        = $q("SELECT COALESCE(SUM(bp.menge * bp.einzelpreis_brutto * (1 - bp.rabatt_prozent / 100)), 0)
                           FROM kassen_bon_positionen bp JOIN kassen_bons b ON b.id = bp.bon_id AND b.typ = 'verkauf' AND b.storniert = 0
                           WHERE bp.web_auftrag_id = ? AND bp.block IN ('auftrag', 'retour')");
        $rechnungen  = $q("SELECT COALESCE(SUM(bruttobetrag), 0) FROM rechnungen WHERE auftrag_id = ?") + $bons;
        $gutschriften= $q("SELECT COALESCE(SUM(bruttobetrag), 0) FROM gutschriften WHERE auftrag_id = ?");
        $zahlungen   = $q("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = ?");
        $gutscheine  = $q("SELECT COALESCE(-SUM(betrag), 0) FROM gutschein_transaktionen WHERE auftrag_id = ? AND betrag < 0");
        $mahn        = $q("SELECT COALESCE(SUM(gebuehr), 0) FROM mahnungen WHERE auftrag_id = ? AND status = 'versendet' AND gebuehr_erlassen_am IS NULL");
        return round($rechnungen - $gutschriften - $zahlungen - $gutscheine + $mahn, 2);
    }

    public const ZAHLUNGSWEGE = [
        'ueberweisung' => 'per Überweisung',
        'paypal'       => 'per PayPal',
        'bar'          => 'bar',
        'karte'        => 'mit Karte',
        'gutschein'    => 'mit Gutschein',
        'nachnahme'    => 'per Nachnahme',
        'sonstig'      => 'Zahlung',
    ];

    private static function zahlungsText(array $z, string $zahlungsart): string
    {
        if ((float)$z['betrag'] < 0) {
            return 'Rückerstattung' . ($z['notiz'] ? ' — ' . $z['notiz'] : '');
        }
        if (!empty($z['bon_nr'])) {
            $weg = self::ZAHLUNGSWEGE[$z['zahlungsweg'] ?? 'bar'] ?? 'bar';
            return $weg . ' an ' . ($z['kasse_name'] ?: 'der Kasse')
                . ' (' . (!empty($z['ist_zahlbeleg']) ? 'Zahlbeleg' : 'Bon') . ' ' . $z['bon_nr'] . ')';
        }
        if (!empty($z['zahlungsweg'])) {
            return self::ZAHLUNGSWEGE[$z['zahlungsweg']] ?? 'Zahlung';
        }
        // Altbestand ohne Zahlungsweg: aus Notiz bzw. Zahlungsart ableiten
        if (preg_match('/Kasse.*Bon\s+(\S+)/u', (string)$z['notiz'], $m)) {
            return 'an der Kasse (Bon ' . $m[1] . ')';
        }
        return match ($zahlungsart) {
            'vorkasse', 'rechnung' => 'per Überweisung',
            'paypal'               => 'per PayPal',
            'nachnahme'            => 'per Nachnahme',
            'bar'                  => 'bar',
            'karte'                => 'mit Karte',
            default                => 'Zahlung',
        };
    }

    /**
     * Erzeugt eine Auftragsbestätigung als PDF.
     */
    public function erstelleAuftragsbestaetigung(int $auftragId, int $benutzerId): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        $dateiname = 'AB-' . $daten['auftrag']['auftrag_nr'] . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('auftragsbestaetigung/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'auftragsbestaetigung', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'auftrag_id' => $auftragId];
    }

    /**
     * Erzeugt einen Lieferschein als PDF.
     */
    public function erstelleLieferschein(int $auftragId, int $benutzerId): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];
        $daten['mit_charge'] = $this->zeigeChargeAufLieferschein();

        $dateiname = 'LS-' . $daten['auftrag']['auftrag_nr'] . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('lieferschein/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'lieferschein', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'auftrag_id' => $auftragId];
    }

    /**
     * Erzeugt einen Abholzettel mit Barcode als PDF.
     * Barcode kodiert die Auftragsnummer → POS scannt und öffnet Auftrag.
     */
    public function erstelleAbholzettel(int $auftragId, int $benutzerId): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        $daten['barcode'] = $this->pdf->barcodeAlsBase64($daten['auftrag']['auftrag_nr']);

        $dateiname = 'AZ-' . $daten['auftrag']['auftrag_nr'] . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('abholzettel/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'abholzettel', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'auftrag_id' => $auftragId];
    }

    /**
     * Erstellt das Gutschein-PDF (Design+Code+Betrag+Grußtext). Nicht an einen
     * Auftrag gebunden (Gutscheine können auch ganz ohne Bestellung entstehen,
     * z.B. manuell im ERP) -- eigener Storage-Pfad, keine auftrag_dokumente-Zeile.
     */
    public function erstelleGutscheinPdf(int $gutscheinId): string
    {
        $gutschein = $this->gutscheinRepo->findById($gutscheinId);
        if (!$gutschein) {
            throw new RuntimeException("Gutschein $gutscheinId nicht gefunden.");
        }

        $firma = $this->repo->ladeFirmaDaten();
        $shop  = $this->ladeShop((int)($gutschein['shop_id'] ?? 1));
        $logoPfad   = __DIR__ . '/../../../public/' . ($shop['logo_pfad'] ?? 'img/logos/mealana.png');
        $logoBase64 = file_exists($logoPfad) ? base64_encode(file_get_contents($logoPfad)) : '';

        $hintergrundBase64 = null;
        if (!empty($gutschein['hintergrundbild_pfad'])) {
            $bildPfad = __DIR__ . '/../../../public/' . $gutschein['hintergrundbild_pfad'];
            if (file_exists($bildPfad)) {
                $hintergrundBase64 = base64_encode(file_get_contents($bildPfad));
            }
        }

        $daten = [
            'code'                   => $gutschein['code'],
            'betrag'                 => $gutschein['betrag'],
            'gueltig_bis'            => $gutschein['gueltig_bis'],
            'empfaenger_name'        => $gutschein['empfaenger_name'],
            'grusstext'              => $gutschein['grusstext'],
            'firma'                  => $firma,
            'logo_base64'            => $logoBase64,
            'hintergrundbild_base64' => $hintergrundBase64,
            // Code 128 zum Scannen an der Kasse (Bezahlen/Abfragen: Scanner tippt Code + Enter)
            'barcode_base64'         => $this->pdf->barcodeHochkantAlsBase64($gutschein['code']),
        ];

        $dateiname = 'Gutschein-' . $gutschein['code'] . '.pdf';
        $dateipfad = $this->storagePfad . '/gutscheine/' . $gutscheinId . '/' . $dateiname;

        $this->pdf->generiere('gutschein/standard.html.twig', $daten, $dateipfad);

        return $dateipfad;
    }

    /**
     * Gibt die aktive (nicht stornierte) Rechnung eines Auftrags zurück, oder null.
     */
    public function getRechnung(int $auftragId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM rechnungen
            WHERE auftrag_id = :id AND storniert = 0
            ORDER BY erstellt_am DESC LIMIT 1
        ");
        $stmt->execute([':id' => $auftragId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Erstellt eine Gutschrift (Vollstorno oder Teilgutschrift) als PDF.
     */
    public function erstelleGutschrift(
        int    $auftragId,
        int    $rechnungId,
        int    $benutzerId,
        string $gsArt,
        array  $positionen,   // leer bei Vollstorno
        string $grund,
        bool   $lagerRueckbuchen,
        bool   $versandErstatten = false
    ): array {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        // Original-Beleg: Rechnung -- oder, wenn die Ware an der Kasse bezahlt wurde, der Kassenbon
        // ($rechnungId 0). Korrigierbar ist in beiden Fällen nur Verrechnetes (menge_verrechnet).
        $rechnung = null;
        if ($rechnungId > 0) {
            $stmt = $this->db->prepare("SELECT * FROM rechnungen WHERE id = :id AND storniert = 0");
            $stmt->execute([':id' => $rechnungId]);
            $rechnung = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$rechnung) return ['erfolg' => false, 'fehler' => 'Rechnung nicht gefunden oder bereits storniert.'];
        }
        $bonBelege = $this->kassenbonBelege($auftragId);
        if (!$rechnung && !$bonBelege) return ['erfolg' => false, 'fehler' => 'Zu diesem Auftrag gibt es keinen Beleg (Rechnung oder Kassenbon).'];

        $gsNr = $this->repo->naechsteNummer('gutschrift', (int)date('Y'));

        // GS-Positionen und Summen bestimmen. Vollstorno kreditiert nur das, was noch NICHT
        // gutgeschrieben wurde (menge - menge_gutgeschrieben; zählt Kasse, Packplatz-Retoure
        // und frühere Gutschriften) — sonst würde eine bereits erstattete Menge hier ein
        // zweites Mal gutgeschrieben. Läuft dafür über dieselbe Positions-Berechnung wie die
        // Teilgutschrift, nur mit allen offenen Mengen statt einer manuellen Auswahl.
        // Gutgeschrieben werden kann nur, was verrechnet ist (Rechnung oder Bon) -- seit dem
        // Belege-Umbau 2026-10-07 menge_verrechnet statt menge.
        if ($gsArt === 'vollstorno') {
            $positionen = [];
            foreach ($daten['positionen'] as $orig) {
                $offen = min((int)$orig['menge'], (int)$orig['menge_verrechnet']) - (int)($orig['menge_gutgeschrieben'] ?? 0);
                if ($offen <= 0) continue;
                $positionen[] = [
                    'pos_id'            => (int)$orig['id'],
                    'menge'             => $offen,
                    'steuer_prozent'    => $orig['steuer_prozent'],
                    'einzelpreis_netto' => $orig['einzelpreis_netto'],
                ];
            }
        }

        $gsPosi = [];
        foreach ($positionen as $item) {
            foreach ($daten['positionen'] as $orig) {
                if ((int)$orig['id'] === $item['pos_id']) {
                    $p = $orig;
                    $p['menge']              = $item['menge'];
                    $z = Positionsrechnung::ausPosition($orig, (float)$item['menge']);
                    $p['gesamtpreis_netto']  = $z['netto'];
                    $p['gesamtpreis_brutto'] = $z['brutto'];
                    $gsPosi[] = $p;
                    break;
                }
            }
        }
        // Versandkosten mit erstatten (Stornorechnung, oder auf Wunsch bei einer Korrektur):
        // höchstens, was auf Rechnungen stand und noch nicht korrigiert wurde
        $gsVersand = $versandErstatten ? $this->versandErstattbar($auftragId) : 0.0;
        if (!$gsPosi && $gsVersand <= 0.004) {
            return ['erfolg' => false, 'fehler' => 'Es gibt nichts mehr zu korrigieren.'];
        }
        $gsSummen = $this->berechneSummen($gsPosi, $gsVersand, $this->versandSteuerBasis($auftragId));

        // Bezug auf die Originalrechnung(en): Nummer + Ausstellungsdatum (Pflichtangabe
        // Korrekturbeleg). Bei Teilrechnungen können die Positionen auf mehreren stehen.
        $posIds = array_map(fn($p) => (int)$p['id'], $gsPosi);
        $originale = [];
        if ($posIds) {
            $ph = implode(',', array_fill(0, count($posIds), '?'));
            $o = $this->db->prepare("
                SELECT DISTINCT CONCAT('Rechnung ', r.rechnung_nr) AS nr, DATE_FORMAT(r.erstellt_am, '%d.%m.%Y') AS datum
                FROM rechnung_positionen rp JOIN rechnungen r ON r.id = rp.rechnung_id
                WHERE rp.auftrag_position_id IN ($ph) ORDER BY r.id
            ");
            $o->execute($posIds);
            $originale = $o->fetchAll(PDO::FETCH_ASSOC);
        }
        // Ware, die an der Kasse bezahlt wurde: Originalbeleg ist der Kassenbon
        if (!$originale && $bonBelege) $originale = $bonBelege;
        if (!$originale && $rechnung) {
            $originale = [['nr' => 'Rechnung ' . $rechnung['rechnung_nr'], 'datum' => date('d.m.Y', strtotime($rechnung['erstellt_am']))]];
        }

        // Daten für Template. Rechnungskorrektur/Stornorechnung (eine "Gutschrift" stellt
        // in AT nur der Leistungsempfänger aus) -- Mengen, Entgelte und Steuer mit Minus.
        $daten['gutschrift'] = [
            'nr'           => $gsNr,
            'datum'        => date('d.m.Y'),
            'rechnung_nr'  => $rechnung['rechnung_nr'] ?? ($originale[0]['nr'] ?? ''),
            'originale'    => $originale,
            'art'          => $gsArt,
            'grund'        => $grund,
        ];
        $daten['positionen'] = array_map(fn($p) => array_merge($p, [
            'menge'              => -(int)$p['menge'],
            'gesamtpreis_netto'  => -$p['gesamtpreis_netto'],
            'gesamtpreis_brutto' => -$p['gesamtpreis_brutto'],
        ]), $gsPosi);
        $daten['summen'] = array_merge($gsSummen, [
            'bloecke'       => array_map(fn($b) => array_merge($b, ['netto' => -$b['netto'], 'steuer' => -$b['steuer'], 'brutto' => -$b['brutto']]), $gsSummen['bloecke']),
            'netto_gesamt'  => -$gsSummen['netto_gesamt'],
            'steuer_gesamt' => -$gsSummen['steuer_gesamt'],
            'brutto_gesamt' => -$gsSummen['brutto_gesamt'],
            'versand'       => $gsSummen['versand'] ? array_merge($gsSummen['versand'], [
                'netto' => -$gsSummen['versand']['netto'], 'steuer' => -$gsSummen['versand']['steuer'], 'brutto' => -$gsSummen['versand']['brutto'],
            ]) : null,
        ]);
        // Auf einer Gutschrift (Rechnungskorrektur) keine Gutschein-Zahlungszeilen
        $daten['gutschein_zahlungen'] = [];
        $daten['gutschein_summe']     = 0.0;

        $dateiname = 'GS-' . $daten['auftrag']['auftrag_nr'] . '_' . $gsNr . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('gutschrift/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'gutschrift', $dateiname, $benutzerId, $gsNr);

        // Gutschrift als Beleg mit Beträgen speichern (Basis für Buchhaltung: Erlösminderung)
        $this->db->prepare("
            INSERT INTO gutschriften (gutschrift_nr, auftrag_id, rechnung_id, art, grund,
                                      nettobetrag, steuerbetrag, bruttobetrag, versandkosten_brutto, dateiname, erstellt_von)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$gsNr, $auftragId, $rechnungId ?: null, $gsArt, $grund ?: null,
                     $gsSummen['netto_gesamt'], $gsSummen['steuer_gesamt'], $gsSummen['brutto_gesamt'], $gsVersand, $dateiname, $benutzerId]);
        $gutschriftId = (int)$this->db->lastInsertId();
        $gsPosStmt = $this->db->prepare("
            INSERT INTO gutschrift_positionen
                (gutschrift_id, auftrag_position_id, artikel_id, bezeichnung, menge, einzelpreis_netto,
                 steuer_prozent, rabatt_prozent, preisbasis, netto, steuer, brutto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($gsPosi as $p) {
            $gsPosStmt->execute([
                $gutschriftId, $p['id'], $p['artikel_id'] ?: null, $p['bezeichnung'], (int)$p['menge'],
                $p['einzelpreis_netto'], $p['steuer_prozent'], $p['rabatt_prozent'] ?? 0, $p['preisbasis'] ?? 'brutto',
                $p['gesamtpreis_netto'], round($p['gesamtpreis_brutto'] - $p['gesamtpreis_netto'], 2), $p['gesamtpreis_brutto'],
            ]);
        }

        // Bei Vollstorno alle Rechnungen des Auftrags als storniert markieren (bei Teilrechnungen
        // gibt es mehrere). Buchhaltung: Rechnungen bleiben Erlös, die Gutschrift mindert ihn.
        if ($gsArt === 'vollstorno') {
            $this->db->prepare("UPDATE rechnungen SET storniert = 1 WHERE auftrag_id = :id")
                ->execute([':id' => $auftragId]);
            // Zahlungsstatus NICHT mehr pauschal auf "erstattet": erstattet ist erst, wenn das Geld
            // zurückgezahlt ist (Rückerstattung buchen / Kasse). Status kommt aus dem Beleg-Saldo (unten).
        }

        // Gutgeschriebene Menge an der Original-Position mitzählen -- gemeinsamer Zähler
        // mit Kasse und Packplatz-Retoure gegen Doppel-Gutschriften.
        $gutgeschriebenStmt = $this->db->prepare("
            UPDATE auftrag_positionen SET menge_gutgeschrieben = menge_gutgeschrieben + :m WHERE id = :id
        ");
        foreach ($gsPosi as $p) {
            $gutgeschriebenStmt->execute([':m' => (int)$p['menge'], ':id' => (int)$p['id']]);
        }

        // "Lager zurückbuchen": NICHT mehr direkt einbuchen, sondern an den Packplatz
        // (Rücklagerungen) weiterleiten -- dort wird die Ware geprüft und Zustand, Lager
        // und Charge entschieden (RetourService). Chargen + Lager sind aus den
        // Lagerbewegungen des Verkaufs vorbefüllt. Früher: direkte Buchung ohne Charge
        // fest in Lager 1, die bei jeder Gutschrift eine neue Null-Charge-Zeile anlegte.
        if ($lagerRueckbuchen && !empty($gsPosi)) {
            require_once __DIR__ . '/../packplatz/RetourService.php';
            require_once __DIR__ . '/../packplatz/RuecklagerungRepository.php';
            $retour = new RetourService();
            $rlRepo = new RuecklagerungRepository();
            $retourStmt = $this->db->prepare("
                UPDATE auftrag_positionen SET menge_retourniert = menge_retourniert + :m WHERE id = :id
            ");
            foreach ($gsPosi as $p) {
                if (empty($p['artikel_id'])) continue;
                $teile = $retour->verteileAufChargen(
                    $retour->verkaufteChargen($auftragId, (int)$p['artikel_id']),
                    (float)$p['menge']
                );
                foreach ($teile as $t) {
                    $rlRepo->insert([
                        'quelle'              => 'gutschrift',
                        'gutschrift_nr'       => $gsNr,
                        'auftrag_id'          => $auftragId,
                        'auftrag_nr'          => $daten['auftrag']['auftrag_nr'],
                        'auftrag_position_id' => (int)$p['id'],
                        'artikel_id'          => (int)$p['artikel_id'],
                        'bezeichnung'         => $p['bezeichnung'],
                        'menge'               => (int)round($t['menge']),
                        'charge'              => $t['charge'],
                        'lager_vorschlag_id'  => $t['lager_id'],
                    ]);
                }
                $retourStmt->execute([':m' => (int)$p['menge'], ':id' => (int)$p['id']]);
            }
        }

        // Gutschrift kann eine offene Retoure erledigen -> Auftrag ggf. wieder abschließen
        require_once __DIR__ . '/../auftraege/AuftragAbschluss.php';
        AuftragAbschluss::zahlungsstatusAusBelegen($auftragId, $benutzerId);
        AuftragAbschluss::pruefe($auftragId, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'gs_nr' => $gsNr, 'gutschrift_id' => $gutschriftId,
                'bruttobetrag' => $gsSummen['brutto_gesamt']];
    }

    /**
     * Erzeugt einen Lieferschein für eine bestimmte (Teil-)Lieferung.
     * $positionen enthält nur die tatsächlich versendeten Artikel dieser Lieferung.
     * Erwartet pro Position: bezeichnung, artikelnummer, menge (Pflicht).
     */
    public function erstelleLieferscheinFuerLieferung(int $auftragId, int $benutzerId, array $positionen): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        $daten['positionen'] = $positionen;
        $daten['mit_charge'] = $this->zeigeChargeAufLieferschein();

        $dateiname = 'LS-' . $daten['auftrag']['auftrag_nr'] . '-' . date('His') . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('lieferschein/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'lieferschein', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'auftrag_id' => $auftragId];
    }

    /**
     * Gibt Pfad + Dateiname + neu_erstellt-Flag zurück.
     * neu_erstellt=true → Rechnung wurde gerade frisch angelegt (noch kein Mail verschickt).
     * neu_erstellt=false → Rechnung existierte bereits (Mail wurde ggf. schon gesendet).
     */

    /**
     * Liefert alle gespeicherten Dokumente eines Auftrags.
     */
    public function getDokumente(int $auftragId): array
    {
        return $this->repo->ladeByAuftrag($auftragId);
    }

    /**
     * Gibt den absoluten Dateipfad eines gespeicherten Dokuments zurück.
     */
    public function getDateipfad(int $auftragId, string $dateiname): string
    {
        return $this->storagePfad . '/' . $auftragId . '/' . $dateiname;
    }

    // ──────────────────────────────────────────────────────────────
    // Interne Helfer
    // ──────────────────────────────────────────────────────────────

    /**
     * Lädt alle Daten die Templates brauchen: Auftrag, Positionen, Firma, Kunde, Summen.
     */
    /** Einstellung lieferschein_charge_anzeigen (System-Tab), Default aus (Migration 141). */
    private function zeigeChargeAufLieferschein(): bool
    {
        return ($this->repo->ladeFirmaDaten()['lieferschein_charge_anzeigen'] ?? '0') === '1';
    }

    private function ladeDaten(int $auftragId): ?array
    {
        $auftrag = $this->ladeAuftrag($auftragId);
        if (!$auftrag) return null;

        $positionen = $this->ladePositionen($auftragId);
        $firma      = $this->repo->ladeFirmaDaten();
        $kunde      = $this->decodeSnapshot($auftrag['kunden_snapshot'] ?? '{}');
        $lieferadr  = $this->decodeSnapshot($auftrag['lieferadresse_snapshot'] ?? '{}');
        $rechnungadr= $this->decodeSnapshot($auftrag['rechnungsadresse_snapshot'] ?? '{}');

        // Händler-Rechnungen (kanal 'haendler') immer mit Netto-Preisen, auch ohne UID
        $istB2B      = !empty($kunde['uid_nummer']) || ($auftrag['kanal'] ?? '') === 'haendler';
        $summen      = $this->berechneSummen($positionen, (float)($auftrag['versandkosten'] ?? 0));

        // Mit Gutschein bezahlte Beträge (Zahlungsmittel, Mehrzweckgutschein) -- die
        // Positionen bleiben zum vollen Preis und voll versteuert, der Gutschein wird
        // erst NACH dem Gesamtbetrag abgezogen. Quelle: Einlöse-Buchungen mit Bezug auf
        // diesen Auftrag (Online-Shop: ShopBestellungSyncService), ein Eintrag pro Code.
        $gsStmt = $this->db->prepare("
            SELECT g.code, -SUM(t.betrag) AS betrag
            FROM gutschein_transaktionen t
            JOIN gutscheine g ON g.id = t.gutschein_id
            WHERE t.auftrag_id = :id AND t.betrag < 0
            GROUP BY g.id, g.code
            ORDER BY MIN(t.id)
        ");
        $gsStmt->execute([':id' => $auftragId]);
        $gutscheinZahlungen = $gsStmt->fetchAll(PDO::FETCH_ASSOC);
        $gutscheinSumme = round(array_sum(array_map(fn($g) => (float)$g['betrag'], $gutscheinZahlungen)), 2);
        $kleinuntern = ($firma['kleinunternehmer'] ?? '0') === '1';

        $shop       = $this->ladeShop((int)($auftrag['shop_id'] ?? 1));
        $logoPfad   = __DIR__ . '/../../../public/' . ($shop['logo_pfad'] ?? 'img/logos/mealana.png');
        $logoBase64 = file_exists($logoPfad) ? base64_encode(file_get_contents($logoPfad)) : '';

        return [
            'firma'       => $firma,
            'shop'        => $shop,
            'auftrag'     => $auftrag,
            'kunde'       => $kunde,
            'lieferadr'   => $lieferadr,
            'rechnungadr' => $rechnungadr,
            'positionen'  => $positionen,
            'summen'      => $summen,
            'gutschein_zahlungen' => $gutscheinZahlungen,
            'gutschein_summe'     => $gutscheinSumme,
            'ist_b2b'     => $istB2B,
            'kleinuntern' => $kleinuntern,
            'logo_base64' => $logoBase64,
            'datum_heute' => date('d.m.Y'),
        ];
    }

    private function ladeAuftrag(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, b.formularname AS bearbeiter_name
            FROM auftraege a
            LEFT JOIN benutzer b ON b.id = a.erstellt_von
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function ladePositionen(int $auftragId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   a.artikelnummer,
                   a.lieferzeit_text,
                   COALESCE(SUM(lb.bestand), 0) AS lagerbestand
            FROM auftrag_positionen p
            LEFT JOIN artikel a ON a.id = p.artikel_id
            LEFT JOIN lagerbestand lb ON lb.artikel_id = p.artikel_id
            WHERE p.auftrag_id = :id
            GROUP BY p.id, a.artikelnummer, a.lieferzeit_text
            ORDER BY p.sort_order, p.id
        ");
        $stmt->execute([':id' => $auftragId]);
        $positionen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Konfigurator-Auswahl (z.B. auf Bestellung gefertigte Schilder) auf Rechnung/AB/
        // Lieferschein sichtbar machen -- konfig_freitext (Shop-Bestellungen) hat Vorrang,
        // da er auch bei einer inzwischen veralteten Preis-Matrix immer vollständig ist;
        // die strukturierte position_konfiguration (z.B. Kasse-Bestellungen) ist der Fallback.
        // Siehe [[project_konfigurator_modul]], Fund 2026-08-29.
        $konfigProPosition = $this->konfiguratorRepo->findAuswahlFuerReferenzIds('auftrag_positionen', array_column($positionen, 'id'));
        foreach ($positionen as &$pos) {
            if (!empty($pos['konfig_freitext'])) {
                $pos['konfig_anzeige'] = str_replace("\n", ' · ', $pos['konfig_freitext']);
            } elseif (!empty($konfigProPosition[$pos['id']])) {
                $pos['konfig_anzeige'] = implode(' · ', array_map(
                    fn($k) => $k['achse_name'] . ': ' . $k['wert'],
                    $konfigProPosition[$pos['id']]
                ));
            }
            // Brutto-Preise für die B2C-Anzeige. Standen früher nur in berechneSummen(),
            // das auf einer KOPIE der Positionen arbeitet -- die Werte kamen nie im
            // Template an, B2C-Dokumente zeigten seit 25.06. pro Zeile 0,00 (Fund 2026-09-30).
            $z = Positionsrechnung::ausPosition($pos);
            $pos['einzelpreis_brutto'] = $z['einzel_brutto'];
            $pos['gesamtpreis_brutto'] = $z['brutto'];
        }
        unset($pos);

        return $positionen;
    }

    /**
     * Berechnet Netto/MwSt/Brutto gruppiert nach Steuersatz.
     * Positionswerte sind in der DB netto gespeichert (gesamtpreis_netto).
     * Versandkosten (brutto) fließen mit dem Satz der überwiegenden Leistung in den
     * passenden Steuerblock und in den Gesamtbetrag ein (Versandsteuer -- gleiche
     * Rechnung wie AuftragService, damit Rechnung und Auftragsbetrag übereinstimmen).
     * Bis 2026-09-30 wurde der Versand nur angezeigt, aber nicht mitgerechnet.
     */
    private function berechneSummen(array $positionen, float $versandBrutto = 0.0, ?array $versandBasis = null): array
    {
        $blöcke       = [];  // ['20.00' => ['netto' => x, 'steuer' => y, 'brutto' => z]]
        $nettoGesamt  = 0.0;
        $steuerGesamt = 0.0;

        foreach ($positionen as $pos) {
            $z      = Positionsrechnung::ausPosition($pos);
            $netto  = $z['netto'];
            $satz   = (float)($pos['steuer_prozent'] ?? 0);
            $steuer = $z['steuer'];
            $brutto = $z['brutto'];

            $key = number_format($satz, 2);
            if (!isset($blöcke[$key])) {
                $blöcke[$key] = ['satz' => $satz, 'netto' => 0.0, 'steuer' => 0.0, 'brutto' => 0.0];
            }
            $blöcke[$key]['netto']  += $netto;
            $blöcke[$key]['steuer'] += $steuer;
            $blöcke[$key]['brutto'] += $brutto;

            $nettoGesamt  += $netto;
            $steuerGesamt += $steuer;
        }

        $versand = null;
        if ($versandBrutto > 0) {
            // Satz der überwiegenden Leistung -- bei einer Korrektur die der Original-Rechnung
            $versand = Versandsteuer::aufteilen($versandBrutto, $versandBasis ?? $positionen);
            $key = number_format($versand['satz'], 2);
            if (!isset($blöcke[$key])) {
                $blöcke[$key] = ['satz' => $versand['satz'], 'netto' => 0.0, 'steuer' => 0.0, 'brutto' => 0.0];
            }
            $blöcke[$key]['netto']  += $versand['netto'];
            $blöcke[$key]['steuer'] += $versand['steuer'];
            $blöcke[$key]['brutto'] += $versand['brutto'];
            $nettoGesamt  += $versand['netto'];
            $steuerGesamt += $versand['steuer'];
        }

        ksort($blöcke);

        return [
            'bloecke'       => array_values($blöcke),
            'netto_gesamt'  => round($nettoGesamt, 2),
            'steuer_gesamt' => round($steuerGesamt, 2),
            'brutto_gesamt' => round($nettoGesamt + $steuerGesamt, 2),
            'versand'       => $versand, // null = kein Versand (auch auf Gutschriften)
        ];
    }

    private function ladeShop(int $shopId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM shops WHERE id = :id");
        $stmt->execute([':id' => $shopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'id' => 1, 'slug' => 'mealana', 'name' => 'MEALANA KG',
            'logo_pfad' => 'img/logos/mealana.png', 'sub_marke' => 0,
        ];
    }

    private function decodeSnapshot(?string $json): array
    {
        if (empty($json)) return [];
        return json_decode($json, true) ?: [];
    }

    /**
     * Fälligkeit der Rechnung: Zahlungsbedingung des Auftrags, sonst die des Kunden, sonst
     * 14 Tage (bar: 4 Tage, wie bisher). Liefert ['datum' => 'Y-m-d', 'text' => '14 Tage'].
     */
    private function berechneFaelligkeit(array $auftrag): array
    {
        $tage = null;
        $bedingungId = $auftrag['zahlungsbedingung_id'] ?? null;
        if (!$bedingungId && !empty($auftrag['kunden_id'])) {
            $k = $this->db->prepare("SELECT zahlungsbedingung_id FROM kunden WHERE id = ?");
            $k->execute([$auftrag['kunden_id']]);
            $bedingungId = $k->fetchColumn() ?: null;
        }
        if ($bedingungId) {
            $z = $this->db->prepare("SELECT netto_tage FROM zahlungsbedingungen WHERE id = ?");
            $z->execute([$bedingungId]);
            $wert = $z->fetchColumn();
            if ($wert !== false && $wert !== null) $tage = (int)$wert;
        }
        $tage ??= ($auftrag['zahlungsart'] ?? '') === 'bar' ? 4 : 14;

        return [
            'datum' => date('Y-m-d', strtotime('+' . $tage . ' days')),
            'text'  => $tage === 0 ? 'sofort' : $tage . ' Tage',
        ];
    }

    /**
     * Mahnung bzw. Zahlungserinnerung als PDF (Verkauf → Mahnwesen, Freigabe).
     * $mahnung: typ (mahnung1|mahnung2), offen_betrag, gebuehr, gebuehren_vorher, neue_frist (Y-m-d)
     */
    public function erstelleMahnung(int $auftragId, array $mahnung, int $benutzerId): array
    {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];
        $rechnung = $this->getRechnung($auftragId);
        if (!$rechnung) return ['erfolg' => false, 'fehler' => 'Zum Auftrag gibt es keine Rechnung.'];

        $stufe = $mahnung['typ'] === 'mahnung2' ? 2 : 1;
        $daten['rechnung'] = [
            'nr'      => $rechnung['rechnung_nr'],
            'datum'   => date('d.m.Y', strtotime($rechnung['erstellt_am'])),
            'faellig' => $rechnung['faellig_am'] ? date('d.m.Y', strtotime($rechnung['faellig_am'])) : '',
            'brutto'  => (float)$rechnung['bruttobetrag'],
        ];
        $daten['mahnung'] = [
            'stufe'            => $stufe,
            'titel'            => $stufe === 2 ? '2. Mahnung' : '1. Mahnung',
            'offen_betrag'     => (float)$mahnung['offen_betrag'],
            'gebuehren_vorher' => (float)($mahnung['gebuehren_vorher'] ?? 0),
            'gebuehr'          => (float)$mahnung['gebuehr'],
            'gesamt'           => round((float)$mahnung['offen_betrag'] + (float)($mahnung['gebuehren_vorher'] ?? 0) + (float)$mahnung['gebuehr'], 2),
            'neue_frist'       => date('d.m.Y', strtotime($mahnung['neue_frist'])),
        ];

        $dateiname = 'M' . $stufe . '-' . $daten['auftrag']['auftrag_nr'] . '_' . date('Ymd_His') . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('mahnung/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'mahnung', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'pfad' => $dateipfad];
    }
}
