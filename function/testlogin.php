<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (isset($_POST['submit']) && !empty($_POST['UEmail']) && !empty($_POST['USenha'])) {
    include_once("pg_config.php");

    $EmailUsuario = $_POST["UEmail"];
    $SenhaUsuario = $_POST["USenha"];

    // Buscar usuário pelo email
    $sql = "SELECT * FROM usuarios WHERE emailusuario = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $EmailUsuario);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se usuário existe e se a senha está correta
    if ($user && password_verify($SenhaUsuario, $user['senhausuario'])) {
        $_SESSION['EmailUsuario'] = $user['emailusuario'];
        $_SESSION['NomeUsuario'] = $user['nomeusuario'];
        $_SESSION['IdUsuario'] = $user['idusuario'];

        // Redirecionar para a página anterior ou home
        $redirectUrl = $_SESSION['previous_page'] ?? '/StrideBR/home.php';
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
