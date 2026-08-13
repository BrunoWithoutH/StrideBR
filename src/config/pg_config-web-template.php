<?php

$dbhost = getenv('STRIDEBR_DB_HOST');
$dbport = getenv('STRIDEBR_DB_PORT') ?: '5432';
$dbname = getenv('STRIDEBR_DB_NAME');
$dbuser = getenv('STRIDEBR_DB_USER');
$dbpassword = getenv('STRIDEBR_DB_PASSWORD');

if (!$dbhost || !$dbname || !$dbuser || !$dbpassword) {
    throw new RuntimeException('Configure as variáveis STRIDEBR_DB_HOST, STRIDEBR_DB_NAME, STRIDEBR_DB_USER e STRIDEBR_DB_PASSWORD.');
}

$dsn = "pgsql:host={$dbhost};port={$dbport};dbname={$dbname}";
$pdo = new PDO($dsn, $dbuser, $dbpassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
$pdo->exec('SET search_path TO stridebr, public');
