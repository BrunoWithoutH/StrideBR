<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stridebr";

// Cria a conexão
$conexao = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conexao->connect_error) {
    die("Conexão falhou: " . $conexao->connect_error);
}
?>