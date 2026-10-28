<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * AbholortService – Abholadressen für Selbstabholung (Migration 210, Jacky 2026-10-28)
 *
 * Abholzettel, Abholmail, Auftragsbestätigung und Rechnungshinweis zeigen den Abholort
 * des Auftrags statt der Firmenadresse (Firmensitz ≠ Geschäft). Regeln:
 *   - Lager → Abholort über lager.abholort_id (ein Abholort, mehrere Lager)
 *   - Lager ohne Abholort (Keller, Messe) → Standard-Abholort
 *   - Auftrag: auftraege.abholort_id, beim Packen automatisch aus dem Lager der
 *     Ausbuchung gesetzt (mehrere Lager → das mit den meisten Artikeln), von Hand
 *     änderbar; NULL = Standard-Abholort
 *   - gar kein Abholort angelegt → Firmenadresse (wie vor Migration 210)
 */
class AbholortService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Alle Abholorte mit ihren Lagern, Standard zuerst */
    public function alle(): array
    {
        $orte = $this->db->query("SELECT * FROM abholorte ORDER BY ist_standard DESC, name")->fetchAll(PDO::FETCH_ASSOC);
        $lager = $this->db->query("SELECT id, name, abholort_id FROM lager WHERE abholort_id IS NOT NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orte as &$o) {
            $o['lager'] = array_values(array_filter($lager, fn($l) => (int)$l['abholort_id'] === (int)$o['id']));
            $o['auftraege'] = (int)$this->db->query("SELECT COUNT(*) FROM auftraege WHERE abholort_id = " . (int)$o['id'])->fetchColumn();
        }
        unset($o);
        return $orte;
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM abholorte WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function standard(): ?array
    {
        return $this->db->query("SELECT * FROM abholorte ORDER BY ist_standard DESC, id LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Anlegen/ändern. $d: id?, name, strasse, plz, ort, telefon, oeffnungszeiten, hinweis,
     * ist_standard, lager_ids[]. Gewählte Lager wandern von einem anderen Abholort hierher.
     */
    public function speichern(array $d): array
    {
        $id   = (int)($d['id'] ?? 0);
        $feld = fn(string $k) => trim((string)($d[$k] ?? ''));
        $fehler = [];
        foreach (['name' => 'Name', 'strasse' => 'Straße', 'plz' => 'PLZ', 'ort' => 'Ort'] as $k => $label) {
            if ($feld($k) === '') $fehler[] = "$label fehlt.";
        }
        if ($id && !$this->get($id)) $fehler[] = 'Abholort nicht gefunden.';
        if ($fehler) return ['erfolg' => false, 'fehler' => $fehler];

        // Erster Abholort ist immer Standard; den einzigen Standard nicht wegnehmen
        $anzahl   = (int)$this->db->query("SELECT COUNT(*) FROM abholorte")->fetchColumn();
        $standard = !empty($d['ist_standard']) || $anzahl === 0 || ($id && $anzahl === 1);
        $werte = [$feld('name'), $feld('strasse'), $feld('plz'), $feld('ort'),
                  $feld('telefon') ?: null, $feld('oeffnungszeiten') ?: null, $feld('hinweis') ?: null, $standard ? 1 : 0];

        $eigeneTransaktion = !$this->db->inTransaction();
        if ($eigeneTransaktion) $this->db->beginTransaction();
        try {
            if ($standard) $this->db->exec("UPDATE abholorte SET ist_standard = 0");
            if ($id) {
                $this->db->prepare("UPDATE abholorte SET name = ?, strasse = ?, plz = ?, ort = ?, telefon = ?, oeffnungszeiten = ?, hinweis = ?, ist_standard = ? WHERE id = ?")
                    ->execute([...$werte, $id]);
            } else {
                $this->db->prepare("INSERT INTO abholorte (name, strasse, plz, ort, telefon, oeffnungszeiten, hinweis, ist_standard) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute($werte);
                $id = (int)$this->db->lastInsertId();
            }
            // Ohne Standard darf es keinen Zustand geben (Standard abgehakt → ältester wird Standard)
            if (!(int)$this->db->query("SELECT COUNT(*) FROM abholorte WHERE ist_standard = 1")->fetchColumn()) {
                $this->db->exec("UPDATE abholorte SET ist_standard = 1 ORDER BY id LIMIT 1");
            }

            $lagerIds = array_values(array_unique(array_filter(array_map('intval', (array)($d['lager_ids'] ?? [])))));
            $this->db->prepare("UPDATE lager SET abholort_id = NULL WHERE abholort_id = ?")->execute([$id]);
            if ($lagerIds) {
                $ph = implode(',', array_fill(0, count($lagerIds), '?'));
                $this->db->prepare("UPDATE lager SET abholort_id = ? WHERE id IN ($ph)")->execute([$id, ...$lagerIds]);
            }
            if ($eigeneTransaktion) $this->db->commit();
        } catch (Throwable $e) {
            if ($eigeneTransaktion) $this->db->rollBack();
            return ['erfolg' => false, 'fehler' => ['Datenbankfehler: ' . $e->getMessage()]];
        }
        Logger::log('abholort.speichern', 'abholorte', $id, ['name' => $feld('name'), 'lager' => $d['lager_ids'] ?? []]);
        return ['erfolg' => true, 'id' => $id];
    }

    /** Löschen nur, wenn kein Auftrag ihn verwendet und er nicht der Standard ist */
    public function loeschen(int $id): array
    {
        $o = $this->get($id);
        if (!$o) return ['erfolg' => false, 'fehler' => ['Abholort nicht gefunden.']];
        if ($o['ist_standard']) return ['erfolg' => false, 'fehler' => ['Der Standard-Abholort kann nicht gelöscht werden — zuerst einen anderen als Standard markieren.']];
        $n = $this->db->prepare("SELECT COUNT(*) FROM auftraege WHERE abholort_id = ?");
        $n->execute([$id]);
        if ($anzahl = (int)$n->fetchColumn()) {
            return ['erfolg' => false, 'fehler' => ["Wird von $anzahl Auftrag/Aufträgen verwendet — kann nicht gelöscht werden."]];
        }
        $this->db->prepare("DELETE FROM abholorte WHERE id = ?")->execute([$id]);  // lager.abholort_id → NULL (FK)
        Logger::log('abholort.loeschen', 'abholorte', $id, ['name' => $o['name']]);
        return ['erfolg' => true];
    }

    /** Abholort eines Lagers: eigener, sonst Standard (null = keiner angelegt) */
    public function idFuerLager(int $lagerId): ?int
    {
        $stmt = $this->db->prepare("SELECT abholort_id FROM lager WHERE id = ?");
        $stmt->execute([$lagerId]);
        return ((int)$stmt->fetchColumn()) ?: ($this->standard()['id'] ?? null);
    }

    /**
     * Beim Packen ("fertig zur Abholung"): Abholort aus dem Lager der Ausbuchung, wenn der
     * Auftrag noch keinen hat (von Hand gewählter bleibt). Mehrere Lager → das mit den
     * meisten verschiedenen Artikeln. Quelle: lager_bewegungen mit referenz = Auftragsnummer.
     */
    public function beimPackenSetzen(int $auftragId): ?int
    {
        $stmt = $this->db->prepare("SELECT auftrag_nr, abholort_id FROM auftraege WHERE id = ?");
        $stmt->execute([$auftragId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;
        if ($a['abholort_id']) return (int)$a['abholort_id'];

        $lager = $this->db->prepare("
            SELECT lager_id FROM lager_bewegungen
            WHERE referenz = ? AND bewegungstyp = 'ausgang'
            GROUP BY lager_id
            ORDER BY COUNT(DISTINCT artikel_id) DESC, MIN(id)
            LIMIT 1
        ");
        $lager->execute([$a['auftrag_nr']]);
        $lagerId = (int)$lager->fetchColumn();
        $id = $lagerId ? $this->idFuerLager($lagerId) : ($this->standard()['id'] ?? null);
        if ($id) $this->setzen($auftragId, $id);
        return $id;
    }

    /** Von Hand im Auftrag (null = Standard) */
    public function setzen(int $auftragId, ?int $abholortId): array
    {
        if ($abholortId && !$this->get($abholortId)) return ['erfolg' => false, 'fehler' => 'Abholort nicht gefunden.'];
        $this->db->prepare("UPDATE auftraege SET abholort_id = ? WHERE id = ?")->execute([$abholortId ?: null, $auftragId]);
        Logger::log('auftrag.abholort', 'auftraege', $auftragId, ['abholort_id' => $abholortId]);
        return ['erfolg' => true];
    }

    /**
     * Anzeige-Daten für Dokumente und Mails: Abholort des Auftrags, sonst Standard, sonst
     * Firmenadresse. Telefon fällt auf das Firmentelefon zurück.
     *
     * @return array{name: string, strasse: string, plz_ort: string, telefon: string, oeffnungszeiten: string[], hinweis: string}
     */
    public function anzeigeFuerAuftrag(?int $abholortId): array
    {
        $o = ($abholortId ? $this->get($abholortId) : null) ?? $this->standard();
        $firma = $this->db->query("
            SELECT schluessel, wert FROM system_einstellungen
            WHERE schluessel IN ('firmenname', 'strasse', 'hausnummer', 'plz', 'ort', 'telefon')
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!$o) {
            return [
                'name'            => $firma['firmenname'] ?? '',
                'strasse'         => trim(($firma['strasse'] ?? '') . ' ' . ($firma['hausnummer'] ?? '')),
                'plz_ort'         => trim(($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? '')),
                'telefon'         => $firma['telefon'] ?? '',
                'oeffnungszeiten' => [],
                'hinweis'         => '',
            ];
        }
        return [
            'name'            => $o['name'],
            'strasse'         => $o['strasse'],
            'plz_ort'         => trim($o['plz'] . ' ' . $o['ort']),
            'telefon'         => $o['telefon'] ?: ($firma['telefon'] ?? ''),
            'oeffnungszeiten' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$o['oeffnungszeiten'])))),
            'hinweis'         => (string)$o['hinweis'],
        ];
    }
}
