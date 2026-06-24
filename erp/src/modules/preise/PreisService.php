<?php

require_once __DIR__ . '/PreisRepository.php';
require_once __DIR__ . '/../../core/Logger.php';

/**
 * PreisService – Geschäftslogik für alle Preisebenen eines Artikels
 *
 * Verwaltet drei Preistypen:
 *   1. Kundengruppen-Preise (artikel_preise) — Standard-Preismatrix
 *   2. Staffelpreise (artikel_staffelpreise) — Mengenrabatte
 *   3. SALE-Overrides (preis_aktionen_positionen) — zeitlich begrenzte Sonderpreise
 *
 * Kernmethode getEffektiverPreis(): bestimmt den aktuell gültigen Preis für eine
 * Artikel/KG-Kombination über 4 Prioritätsstufen.
 *
 * Prioritätskette (höchste zuerst):
 *   sale → aktion → kundengruppe → standard
 *
 * Rückgabe von getEffektiverPreis():
 *   ['brutto_vk' => float|null, 'netto_vk' => float|null,
 *    'quelle' => 'sale'|'aktion'|'kundengruppe'|'standard'|'kein_preis',
 *    'info' => string|null, 'bis' => string|null]
 */
class PreisService
{
    private PreisRepository $repo;

    public function __construct()
    {
        $this->repo = new PreisRepository();
    }

    /**
     * Gibt die Preismatrix (alle KGs + ihre Preise) für einen Artikel zurück.
     * KGs ohne Preis sind im Ergebnis mit NULL-Werten dabei.
     */
    public function getKundengruppenPreise(int $artikelId): array
    {
        return $this->repo->findKundengruppenPreise($artikelId);
    }

    /**
     * Gibt alle Aktionen zurück in denen dieser Artikel einen Aktionspreis hat.
     * Für die Anzeige im Preis-Tab der Artikel-Detailansicht.
     */
    public function getAktionenFuerArtikel(int $artikelId): array
    {
        return $this->repo->findAktionenFuerArtikel($artikelId);
    }

    /** Löscht den Preis einer KG für einen Artikel und loggt es. */
    public function loescheKundengruppenPreis(int $artikelId, int $kgId): array
    {
        $this->repo->deleteKundengruppenPreis($artikelId, $kgId);
        Logger::log('preis.kundengruppe.loeschen', 'artikel_preise', $artikelId, ['kg_id' => $kgId]);
        return ['erfolg' => true];
    }

    /** Gibt alle Staffelpreise eines Artikels zurück. */
    public function getStaffelpreise(int $artikelId): array
    {
        return $this->repo->findStaffelpreise($artikelId);
    }

    /**
     * Speichert oder aktualisiert einen Staffelpreis.
     * Pflichtfelder: artikel_id, kundengruppen_id, menge_ab, brutto_vk, netto_vk.
     * Wenn $data['id'] gesetzt → Update, sonst Insert.
     */
    public function speichereStaffelpreis(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id']))       $fehler[] = 'Artikel fehlt';
        if (empty($data['kundengruppen_id'])) $fehler[] = 'Kundengruppe fehlt';
        if (!isset($data['menge_ab']) || $data['menge_ab'] === '') $fehler[] = 'Menge fehlt';
        if (!isset($data['brutto_vk']) || $data['brutto_vk'] === '') $fehler[] = 'Brutto VK fehlt';
        if (!isset($data['netto_vk'])  || $data['netto_vk']  === '') $fehler[] = 'Netto VK fehlt';

        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => implode(', ', $fehler)];

        if (!empty($data['id'])) {
            $this->repo->updateStaffelpreis($data);
            Logger::log('preis.staffel.bearbeiten', 'artikel_staffelpreise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id'], 'menge_ab' => $data['menge_ab']]);
        } else {
            $this->repo->insertStaffelpreis($data);
            Logger::log('preis.staffel.anlegen', 'artikel_staffelpreise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id'], 'menge_ab' => $data['menge_ab']]);
        }
        return ['erfolg' => true];
    }

    /** Löscht einen Staffelpreis und loggt es. */
    public function loescheStaffelpreis(int $id, int $artikelId): array
    {
        $this->repo->deleteStaffelpreis($id, $artikelId);
        Logger::log('preis.staffel.loeschen', 'artikel_staffelpreise', $artikelId, ['staffelpreis_id' => $id]);
        return ['erfolg' => true];
    }

    /**
     * Speichert oder aktualisiert einen Kundengruppen-Preis.
     * Pflichtfelder: artikel_id, kundengruppen_id, brutto_vk, netto_vk.
     */
    public function speichereKundengruppenPreis(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id']))       $fehler[] = 'Artikel fehlt';
        if (empty($data['kundengruppen_id'])) $fehler[] = 'Kundengruppe fehlt';
        if (!isset($data['brutto_vk']) || $data['brutto_vk'] === '') $fehler[] = 'Brutto VK fehlt';
        if (!isset($data['netto_vk'])  || $data['netto_vk']  === '') $fehler[] = 'Netto VK fehlt';

        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => implode(', ', $fehler)];

        $this->repo->upsertKundengruppenPreis($data);
        Logger::log('preis.kundengruppe.speichern', 'artikel_preise', $data['artikel_id'], ['kg_id' => $data['kundengruppen_id']]);
        return ['erfolg' => true];
    }

    /**
     * Bestimmt den aktuell gültigen Preis für einen Artikel für eine Kundengruppe.
     *
     * Prioritätskette (4 Stufen):
     *   Schritt 1: SALE-Override  → preis_aktionen_positionen (zeitlich begrenzte Sonderpreise)
     *   Schritt 2: Kategorie-Aktion → aktionen_artikel_preise (Aktionsmodul, gestartet + Zeitraum)
     *   Schritt 3: KG-Festpreis   → artikel_preise für diese KG (mit optionalem Zeitraum)
     *   Schritt 4: Standard-Preis → artikel_preise der Standard-KG (Fallback)
     *
     * Wenn kein Preis gefunden: quelle = 'kein_preis', brutto_vk = null.
     *
     * @return array ['brutto_vk', 'netto_vk', 'quelle', 'info', 'bis']
     */
    public function getEffektiverPreis(int $artikelId, int $kgId): array
    {
        // Priorität 1: SALE-Override (höchste Priorität — überschreibt alles)
        $sale = $this->repo->findSaleOverride($artikelId, $kgId);
        if ($sale) {
            return ['brutto_vk' => $sale['brutto_vk'], 'netto_vk' => $sale['netto_vk'], 'quelle' => 'sale', 'info' => null, 'bis' => $sale['gueltig_bis']];
        }
        // Priorität 2: Aktionspreis aus Aktionsmodul
        $aktion = $this->repo->findAktionsPreis($artikelId, $kgId);
        if ($aktion) {
            return ['brutto_vk' => $aktion['brutto_vk'], 'netto_vk' => $aktion['netto_vk'], 'quelle' => 'aktion', 'info' => $aktion['aktion_name'], 'bis' => $aktion['gueltig_bis']];
        }
        // Priorität 3: KG-spezifischer Festpreis
        $kgPreis = $this->repo->findKundengruppenPreisFuerKg($artikelId, $kgId);
        if ($kgPreis) {
            return ['brutto_vk' => $kgPreis['brutto_vk'], 'netto_vk' => $kgPreis['netto_vk'], 'quelle' => 'kundengruppe', 'info' => $kgPreis['name'], 'bis' => null];
        }
        // Priorität 4: Fallback → Standard-KG-Preis
        $standard = $this->repo->findStandardPreis($artikelId);
        if ($standard) {
            return ['brutto_vk' => $standard['brutto_vk'], 'netto_vk' => $standard['netto_vk'], 'quelle' => 'standard', 'info' => null, 'bis' => null];
        }

        // Kein Preis gefunden
        return ['brutto_vk' => null, 'netto_vk' => null, 'quelle' => 'kein_preis', 'info' => null, 'bis' => null];
    }

    /** Gibt alle SALE-Overrides für einen Artikel zurück (inkl. aktiv/inaktiv Status). */
    public function getSaleOverridesFuerArtikel(int $artikelId): array
    {
        return $this->repo->findSaleOverridesFuerArtikel($artikelId);
    }

    /**
     * Speichert oder aktualisiert einen SALE-Override.
     * Pflichtfelder: artikel_id, brutto_vk, netto_vk.
     * Loggt je nachdem ob Anlegen oder Bearbeiten.
     */
    public function speichereSaleOverride(array $data): array
    {
        $fehler = [];
        if (empty($data['artikel_id']))                              $fehler[] = 'Artikel fehlt';
        if (!isset($data['brutto_vk']) || $data['brutto_vk'] === '') $fehler[] = 'Brutto VK fehlt';
        if (!isset($data['netto_vk'])  || $data['netto_vk']  === '') $fehler[] = 'Netto VK fehlt';
        if (!empty($fehler)) return ['erfolg' => false, 'fehler' => implode(', ', $fehler)];

        $saleId = $this->repo->upsertSaleOverride($data);
        Logger::log(
            !empty($data['id']) ? 'preis.sale.bearbeiten' : 'preis.sale.anlegen',
            'preis_aktionen_positionen',
            (int)$data['artikel_id'],
            ['sale_id' => $saleId, 'brutto_vk' => $data['brutto_vk']]
        );
        return ['erfolg' => true];
    }

    /** Löscht einen SALE-Override und loggt es. */
    public function loescheSaleOverride(int $id, int $artikelId): array
    {
        $this->repo->deleteSaleOverride($id, $artikelId);
        Logger::log('preis.sale.loeschen', 'preis_aktionen_positionen', $artikelId, ['sale_id' => $id]);
        return ['erfolg' => true];
    }
}
