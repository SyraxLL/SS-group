<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "DIR: " . __DIR__ . "<br>";
$env_path = __DIR__ . '/.env';
echo "ENV PATH: " . $env_path . "<br>";
echo "ENV EXISTS: " . (file_exists($env_path) ? 'YES' : 'NO') . "<br>";

require_once 'db.php';

echo "DB_HOST: " . ($db_host ?? 'not set') . "<br>";
echo "DB_NAME: " . ($db_name ?? 'not set') . "<br>";
echo "DB_USER: " . ($db_user ?? 'not set') . "<br>";
echo "DB_PASS: " . ($db_pass ?? 'not set') . "<br>";
echo "DB_CHARSET: " . ($db_charset ?? 'not set') . "<br>";
?>
