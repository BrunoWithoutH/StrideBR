<?php
$host = 'localhost';
$dbname = 'stridebr';
$user = 'admin';
$password = 'admin';

try {
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $password, $options);
    // Força o timezone da sessão do banco
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

?>