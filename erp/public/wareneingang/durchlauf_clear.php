<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json');

unset($_SESSION['we_durchlauf']);
echo json_encode(['erfolg' => true]);
