<?php
session_start();

if (isset($_POST['submit']) && !empty($_POST['UEmail']) && !empty($_POST['USenha'])) {
    
    include_once("config.php");
    $UEmail = $_POST["UEmail"];
    $USenha = $_POST["USenha"];

    $sql = "SELECT * FROM usuarios WHERE Email = '$UEmail' AND Senha = '$USenha'";
    $result = $conexao->query($sql);

    if (mysqli_num_rows($result) < 1) { // Se os dados inseridos não coincidirem com o banco de dados
        unset($_SESSION['UEmail']);
        unset($_SESSION['USenha']);
        header('Location: ../login.php');
    } else { // Se os dados inseridos coincidirem com o banco de dados
        $user = $result->fetch_assoc();
        $_SESSION['UEmail'] = $UEmail;
        $_SESSION['USenha'] = $USenha;
        $_SESSION['UNome'] = $user['Nome'];

        // Redirecionar para a página anterior ou home.php se não houver uma página anterior
        $redirectUrl = isset($_SESSION['previous_page']) ? $_SESSION['previous_page'] : '../home.php';
        header('Location: ' . $redirectUrl);
    }
} else {
    header("Location: ../login.php");
}

?>
