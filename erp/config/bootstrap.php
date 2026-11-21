<?php

$pfad = $_SERVER['SCRIPT_NAME'];
$pfadTeile = array_filter(explode('/', $pfad));
$base = ('/' . reset($pfadTeile));
define('BASE_PATH', $base);

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
define('APP_VERSION', $version);

// Demo-Installation (config/demo.php): eigenes Sitzungs-Cookie, sonst teilt sie sich auf
// demselben Rechner (localhost/mealana + localhost/nahtlos-demo) die Anmeldung mit dem echten ERP.
require_once __DIR__ . '/../src/core/Demo.php';
if (Demo::aktiv() && session_status() === PHP_SESSION_NONE) {
    session_name('NAHTLOSDEMO');
}
// Demo ohne Cronjob: neuer Tag → Neuaufbau, sonst Belege seit dem letzten Aufruf nachholen
Demo::frischHalten();
