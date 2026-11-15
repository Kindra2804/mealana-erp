<?php
/**
 * Einstieg fürs Handgerät (Sunmi) — ohne Kassen-Arbeitsplatz: offene Mobil-Messen als große Knöpfe.
 * Am Gerät einfach …/mealana/kasse/mobil.php aufrufen (nach der Anmeldung geht es hierher zurück).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
$messen = (new MesseSyncService())->getOffeneMobilMessen();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mobil-Messe</title>
  <style>
    body { margin: 0; background: #f1f5f9; font-family: -apple-system, 'Segoe UI', Arial, sans-serif; color: #1e293b; }
    .kopf { background: #1e3a5f; color: #fff; padding: 14px 16px; font-size: 18px; font-weight: 800; }
    .kopf small { display: block; font-size: 12px; font-weight: 400; color: #93c5fd; margin-top: 2px; }
    .inhalt { padding: 14px; max-width: 520px; margin: 0 auto; }
    a.messe { display: block; background: #fff; border: 2px solid #cbd5e1; border-radius: 12px; padding: 16px; margin-bottom: 12px;
              text-decoration: none; color: inherit; }
    a.messe:active { background: #eff6ff; border-color: #2563eb; }
    .titel { font-size: 18px; font-weight: 800; }
    .info { font-size: 14px; color: #64748b; margin-top: 4px; }
    .leer { text-align: center; color: #64748b; padding: 30px 10px; font-size: 15px; }
    .hinweis { font-size: 13px; color: #64748b; margin-top: 18px; line-height: 1.5; }
  </style>
</head>
<body>
  <div class="kopf">📱 Mobil-Messe <small><?= htmlspecialchars($_SESSION['benutzer']['formularname'] ?? '') ?></small></div>
  <div class="inhalt">
    <?php if (!$messen): ?>
      <div class="leer">Keine offene Mobil-Messe.<br>Am PC: Kasse → 🎪 Messe → „📱 Mobil-Messe (Sunmi)“ vorbereiten.</div>
    <?php endif; ?>
    <?php foreach ($messen as $m): ?>
      <a class="messe" href="bon_offline.php?sync_id=<?= (int)$m['id'] ?>">
        <div class="titel">Messe Nr. <?= (int)$m['id'] ?> · <?= htmlspecialchars($m['lager_name'] ?? '') ?></div>
        <div class="info"><?= (int)$m['artikel_count'] ?> Artikel · vorbereitet <?= date('d.m.Y', strtotime($m['erstellt_am'])) ?>
          <?= (int)$m['beleg_count'] ? ' · ' . (int)$m['beleg_count'] . ' Belege hochgeladen' : '' ?></div>
      </a>
    <?php endforeach; ?>
    <div class="hinweis">Messe antippen → Gerät-Nr. eintragen → „📥 Sync-Daten laden“. Danach funktioniert die Kasse ohne Internet.
      Zurück im Laden-WLAN: „⤴ Belege hochladen“.</div>
  </div>
</body>
</html>
