<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("../config/pg_config.php");
session_start();

if (isset($_POST['submit']) && !empty($_POST['UEmail']) && !empty($_POST['USenha'])) {

    $EmailUsuario = $_POST["UEmail"];
    $SenhaUsuario = $_POST["USenha"];

    $sql = "SELECT * FROM usuarios WHERE emailusuario = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $EmailUsuario);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($SenhaUsuario, $user['senhausuario'])) {
        $_SESSION['EmailUsuario'] = $user['emailusuario'];
        $_SESSION['NomeUsuario'] = $user['nomeusuario'];
        $_SESSION['IdUsuario'] = $user['idusuario'];

        $redirectUrl = $_SESSION['previous_page'] ?? '../../public/home.php';
        unset($_SESSION['previous_page']);
        header('Location: ' . $redirectUrl);
        exit();
    } else {
        echo "Credenciais inválidas.";
    }
} else {
    echo "Formulário incompleto.";
}
?>
