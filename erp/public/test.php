<?php

require_once __DIR__ . '/../src/modules/artikel/ArtikelController.php';

$controller = new ArtikelController();

echo '<h2>Index (alle Artikel):</h2>';
echo '<pre>';
print_r($controller->index());
echo '</pre>';

echo '<h2>Detail Artikel 1:</h2>';
echo '<pre>';
print_r($controller->detail(1));
echo '</pre>';

echo '<h2>Ungültige ID:</h2>';
echo '<pre>';
print_r($controller->detail(-5));
echo '</pre>';
