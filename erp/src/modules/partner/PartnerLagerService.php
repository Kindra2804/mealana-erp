<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../artikel/ArtikelService.php';
require_once __DIR__ . '/../artikel/ArtikelRepository.php';
require_once __DIR__ . '/../lager/LagerService.php';
require_once __DIR__ . '/../lager/LagerRepository.php';
require_once __DIR__ . '/PartnerRepository.php';

/**
 * PartnerLagerService – Ware von Partnern (Mietfach/Kommission/Spende) im eigenen Lager
 * des Partners (Jacky 2026-10-01).
 *
 * - Ein Lager je Partner: lager.lager_beziehung = 'partner_bestand', lager.partner_id.
 * - Die Mietfächer des Partners sind Lagerplätze in diesem Lager (lagerplaetze.mietfach_id);
 *   beim Mieterwechsel wandert der Platz mit, solange dort keine Ware des alten Mieters liegt.
 * - Partnerware = Artikel mit artikel.partner_id, Nummer "XP<Partner-ID>-…", wird nur hier
 *   gepflegt, steht nicht in der normalen Artikelliste und nie im Onlineshop.
 */
class PartnerLagerService
{
    private PDO $db;
    private PartnerRepository $partnerRepo;

    public function __construct()
    {
        $this->db          = Database::getInstance();
        $this->partnerRepo = new PartnerRepository();
    }

    /** "XP01-" -- fester Anfang der Artikelnummern dieses Partners */
    public static function nummernPraefix(int $partnerId): string
    {
        return 'XP' . str_pad((string)$partnerId, 2, '0', STR_PAD_LEFT) . '-';
    }

    // ── Lager ─────────────────────────────────────────────────────────────────

    public function getLager(int $partnerId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM lager WHERE partner_id = ? AND lager_beziehung = 'partner_bestand'");
        $stmt->execute([$partnerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Legt das Partner-Lager an (falls noch keins da ist) und hängt die Mietfächer als Plätze ein. */
    public function lagerAnlegen(int $partnerId): array
    {
        $partner = $this->partnerRepo->findById($partnerId);
        if (!$partner) return ['erfolg' => false, 'fehler' => ['Partner nicht gefunden.']];

        $lager = $this->getLager($partnerId);
        if (!$lager) {
            $this->db->prepare("
                INSERT INTO lager (name, typ, aktiv, fuer_offline_kasse_waehlbar, lager_beziehung, partner_id)
                VALUES (?, 'lager', 1, 0, 'partner_bestand', ?)
            ")->execute([mb_substr($partner['name'], 0, 50), $partnerId]);
            $lagerId = (int)$this->db->lastInsertId();
            Logger::log('partner.lager_anlegen', 'lager', $lagerId, ['partner_id' => $partnerId]);
        }
        $hinweise = $this->mietfaecherSichern($partnerId);
        return ['erfolg' => true, 'hinweise' => $hinweise];
    }

    /**
     * Stellt sicher, dass jedes aktuell gemietete Fach des Partners ein Lagerplatz in
     * dessen Lager ist. Liegt der Platz noch im Lager des Vormieters und dort ist noch
     * Ware, bleibt er dort (Hinweis) -- sonst wandert er um.
     *
     * @return string[] Hinweise für die Oberfläche
     */
    public function mietfaecherSichern(int $partnerId): array
    {
        $lager = $this->getLager($partnerId);
        if (!$lager) return [];
        $hinweise = [];

        $stmt = $this->db->prepare("
            SELECT f.id, f.fach_bezeichnung
            FROM mietfach_mietvertraege v
            JOIN mietfaecher f ON f.id = v.mietfach_id
            WHERE v.partner_id = ? AND (v.mietende IS NULL OR v.mietende >= CURDATE())
        ");
        $stmt->execute([$partnerId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fach) {
            $platzStmt = $this->db->prepare("SELECT * FROM lagerplaetze WHERE mietfach_id = ?");
            $platzStmt->execute([$fach['id']]);
            $platz = $platzStmt->fetch(PDO::FETCH_ASSOC);
            $bez   = mb_substr(trim($fach['fach_bezeichnung']), 0, 50);

            if (!$platz) {
                $this->db->prepare("
                    INSERT INTO lagerplaetze (lager_id, mietfach_id, bezeichnung, sortierung, aktiv)
                    VALUES (?, ?, ?, ?, 1)
                ")->execute([$lager['id'], $fach['id'], $bez, LagerService::lagerplatzSortierung(null, null, null, $bez)]);
                continue;
            }
            if ((int)$platz['lager_id'] === (int)$lager['id']) continue;

            // Platz liegt noch beim Vormieter: nur umziehen, wenn dort nichts mehr liegt
            $rest = $this->db->prepare("
                SELECT COALESCE(SUM(lb.bestand), 0) FROM artikel a
                JOIN lagerbestand lb ON lb.artikel_id = a.id AND lb.lager_id = ?
                WHERE a.stammplatz_id = ?
            ");
            $rest->execute([$platz['lager_id'], $platz['id']]);
            if ((float)$rest->fetchColumn() > 0) {
                $hinweise[] = 'Fach ' . $bez . ': dort liegt noch Ware des Vormieters — erst Rückgabe buchen, dann wird das Fach übernommen.';
                continue;
            }
            $this->db->prepare("UPDATE artikel SET stammplatz_id = NULL WHERE stammplatz_id = ?")->execute([$platz['id']]);
            $this->db->prepare("UPDATE lagerplaetze SET lager_id = ?, bezeichnung = ?, aktiv = 1 WHERE id = ?")
                ->execute([$lager['id'], $bez, $platz['id']]);
            Logger::log('partner.fach_uebernommen', 'lagerplaetze', (int)$platz['id'], ['partner_id' => $partnerId]);
        }
        return $hinweise;
    }

    /** Lagerplätze (= Mietfächer) des Partner-Lagers */
    public function getPlaetze(int $partnerId): array
    {
        $stmt = $this->db->prepare("
            SELECT lp.id, lp.bezeichnung, lp.mietfach_id, f.ort_beschreibung
            FROM lagerplaetze lp
            JOIN lager l ON l.id = lp.lager_id AND l.partner_id = ?
            LEFT JOIN mietfaecher f ON f.id = lp.mietfach_id
            WHERE lp.aktiv = 1
            ORDER BY lp.sortierung, lp.bezeichnung
        ");
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Artikel ───────────────────────────────────────────────────────────────

    /** Partnerware mit Preis, EAN, Fach und Bestand im Partner-Lager */
    public function getArtikel(int $partnerId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.artikelnummer, a.name, a.aktiv, a.partner_modus, a.steuerklasse_id, a.stammplatz_id,
                   s.satz AS steuersatz, lp.bezeichnung AS fach,
                   (SELECT ap.brutto_vk FROM artikel_preise ap JOIN kundengruppen kg ON kg.id = ap.kundengruppen_id AND kg.ist_standard = 1
                    WHERE ap.artikel_id = a.id LIMIT 1) AS brutto_vk,
                   (SELECT code FROM artikel_codes WHERE artikel_id = a.id AND typ = 'GTIN13' LIMIT 1) AS ean,
                   (SELECT COALESCE(SUM(lb.bestand), 0) FROM lagerbestand lb
                    JOIN lager l ON l.id = lb.lager_id AND l.partner_id = a.partner_id
                    WHERE lb.artikel_id = a.id) AS bestand
            FROM artikel a
            LEFT JOIN steuerklassen s ON s.id = a.steuerklasse_id
            LEFT JOIN lagerplaetze lp ON lp.id = a.stammplatz_id
            WHERE a.partner_id = ?
            ORDER BY a.aktiv DESC, lp.sortierung, a.artikelnummer
        ");
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Partner-Artikel anlegen oder ändern (schlankes Formular auf der Partnerseite).
     * $data: id?, nummer_suffix, name, brutto_vk, steuerklasse_id, ean, stammplatz_id, aktiv
     */
    public function artikelSpeichern(int $partnerId, array $data): array
    {
        $partner = $this->partnerRepo->findById($partnerId);
        if (!$partner) return ['erfolg' => false, 'fehler' => ['Partner nicht gefunden.']];

        $suffix = strtoupper(trim((string)($data['nummer_suffix'] ?? '')));
        $suffix = preg_replace('/\s+/', '-', $suffix);
        $name   = trim((string)($data['name'] ?? ''));
        $brutto = round((float)str_replace(',', '.', (string)($data['brutto_vk'] ?? '0')), 2);
        $stkId  = (int)($data['steuerklasse_id'] ?? 1);
        $ean    = trim((string)($data['ean'] ?? '')) ?: null;
        $platz  = !empty($data['stammplatz_id']) ? (int)$data['stammplatz_id'] : null;
        $aktiv  = isset($data['aktiv']) ? (int)!empty($data['aktiv']) : 1;
        $modus  = $partner['typ'] === 'spende' ? 'spende' : 'kommission';

        $fehler = [];
        if ($suffix === '' || !preg_match('/^[A-Z0-9][A-Z0-9\-_.]*$/', $suffix)) $fehler[] = 'Artikelnummer (Teil nach ' . self::nummernPraefix($partnerId) . ') fehlt oder enthält Sonderzeichen.';
        if ($name === '')  $fehler[] = 'Bezeichnung fehlt.';
        if ($brutto <= 0)  $fehler[] = 'Verkaufspreis fehlt.';
        $satz = $this->db->prepare("SELECT satz FROM steuerklassen WHERE id = ?");
        $satz->execute([$stkId]);
        $steuersatz = $satz->fetchColumn();
        if ($steuersatz === false) $fehler[] = 'Steuersatz ungültig.';
        if ($platz) {
            $chk = $this->db->prepare("SELECT 1 FROM lagerplaetze lp JOIN lager l ON l.id = lp.lager_id WHERE lp.id = ? AND l.partner_id = ?");
            $chk->execute([$platz, $partnerId]);
            if (!$chk->fetchColumn()) $fehler[] = 'Fach gehört nicht zu diesem Partner.';
        }
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];

        $nummer = self::nummernPraefix($partnerId) . $suffix;
        $netto  = round($brutto / (1 + (float)$steuersatz / 100), 4);
        $id     = !empty($data['id']) ? (int)$data['id'] : 0;

        if ($id) {
            $alt = $this->db->prepare("SELECT partner_id FROM artikel WHERE id = ?");
            $alt->execute([$id]);
            if ((int)$alt->fetchColumn() !== $partnerId) return ['erfolg' => false, 'fehler' => ['Artikel gehört nicht zu diesem Partner.']];
            $dup = (new ArtikelRepository())->findByArtikelnummer($nummer, $id);
            if ($dup) return ['erfolg' => false, 'fehler' => ['Artikelnummer ' . $nummer . ' gibt es schon.']];

            $this->db->prepare("UPDATE artikel SET artikelnummer = ?, name = ?, steuerklasse_id = ?, stammplatz_id = ?, aktiv = ? WHERE id = ?")
                ->execute([$nummer, $name, $stkId, $platz, $aktiv, $id]);
            $repo = new ArtikelRepository();
            $repo->updatePreis($id, $brutto, $netto);
            $repo->deleteCodesByArtikelIdAndType($id, 'GTIN13');
            if ($ean) $repo->insertCode($id, 'GTIN13', $ean);
            Logger::log('partner.artikel_bearbeiten', 'artikel', $id, ['partner_id' => $partnerId, 'nummer' => $nummer]);
            return ['erfolg' => true, 'id' => $id];
        }

        $gruppe = (int)$this->db->query("SELECT id FROM artikel_gruppen WHERE konto_nr = 'PARTNER' LIMIT 1")->fetchColumn();
        $felder = array_fill_keys([
            'vaterartikel_id', 'hat_eigenen_lagerstand', 'hersteller_id', 'kurzbeschreibung', 'beschreibung',
            'technische_details', 'beschreibung_intern', 'meta_titel', 'meta_description', 'url_slug',
            'inhalt_menge', 'inhalt_einheit', 'gewicht_artikel', 'gewicht_versand', 'laenge', 'breite', 'hoehe',
            'herkunftsland', 'taric_code', 'grundpreis_bezugsmenge', 'mindestabnahme_modus', 'mindestabnahme',
            'abnahmeintervall', 'zustand_vater_id',
        ], null);
        $ergebnis = (new ArtikelService())->save($felder + [
            'artikelnummer'        => $nummer,
            'name'                 => $name,
            'artikeltyp'           => 'STANDARD',
            'artikel_gruppe_id'    => $gruppe ?: null,
            'steuerklasse_id'      => $stkId,
            'einheit_id'           => 4, // Stück
            'grundpreis_anzeigen'  => 0,
            'charge_pflicht'       => 0,
            'ist_auslaufartikel'   => 0,
            'ueberverkauf_erlaubt' => 0,
            'aktiv'                => $aktiv,
            'zustand'              => 'neu',
            'brutto_vk'            => $brutto,
            'netto_vk'             => $netto,
            'ean_gtin13'           => $ean,
            '_partnerware'         => true,
        ]);
        if (!$ergebnis['erfolg']) return $ergebnis;

        $this->db->prepare("UPDATE artikel SET partner_id = ?, partner_modus = ?, stammplatz_id = ? WHERE id = ?")
            ->execute([$partnerId, $modus, $platz, $ergebnis['id']]);
        Logger::log('partner.artikel_anlegen', 'artikel', $ergebnis['id'], ['partner_id' => $partnerId, 'nummer' => $nummer]);
        return ['erfolg' => true, 'id' => $ergebnis['id']];
    }

    // ── Übernahme / Rückgabe mit Beleg ────────────────────────────────────────

    /**
     * Ware vom Partner übernehmen (Eingang ins Partner-Lager) oder an ihn zurückgeben
     * (Ausgang). Erzeugt einen nummerierten Beleg (US-/RS-…) und bucht jede Position
     * über den normalen LagerService -- damit steht alles auch im Lagerprotokoll.
     *
     * @param array $positionen [['artikel_id'=>…, 'menge'=>…, 'charge'=>?], …]
     */
    public function belegBuchen(int $partnerId, string $typ, array $positionen, ?string $notiz, int $benutzerId): array
    {
        if (!in_array($typ, ['uebernahme', 'rueckgabe'], true)) return ['erfolg' => false, 'fehler' => ['Ungültiger Belegtyp.']];
        $lager = $this->getLager($partnerId);
        if (!$lager) return ['erfolg' => false, 'fehler' => ['Partner hat kein Lager.']];

        $lagerRepo = new LagerRepository();
        $sauber = [];
        $fehler = [];
        foreach ($positionen as $p) {
            $menge = round((float)str_replace(',', '.', (string)($p['menge'] ?? 0)), 3);
            if ($menge <= 0) continue;
            $charge = trim((string)($p['charge'] ?? '')) ?: null;
            $a = $this->db->prepare("SELECT id, artikelnummer, name, partner_id, stammplatz_id FROM artikel WHERE id = ?");
            $a->execute([(int)($p['artikel_id'] ?? 0)]);
            $art = $a->fetch(PDO::FETCH_ASSOC);
            if (!$art || (int)$art['partner_id'] !== $partnerId) { $fehler[] = 'Artikel gehört nicht zu diesem Partner.'; continue; }
            if ($typ === 'rueckgabe') {
                $da = $charge !== null ? $lagerRepo->getBestand((int)$art['id'], (int)$lager['id'], $charge)
                                       : $lagerRepo->getTotalBestand((int)$art['id'], (int)$lager['id']);
                if ($menge > $da + 0.0001) {
                    $fehler[] = $art['name'] . ': nur ' . rtrim(rtrim(number_format($da, 3, ',', ''), '0'), ',') . ' im Partner-Lager.';
                    continue;
                }
            }
            $sauber[] = $art + ['menge' => $menge, 'charge' => $charge];
        }
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];
        if (!$sauber) return ['erfolg' => false, 'fehler' => ['Keine Mengen eingetragen.']];

        require_once __DIR__ . '/../dokumente/DokumentRepository.php';
        $lagerSvc = new LagerService();
        $this->db->beginTransaction();
        try {
            $nummer = (new DokumentRepository())->naechsteNummer($typ === 'uebernahme' ? 'partner_uebernahme' : 'partner_rueckgabe', (int)date('Y'));
            $this->db->prepare("INSERT INTO partner_belege (partner_id, typ, nummer, lager_id, notiz, benutzer_id) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$partnerId, $typ, $nummer, $lager['id'], $notiz ?: null, $benutzerId ?: null]);
            $belegId = (int)$this->db->lastInsertId();
            $posStmt = $this->db->prepare("
                INSERT INTO partner_beleg_positionen (beleg_id, artikel_id, artikelnummer, bezeichnung, menge, charge, lagerplatz_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $referenz = ($typ === 'uebernahme' ? 'Übernahme ' : 'Rückgabe an Partner ') . $nummer;
            foreach ($sauber as $p) {
                $posStmt->execute([$belegId, $p['id'], $p['artikelnummer'], $p['name'], $p['menge'], $p['charge'], $p['stammplatz_id']]);
                $daten = ['artikel_id' => $p['id'], 'lager_id' => $lager['id'], 'menge' => $p['menge'], 'charge' => $p['charge'],
                          'referenz' => $referenz, 'notiz' => $notiz ?: null, 'benutzer_id' => $benutzerId ?: null];
                $r = $typ === 'uebernahme' ? $lagerSvc->wareneingang($daten) : $lagerSvc->warenausgang($daten);
                if (empty($r['erfolg'])) throw new RuntimeException(is_array($r['fehler'] ?? null) ? implode(' ', $r['fehler']) : (string)($r['fehler'] ?? 'Buchung fehlgeschlagen'));
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => [$e->getMessage()]];
        }
        Logger::log('partner.' . $typ, 'partner_belege', $belegId, ['partner_id' => $partnerId, 'nummer' => $nummer, 'positionen' => count($sauber)]);
        return ['erfolg' => true, 'id' => $belegId, 'nummer' => $nummer];
    }

    public function getBelege(int $partnerId): array
    {
        $stmt = $this->db->prepare("
            SELECT b.*, (SELECT SUM(menge) FROM partner_beleg_positionen WHERE beleg_id = b.id) AS menge_gesamt,
                   (SELECT COUNT(*) FROM partner_beleg_positionen WHERE beleg_id = b.id) AS anzahl,
                   u.formularname AS benutzer_name
            FROM partner_belege b LEFT JOIN benutzer u ON u.id = b.benutzer_id
            WHERE b.partner_id = ? ORDER BY b.erstellt_am DESC, b.id DESC
        ");
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBeleg(int $belegId): array|false
    {
        $stmt = $this->db->prepare("SELECT b.*, u.formularname AS benutzer_name FROM partner_belege b LEFT JOIN benutzer u ON u.id = b.benutzer_id WHERE b.id = ?");
        $stmt->execute([$belegId]);
        $beleg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$beleg) return false;
        $pos = $this->db->prepare("
            SELECT p.*, lp.bezeichnung AS fach FROM partner_beleg_positionen p
            LEFT JOIN lagerplaetze lp ON lp.id = p.lagerplatz_id
            WHERE p.beleg_id = ? ORDER BY lp.sortierung, p.artikelnummer
        ");
        $pos->execute([$belegId]);
        $beleg['positionen'] = $pos->fetchAll(PDO::FETCH_ASSOC);
        return $beleg;
    }

    // ── Nachverfolgung ────────────────────────────────────────────────────────

    /** Alle Lagerbewegungen im Partner-Lager (Übernahme, Verkauf, Retoure, Rückgabe, Korrektur) */
    public function getBewegungen(int $partnerId, int $limit = 300): array
    {
        $stmt = $this->db->prepare("
            SELECT lb.erstellt_am, lb.bewegungstyp, lb.menge, lb.bestand_nachher, lb.charge, lb.referenz, lb.notiz,
                   a.artikelnummer, a.name, u.formularname AS benutzer_name
            FROM lager_bewegungen lb
            JOIN lager l ON l.id = lb.lager_id AND l.partner_id = ?
            JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN benutzer u ON u.id = lb.benutzer_id
            ORDER BY lb.erstellt_am DESC, lb.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verkäufe von Partnerware in einem Zeitraum (Kassenbons, ohne stornierte) --
     * Grundlage für die Verkaufsliste an den Partner und später für die Abrechnung.
     */
    public function getVerkaeufe(int $partnerId, string $von, string $bis): array
    {
        $stmt = $this->db->prepare("
            SELECT b.erstellt_am, b.bon_nr, b.typ AS bon_typ, a.artikelnummer, p.bezeichnung, p.menge,
                   p.einzelpreis_brutto, p.rabatt_prozent, p.steuer_prozent,
                   ROUND(p.menge * p.einzelpreis_brutto * (1 - p.rabatt_prozent / 100), 2) AS summe
            FROM kassen_bon_positionen p
            JOIN kassen_bons b ON b.id = p.bon_id
            JOIN artikel a ON a.id = p.artikel_id AND a.partner_id = ?
            WHERE DATE(b.erstellt_am) BETWEEN ? AND ?
              -- nur gültige Verkaufsbons: stornierte Bons fallen ganz weg (ihr Storno-Beleg
              -- also auch), Retouren stehen im Verkaufsbon schon mit negativer Menge
              AND b.typ = 'verkauf' AND b.storniert = 0
            ORDER BY b.erstellt_am, b.id
        ");
        $stmt->execute([$partnerId, $von, $bis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Firmendaten + Logo für die Partner-Belege (wie die übrigen Dokumente) */
    public function dokumentBasis(): array
    {
        require_once __DIR__ . '/../dokumente/DokumentRepository.php';
        $firma = (new DokumentRepository())->ladeFirmaDaten();
        $logo  = $this->db->query("SELECT logo_pfad FROM shops WHERE id = 1")->fetchColumn() ?: 'img/logos/logo_mealana.png';
        $pfad  = __DIR__ . '/../../../public/' . $logo;
        return ['firma' => $firma, 'logo_base64' => file_exists($pfad) ? base64_encode(file_get_contents($pfad)) : ''];
    }
}
