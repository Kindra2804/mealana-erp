<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Logger.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt.']);
    exit;
}

// ESC/POS Kassenlade-Befehl (Pin 2 oder Pin 5 des RJ-11-Anschlusses)
// Sequenz: ESC p <pin> <t1> <t2>
//   ESC = 0x1B, p = 0x70, pin 2 = 0x00, t1 = 0x19 (25ms), t2 = 0xFA (250ms)
$befehl = chr(0x1B) . chr(0x70) . chr(0x00) . chr(0x19) . chr(0xFA);

// Drucker-Port aus Konfiguration (Fallback: LPT1)
$druckerPort = defined('KASSENLADE_PORT') ? KASSENLADE_PORT : null;

if ($druckerPort && file_exists($druckerPort)) {
    $ok = @file_put_contents($druckerPort, $befehl) !== false;
    if ($ok) {
        Logger::log('kasse', 'kassenlade', 'Kassenlade geöffnet via ' . $druckerPort);
        echo json_encode(['erfolg' => true, 'hinweis' => '⊟ Kassenlade geöffnet']);
    } else {
        echo json_encode(['erfolg' => false, 'fehler' => 'Kassenlade-Befehl fehlgeschlagen (Port: ' . $druckerPort . ')']);
    }
} else {
    // Kein Port konfiguriert — Platzhalter (Drucker noch nicht eingerichtet)
    // TODO: KASSENLADE_PORT in config/config.php setzen (z.B. 'LPT1', '/dev/usb/lp0', oder TCP-Socket)
    Logger::log('kasse', 'kassenlade', 'Kassenlade geöffnet (kein Port konfiguriert — Platzhalter)');
    echo json_encode([
        'erfolg' => true,
        'hinweis' => '⊟ Kassenlade (Drucker noch nicht konfiguriert — KASSENLADE_PORT in config.php setzen)'
    ]);
}
