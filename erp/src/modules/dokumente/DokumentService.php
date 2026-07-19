<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/DokumentRepository.php';
require_once __DIR__ . '/PdfGenerator.php';

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

    private string $storagePfad;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->repo        = new DokumentRepository();
        $this->pdf         = new PdfGenerator();
        $this->storagePfad = __DIR__ . '/../../../storage/dokumente';
    }

    // ──────────────────────────────────────────────────────────────
    // Öffentliche Methoden
    // ──────────────────────────────────────────────────────────────

    /**
     * Erzeugt eine Rechnung als PDF und speichert sie.
     * Gibt den relativen Dateipfad zurück (für Download-Link).
     */
    public function erstelleRechnung(int $auftragId, int $benutzerId): array
    {
        // Sicherung: pro Auftrag nur eine aktive Rechnung
        $vorhandene = $this->getRechnung($auftragId);
        if ($vorhandene) {
            return [
                'erfolg'  => false,
                'fehler'  => 'Rechnung ' . $vorhandene['rechnung_nr'] . ' existiert bereits. '
                           . 'Für Korrekturen bitte Gutschrift erstellen.',
            ];
        }

        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        $rechnungsNr = $this->repo->naechsteNummer('rechnung', (int)date('Y'));

        $this->db->prepare("
            INSERT INTO rechnungen (auftrag_id, rechnung_nr, nettobetrag, steuerbetrag, bruttobetrag, erstellt_von, erstellt_am)
            VALUES (:aid, :nr, :netto, :steuer, :brutto, :buid, NOW())
            ON DUPLICATE KEY UPDATE rechnung_nr = rechnung_nr
        ")->execute([
            ':aid'    => $auftragId,
            ':nr'     => $rechnungsNr,
            ':netto'  => $daten['summen']['netto_gesamt'],
            ':steuer' => $daten['summen']['steuer_gesamt'],
            ':brutto' => $daten['summen']['brutto_gesamt'],
            ':buid'   => $benutzerId,
        ]);

        $daten['rechnung'] = [
            'nr'        => $rechnungsNr,
            'datum'     => date('d.m.Y'),
            'faellig'   => $this->berechneFaelligDatum($daten['auftrag']),
        ];

        $dateiname = 'R-' . $daten['auftrag']['auftrag_nr'] . '_' . $rechnungsNr . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('rechnung/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'rechnung', $dateiname, $benutzerId);

        return ['erfolg' => true, 'dateiname' => $dateiname, 'auftrag_id' => $auftragId];
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
        bool   $lagerRueckbuchen
    ): array {
        $daten = $this->ladeDaten($auftragId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Auftrag nicht gefunden.'];

        // Original-Rechnung laden
        $stmt = $this->db->prepare("SELECT * FROM rechnungen WHERE id = :id AND storniert = 0");
        $stmt->execute([':id' => $rechnungId]);
        $rechnung = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rechnung) return ['erfolg' => false, 'fehler' => 'Rechnung nicht gefunden oder bereits storniert.'];

        $gsNr = $this->repo->naechsteNummer('gutschrift', (int)date('Y'));

        // GS-Positionen und Summen bestimmen. Vollstorno kreditiert nur das, was noch NICHT
        // über die Kasse retourniert wurde (menge - menge_retourniert) — sonst würde eine
        // bereits erstattete Menge hier ein zweites Mal gutgeschrieben. Läuft dafür über
        // dieselbe Positions-Berechnung wie die Teilgutschrift, nur mit allen offenen Mengen
        // statt einer manuellen Auswahl.
        if ($gsArt === 'vollstorno') {
            $positionen = [];
            foreach ($daten['positionen'] as $orig) {
                $offen = (int)$orig['menge'] - (int)($orig['menge_retourniert'] ?? 0);
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
                    $p['gesamtpreis_netto']  = round($item['einzelpreis_netto'] * $item['menge'] * (1 - ($orig['rabatt_prozent'] ?? 0) / 100), 4);
                    $p['gesamtpreis_brutto'] = round($p['gesamtpreis_netto'] * (1 + $item['steuer_prozent'] / 100), 2);
                    $gsPosi[] = $p;
                    break;
                }
            }
        }
        $gsSummen = $this->berechneSummen($gsPosi);

        // Daten für Template
        $daten['gutschrift'] = [
            'nr'           => $gsNr,
            'datum'        => date('d.m.Y'),
            'rechnung_nr'  => $rechnung['rechnung_nr'],
            'art'          => $gsArt,
            'grund'        => $grund,
        ];
        $daten['positionen'] = $gsPosi;
        $daten['summen']     = $gsSummen;

        $dateiname = 'GS-' . $daten['auftrag']['auftrag_nr'] . '_' . $gsNr . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $auftragId . '/' . $dateiname;

        $this->pdf->generiere('gutschrift/standard.html.twig', $daten, $dateipfad);
        $this->repo->speichern($auftragId, 'gutschrift', $dateiname, $benutzerId);

        // Original-Rechnung bei Vollstorno als storniert markieren
        if ($gsArt === 'vollstorno') {
            $this->db->prepare("UPDATE rechnungen SET storniert = 1 WHERE id = :id")
                ->execute([':id' => $rechnungId]);
            // Zahlungsstatus auf 'erstattet' setzen
            $this->db->prepare("UPDATE auftraege SET zahlungsstatus = 'erstattet' WHERE id = :id")
                ->execute([':id' => $auftragId]);
        }

        // Lager zurückbuchen wenn gewünscht
        if ($lagerRueckbuchen && !empty($gsPosi)) {
            $lagerId = 1; // Standard-Lager
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(lb.bestand), 0) AS bestand
                FROM lagerbestand lb WHERE lb.lager_id = :lid AND lb.artikel_id = :aid
            ");
            foreach ($gsPosi as $p) {
                if (empty($p['artikel_id'])) continue;
                $stmt->execute([':lid' => $lagerId, ':aid' => $p['artikel_id']]);
                $bestand = (float)$stmt->fetchColumn();
                $this->db->prepare("
                    INSERT INTO lager_bewegungen
                        (lager_id, artikel_id, bewegungstyp, menge, bestand_vorher, bestand_nachher,
                         referenz, notiz, benutzer_id)
                    VALUES
                        (:lid, :aid, 'eingang', :menge, :vorher, :nachher, :ref, :notiz, :buid)
                ")->execute([
                    ':lid'    => $lagerId,
                    ':aid'    => $p['artikel_id'],
                    ':menge'  => $p['menge'],
                    ':vorher' => $bestand,
                    ':nachher'=> $bestand + $p['menge'],
                    ':ref'    => $gsNr,
                    ':notiz'  => 'Rückbuchung Gutschrift ' . $gsNr,
                    ':buid'   => $benutzerId,
                ]);
                // lagerbestand-Tabelle aktualisieren
                $this->db->prepare("
                    INSERT INTO lagerbestand (lager_id, artikel_id, bestand)
                    VALUES (:lid, :aid, :menge)
                    ON DUPLICATE KEY UPDATE bestand = bestand + :menge2
                ")->execute([
                    ':lid'   => $lagerId,
                    ':aid'   => $p['artikel_id'],
                    ':menge' => $p['menge'],
                    ':menge2'=> $p['menge'],
                ]);
            }
        }

        return ['erfolg' => true, 'dateiname' => $dateiname, 'gs_nr' => $gsNr];
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
    public function holeOderErstelleRechnung(int $auftragId, int $benutzerId): array
    {
        foreach ($this->repo->ladeByAuftrag($auftragId) as $dok) {
            if ($dok['typ'] === 'rechnung') {
                $pfad = $this->storagePfad . '/' . $auftragId . '/' . $dok['dateiname'];
                if (file_exists($pfad)) {
                    return ['erfolg' => true, 'pfad' => $pfad, 'dateiname' => $dok['dateiname'], 'neu_erstellt' => false];
                }
            }
        }

        $result = $this->erstelleRechnung($auftragId, $benutzerId);
        if (!$result['erfolg']) return ['erfolg' => false];

        $pfad = $this->storagePfad . '/' . $auftragId . '/' . $result['dateiname'];
        return ['erfolg' => true, 'pfad' => $pfad, 'dateiname' => $result['dateiname'], 'neu_erstellt' => true];
    }

    /** @deprecated Nutze holeOderErstelleRechnung() für den neu_erstellt-Flag */
    public function holeOderErstelleRechnungPfad(int $auftragId, int $benutzerId): ?string
    {
        $res = $this->holeOderErstelleRechnung($auftragId, $benutzerId);
        return $res['erfolg'] ? $res['pfad'] : null;
    }

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

        $istB2B      = !empty($kunde['uid_nummer']);
        $summen      = $this->berechneSummen($positionen);
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Berechnet Netto/MwSt/Brutto gruppiert nach Steuersatz.
     * Positionswerte sind in der DB netto gespeichert (gesamtpreis_netto).
     */
    private function berechneSummen(array $positionen): array
    {
        $blöcke       = [];  // ['20.00' => ['netto' => x, 'steuer' => y, 'brutto' => z]]
        $nettoGesamt  = 0.0;
        $steuerGesamt = 0.0;

        foreach ($positionen as $pos) {
            $netto  = (float)($pos['gesamtpreis_netto'] ?? 0);
            $satz   = (float)($pos['steuer_prozent']    ?? 0);
            $steuer = round($netto * $satz / 100, 2);
            $brutto = round($netto + $steuer, 2);

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

        // Brutto-Einzelpreise für B2C-Anzeige berechnen
        foreach ($positionen as &$pos) {
            $netto  = (float)($pos['einzelpreis_netto'] ?? 0);
            $satz   = (float)($pos['steuer_prozent']    ?? 0);
            $pos['einzelpreis_brutto'] = round($netto * (1 + $satz / 100), 2);

            $gNetto  = (float)($pos['gesamtpreis_netto'] ?? 0);
            $pos['gesamtpreis_brutto'] = round($gNetto * (1 + $satz / 100), 2);
        }
        unset($pos);

        ksort($blöcke);

        return [
            'bloecke'       => array_values($blöcke),
            'netto_gesamt'  => round($nettoGesamt, 2),
            'steuer_gesamt' => round($steuerGesamt, 2),
            'brutto_gesamt' => round($nettoGesamt + $steuerGesamt, 2),
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

    private function berechneFaelligDatum(array $auftrag): string
    {
        $tage = 14;
        if (!empty($auftrag['zahlungsart']) && $auftrag['zahlungsart'] === 'bar') {
            $tage = 4;
        }
        return date('d.m.Y', strtotime('+' . $tage . ' days'));
    }
}
