<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$service = new AuftragService();

$data = [
    'kunden_id'     => !empty($_POST['kunden_id'])     ? (int)$_POST['kunden_id']     : null,
    'zahlungsart'   => $_POST['zahlungsart']            ?? 'vorkasse',
    'versandkosten' => !empty($_POST['versandkosten'])  ? (float)$_POST['versandkosten'] : 0.00,
    'notiz_intern'  => !empty($_POST['notiz_intern'])   ? trim($_POST['notiz_intern'])  : null,
    'notiz_versand' => !empty($_POST['notiz_versand'])  ? trim($_POST['notiz_versand']) : null,
    'kanal'         => 'manuell',
];

// Kunden-Snapshot einfrieren wenn Kunde ausgewählt
if (!empty($data['kunden_id'])) {
    $kundenService = new KundenService();
    $kunde = $kundenService->getById($data['kunden_id']);
    if ($kunde) {
        $data['kunden_snapshot'] = [
            'name'    => $kunde['name'],
            'email'   => $kunde['email'] ?? '',
            'strasse' => $kunde['strasse'] ?? '',
            'plz'     => $kunde['plz'] ?? '',
            'ort'     => $kunde['ort'] ?? '',
            'land'    => $kunde['land'] ?? 'AT',
        ];
    }
}

$positionen = $_POST['positionen'] ?? [];

$ergebnis = $service->anlegen($data, $positionen);

if (!$ergebnis['erfolg']) {
    $_SESSION['fehler']   = $ergebnis['fehler'];
    $_SESSION['formdata'] = $_POST;
    header('Location: /mealana/auftraege/neu.php');
    exit;
}

$_SESSION['erfolg'] = 'Auftrag wurde angelegt.';
header('Location: /mealana/auftraege/detail.php?id=' . $ergebnis['id']);
exit;
