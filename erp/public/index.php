<?php
require_once __DIR__ . '/../config/bootstrap.php';
// Kein eigener Inhalt hier — login.php entscheidet selbst, ob zu start.php
// weitergeleitet wird (bereits eingeloggt) oder das Login-Formular zeigt.
header('Location: ' . BASE_PATH . '/login.php');
exit;
