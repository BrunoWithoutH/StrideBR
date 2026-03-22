<?php
require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
session_start();

if (isset($_SESSION['EmailUsuario'])) {
    header('Location: home.php');
}

$estalogado = isset($_SESSION['EmailUsuario']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bruno Evaristo Pinheiro">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/index.css">
    <title>StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <?php
        require_once dirname(__DIR__) . '/src/layout/header.php';
        ?>
        <div class="row">
            <div class="col-sm-12">
                <section class="intro">
                    <h1>StrideBR</h1>
                    <h5>O amigo do atleta</h4>
                </section>
            </div>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__) . '/src/layout/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>