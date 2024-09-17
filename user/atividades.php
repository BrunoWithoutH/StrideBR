<?php
session_start();
include_once("../function/config.php");

// Verifica se o usuário está logado
if (!isset($_SESSION['UEmail'])) {
    $_SESSION['previous_page'] = "../user/atividades.php"; // Caminho correto para a página atual
    header('Location: ../login.php');
    exit;
}

$email = $_SESSION['UEmail'];
$sql = "SELECT ID FROM usuarios WHERE Email = '$email'";
$result = $conexao->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $usuario_id = $row['ID'];
} else {
    echo "Erro: Usuário não encontrado.";
    exit;
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_atividade = $_POST['tipo_atividade'];
    $data_atividade = $_POST['data_atividade'];
    $hora_atividade = $_POST['hora_atividade'];
    $duracao = !empty($_POST['duracao']) ? $_POST['duracao'] : NULL;
    $distancia = !empty($_POST['distancia']) ? $_POST['distancia'] : NULL;
    $peso = isset($_POST['Peso']) ? $_POST['Peso'] : NULL;

    // Valida a data e hora
    if (empty($data_atividade)) {
        echo "<div class='alert alert-danger'>Erro: Data e hora são obrigatórios.</div>";
    } else {
        // Calcula as calorias se possível
        $calorias = NULL;
        if ($duracao && $distancia && $peso) {
            $velocidade = ($distancia / $duracao) * 60;
            $calorias = $velocidade * $peso * 0.0175 * $duracao;
        }

        // Protege contra injeções SQL
        $tipo_atividade = $conexao->real_escape_string($tipo_atividade);
        $data_atividade = $conexao->real_escape_string($data_atividade);
        $hora_atividade = $conexao->real_escape_string($hora_atividade);
        $duracao = $conexao->real_escape_string($duracao);
        $distancia = $conexao->real_escape_string($distancia);
        $calorias = $calorias ? $conexao->real_escape_string($calorias) : NULL;

        $sql = "INSERT INTO atividades_fisicas (usuario_id, tipo_atividade, data_atividade, hora_atividade, duracao, distancia, calorias) 
                        VALUES ('$usuario_id', '$tipo_atividade', '$data_atividade', '$hora_atividade', '$duracao', '$distancia', '$calorias')";

        if ($conexao->query($sql) === TRUE) {
            header("Location: atividades.php");
            exit;
        } else {
            echo "<div class='alert alert-danger'>Erro: " . $sql . "<br>" . $conexao->error . "</div>";
        }
    }
}


// Função para formatar data
function formatar_data($data)
{
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('d/m/Y') : $data;
}

// Obtém as atividades do banco de dados
$sql = "SELECT * FROM atividades_fisicas WHERE usuario_id = '$usuario_id' ORDER BY data_atividade DESC";
$result = $conexao->query($sql);

$estalogado = isset($_SESSION['UEmail']) && isset($_SESSION['USenha']);
$logado = $estalogado ? $_SESSION['UNome'] : null;
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
    <link rel="stylesheet" href="../css/atividades.css">
    <link rel="stylesheet" href="../css/style.css">
    <title>Celeratus</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="../index.php"><img src="../images/CeleratusLogo.png" alt="Celeratus Running"
                                class="logoCR"></a>
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
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Ajuda<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="" class="NavItem">Suporte Celeratus</a>
                                <a href="" class="NavItem">FAQ</a>
                            </div>
                        </div>
                        <div class="usersection">
                            <?php if ($estalogado): ?>
                                <div class="dropdown" style="float:right;">
                                    <button class="dropbtnimg"><img class="userimage" src="../Images/userdefault.svg" alt="Usuário"></button>
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
            <h1 class="textcenter">Suas Atividades</h1>
            <button class="addbutton">Registrar atividade manualmente</button>
        </div>
        <div class="row">
            <!-- Cria -->
            <div class="col-sm-12">
                <form class="AtividadeForm" id="formulario" action="#" method="POST">
                    <span class="title">Registrar atividade</span>

                    <div class="input-field tipo">
                        <select name="tipo_atividade" class="tipo_atividade" required>
                            <option class="select" disabled selected>Tipo de Atividade:</option>
                            <optgroup label="Caminhada e Corrida">
                                <option value="Caminhada">Caminhada</option>
                                <option value="Corrida">Corrida</option>
                                <option value="Marcha Atlética">Marcha Atlética</option>
                                <option value="Trilha">Trilha</option>
                            </optgroup>
                            <optgroup label="Ciclismo">
                                <option value="Ciclismo">Ciclismo</option>
                                <option value="Mountain Bike">Mountain Bike</option>
                                <option value="Downhill">Downhill</option>
                                <option value="BMX">BMX</option>
                            </optgroup>
                            <optgroup label="Esportes de Natação">
                                <option value="Nado de peito">Nado de peito</option>
                                <option value="Nado de costas">Nado de costas</option>
                                <option value="Nado borboleta">Nado borboleta</option>
                            </optgroup>
                            <optgroup label="Esportes de raquete">
                                <option value="Tênis">Tênis</option>
                                <option value="Tênis de mesa">Tênis de mesa</option>
                                <option value="Badminton">Badminton</option>
                                <option value="Padel">Padel</option>
                                <option value="Beach Tennis">Beach Tennis</option>
                            </optgroup>
                            <optgroup label="Outros">
                                <option value="Ioga">Ioga</option>
                                <option value="outro">outro</option>
                            </optgroup>
                        </select>
                        <i class="uil uil-grid icon"></i>
                    </div>

                    <div class="input-field estilo">
                        <select name="estilo_atividade" class="estilo_atividade" required>
                            <option class="select" disabled selected>Estilo da Atividade:</option>
                            <option value="Leve">Leve</option>
                            <option value="Moderado">Moderado</option>
                            <option value="Intenso">Intenso</option>
                    </div>

                    <div class="input-field">
                        <input type="text" id="data_atividade" name="data_atividade" placeholder="Data da Atividade (dd/mm/aaaa)" required>
                        <i class="uil uil-calendar-alt icon"></i>
                    </div>


                    <div class="input-field">
                        <input type="text" id="hora_atividade" name="hora_atividade" placeholder="Hora da Atividade (opcional)">
                        <i class="uil uil-clock icon"></i>
                    </div>

                    <div class="input-field">
                        <input type="number" name="duracao" placeholder="Duração em Min (opcional):">
                        <i class="uil uil-stopwatch icon"></i>
                    </div>

                    <div class="input-field">
                        <input type="number" step="0.01" placeholder="Distância em Km (opcional):" name="distancia">
                        <i class="uil uil-ruler icon"></i>
                    </div>

                    <div class="checkbox-text">
                        <div class="checkbox-content">
                            <input type="checkbox" id="checkPeso" onclick="togglePesoInput()">
                            <label for="checkPeso" class="text">Mostrar gasto calórico aproximado</label>
                        </div>
                    </div>

                    <div class="input-field" id="pesoField" style="display: none;">
                        <input type="text" id="Peso" name="Peso" placeholder="Insira seu peso">
                        <i class="uil uil-weight icon"></i>
                    </div>


                    <div class="input-field button">
                        <button type="submit" class="submit">Adicionar Atividade</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="atividades textcenter">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="atividades_fisicas">
                                <a href='editatividade.php?id=<?php echo $row['id']; ?>' title='Editar' class="uil uil-pen icon"></a>
                                <h3><?php echo htmlspecialchars($row['tipo_atividade']); ?></h3>
                                <p>Data: <?php echo htmlspecialchars(formatar_data($row['data_atividade'])); ?></p>
                                <?php if ($row['hora_atividade'] != 0): ?>
                                    <p>Hora: <?php echo htmlspecialchars($row['hora_atividade']); ?></p>
                                <?php else: ?>
                                    <p>Hora: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['duracao'] != 0): ?>
                                    <p>Duração: <?php echo htmlspecialchars($row['duracao']); ?> minutos</p>
                                <?php else: ?>
                                    <p>Duração: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['distancia'] != 0): ?>
                                    <p>Distância: <?php echo htmlspecialchars($row['distancia']); ?> km</p>
                                <?php else: ?>
                                    <p>Distância: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['calorias']): ?>
                                    <p>Gasto Calórico: ≈ <?php echo htmlspecialchars($row['calorias']); ?> cal</p>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Você ainda não possui atividades registradas.</p>
                <?php endif; ?>
            </div>
        </div>

        <footer class="textcenter footer">
            <p>Feito Por Bruno Evaristo Pinheiro - 2024</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../js/atividades.js"></script>
</body>

</html>