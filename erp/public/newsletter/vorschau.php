<?php
// Newsletter-Vorschau (für den iframe im Editor): genau das Mail-HTML, Abmeldelink als Platzhalter
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/newsletter/NewsletterRenderer.php';

$n = (new NewsletterService())->laden((int)($_GET['id'] ?? 0));
if (!$n) { http_response_code(404); exit('Newsletter nicht gefunden.'); }
header('Content-Type: text/html; charset=utf-8');
echo (new NewsletterRenderer())->html($n, ['vorname' => trim((string)($_GET['vorname'] ?? 'Maria')), 'abmelde_link' => '#vorschau']);
