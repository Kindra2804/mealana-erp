<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/BestellungService.php';
require_once __DIR__ . '/../dokumente/PdfGenerator.php';
require_once __DIR__ . '/../dokumente/DokumentRepository.php';

/**
 * BestellDokumentService – Erzeugt die Bestellungs-PDF (an den Lieferanten) und
 * verwaltet deren Mail-Versand.
 *
 * Eigenständig statt Erweiterung von DokumentService, weil dort Daten/Adresse
 * fest auf "Kunde empfängt" ausgelegt sind (auftrag_dokumente, Kunden-Snapshot).
 * Hier ist die Firma der Absender und der Lieferant der Empfänger — umgekehrte Richtung.
 *
 * PDFs liegen unter storage/dokumente/bestellungen/{bestellung_id}/, damit die ID
 * nicht mit auftrag_id im gemeinsamen storage/dokumente/{id}/-Baum kollidiert.
 */
class BestellDokumentService
{
    private PDO           $db;
    private PdfGenerator  $pdf;
    private DokumentRepository $dokRepo;
    private string        $storagePfad;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->pdf         = new PdfGenerator();
        $this->dokRepo     = new DokumentRepository();
        $this->storagePfad = __DIR__ . '/../../../storage/dokumente/bestellungen';
    }

    /**
     * Erzeugt die Bestellungs-PDF und speichert sie im Dokumente-Verlauf.
     * Kann mehrfach aufgerufen werden (z.B. nach Mengenänderung) — jeder Aufruf
     * legt eine neue Zeile in bestellung_dokumente an, alte Dateien bleiben erhalten.
     */
    public function erstellePdf(int $bestellungId, int $benutzerId): array
    {
        $daten = $this->ladeDaten($bestellungId);
        if (!$daten) return ['erfolg' => false, 'fehler' => 'Bestellung nicht gefunden.'];

        $dateiname = $daten['bestellung']['nummer'] . '_' . date('YmdHis') . '.pdf';
        $dateipfad = $this->storagePfad . '/' . $bestellungId . '/' . $dateiname;

        $this->pdf->generiere('bestellung/standard.html.twig', $daten, $dateipfad);

        $stmt = $this->db->prepare("
            INSERT INTO bestellung_dokumente (bestellung_id, typ, dateiname, erstellt_von, erstellt_am)
            VALUES (:bid, 'bestellung', :dateiname, :buid, NOW())
        ");
        $stmt->execute([':bid' => $bestellungId, ':dateiname' => $dateiname, ':buid' => $benutzerId]);

        return ['erfolg' => true, 'dokument_id' => (int)$this->db->lastInsertId(), 'dateiname' => $dateiname];
    }

    /** Alle erzeugten PDFs einer Bestellung, neueste zuerst. */
    public function getDokumente(int $bestellungId): array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, b.formularname AS erstellt_von_name
            FROM bestellung_dokumente d
            LEFT JOIN benutzer b ON b.id = d.erstellt_von
            WHERE d.bestellung_id = :id
            ORDER BY d.erstellt_am DESC
        ");
        $stmt->execute([':id' => $bestellungId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ein einzelnes Dokument samt Bestellung + Lieferant (für Mail-Vorschau). */
    public function getDokumentMitBestellung(int $dokumentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, b.id AS bestellung_id, l.name AS lieferant_name, l.email AS lieferant_email
            FROM bestellung_dokumente d
            JOIN bestellungen b ON b.id = d.bestellung_id
            JOIN lieferanten  l ON l.id = b.lieferant_id
            WHERE d.id = :id
        ");
        $stmt->execute([':id' => $dokumentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDateipfad(int $bestellungId, string $dateiname): string
    {
        return $this->storagePfad . '/' . $bestellungId . '/' . basename($dateiname);
    }

    /** Markiert ein Dokument als per Mail versendet (Zeitstempel für die Historie-Anzeige). */
    public function markiereMailGesendet(int $dokumentId): void
    {
        $this->db->prepare("UPDATE bestellung_dokumente SET mail_gesendet_am = NOW() WHERE id = :id")
            ->execute([':id' => $dokumentId]);
    }

    // ── Interne Helfer ──────────────────────────────────────────────────

    private function ladeDaten(int $bestellungId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, ben.formularname AS bearbeiter_name
            FROM bestellungen b
            LEFT JOIN benutzer ben ON ben.id = b.benutzer_id
            WHERE b.id = :id
        ");
        $stmt->execute([':id' => $bestellungId]);
        $bestellung = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bestellung) return null;

        $lStmt = $this->db->prepare("
            SELECT li.*, la.name_de AS land_name
            FROM lieferanten li
            LEFT JOIN laender la ON la.iso_code = li.land
            WHERE li.id = :id
        ");
        $lStmt->execute([':id' => $bestellung['lieferant_id']]);
        $lieferant = $lStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pStmt = $this->db->prepare("
            SELECT bp.*, COALESCE(vater.name, a.name) AS artikel_name,
                   COALESCE(vater.artikelnummer, a.artikelnummer) AS artikel_nr
            FROM bestellung_positionen bp
            JOIN artikel a ON a.id = bp.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            WHERE bp.bestellung_id = :id AND bp.gestrichen = 0
            ORDER BY bp.id
        ");
        $pStmt->execute([':id' => $bestellungId]);
        $positionen = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $gesamtEk = 0.0;
        foreach ($positionen as &$p) {
            $p['gesamt_ek'] = round((float)$p['menge_bestellt'] * (float)($p['ek_preis'] ?? 0), 2);
            $gesamtEk += $p['gesamt_ek'];
        }
        unset($p);

        $bestellung['nummer'] = BestellungService::bestellnummer($bestellungId, $bestellung['bestelldatum']);

        return [
            'firma'      => $this->dokRepo->ladeFirmaDaten(),
            'bestellung' => $bestellung,
            'lieferant'  => $lieferant,
            'positionen' => $positionen,
            'gesamt_ek'  => round($gesamtEk, 2),
            'datum_heute'=> date('d.m.Y'),
        ];
    }
}
