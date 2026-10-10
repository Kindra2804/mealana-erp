<?php
/**
 * Partial: rendert die Lagerbewegungen-Tabelle.
 * Erwartet $bewegungslog (Array) im Scope — wird sowohl direkt in detail.php
 * als auch von bewegungslog_ajax.php (bei Chargen-Filterwechsel) eingebunden.
 */

// Alte Messe-Referenzen ("Messe-Umbuchung Sync #2") in die lesbare Form bringen,
// neue Buchungen schreiben bereits "Messe Nr. 2: zur Messe".
$bewegungReferenzText = static function (?string $ref): string {
    if ($ref === null || $ref === '') return '';
    $alt = [
        '/^Messe-Umbuchung Sync #(\d+)$/u' => 'Messe Nr. $1: zur Messe',
        '/^Messe-Rückkehr Sync #(\d+)$/u'  => 'Messe Nr. $1: Rückbuchung',
        '/^Messe-Verkäufe Sync #(\d+)$/u'  => 'Messe Nr. $1: verkauft',
        '/^Schwund Messe Sync #(\d+)$/u'   => 'Messe Nr. $1: Schwund',
    ];
    foreach ($alt as $muster => $ersatz) {
        $neu = preg_replace($muster, $ersatz, $ref);
        if ($neu !== $ref) return $neu;
    }
    return $ref;
};

// Umlagerungen (Ausgang Lager A + Eingang Lager B, gleiche Buchung) zu EINER
// Zeile zusammenfassen — sonst stehen bei jeder Messe je Charge zwei Zeilen da,
// bei denen niemand erkennt, wohin die Ware gegangen ist.
$bewegungZeilen = [];
$verbraucht     = [];
foreach ($bewegungslog as $i => $b) {
    if (isset($verbraucht[$i])) continue;
    if (in_array($b['bewegungstyp'], ['ausgang', 'eingang'], true)) {
        foreach ($bewegungslog as $j => $g) {
            if ($j === $i || isset($verbraucht[$j])) continue;
            if ($g['bewegungstyp'] !== ($b['bewegungstyp'] === 'ausgang' ? 'eingang' : 'ausgang')) continue;
            if ($g['lager_id'] == $b['lager_id'] || $g['erstellt_am'] !== $b['erstellt_am']
                || (float)$g['menge'] !== (float)$b['menge'] || ($g['charge'] ?? '') !== ($b['charge'] ?? '')
                || ($g['referenz'] ?? '') !== ($b['referenz'] ?? '')) continue;
            $aus = $b['bewegungstyp'] === 'ausgang' ? $b : $g;
            $ein = $b['bewegungstyp'] === 'ausgang' ? $g : $b;
            $verbraucht[$j] = true;
            $bewegungZeilen[] = $b + ['umlagerung' => ['aus' => $aus, 'ein' => $ein]];
            continue 2;
        }
    }
    $bewegungZeilen[] = $b;
}
// Ohne Chargen-Filter nur die letzten 10 (Abfrage holt 20 Rohzeilen)
if (empty($charge)) {
    $bewegungZeilen = array_slice($bewegungZeilen, 0, 10);
}

if (empty($bewegungslog)): ?>
    <p style="color:var(--color-text-muted);font-size:13px">Noch keine Lagerbewegungen vorhanden.</p>
<?php else: ?>
    <table class="erp-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Typ</th>
                <th style="text-align:right">Menge</th>
                <th>Vorher → Nachher</th>
                <th>Charge</th>
                <th>Lager</th>
                <th>Referenz / Notiz</th>
                <th>Benutzer</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $typFarben = [
                'eingang'   => ['#dcfce7', '#166534'],
                'ausgang'   => ['#fee2e2', '#991b1b'],
                'korrektur' => ['#fff7ed', '#9a3412'],
                'inventur'  => ['#eff6ff', '#1e40af'],
                'schwund'   => ['#fef3c7', '#92400e'],
                'umlagerung'=> ['#f3e8ff', '#6b21a8'],
            ];
            ?>
            <?php foreach ($bewegungZeilen as $b): ?>
                <?php $uml = $b['umlagerung'] ?? null; ?>
                <?php [$bg, $fg] = $typFarben[$uml ? 'umlagerung' : $b['bewegungstyp']] ?? ['#f1f5f9', '#334155']; ?>
                <tr>
                    <td style="white-space:nowrap"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                    <td>
                        <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600">
                            <?= $uml ? 'Umlagerung' : htmlspecialchars(ucfirst($b['bewegungstyp'])) ?>
                        </span>
                    </td>
                    <td style="text-align:right"><?= formatBestand($b['menge']) ?></td>
                    <?php if ($uml): ?>
                    <td style="white-space:nowrap;font-size:12px">
                        <?= htmlspecialchars($uml['aus']['lager_name']) ?>: <?= formatBestand($uml['aus']['bestand_vorher']) ?> → <?= formatBestand($uml['aus']['bestand_nachher']) ?><br>
                        <?= htmlspecialchars($uml['ein']['lager_name']) ?>: <?= formatBestand($uml['ein']['bestand_vorher']) ?> → <?= formatBestand($uml['ein']['bestand_nachher']) ?>
                    </td>
                    <?php else: ?>
                    <td style="white-space:nowrap"><?= formatBestand($b['bestand_vorher']) ?> → <?= formatBestand($b['bestand_nachher']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($b['charge'] ?? '–') ?></td>
                    <td style="white-space:nowrap">
                        <?php if ($uml): ?>
                            <?= htmlspecialchars($uml['aus']['lager_name']) ?> → <?= htmlspecialchars($uml['ein']['lager_name']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($b['lager_name']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($b['referenz'])): ?>
                            <span style="font-weight:600"><?= htmlspecialchars($bewegungReferenzText($b['referenz'])) ?></span><?= !empty($b['notiz']) ? ' · ' : '' ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($b['notiz'] ?? (!empty($b['referenz']) ? '' : '–')) ?>
                    </td>
                    <td><?= htmlspecialchars($b['formularname'] ?? '–') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
