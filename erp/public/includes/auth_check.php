<?php

require_once __DIR__ . '/../../config/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/core/Auth.php';
Auth::check();
Auth::pruefeSeite();
// Browser darf keine ERP-Seiten cachen — Back-Button nach Logout zeigt sonst alte Seite
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
