<?php
/**
 * Partial: rendert die Lagerbewegungen-Tabelle.
 * Erwartet $bewegungslog (Array) im Scope — wird sowohl direkt in detail.php
 * als auch von bewegungslog_ajax.php (bei Chargen-Filterwechsel) eingebunden.
 */
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
            ];
            ?>
            <?php foreach ($bewegungslog as $b): ?>
                <?php [$bg, $fg] = $typFarben[$b['bewegungstyp']] ?? ['#f1f5f9', '#334155']; ?>
                <tr>
                    <td style="white-space:nowrap"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                    <td>
                        <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600">
                            <?= htmlspecialchars(ucfirst($b['bewegungstyp'])) ?>
                        </span>
                    </td>
                    <td style="text-align:right"><?= formatBestand($b['menge']) ?></td>
                    <td style="white-space:nowrap"><?= formatBestand($b['bestand_vorher']) ?> → <?= formatBestand($b['bestand_nachher']) ?></td>
                    <td><?= htmlspecialchars($b['charge'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($b['lager_name']) ?></td>
                    <td>
                        <?php if (!empty($b['referenz'])): ?>
                            <span style="font-weight:600"><?= htmlspecialchars($b['referenz']) ?></span><?= !empty($b['notiz']) ? ' · ' : '' ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($b['notiz'] ?? (!empty($b['referenz']) ? '' : '–')) ?>
                    </td>
                    <td><?= htmlspecialchars($b['formularname'] ?? '–') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
