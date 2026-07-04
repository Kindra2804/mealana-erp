<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/BonA4Renderer.php';

$bonId = (int)($_GET['id'] ?? 0);
$html  = BonA4Renderer::render($bonId, fuerPdf: false);

if ($html === null) {
    die('<p style="font-family:sans-serif;padding:20px">Bon nicht gefunden.</p>');
}

echo $html;
