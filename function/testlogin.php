<?php
session_start();

if (isset($_POST['submit']) && !empty($_POST['EmailUsuario']) && !empty($_POST['SenhaUsuario'])) {
    
    include_once("config.php");
    $EmailUsuario = $_POST["EmailUsuario"];
    $SenhaUsuario = $_POST["SenhaUsuario"];

    $sql = "SELECT * FROM usuarios WHERE EmailUsuario = '$EmailUsuario' AND SenhaUsuario = '$SenhaUsuario'";
    $result = $conexao->query($sql);

    if (mysqli_num_rows($result) < 1) { // Se os dados inseridos não coincidirem com o banco de dados
        unset($_SESSION['EmailUsuario']);
        unset($_SESSION['SenhaUsuario']);
        header('Location: ../login.php');
    } else { // Se os dados inseridos coincidirem com o banco de dados
        $user = $result->fetch_assoc();
        $_SESSION['EmailUsuario'] = $UEmail;
        $_SESSION['SenhaUsuario'] = $USenha;
        $_SESSION['NomeUsuario'] = $user['NomeUsuario'];

        // Redirecionar para a página anterior ou home.php se não houver uma página anterior
        $redirectUrl = isset($_SESSION['previous_page']) ? $_SESSION['previous_page'] : '../home.php';
        header('Location: ' . $redirectUrl);
    }
} else {
    header("Location: ../login.php");
}

?>
