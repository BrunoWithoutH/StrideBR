<?php
    session_start();
    include_once("../function/config.php");

    // Verifica se o usuário está logado
    if (!isset($_SESSION['UEmail'])) {
        $_SESSION['previous_page'] = "user/cronogramatreinos.php"; // Caminho correto para a página atual
        header('Location: ../login.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/img/favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../assets/css/cronogramas.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <title>Cronograma de Treinos</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="index.php"><img src="../assets/img/StrideBRLogo.png" alt="StrideBR"
                                class="logoSTBR"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Painel principal<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="../index.php" class="NavItem">Início</a>
                                <a href="" class="NavItem">aaaa</a>
                                <a href="calendario.php" class="NavItem">Calendário de corridas</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Treinos</a><i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="cronogramatreinos.php" class="NavItem">Seu Cronograma de Treinos</a>
                                <a href="atividades.php" class="NavItem">Atividades</a>
                                <a href="" class="NavItem">aaaa</a>
                            </div>
                        </div>
                        <a href="../function/logout.php"><button class="LogOutButton">Sair</button></a>
                    </nav>
                </section>
            </div>
        </div>
        <h2>Cronograma de Treinos</h2>
        <form action="../function/salvartreino.php" method="POST">
            <table class="cronogramatable">
                <thead>
                    <tr>
                        <th></th>
                        <th>Domingo</th>
                        <th>Segunda</th>
                        <th>Terça</th>
                        <th>Quarta</th>
                        <th>Quinta</th>
                        <th>Sexta</th>
                        <th>Sábado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Manhã</th>
                        <td><textarea type="text" name="Domingo_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Segunda_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Terca_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quarta_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quinta_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sexta_Manha" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sabado_Manha" placeholder="-"></textarea></td>
                    </tr>
                    <!-- Tarde -->
                    <tr>
                        <th>Tarde</th>
                        <td><textarea type="text" name="Domingo_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Segunda_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Terca_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quarta_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quinta_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sexta_Tarde" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sabado_Tarde" placeholder="-"></textarea></td>
                    </tr>
                    <!-- Noite -->
                    <tr>
                        <th>Noite</th>
                        <td><textarea type="text" name="Domingo_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Segunda_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Terca_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quarta_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Quinta_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sexta_Noite" placeholder="-"></textarea></td>
                        <td><textarea type="text" name="Sabado_Noite" placeholder="-"></textarea></td>
                    </tr>
                </tbody>
            </table>
            <button type="submit" name="submit">Enviar</button>
        </form>
</body>

</html>