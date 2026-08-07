<?php
/**
 * Baut die HTML-Zeilen für "Letzte Cron-/Komplettabgleich-Aktivität" --
 * von index.php beim ersten Seitenaufbau UND von shop_sync_status.php für
 * die Live-Aktualisierung während eines laufenden Komplettabgleichs benutzt
 * (ein Formatierungsort statt zwei Kopien in PHP und JS).
 */
function renderShopSyncAktivitaetenZeilen(array $laeufe): string
{
    $html = '';
    foreach (array_slice($laeufe, 0, 5) as $lauf) {
        $d     = json_decode($lauf['details'] ?? '', true) ?: [];
        $farbe = $lauf['stufe'] === 'error' ? 'var(--color-danger)' : ($lauf['stufe'] === 'warn' ? '#e67e22' : 'inherit');

        $html .= '<div style="font-size:12px;padding:2px 0;color:' . $farbe . '">';
        $html .= htmlspecialchars($lauf['erstellt_am']) . ' — ';

        if ($lauf['aktion'] === 'shop.sync_lauf') {
            $html .= 'Cron (' . htmlspecialchars($d['richtung'] ?? '?') . '): '
                . (int)($d['erfolg'] ?? 0) . ' erfolgreich, ' . (int)($d['fehler'] ?? 0) . ' Fehler';
        } elseif ($lauf['aktion'] === 'shop.cron_fehler') {
            $html .= 'Cron-Fehler: ' . htmlspecialchars($d['fehler'] ?? '');
        } elseif ($lauf['aktion'] === 'shop.bilder_ftp_gestartet') {
            $html .= 'Bilder-Verknüpfung (FTP) manuell gestartet';
        } else {
            $html .= 'Komplettabgleich manuell gestartet (Batch ' . (int)($d['batch_groesse'] ?? 0)
                . (empty($d['mit_bildern']) ? ', ohne Bilder' : '') . ')';
        }

        $html .= '</div>';
    }
    return $html;
}
