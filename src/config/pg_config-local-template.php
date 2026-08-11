<?php
$host = getenv('STRIDEBR_DB_HOST') ?: 'localhost';
$dbname = getenv('STRIDEBR_DB_NAME') ?: 'stridebr';
$user = getenv('STRIDEBR_DB_USER') ?: 'dev';
$password = getenv('STRIDEBR_DB_PASSWORD') ?: 'dev123';

try {
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $password, $options);
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    $pdo->exec("SET search_path TO stridebr, public");
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>