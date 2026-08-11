<?php
require_once __DIR__ . '/../config/pg_config.php';
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['EmailUsuario']) && !isset($_SESSION['IdUsuario'])) {
    header('Location: /stridebr/public/login.php');
    exit;
}

$IdUsuario = $_SESSION['IdUsuario'];

// Verifica se um ID foi passado na URL
if (!isset($_GET['id'])) {
    echo "Erro: ID de atividade não especificado.";
    exit;
}

$idatividade = $_GET['id'];

// Exclui a atividade do banco de dados
$stmt = $pdo->prepare("DELETE FROM registros_atividade WHERE idregistro = :id AND idusuario = :usuario_id");
$deleted = $stmt->execute([
    ':id' => $idatividade,
    ':usuario_id' => $IdUsuario
]);

if ($deleted) {
    header('Location: /stridebr/public/user/atividades.php');
    exit;
} else {
    echo "Erro ao excluir a atividade.";
}
?>
