<?php
$dbhost = 'localhost';
$dbname = 'homelab';
$user = 'homelab';
$password = 'homelab';

try {
    $dsn = "pgsql:host=$dbhost;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $password, $options);
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    $pdo->exec("SET search_path TO stridebr");
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>