<?php

$pfad = $_SERVER['SCRIPT_NAME'];
$pfadTeile = array_filter(explode('/', $pfad));
$base = ('/' . reset($pfadTeile));
define('BASE_PATH', $base);

$version = trim(file_get_contents(__DIR__ . '/../VERSION'));
define('APP_VERSION', $version);
