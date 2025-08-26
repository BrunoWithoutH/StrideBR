<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("../src/config/pg_config.php");
require_once __DIR__ . "/../vendor/autoload.php"; 
use Hidehalo\Nanoid\Client;

session_start();

if (isset($_POST["submit"])) {

    if ($_POST["ConfirmarSenhaUsuario"] === $_POST["SenhaUsuario"]) {

        $NomeUsuario   = $_POST["NomeUsuario"];
        $EmailUsuario  = $_POST["EmailUsuario"];
        $SenhaUsuario  = $_POST["SenhaUsuario"];

        $client = new Client();
        $userId = $client->generateId(12);

        $SenhaHash = password_hash($SenhaUsuario, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios 
                (idusuario, nomeusuario, emailusuario, senhausuario) 
                VALUES (:id, :nome, :email, :senha)");

            $stmt->bindParam(':id', $userId);
            $stmt->bindParam(':nome', $NomeUsuario);
            $stmt->bindParam(':email', $EmailUsuario);
            $stmt->bindParam(':senha', $SenhaHash);

            if ($stmt->execute()) {
                header('Location: login.php');
                exit;
            } else {
                echo "Erro ao cadastrar usuário.";
            }
        } catch (PDOException $e) {
            echo "Erro ao cadastrar usuário: " . $e->getMessage();
        }
    } else {
        echo "As senhas não coincidem";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/loginsignup.css">
    <title>Cadastro | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <header>
            <a href="index.php"><img src="assets/img/StrideBRLogoB.png" alt="StrideBR"
                    class="logoSTBR"></a>
            <h2>Sua jornada começa aqui</h2>
        </header>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="forms">
                <div class="form">
                    <span class="title">Cadastre-se</span>
                    <form action="signup.php" method="POST">
                        <div class="input-field">
                            <input type="text" name="NomeUsuario" placeholder="Insira seu nome" required>
                            <i class="uil uil-user"></i>
                        </div>
                        <div class="input-field">
                            <input type="email" name="EmailUsuario" placeholder="Insira seu email" required>
                            <i class="uil uil-envelope icon"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="SenhaUsuario" class="password" placeholder="Crie uma senha" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="ConfirmarSenhaUsuario" class="password" placeholder="Confirme sua senha" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw"></i>
                        </div>

                        <div class="checkbox-text">
                            <div class="checkbox-content">
                                <input type="checkbox" id="termCon">
                                <label for="termCon" class="text">Li e aceito os <a href="eula.html">Termos e condições de Uso</a></label>
                            </div>
                        </div>

                        <div class="input-field button">
                            <input type="submit" name="submit" value="Cadastrar">
                        </div>
                        <div class="login-signup">
                            <span class="text">Já é membro? <a href="login.php">Entrar</a></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="assets/js/loginform.js"></script>
</body>

</html>