<?php
include_once('../src/config/pg_config.php');
session_start();
if (isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario'])) {
    $estalogado = true;
    $user = $_SESSION['NomeUsuario'] ?? '';
    $foto = false;
    if (isset($_SESSION['FotoUsuario'])) {
        $foto = true;
    }
} else {
    $_SESSION['previous_page'] = "../../public/home.php";
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Página inicial | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <?php
        include_once '../src/layout/header.php';
        ?>

        <div class="row">
            <div class="col-sm-12">
                <section class="intro">
                    <h1>Painel Principal</h1>
                    <?php echo "<h5>Bem vindo, <b>$user</b></h5>"; ?>
                </section>
            </div>
        </div>
    </div>
    <?php
    include_once '../src/layout/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>