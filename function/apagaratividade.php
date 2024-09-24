<?php
    session_start();
    include_once("../function/config.php");

    // Verifica se o usuário está logado
    if (!isset($_SESSION['EmailUsuario'])) {
        header('Location: ../login.php');
        exit;
    }

    $email = $_SESSION['EmailUsuario'];
    $sql = "SELECT ID FROM usuarios WHERE EmailUsuario = '$email'";
    $result = $conexao->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $usuario_id = $row['ID'];
    } else {
        echo "Erro: Usuário não encontrado.";
        exit;
    }

    // Verifica se um ID foi passado na URL
    if (!isset($_GET['id'])) {
        echo "Erro: ID de atividade não especificado.";
        exit;
    }

    $atividade_id = $_GET['id'];

    // Exclui a atividade do banco de dados
    $sql = "DELETE FROM atividades_fisicas WHERE IdAtividade = '$atividade_id' AND usuario_id = '$usuario_id'";

    if ($conexao->query($sql) === TRUE) {
        header('Location: ../user/atividades.php');
        exit;
    } else {
        echo "Erro ao excluir a atividade: " . $conexao->error;
    }
?>
