<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/AuftragRepository.php';

/**
 * AuftragAbschluss – EINZIGE Stelle, die über "abgeschlossen" / "Retoure offen" entscheidet
 * (Belege-Umbau, Jacky 2026-10-07). Vorher setzten Packplatz, Zahlung buchen, Bearbeiten,
 * Kasse und Shop-Sync "abgeschlossen" jeweils selbst -- ohne zu prüfen, ob es einen Beleg gibt.
 *
 * Abgeschlossen nur, wenn ALLES erfüllt ist:
 *   1. vollständig ausgeliefert (Versand: menge_geliefert, Abholung: menge_abgeholt)
 *   2. alles Ausgelieferte steht auf einem Beleg (menge_verrechnet: Rechnung oder Bon)
 *   3. keine Retoure offen (s.u.)
 *   4. bezahlt bzw. erstattet
 *
 * Retoure offen: Ware ist zurück, aber am Packplatz noch nicht eingelagert (offene
 * Rücklagerung) ODER verrechnete Ware zurückgenommen und noch nicht gutgeschrieben/erstattet.
 * Ist das erledigt, wird wieder normal geprüft -- ein abgeschlossener Auftrag öffnet sich
 * bei einer Retoure also automatisch und schließt sich danach wieder.
 *
 * Aufrufen nach jedem Ereignis, das etwas davon ändert: Lieferung, Abholung, Rechnung,
 * Zahlung, Retoure, Gutschrift, Rücklagerung eingebucht. Idempotent.
 */
class AuftragAbschluss
{
    /** Aufträge, die diese Prüfung nie anfasst. */
    private const AUSGENOMMENE_KANAELE = ['jtl_archiv', 'kasse'];

    /** @return string|null neuer Lieferstatus, null = unverändert */
    public static function pruefe(int $auftragId, ?int $benutzerId = null): ?string
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, kanal, lieferart, lieferstatus, zahlungsstatus FROM auftraege WHERE id = ?");
        $stmt->execute([$auftragId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a || $a['lieferstatus'] === 'storniert' || in_array($a['kanal'], self::AUSGENOMMENE_KANAELE, true)) {
            return null;
        }

        $k = self::kriterien($auftragId, $a);
        $alt = $a['lieferstatus'];

        if ($k['retoure_offen']) {
            $neu = 'retoure_offen';
        } elseif ($k['geliefert'] && $k['belegt'] && $k['bezahlt']) {
            $neu = 'abgeschlossen';
        } elseif (in_array($alt, ['abgeschlossen', 'retoure_offen'], true)) {
            // Fällt ein Kriterium wieder weg (z.B. Retoure erledigt, aber Rest unbezahlt)
            $neu = $k['geliefert'] ? 'versendet' : 'teilgeliefert';
        } else {
            return null;
        }
        if ($neu === $alt) return null;

        $repo = new AuftragRepository();
        $repo->updateStatus($auftragId, ['lieferstatus' => $neu]);
        $repo->logStatus($auftragId, ['lieferstatus' => [$alt, $neu]], self::grund($neu, $k), $benutzerId ?? self::jarvisId());
        if ($neu === 'abgeschlossen') {
            $repo->schliesseReservierungen($auftragId);
        }
        return $neu;
    }

    /**
     * Einzelne Kriterien -- auch für Anzeigen (z.B. "geliefert, aber nicht verrechnet").
     * @return array{geliefert: bool, belegt: bool, retoure_offen: bool, bezahlt: bool}
     */
    public static function kriterien(int $auftragId, ?array $auftrag = null): array
    {
        $db = Database::getInstance();
        if (!$auftrag) {
            $s = $db->prepare("SELECT id, lieferart, zahlungsstatus FROM auftraege WHERE id = ?");
            $s->execute([$auftragId]);
            $auftrag = $s->fetch(PDO::FETCH_ASSOC);
        }
        $istAbholung = ($auftrag['lieferart'] ?? '') === 'abholung';

        $pos = $db->prepare("
            SELECT menge, menge_geliefert, menge_abgeholt, menge_retourniert, menge_gutgeschrieben, menge_verrechnet
            FROM auftrag_positionen WHERE auftrag_id = ? AND menge > 0
        ");
        $pos->execute([$auftragId]);

        $geliefert = true; $belegt = true; $gsOffen = false;
        foreach ($pos->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $raus = $istAbholung ? (int)$p['menge_abgeholt'] : (int)$p['menge_geliefert'];
            if ($raus < (int)$p['menge']) $geliefert = false;

            // Ausgeliefert und behalten (Abholung: "will er nicht" läuft als Retoure)
            $zuBelegen = $istAbholung ? $raus - (int)$p['menge_retourniert'] : $raus;
            if ($zuBelegen > (int)$p['menge_verrechnet']) $belegt = false;

            // Verrechnete Ware zurück, aber noch nicht gutgeschrieben/erstattet
            if (min((int)$p['menge_retourniert'], (int)$p['menge_verrechnet']) > (int)$p['menge_gutgeschrieben']) {
                $gsOffen = true;
            }
        }

        $rl = $db->prepare("SELECT COUNT(*) FROM packplatz_ruecklagerungen WHERE auftrag_id = ? AND status = 'offen'");
        $rl->execute([$auftragId]);

        // Bezahlt = Beleg-Saldo 0 (Rechnungen/Bons − Korrekturen − Zahlungen). Vorher galt der
        // Zahlungsstatus -- eine Stornorechnung setzte "erstattet", obwohl das Geld noch nicht
        // zurückgezahlt war (Klicktest 2026-10-07, A-2026-00067). Ohne Belege: Zahlungsstatus.
        $saldo = self::hatBelege($auftragId) ? self::saldo($auftragId) : null;
        $bezahlt = $saldo === null
            ? in_array($auftrag['zahlungsstatus'], ['bezahlt', 'erstattet'], true)
            : abs($saldo) < 0.005;
        // Nach Retoure/Korrektur noch Geld an den Kunden zurückzuzahlen -> Retoure ist nicht erledigt
        // (nur wenn alles verrechnet ist -- sonst fehlt noch eine Rechnung und der Saldo sagt nichts)
        $erstattungOffen = $belegt && $saldo !== null && $saldo < -0.004 && ($gsOffen || self::hatKorrekturOderRetoure($auftragId));

        return [
            'geliefert'     => $geliefert,
            'belegt'        => $belegt,
            'retoure_offen' => $gsOffen || (int)$rl->fetchColumn() > 0 || $erstattungOffen,
            'bezahlt'       => $bezahlt,
            'saldo'         => $saldo,
        ];
    }

    /**
     * Was bei "Retoure offen" konkret fehlt -- für die Anzeige im Auftrag:
     *   korrektur:     zurückgenommene, verrechnete Ware ohne Rechnungskorrektur/Erstattung
     *   ruecklagerung: am Packplatz noch nicht eingebuchte Rücklagerungen
     *   erstattung:    Guthaben des Kunden (Korrektur da, Geld noch nicht zurück)
     */
    public static function retoureDetails(int $auftragId): array
    {
        require_once __DIR__ . '/Positionsrechnung.php';
        $db = Database::getInstance();
        $korrektur = [];
        $p = $db->prepare("SELECT * FROM auftrag_positionen WHERE auftrag_id = ? AND menge > 0 ORDER BY sort_order, id");
        $p->execute([$auftragId]);
        foreach ($p->fetchAll(PDO::FETCH_ASSOC) as $pos) {
            $offen = min((int)$pos['menge_retourniert'], (int)$pos['menge_verrechnet']) - (int)$pos['menge_gutgeschrieben'];
            if ($offen > 0) {
                $korrektur[] = ['bezeichnung' => $pos['bezeichnung'], 'menge' => $offen,
                                'betrag' => Positionsrechnung::ausPosition($pos, (float)$offen)['brutto']];
            }
        }
        $rl = $db->prepare("SELECT bezeichnung, menge, COALESCE(bon_nr, gutschrift_nr) AS quelle FROM packplatz_ruecklagerungen WHERE auftrag_id = ? AND status = 'offen'");
        $rl->execute([$auftragId]);
        $saldo = self::saldoAussagekraeftig($auftragId) ? self::saldo($auftragId) : 0.0;
        return [
            'korrektur'     => $korrektur,
            'ruecklagerung' => $rl->fetchAll(PDO::FETCH_ASSOC),
            'erstattung'    => $saldo < -0.004 ? round(-$saldo, 2) : 0.0,
        ];
    }

    /**
     * Ist der Beleg-Saldo aussagekräftig? Nur wenn es Belege gibt UND alle ausgelieferte Ware
     * darauf steht. Sonst (z.B. Abholung von vor dem Belege-Umbau ohne Rechnung, nur eine
     * Bon-Retourzeile) wäre der Saldo Unsinn -- Klicktest 2026-10-07, A-2026-00060 zeigte
     * "Rückerstattung offen 7,50" statt 0. Dann gilt Auftragsbetrag − Zahlungen.
     */
    public static function saldoAussagekraeftig(int $auftragId): bool
    {
        return self::hatBelege($auftragId) && self::kriterien($auftragId)['belegt'];
    }

    /** Saldo aus Belegen (siehe DokumentService::offenerRechnungsbetrag). */
    public static function saldo(int $auftragId): float
    {
        require_once __DIR__ . '/../dokumente/DokumentService.php';
        return (new DokumentService())->offenerRechnungsbetrag($auftragId);
    }

    /** Gibt es zum Auftrag überhaupt einen Beleg (Rechnung, Korrektur, Kassen-Bon-Zeile)? */
    public static function hatBelege(int $auftragId): bool
    {
        $s = Database::getInstance()->prepare("
            SELECT EXISTS(SELECT 1 FROM rechnungen WHERE auftrag_id = :a1)
                OR EXISTS(SELECT 1 FROM gutschriften WHERE auftrag_id = :a2)
                OR EXISTS(SELECT 1 FROM kassen_bon_positionen bp JOIN kassen_bons b ON b.id = bp.bon_id AND b.storniert = 0
                          WHERE bp.web_auftrag_id = :a3 AND bp.block IN ('auftrag', 'retour'))
        ");
        $s->execute([':a1' => $auftragId, ':a2' => $auftragId, ':a3' => $auftragId]);
        return (bool)$s->fetchColumn();
    }

    private static function hatKorrekturOderRetoure(int $auftragId): bool
    {
        $s = Database::getInstance()->prepare("
            SELECT EXISTS(SELECT 1 FROM gutschriften WHERE auftrag_id = :a1)
                OR EXISTS(SELECT 1 FROM auftrag_positionen WHERE auftrag_id = :a2 AND menge_retourniert > 0)
        ");
        $s->execute([':a1' => $auftragId, ':a2' => $auftragId]);
        return (bool)$s->fetchColumn();
    }

    /**
     * Zahlungsstatus aus dem Beleg-Saldo setzen (nach Rechnungskorrektur / Rückerstattung):
     * Saldo > 0 -> teilbezahlt/ausstehend · Saldo 0 -> bezahlt, bzw. "erstattet", wenn
     * netto nichts mehr bezahlt ist · Saldo < 0 (Rückerstattung offen) -> bleibt bezahlt.
     */
    public static function zahlungsstatusAusBelegen(int $auftragId, ?int $benutzerId = null): ?string
    {
        if (!self::saldoAussagekraeftig($auftragId)) return null;
        $db = Database::getInstance();
        $a = $db->prepare("SELECT zahlungsstatus, gutschein_betrag FROM auftraege WHERE id = ?");
        $a->execute([$auftragId]);
        $auftrag = $a->fetch(PDO::FETCH_ASSOC);
        if (!$auftrag || $auftrag['zahlungsstatus'] === 'storniert') return null;

        $saldo = self::saldo($auftragId);
        $z = $db->prepare("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = ?");
        $z->execute([$auftragId]);
        $netto = (float)$z->fetchColumn() + (float)$auftrag['gutschein_betrag'];

        $neu = $saldo > 0.004 ? ($netto > 0.004 ? 'teilbezahlt' : 'ausstehend')
             : ($saldo < -0.004 ? 'bezahlt' : ($netto <= 0.004 ? 'erstattet' : 'bezahlt'));
        if ($neu === $auftrag['zahlungsstatus']) return null;

        $repo = new AuftragRepository();
        $repo->updateStatus($auftragId, ['zahlungsstatus' => $neu]);
        $repo->logStatus($auftragId, ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neu]],
            'Zahlungsstatus aus den Belegen (Saldo ' . number_format($saldo, 2, ',', '.') . ' €)', $benutzerId ?? self::jarvisId());
        return $neu;
    }

    private static function grund(string $neu, array $k): string
    {
        return match ($neu) {
            'abgeschlossen' => 'Automatisch abgeschlossen (vollständig geliefert, verrechnet und bezahlt)',
            'retoure_offen' => 'Retoure offen (Ware zurück — Einlagerung am Packplatz bzw. Gutschrift/Erstattung ausständig)',
            default         => 'Wieder geöffnet — ' . implode(', ', array_filter([
                                   !$k['geliefert'] ? 'nicht vollständig geliefert' : null,
                                   !$k['belegt']    ? 'nicht vollständig verrechnet' : null,
                                   !$k['bezahlt']   ? 'nicht vollständig bezahlt' : null,
                               ])),
        };
    }

    private static function jarvisId(): int
    {
        static $id = null;
        $id ??= (int)Database::getInstance()->query("SELECT id FROM benutzer WHERE username = 'system' LIMIT 1")->fetchColumn();
        return $id;
    }
}
