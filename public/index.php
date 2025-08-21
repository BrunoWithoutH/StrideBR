<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../src/config/pg_config.php');

$estalogado = isset($_SESSION['UEmail']) && isset($_SESSION['USenha']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bruno Evaristo Pinheiro">
    <link rel="icon" type="image/png" href="assets/img/favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="index.php"><img src="assets/img/StrideBRLogo.png" alt="StrideBR"
                                class="logoSTBR"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Início<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="home.php" class="NavItem">Painel principal</a>
                                <a href="calendario.php" class="NavItem">Calendário de corridas</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Treinos<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="user/cronogramatreinos.php" class="NavItem">Seu Cronograma de Treinos</a>
                                <a href="user/atividades.php" class="NavItem">Atividades</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Ajuda<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="" class="NavItem">Suporte StrideBR</a>
                                <a href="" class="NavItem">FAQ</a>
                            </div>
                        </div>
                        <div class="usersection">
                            <?php if ($estalogado): ?>
                                <div class="dropdown" style="float:right;">
                                    <button class="dropbtnimg"><img class="userimage" src="assets/img/userdefault.svg" alt="user"></button>
                                    <div class="dropdown-content" style="right: 0;">
                                        <a href="" class="NavItem">Configurações</a>
                                        <a href="function/logout.php">Sair</a>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <a href="login.php"><button class="LogButton">Entrar</button></a>
                            <?php endif; ?>
                        </div>

                    </nav>
                </section>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <section class="intro">
                    <h1>StrideBR</h1>
                    <h4>O amigo do atleta</h4>
                </section>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <section class="description">
                </section>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src=""></script>
</body>

</html>