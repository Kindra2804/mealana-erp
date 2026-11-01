<?php
// Hinweisleiste oben in der Kasse (Wartung, Signaturzertifikat) — eingebunden aus shell_top.php und bon.php
require_once __DIR__ . '/../../src/modules/kasse/KassenHinweise.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
$ksHinweisKasse = (int)($kasseInfo['id'] ?? $aktuelleKasseId ?? (new ArbeitsplatzService())->aktuelleKasseId() ?? 0);
$ksHinweise = $ksHinweisKasse ? KassenHinweise::fuerKasse($ksHinweisKasse) : [];
foreach ($ksHinweise as $ksH): ?>
<div title="<?= htmlspecialchars($ksH['text']) ?>" style="height:36px;line-height:36px;padding:0 18px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:14px;font-weight:600;<?= $ksH['stufe'] === 'fehler'
        ? 'background:#7f1d1d;color:#fff'
        : 'background:#fef3c7;color:#78350f;border-bottom:1px solid #fcd34d' ?>">
    <?= $ksH['stufe'] === 'fehler' ? '⛔' : '!' ?> <?= htmlspecialchars($ksH['text']) ?>
</div>
<?php endforeach;
