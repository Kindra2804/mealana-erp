<?php

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/artikel/ArtikelRepository.php';

$repo = new ArtikelRepository();
// $artikel = $repo->findAll();
// $artikel = $repo->findById(1);

$varianten = $repo->findVariantenByArtikelId(1);
print_r($varianten);

echo '<pre>';
print_r($artikel);
echo '</pre>';
