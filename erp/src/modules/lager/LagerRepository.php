<?php

require_once __DIR__ . '/../../core/Database.php';

/**
 * LagerRepository – Datenzugriff für Lager, Lagerbestand und Lagerbewegungen
 *
 * Kernfunktionen:
 *   getBestand()      → aktuellen Bestand lesen (mit/ohne Charge)
 *   upsertBestand()   → Bestand anlegen oder aktualisieren
 *   insertBewegung()  → Audit-Log-Eintrag für jede Bestandsänderung
 *   findUebersicht()  → komplexe UNION ALL für die Lager-Übersichtsliste
 *
 * upsertBestand() hat eine kritische Besonderheit:
 * Wenn charge = NULL, funktioniert "ON DUPLICATE KEY UPDATE" NICHT (NULL ≠ NULL in SQL-Vergleichen).
 * Deshalb wird in diesem Fall manuell SELECT + UPDATE oder INSERT gemacht.
 * Wenn charge = 'LOT-123', kann normale ON DUPLICATE KEY UPDATE genutzt werden.
 *
 * findUebersicht() baut eine 4-stufige UNION ALL:
 *   vater         → Vater-Artikel (Summe der Kinder)
 *   kind          → Kind-Artikel (je Charge/Lager-Zeile)
 *   standalone    → Standalone-Artikel (Kopf-Summe)
 *   standalone_kind → Standalone-Artikel (je Charge/Lager-Zeile)
 *
 * Charge-Status:
 *   erfasst      → Chargenummer bekannt
 *   nachzutragen → Artikel charge_pflicht=1 aber beim Eingang keine Charge angegeben
 *   null         → Artikel ohne Chargenpflicht
 */
class LagerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt alle Lager mit ihrem aktuellen Bestand zurück.
     * Nur Zeilen mit vorhandenem Lagerbestand (INNER JOIN, nicht LEFT JOIN).
     */
    public function findAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                l.id, l.name, l.aktiv, l.erstellt_am, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
        ");
        return $stmt->fetchAll();
    }

    /**
     * Gibt den gesamten Bestand eines Artikels über alle Lager zurück.
     * Sortiert nach Bestand absteigend (lagerstärkste Charge zuerst).
     */
    public function findBestandByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /** Gibt den gesamten Bestand eines Lagers zurück (alle Artikel). */
    public function findBestandByLager(int $lagerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE l.id = :lager_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['lager_id' => $lagerId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt alle Chargen eines Artikels zurück (identisch zu findBestandByArtikelId).
     * Existiert als separater Methode für semantische Klarheit in LagerController.
     */
    public function findChargenByArtikelId(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                l.id, l.name, l.aktiv, l.typ,
                lb.charge, lb.charge_status, lb.bestand, lb.mindestbestand,
                a.id AS ArtikelID
            FROM lager l
            INNER JOIN lagerbestand lb ON l.id = lb.lager_id
            INNER JOIN artikel a ON a.id = lb.artikel_id
            WHERE lb.artikel_id = :artikel_id
            ORDER BY lb.bestand DESC
        ");
        $stmt->execute(['artikel_id' => $artikelId]);
        return $stmt->fetchAll();
    }

    /**
     * Gibt alle Lagerbestand-Einträge zurück bei denen charge_status = 'nachzutragen'
     * und der Artikel charge_pflicht = 1 hat.
     * Für die Charge-Nachtragsliste (nachtrag_liste.php).
     * COALESCE für Vater/Kind-Name-Anzeige.
     */
    public function findNachzutragendeChargen(): array
    {
        $stmt = $this->db->query("
            SELECT
                lb.id,
                COALESCE(vater.name, a.name) AS artikel_name,
                COALESCE(vater.artikelnummer, a.artikelnummer) AS vater_nr,
                CASE WHEN a.vaterartikel_id IS NOT NULL THEN a.artikelnummer END AS variante_nr,
                l.name AS lager_name,
                lb.bestand,
                lb.artikel_id
            FROM lagerbestand lb
            INNER JOIN artikel a ON a.id = lb.artikel_id
            LEFT JOIN artikel vater ON vater.id = a.vaterartikel_id
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE lb.charge_status = 'nachzutragen'
            AND a.charge_pflicht = 1
            ORDER BY artikel_name, a.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Trägt eine Charge für einen Lagerbestand-Eintrag nach.
     * Setzt charge und charge_status = 'erfasst'.
     */
    public function updateCharge(int $lagerbestandId, string $charge): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lagerbestand
            SET charge = :charge, charge_status = 'erfasst'
            WHERE id = :id
        ");
        $stmt->execute(['charge' => $charge, 'id' => $lagerbestandId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Gibt den aktuellen Bestand eines Artikels in einem Lager zurück.
     * Bei charge = null: sucht nach Zeile mit charge IS NULL (kein Charge-Filter).
     * Bei charge = 'LOT-123': sucht nach exakter Charge.
     * Gibt 0.0 zurück wenn kein Bestand-Eintrag vorhanden.
     */
    public function getBestand(int $artikelId, int $lagerId, ?string $charge = null): float
    {
        if ($charge !== null) {
            $stmt = $this->db->prepare("
                SELECT bestand FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge = :charge
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId, 'charge' => $charge]);
        } else {
            $stmt = $this->db->prepare("
                SELECT bestand FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge IS NULL
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId]);
        }
        $result = $stmt->fetch();
        return $result ? (float) $result['bestand'] : 0.0;
    }

    /** Gibt den Gesamtbestand eines Artikels in einem Lager zurück (Summe aller Chargen). */
    public function getTotalBestand(int $artikelId, int $lagerId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(bestand), 0) FROM lagerbestand
            WHERE artikel_id = :aid AND lager_id = :lid
        ");
        $stmt->execute(['aid' => $artikelId, 'lid' => $lagerId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Reduziert den Lagerbestand für einen Warenausgang.
     * Wenn $charge angegeben: direkt diese Charge-Zeile reduzieren.
     * Ohne $charge: erst charge=NULL Zeile, dann Zeile mit höchstem Bestand (Fallback).
     */
    public function reduziereBestand(int $artikelId, int $lagerId, float $menge, ?string $charge = null): void
    {
        if ($charge !== null) {
            $this->db->prepare("
                UPDATE lagerbestand
                SET bestand = GREATEST(0, bestand - :menge), geaendert_am = NOW()
                WHERE artikel_id = :aid AND lager_id = :lid AND charge = :charge
            ")->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId, 'charge' => $charge]);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE lagerbestand
            SET bestand = GREATEST(0, bestand - :menge), geaendert_am = NOW()
            WHERE artikel_id = :aid AND lager_id = :lid AND charge IS NULL
        ");
        $stmt->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId]);

        if ($stmt->rowCount() === 0) {
            $this->db->prepare("
                UPDATE lagerbestand
                SET bestand = GREATEST(0, bestand - :menge), geaendert_am = NOW()
                WHERE artikel_id = :aid AND lager_id = :lid
                ORDER BY bestand DESC LIMIT 1
            ")->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId]);
        }
    }

    /**
     * Wie reduziereBestand(), aber erlaubt negativen Bestand (für Kasse-Verkauf bei 0-Bestand).
     * Wenn $charge angegeben: direkt diese Charge-Zeile reduzieren (kann negativ werden).
     */
    public function reduziereBestandKasse(int $artikelId, int $lagerId, float $menge, ?string $charge = null): void
    {
        if ($charge !== null) {
            $stmt = $this->db->prepare("
                UPDATE lagerbestand
                SET bestand = bestand - :menge, geaendert_am = NOW()
                WHERE artikel_id = :aid AND lager_id = :lid AND charge = :charge
            ");
            $stmt->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId, 'charge' => $charge]);
            if ($stmt->rowCount() === 0) {
                // Charge existiert noch nicht → negativen Eintrag anlegen
                $this->db->prepare("
                    INSERT INTO lagerbestand (artikel_id, lager_id, charge, charge_status, bestand, geaendert_am)
                    VALUES (:aid, :lid, :charge, 'erfasst', :neg, NOW())
                    ON DUPLICATE KEY UPDATE bestand = bestand - :menge2, geaendert_am = NOW()
                ")->execute(['aid' => $artikelId, 'lid' => $lagerId, 'charge' => $charge, 'neg' => -$menge, 'menge2' => $menge]);
            }
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE lagerbestand
            SET bestand = bestand - :menge, geaendert_am = NOW()
            WHERE artikel_id = :aid AND lager_id = :lid AND charge IS NULL
        ");
        $stmt->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId]);

        if ($stmt->rowCount() === 0) {
            $stmt2 = $this->db->prepare("
                UPDATE lagerbestand
                SET bestand = bestand - :menge, geaendert_am = NOW()
                WHERE artikel_id = :aid AND lager_id = :lid
                ORDER BY bestand DESC LIMIT 1
            ");
            $stmt2->execute(['menge' => $menge, 'aid' => $artikelId, 'lid' => $lagerId]);

            // Kein Lagerbestand-Eintrag vorhanden → negativen Eintrag anlegen
            if ($stmt2->rowCount() === 0) {
                $this->db->prepare("
                    INSERT INTO lagerbestand (artikel_id, lager_id, bestand, geaendert_am)
                    VALUES (:aid, :lid, :neg, NOW())
                    ON DUPLICATE KEY UPDATE bestand = bestand - :menge2, geaendert_am = NOW()
                ")->execute(['aid' => $artikelId, 'lid' => $lagerId, 'neg' => -$menge, 'menge2' => $menge]);
            }
        }
    }

    /**
     * Speichert oder aktualisiert einen Lagerbestand-Eintrag.
     *
     * WICHTIG: Zwei unterschiedliche Code-Pfade je nach Charge:
     *
     * Wenn charge IS NOT NULL:
     *   → "INSERT ... ON DUPLICATE KEY UPDATE" funktioniert normal,
     *     weil der UNIQUE-Index auf (artikel_id, lager_id, charge) greift.
     *
     * Wenn charge IS NULL:
     *   → ON DUPLICATE KEY würde nie triggern, weil NULL != NULL in SQL-Indizes.
     *     Deshalb: manuelles "SELECT id WHERE charge IS NULL" + UPDATE oder INSERT.
     */
    public function upsertBestand(array $data): bool
    {
        if ($data['charge'] !== null) {
            // Normale Variante: UNIQUE-Index auf (artikel_id, lager_id, charge) greift
            $stmt = $this->db->prepare("
                INSERT INTO lagerbestand (
                    artikel_id, lager_id, charge, charge_status, bestand, mindestbestand
                ) VALUES (
                    :artikel_id, :lager_id, :charge, :charge_status, :bestand, :mindestbestand
                )
                ON DUPLICATE KEY UPDATE
                    charge        = VALUES(charge),
                    charge_status = VALUES(charge_status),
                    bestand       = VALUES(bestand),
                    mindestbestand = VALUES(mindestbestand)
            ");
            $stmt->execute($data);
            return $stmt->rowCount() > 0;
        } else {
            // NULL-Sonderfall: NULL != NULL in SQL — ON DUPLICATE KEY funktioniert nicht
            $check = $this->db->prepare("
                SELECT id FROM lagerbestand
                WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge IS NULL
            ");
            $check->execute(['artikel_id' => $data['artikel_id'], 'lager_id' => $data['lager_id']]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE lagerbestand SET
                        bestand        = :bestand,
                        charge_status  = :charge_status,
                        mindestbestand = :mindestbestand
                    WHERE id = :id
                ");
                $stmt->execute([
                    'bestand'        => $data['bestand'],
                    'charge_status'  => $data['charge_status'],
                    'mindestbestand' => $data['mindestbestand'],
                    'id'             => $existing['id'],
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO lagerbestand (
                        artikel_id, lager_id, charge, charge_status, bestand, mindestbestand
                    ) VALUES (
                        :artikel_id, :lager_id, :charge, :charge_status, :bestand, :mindestbestand
                    )
                ");
                $stmt->execute($data);
            }
            return true;
        }
    }

    /** Löscht einen Lagerbestand-Eintrag dauerhaft (z.B. nach Charge-Nachtragung mit vollständiger Umbuchung). */
    public function deleteBestand(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lagerbestand WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Aktualisiert nur den Bestand-Mengenwert eines Eintrags (für Charge-Nachtrag-Umbuchung). */
    public function updateBestandMenge(int $id, float $bestand): bool
    {
        $stmt = $this->db->prepare("UPDATE lagerbestand SET bestand = :bestand WHERE id = :id");
        $stmt->execute(['id' => $id, 'bestand' => $bestand]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Schreibt einen neuen Eintrag in lager_bewegungen (Audit-Log).
     * Alle Bestandsänderungen werden hier als unveränderliche Zeilen protokolliert.
     * bestand_vorher + bestand_nachher müssen immer mitgegeben werden.
     * Gibt die neue ID zurück (wird für bestellung_eingaenge.bewegung_id benötigt).
     */
    public function insertBewegung(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lager_bewegungen (
                artikel_id, lager_id, lieferant_id, ek_preis,
                charge, bewegungstyp, menge, bestand_vorher, bestand_nachher,
                referenz, notiz, benutzer_id
            ) VALUES (
                :artikel_id, :lager_id, :lieferant_id, :ek_preis,
                :charge, :bewegungstyp, :menge, :bestand_vorher, :bestand_nachher,
                :referenz, :notiz, :benutzer_id
            )
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Gibt eine strukturierte Lager-Übersicht aller Artikel mit Bestand > 0.
     *
     * UNION ALL mit 4 Teilabfragen für die hierarchische Listenansicht:
     *
     *   'vater'          → Vater-Artikel (Summe der Kind-Bestände, keine eigene Bestandszeile)
     *   'kind'           → Kind-Artikel (Variante) mit je einer Zeile pro Charge/Lager-Kombination
     *   'standalone'     → Normale Artikel ohne Varianten (Kopfzeile mit Summe aller Chargen)
     *   'standalone_kind' → Normale Artikel: je eine Zeile pro Charge/Lager-Kombination
     *
     * Sortierung: Artikel-Name, dann zeilentyp DESC (Kopfzeilen vor Detail-Zeilen), dann Variante.
     */
    public function findUebersicht(): array
    {
        $stmt = $this->db->query("
            -- Vater-Artikel (haben Kinder, kein eigener Bestand)
            SELECT
                'vater' AS zeilentyp,
                a.id AS artikel_id,
                a.artikelnummer AS vater_artikelnummer,
                a.name AS artikel_name,
                NULL AS varianten_artikelnummer,
                NULL AS farbe,
                NULL AS lager_name,
                SUM(lb.bestand) AS bestand,
                NULL AS charge,
                NULL AS charge_status
            FROM artikel a
            INNER JOIN artikel kind ON kind.vaterartikel_id = a.id
            INNER JOIN lagerbestand lb ON lb.artikel_id = kind.id
            WHERE lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Kind-Artikel (je Charge/Lager eine Zeile)
            SELECT
                'kind' AS zeilentyp,
                a.vaterartikel_id AS artikel_id,
                NULL AS vater_artikelnummer,
                vater.name AS artikel_name,
                a.artikelnummer AS varianten_artikelnummer,
                a.name AS farbe,
                l.name AS lager_name,
                lb.bestand AS bestand,
                lb.charge AS charge,
                lb.charge_status AS charge_status
            FROM artikel a
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            INNER JOIN artikel vater ON vater.id = a.vaterartikel_id
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE lb.bestand > 0

            UNION ALL

            -- Standalone-Artikel (Kopf – Summe aller Chargen)
            SELECT
                'standalone' AS zeilentyp,
                a.id AS artikel_id,
                a.artikelnummer AS vater_artikelnummer,
                a.name AS artikel_name,
                NULL AS varianten_artikelnummer,
                NULL AS farbe,
                NULL AS lager_name,
                SUM(lb.bestand) AS bestand,
                NULL AS charge,
                NULL AS charge_status
            FROM artikel a
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0 AND lb.bestand > 0
            GROUP BY a.id, a.artikelnummer, a.name

            UNION ALL

            -- Standalone-Artikel (je Charge/Lager eine Zeile)
            SELECT
                'standalone_kind' AS zeilentyp,
                a.id AS artikel_id,
                a.artikelnummer AS vater_artikelnummer,
                a.name AS artikel_name,
                NULL AS varianten_artikelnummer,
                NULL AS farbe,
                l.name AS lager_name,
                lb.bestand AS bestand,
                lb.charge AS charge,
                lb.charge_status AS charge_status
            FROM artikel a
            INNER JOIN lagerbestand lb ON lb.artikel_id = a.id
            INNER JOIN lager l ON l.id = lb.lager_id
            WHERE a.vaterartikel_id IS NULL AND a.ist_vater = 0 AND lb.bestand > 0

            ORDER BY artikel_name, zeilentyp DESC, farbe
        ");
        return $stmt->fetchAll();
    }

    /** Gibt zurück ob ein Artikel Chargenpflicht hat (charge_pflicht = 1). */
    public function getChargePflicht(int $artikelId): bool
    {
        $stmt = $this->db->prepare("SELECT charge_pflicht FROM artikel WHERE id = :id");
        $stmt->execute(['id' => $artikelId]);
        $result = $stmt->fetch();
        return (bool) ($result['charge_pflicht'] ?? false);
    }

    /**
     * Gibt die ID eines Lagerbestand-Eintrags anhand Artikel/Lager/Charge zurück (nach einem
     * vorherigen upsertBestand(), um die Zeile z.B. für lagerbestand_lagerplaetze zu referenzieren).
     */
    public function findLagerbestandIdByKey(int $artikelId, int $lagerId, ?string $charge): ?int
    {
        if ($charge !== null) {
            $stmt = $this->db->prepare("
                SELECT id FROM lagerbestand WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge = :charge
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId, 'charge' => $charge]);
        } else {
            $stmt = $this->db->prepare("
                SELECT id FROM lagerbestand WHERE artikel_id = :artikel_id AND lager_id = :lager_id AND charge IS NULL
            ");
            $stmt->execute(['artikel_id' => $artikelId, 'lager_id' => $lagerId]);
        }
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /**
     * Trägt die gezählte Menge eines Lagerbestands an einem Lagerplatz ein (Inventur-Abschluss).
     * Additiv zur bestehenden Buchungslogik — betrifft nur die Lagerplatz-Zuordnung,
     * nicht den Gesamtbestand selbst (siehe Migration 136).
     */
    public function upsertLagerbestandLagerplatz(int $lagerbestandId, int $lagerplatzId, float $menge): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO lagerbestand_lagerplaetze (lagerbestand_id, lagerplatz_id, menge)
            VALUES (:lagerbestand_id, :lagerplatz_id, :menge)
            ON DUPLICATE KEY UPDATE menge = VALUES(menge)
        ");
        $stmt->execute(['lagerbestand_id' => $lagerbestandId, 'lagerplatz_id' => $lagerplatzId, 'menge' => $menge]);
    }

    /** Gibt einen Lagerbestand-Eintrag anhand seiner ID zurück (für Charge-Nachtrag). */
    public function findLagerbestandById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id, artikel_id, lager_id, bestand FROM lagerbestand WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Gibt den Bestand eines Artikels pro Lager + Charge zurück, gruppiert nach Lager.
     * Ergebnis-Format: Lager-ID → ['name', 'gesamt', 'mindestbestand', 'chargen']
     * Zeilen ohne Charge werden zur Lager-Summe addiert, aber nicht in 'chargen' aufgenommen.
     * Wird für die Lager-Tab-Anzeige in der Artikel-Detailseite verwendet.
     */
    public function findBestandChargeProLager(int $artikelId): array
    {
        $stmt = $this->db->prepare("
        SELECT
            lb.id,
            lb.lager_id,
            l.name AS lager_name,
            lb.charge,
            lb.charge_status,
            lb.bestand,
            lb.mindestbestand
        FROM lagerbestand lb
        JOIN lager l ON l.id = lb.lager_id
        WHERE lb.artikel_id = :artikel_id
        ORDER BY l.name, lb.charge
        ");

        $stmt->execute(['artikel_id' => $artikelId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lagerGruppen = [];

        foreach ($rows as $row) {
            $lid = $row['lager_id'];

            if (!isset($lagerGruppen[$lid])) {
                $lagerGruppen[$lid] = [
                    'name'          => $row['lager_name'],
                    'gesamt'        => 0,
                    'mindestbestand' => $row['mindestbestand'],
                    'chargen'       => [],
                ];
            }

            $lagerGruppen[$lid]['gesamt'] += $row['bestand'];
            $lagerGruppen[$lid]['chargen'][] = $row;
        }

        return $lagerGruppen;
    }

    /** Gibt alle aktiven Lager zurück (für Dropdowns). */
    public function findAlleLager(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM lager WHERE aktiv = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Lager-Stammdaten (Verwaltungs-UI)
    // -------------------------------------------------------------------------

    /**
     * Gibt alle Lager mit Partner-/Kundenname (falls verknüpft) zurück.
     *
     * @param array $filter Optionale Filter: ['aktiv' => 0|1]
     */
    public function findAlleMitDetails(array $filter = []): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (isset($filter['aktiv'])) {
            $conditions[] = 'l.aktiv = :aktiv';
            $params['aktiv'] = $filter['aktiv'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->prepare("
            SELECT l.*,
                   p.name AS partner_name,
                   k.kundennummer AS kunde_kundennummer
            FROM lager l
            LEFT JOIN partner p ON p.id = l.partner_id
            LEFT JOIN kunden  k ON k.id = l.kunde_id
            $where
            ORDER BY l.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Gibt ein Lager anhand ID zurück, oder false wenn nicht gefunden. */
    public function findLagerById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM lager WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /** Legt ein neues Lager an und gibt die neue ID zurück. */
    public function insertLager(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO lager (
                name, typ, aktiv, fuer_offline_kasse_waehlbar,
                lager_beziehung, partner_id, kunde_id
            ) VALUES (
                :name, :typ, :aktiv, :fuer_offline_kasse_waehlbar,
                :lager_beziehung, :partner_id, :kunde_id
            )
        ');
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Aktualisiert Name/Typ/Beziehung/Aktiv/Offline-Flag eines Lagers.
     * Partner-/Kunde-Zuweisung läuft separat, nicht hier.
     *
     * Bindet die Parameter explizit (statt $data direkt durchzureichen) — sonst wirft PDO
     * bei zusätzlichen, nicht in der Query verwendeten Array-Keys (z.B. partner_id/kunde_id)
     * "SQLSTATE[HY093]: Invalid parameter number" (gleiches Muster wie der Hersteller-Insert-Bug).
     */
    public function updateLager(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE lager SET
                name                        = :name,
                typ                         = :typ,
                aktiv                       = :aktiv,
                fuer_offline_kasse_waehlbar = :fuer_offline_kasse_waehlbar,
                lager_beziehung             = :lager_beziehung
            WHERE id = :id
        ');
        $stmt->execute([
            'name'                        => $data['name'],
            'typ'                         => $data['typ'],
            'aktiv'                       => $data['aktiv'],
            'fuer_offline_kasse_waehlbar' => $data['fuer_offline_kasse_waehlbar'],
            'lager_beziehung'             => $data['lager_beziehung'],
            'id'                          => $data['id'],
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Setzt den Aktiv-Status eines Lagers (1 = aktiv, 0 = inaktiv). */
    public function setLagerAktiv(int $id, int $aktiv): bool
    {
        $stmt = $this->db->prepare('UPDATE lager SET aktiv = :aktiv WHERE id = :id');
        $stmt->execute(['aktiv' => $aktiv, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Summiert den Bestand eines Lagers über alle Artikel/Chargen (für die Deaktivieren-Sperre). */
    public function getGesamtbestandFuerLager(int $lagerId): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(bestand), 0) FROM lagerbestand WHERE lager_id = :lager_id');
        $stmt->execute(['lager_id' => $lagerId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Gibt die Lagerbewegungen für einen Artikel zurück.
     * Enthält: Bewegungstyp, Menge, Bestand vorher/nachher, Charge, Referenz (Bestellnummer),
     * Benutzer-Formularname und Lager-Name.
     * Ohne Charge-Filter: nur die letzten 10 (schnelle Standardansicht).
     * Mit Charge-Filter: vollständige Historie dieser einen Charge (EK bis letzter Verkauf) — kein Limit.
     */
    public function findBewegungslogFuerArtikel(int $artikelId, ?string $charge = null): array
    {
        $sql = "
        SELECT
            lb.artikel_id,
            lb.lager_id,
            l.name,
            lb.bewegungstyp,
            lb.menge,
            lb.bestand_vorher,
            lb.bestand_nachher,
            lb.charge,
            lb.referenz,
            lb.notiz,
            lb.erstellt_am,
            b.formularname,
            l.name AS lager_name
            FROM lager_bewegungen lb
            LEFT JOIN benutzer b ON b.id = lb.benutzer_id
            JOIN lager l ON l.id = lb.lager_id
            WHERE lb.artikel_id = :artikel_id
        ";
        $params = ['artikel_id' => $artikelId];

        if ($charge !== null) {
            $sql .= " AND lb.charge = :charge";
            $params['charge'] = $charge;
        }

        $sql .= " ORDER BY lb.erstellt_am DESC";

        if ($charge === null) {
            $sql .= " LIMIT 10";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Alle bekannten (historischen) Chargen eines Artikels — für das Chargen-Filter-Dropdown. */
    public function findChargenFuerArtikel(int $artikelId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT charge FROM lager_bewegungen
            WHERE artikel_id = :artikel_id AND charge IS NOT NULL
            ORDER BY charge
        ");
        $stmt->execute(['artikel_id' => $artikelId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // -------------------------------------------------------------------------
    // Lagerplätze (Regal/Fach-Struktur, Voraussetzung fürs Inventur-Modul)
    // -------------------------------------------------------------------------

    /** Gibt alle Lagerplätze mit Lagername zurück, optional nach Lager oder Aktiv-Status gefiltert. */
    public function findAlleLagerplaetze(int $lagerId = 0, ?int $aktiv = null): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($lagerId > 0) {
            $where[] = 'lp.lager_id = :lager_id';
            $params['lager_id'] = $lagerId;
        }
        if ($aktiv !== null) {
            $where[] = 'lp.aktiv = :aktiv';
            $params['aktiv'] = $aktiv;
        }

        $stmt = $this->db->prepare("
            SELECT lp.*, l.name AS lager_name
            FROM lagerplaetze lp
            JOIN lager l ON l.id = lp.lager_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.name, lp.bezeichnung
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findLagerplatzById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM lagerplaetze WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function insertLagerplatz(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO lagerplaetze (lager_id, bezeichnung, aktiv)
            VALUES (:lager_id, :bezeichnung, :aktiv)
        ');
        $stmt->execute([
            'lager_id'    => $data['lager_id'],
            'bezeichnung' => $data['bezeichnung'],
            'aktiv'       => $data['aktiv'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateLagerplatz(array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE lagerplaetze SET lager_id = :lager_id, bezeichnung = :bezeichnung, aktiv = :aktiv
            WHERE id = :id
        ');
        $stmt->execute([
            'lager_id'    => $data['lager_id'],
            'bezeichnung' => $data['bezeichnung'],
            'aktiv'       => $data['aktiv'],
            'id'          => $data['id'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function setLagerplatzAktiv(int $id, int $aktiv): bool
    {
        $stmt = $this->db->prepare('UPDATE lagerplaetze SET aktiv = :aktiv WHERE id = :id');
        $stmt->execute(['aktiv' => $aktiv, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
