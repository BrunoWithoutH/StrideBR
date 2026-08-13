<?php

declare(strict_types=1);

$dbhost = getenv('STRIDEBR_DB_HOST') ?: 'localhost';
$dbport = getenv('STRIDEBR_DB_PORT') ?: '5432';
$dbname = getenv('STRIDEBR_DB_NAME') ?: 'stridebr';
$dbuser = getenv('STRIDEBR_DB_USER') ?: 'stridebr';
$dbpassword = getenv('STRIDEBR_DB_PASSWORD') ?: 'stridebr_dev';

try {
    $dsn = "pgsql:host={$dbhost};port={$dbport};dbname={$dbname}";
    $pdo = new PDO($dsn, $dbuser, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    $pdo->exec('SET search_path TO stridebr, public');
} catch (PDOException $e) {
    error_log('StrideBR database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}
