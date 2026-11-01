<?php
// Hinweisseite: aufgerufene Seite gehört zu einem Modul, das diese Installation nicht lizenziert hat
require_once __DIR__ . '/../includes/auth_check.php';

$modul = (string)($_GET['modul'] ?? '');
$label = Lizenz::label($modul);

$pageTitle = 'Modul nicht lizenziert';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="max-width:560px;margin:40px auto;padding:28px 32px;text-align:center">
    <div style="font-size:40px;filter:grayscale(1)">🔒</div>
    <h2 style="margin:10px 0 8px">„<?= htmlspecialchars($label) ?>“ ist nicht lizenziert</h2>
    <p style="color:var(--color-text-muted);line-height:1.5;margin:0 0 18px">
        Dieses Modul ist in eurer Lizenz nicht enthalten. Bereits vorhandene Daten bleiben lesbar
        (Belege, Listen, Ausdrucke). Zum Freischalten bei NAHTLOS eine erweiterte Lizenzdatei anfragen.
    </p>
    <div style="display:flex;gap:10px;justify-content:center">
        <a class="btn btn-secondary btn-sm" href="javascript:history.back()">← Zurück</a>
        <?php if (Auth::kann('lizenz.verwalten')): ?>
            <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/lizenz/index.php">Lizenzverwaltung</a>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
