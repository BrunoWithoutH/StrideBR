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
    <title>Entrar | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <header>
                <a href="index.php"><img src="assets/img/StrideBRLogoB.png" alt="StrideBR"
                        class="logoSTBR"></a>
                <h2>Sua jornada começa aqui</h2>
            </header>
            <div class="form">
                <span class="title">Entrar</span>

                <form action="../src/function/testlogin.php" method="POST">
                    <input type="hidden" name="redirect" value="<?php echo isset($_GET['redirect']) ? $_GET['redirect'] : ''; ?>">
                    <div class="input-field">
                        <input type="text" name="UEmail" placeholder="Insira seu email" required>
                        <i class="uil uil-envelope icon"></i>
                    </div>
                    <div class="input-field">
                        <input type="password" name="USenha" class="password" placeholder="Insira sua senha" required>
                        <i class="uil uil-lock icon"></i>
                        <i class="uil uil-eye-slash showHidePw"></i>
                    </div>

                    <div class="checkbox-text">
                        <!-- <div class="checkbox-content">
                            <input type="checkbox" name="ULembrar">
                            <label for="ULembrar" class="text">Lembrar-me</label>
                        </div> 
                        Quando eu tiver mais tempo, vou trabalhar nisso-->

                        <a href="#" class="text">Esqueceu sua senha?</a>
                    </div>

                    <div class="input-field button">
                        <input type="submit" name="submit" value="Entrar">
                    </div>
                    <div class="login-signup">
                        <span class="text">Não tem uma conta?
                            <a href="signup.php">Cadastre-se Agora</a>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="assets/js/loginform.js"></script>
</body>

</html>