<?php
$servername = "localhost";
$username = "bruno";
$password = "0203";
$dbname = "stridebr";

// Cria a conexão
$conexao = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conexao->connect_error) {
    die("Conexão falhou: " . $conexao->connect_error);
}
?>