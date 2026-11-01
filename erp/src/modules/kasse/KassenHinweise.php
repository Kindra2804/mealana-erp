<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Lizenz.php';

/**
 * Hinweise oben in der Kasse (Lizenz-Bau Schritt 2, Jacky 2026-11-01):
 *  - Wartung abgelaufen (Kasse läuft weiter, aber keine Updates mehr)
 *  - Signaturzertifikat läuft ab / ist abgelaufen — nur bei aktiver RKSV, unabhängig von der Wartung
 */
class KassenHinweise
{
    public const VORLAUF_TAGE = 60;

    /** [['stufe' => 'warn'|'fehler', 'text' => …], …] */
    public static function fuerKasse(int $kasseId): array
    {
        $hinweise = [];
        if (Lizenz::wartungAbgelaufen()) {
            $hinweise[] = ['stufe' => 'fehler', 'text' => 'Wartung abgelaufen! Die Kasse funktioniert weiter, es gibt aber keine Updates mehr '
                . '(auch keine gesetzlichen Anpassungen). Wartung bei NAHTLOS verlängern.'];
        }

        $bis = self::zertifikatGueltigBis($kasseId);
        if ($bis !== null) {
            $tage = (int)floor((strtotime($bis) - strtotime(date('Y-m-d'))) / 86400);
            if ($tage < 0) {
                $hinweise[] = ['stufe' => 'fehler', 'text' => 'Signaturzertifikat abgelaufen seit ' . date('d.m.Y', strtotime($bis))
                    . ' — die Kasse kann nicht mehr gültig signieren. Neue Signaturkarte einsetzen und registrieren.'];
            } elseif ($tage <= self::VORLAUF_TAGE) {
                $hinweise[] = ['stufe' => 'warn', 'text' => 'Signaturzertifikat läuft am ' . date('d.m.Y', strtotime($bis))
                    . ' ab (noch ' . $tage . ' Tage) — rechtzeitig eine neue Signaturkarte bestellen.'];
            }
        }
        return $hinweise;
    }

    /**
     * Ablaufdatum des Zertifikats der aktiven RKSV-Registrierung (Y-m-d) oder null,
     * wenn die Kasse nicht RKSV-aktiv ist. Ohne Eintrag: gemeldet am + 5 Jahre.
     */
    public static function zertifikatGueltigBis(int $kasseId): ?string
    {
        $stmt = Database::getInstance()->prepare("
            SELECT COALESCE(r.zertifikat_gueltig_bis, DATE(DATE_ADD(r.zertifikat_gemeldet_am, INTERVAL 5 YEAR)))
            FROM kassen k
            JOIN bfr_kassen_registrierungen r ON r.kasse_id = k.id AND r.abgeschlossen_am IS NOT NULL
            WHERE k.id = ? AND k.bfr_aktiv_seit IS NOT NULL AND r.zertifikat_gemeldet_am IS NOT NULL
            ORDER BY r.abgeschlossen_am DESC LIMIT 1
        ");
        $stmt->execute([$kasseId]);
        return $stmt->fetchColumn() ?: null;
    }
}
