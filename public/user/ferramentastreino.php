<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
use Hidehalo\Nanoid\Client;

if (isset($_SESSION['EmailUsuario']) || isset($_SESSION['SenhaUsuario'])) {
    $estalogado = TRUE;
    $user = $_SESSION['NomeUsuario'];
    $idusuario = $_SESSION['IdUsuario'];
} else {
    $_SESSION['previous_page'] = "../../public/user/ferramentastreino.php";
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/treinos.css">
    <title>Cronograma | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="../index.php"><img src="../assets/img/StrideBRLogo.png" alt="StrideBR" class="logoSTBR"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Início<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="../home.php" class="NavItem">Painel principal</a>
                                <a href="../calendario.php" class="NavItem">Calendário de corridas</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Treinos<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="cronogramatreinos.php" class="NavItem">Seu Cronograma de Treinos</a>
                                <a href="atividades.php" class="NavItem">Atividades</a>
                                <a href="ferramentastreino.php" class="NavItem">Treino</a>
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
                                    <button class="dropbtnimg"><img class="userimage" src="../assets/img/userdefault.svg" alt="Usuário"></button>
                                    <div class="dropdown-content" style="right: 0;">
                                        <a href="" class="NavItem">Configurações</a>
                                        <a href="../function/logout.php">Sair</a>
                                    </div>
                                </div>

                            <?php else: ?>
                                <a href="../login.php"><button class="LogButton">Entrar</button></a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </section>
            </div>
        </div>
        <div class="row">
            <h1 class="textcenter">Ferramentas para Treino</h1>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="contador textcenter">
                    <h2>Contador de Sets</h2>
                    <div id="counter">
                        <div>
                            <button id="minus" class="count-button">-</button>
                            <span id="value">0</span>
                            <button id="plus" class="count-button">+</button>
                        </div>
                        <button id="reset" class="reset-button">Resetar</button>
                    </div>
                </div>
                <div class="temporizador textcenter">
                    <h2>Temporizador</h2>
                        <input type="number" id="minutes" placeholder="Minutos">
                        <button onclick="startTimer()">Iniciar</button>
                        <p id="timer"></p>
                </div>
            </div>
        </div>
    </div>
        <footer class="textcenter footer">
            <p>© 2024 StrideBR. Todos os direitos reservados.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/treino.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>

</html>