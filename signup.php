<?php
if (isset($_POST["submit"])) { //Se o usuário submeteu o formulário

    if ($_POST["UCSenha"] == $_POST["USenha"]) { //Confirma a senha do usuário 
        include_once("function/config.php");

        $UNome = ($_POST["UNome"]);
        $USNome = ($_POST["USNome"]);
        $UEmail = ($_POST["UEmail"]);
        $USenha = ($_POST["USenha"]);

        $result = mysqli_query($conexao, "INSERT INTO usuarios(Nome, Sobrenome, Email, Senha) VALUES ('$UNome', '$USNome', '$UEmail', '$USenha')"); //Guarda os dados no Banco de Dados
    } elseif ($_POST["UCSenha"] != $_POST["USenha"]) { 
        echo "as senhas não coincidem";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/loginsignup.css">
    <title>Register</title>
</head>

<body>
    <div class="container-fluid">
        <header>
            <a href="index.php"><img src="images\StrideBRLogo.png" alt="StrideBR"
                    class="logoCR"></a>
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
                            <input type="text" name="UNome" placeholder="Insira seu nome" required>
                            <i class="uil uil-user"></i>
                        </div>
                        <div class="input-field">
                            <input type="text" name="USNome" placeholder="Insira seu sobrenome" required>
                            <i class="uil uil-user"></i>
                        </div>
                        <div class="input-field">
                            <input type="email" name="UEmail" placeholder="Insira seu email" required>
                            <i class="uil uil-envelope icon"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="USenha" class="password" placeholder="Crie uma senha" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw"></i>
                        </div>
                        <div class="input-field">
                            <input type="password" name="UCSenha" class="password" placeholder="Confirme sua senha" required>
                            <i class="uil uil-lock icon"></i>
                            <i class="uil uil-eye-slash showHidePw"></i>
                        </div>

                        <div class="checkbox-text">
                            <div class="checkbox-content">
                                <input type="checkbox" id="termCon">
                                <label for="termCon" class="text">Li e aceito os <a href="eula.html">Termos e condições
                                        de
                                        Uso</a></label>
                            </div>
                        </div>

                        <div class="input-field button">
                            <input type="submit" name="submit" value="Cadastrar">
                        </div>
                    </form>
                    <div class="login-signup">
                        <span class="text">Já é membro? <a href="login.php">Entrar</a></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="js/loginform.js"></script>
</body>

</html>