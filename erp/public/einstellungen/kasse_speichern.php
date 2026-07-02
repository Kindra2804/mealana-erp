<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=kassen');
    exit;
}

$db    = Database::getInstance();
$id    = (int)($_POST['id'] ?? 0);
$istNeu = $id === 0;

$name          = trim($_POST['name'] ?? '');
$kasseNr       = trim($_POST['kasse_nr'] ?? '');
$lagerId       = (int)($_POST['lager_id'] ?? 0);
$modus          = in_array($_POST['modus'] ?? '', ['online','offline']) ? $_POST['modus'] : 'online';
$ausgabeFormat  = in_array($_POST['ausgabe_format'] ?? '', ['fragen','80mm','a4']) ? $_POST['ausgabe_format'] : 'fragen';
$bonLogo       = isset($_POST['bon_logo']) ? 1 : 0;
$aktiv         = isset($_POST['aktiv']) ? 1 : 0;

$fehler = [];
if ($name === '')                    $fehler[] = 'Kassenname ist erforderlich.';
if ($istNeu && $kasseNr === '')      $fehler[] = 'Kassennummer ist erforderlich.';
if ($lagerId < 1)                    $fehler[] = 'Lager muss gewählt werden.';

if ($istNeu && $kasseNr !== '') {
    $stmt = $db->prepare("SELECT id FROM kassen WHERE kasse_nr = ?");
    $stmt->execute([$kasseNr]);
    if ($stmt->fetch()) $fehler[] = 'Kassennummer bereits vergeben.';
}

if ($fehler) {
    $_SESSION['fehler'] = implode(' ', $fehler);
    header('Location: kasse_edit.php' . ($istNeu ? '?neu=1' : '?id=' . $id));
    exit;
}

try {
    $db->beginTransaction();

    if ($istNeu) {
        $stmt = $db->prepare("
            INSERT INTO kassen (name, kasse_nr, lager_id, modus, ausgabe_format, bon_logo, aktiv)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $kasseNr, $lagerId, $modus, $ausgabeFormat, $bonLogo, $aktiv]);
        $id = (int)$db->lastInsertId();
    } else {
        $stmt = $db->prepare("
            UPDATE kassen
            SET name = ?, lager_id = ?, modus = ?, ausgabe_format = ?, bon_logo = ?, aktiv = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $lagerId, $modus, $ausgabeFormat, $bonLogo, $aktiv, $id]);

        // Schnellwahl speichern
        $swArtikelIds = $_POST['sw_artikel_id'] ?? [];
        $swLabels     = $_POST['sw_label'] ?? [];

        $stmtDel = $db->prepare("DELETE FROM kassen_schnellwahl WHERE kasse_id = ? AND slot = ?");
        $stmtUps = $db->prepare("
            INSERT INTO kassen_schnellwahl (kasse_id, slot, artikel_id, label)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE artikel_id = VALUES(artikel_id), label = VALUES(label)
        ");

        for ($slot = 1; $slot <= 9; $slot++) {
            $artikelId = (int)($swArtikelIds[$slot] ?? 0) ?: null;
            $label     = trim($swLabels[$slot] ?? '') ?: null;

            if ($artikelId === null && $label === null) {
                $stmtDel->execute([$id, $slot]);
            } else {
                $stmtUps->execute([$id, $slot, $artikelId, $label]);
            }
        }
    }

    $db->commit();
    $_SESSION['erfolg'] = $istNeu ? 'Kasse erfolgreich angelegt.' : 'Kasse gespeichert.';
    header('Location: kasse_edit.php?id=' . $id);
    exit;

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['fehler'] = 'Datenbankfehler: ' . $e->getMessage();
    header('Location: kasse_edit.php' . ($istNeu ? '?neu=1' : '?id=' . $id));
    exit;
}
