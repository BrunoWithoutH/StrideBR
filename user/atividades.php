<?php
session_start();
include_once("../function/config.php");

// Verifica se o usuário está logado
if (!isset($_SESSION['EmailUsuario'])) {
    $_SESSION['previous_page'] = "../user/atividades.php";
    header('Location: ../login.php');
    exit;
}

$EmailUsuario = $_SESSION['EmailUsuario'];
$sql = "SELECT IdUsuario FROM usuarios WHERE EmailUsuario = '$EmailUsuario'";
$result = $conexao->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $IdUsuario = $row['IdUsuario'];
} else {
    echo "Erro: Usuário não encontrado.";
    exit;
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EsporteAtividade = $_POST['EsporteAtividade'];
    $EstiloAtividade = $_POST['EstiloAtividade'];
    $DataAtividade = $_POST['DataAtividade'];
    $HoraAtividade = $_POST['HoraAtividade'];
    $DuracaoAtividade = !empty($_POST['DuracaoAtividade']) ? $_POST['DuracaoAtividade'] : NULL;
    $DistanciaAtividade = !empty($_POST['DistanciaAtividade']) ? $_POST['DistanciaAtividade'] : NULL;
    $PesoInseridoAtividade = isset($_POST['Peso']) ? $_POST['Peso'] : NULL;
    $TituloAtividade = $_POST['EsporteAtividade'];

    // Valida a data e hora
    if (empty($DataAtividade)) {
        echo "<div class='alert alert-danger'>Erro: Data e hora são obrigatórios.</div>";
    } else {
        // Formata a data para o banco de dados (Y-m-d)
        $DataAtividade = DateTime::createFromFormat('d/m/Y', $DataAtividade)->format('Y-m-d');

        // Calcula as calorias se possível
        $CaloriasAtividade = NULL;
        if ($DuracaoAtividade && $DistanciaAtividade && $PesoInseridoAtividade) {
            $VelocidadeMediaAtividade = ($DistanciaAtividade / $DuracaoAtividade) * 60;
            $CaloriasAtividade = $VelocidadeMediaAtividade * $PesoInseridoAtividade * 0.0175 * $DuracaoAtividade;
        }

        // Protege contra injeções SQL
        $EsporteAtividade = $conexao->real_escape_string($EsporteAtividade);
        $EstiloAtividade = $conexao->real_escape_string($EstiloAtividade);
        $HoraAtividade = $conexao->real_escape_string($HoraAtividade);
        $DuracaoHrsAtividade = $conexao->real_escape_string($DuracaoHrsAtividade);
        $DuracaoMinAtividade = $conexao->real_escape_string($DuracaoMinAtividade);
        $DuracaoSegAtividade = $conexao->real_escape_string($DuracaoSegAtividade);
        $DistanciaAtividade = $conexao->real_escape_string($DistanciaAtividade);
        $UnidadeDistanciaAtividade = $conexao->real_escape_string($UnidadeDistanciaAtividade);
        $CaloriasAtividade = $CaloriasAtividade ? $conexao->real_escape_string($CaloriasAtividade) : NULL;

        $sql = "INSERT INTO atividades_fisicas (IdUsuario, EsporteAtividade, EstiloAtividade, DataAtividade, HoraAtividade, DuracaoAtividade, DistanciaAtividade, CaloriasAtividade) 
                    VALUES ('$IdUsuario', '$EsporteAtividade', '$EstiloAtividade', '$DataAtividade', '$HoraAtividade', '$DuracaoAtividade', '$DistanciaAtividade', '$CaloriasAtividade')";

        if ($conexao->query($sql) === TRUE) {
            header("Location: atividades.php");
            exit;
        } else {
            echo "<div class='alert alert-danger'>Erro: " . $sql . "<br>" . $conexao->error . "</div>";
        }
    }
}

// Função para formatar a data no formato dd/mm/yyyy
function formatar_data($data)
{
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('d/m/Y') : $data;
}

// Obtém as atividades do banco de dados
$sql = "SELECT * FROM atividades_fisicas WHERE IdUsuario = '$IdUsuario' ORDER BY DataAtividade DESC";
$result = $conexao->query($sql);

$estalogado = isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario']);
$logado = $estalogado ? $_SESSION['NomeUsuario'] : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/img/favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../assets/css/atividades.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <title>Suas Atividades</title>
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
            <h1 class="textcenter">Suas Atividades</h1>
            <?php if ($result->num_rows > 0): ?>
                <p>Opa! Você ainda não possui atividades registradas.</p>
            <?php endif; ?>
            <button class="addbutton">Registrar atividade manualmente</button>
        </div>
        <div class="row">
            <!-- Cria -->
            <div class="col-sm-12">
                <form class="AtividadeForm" id="formulario" action="#" method="POST">
                    <span class="title">Registrar atividade</span>

                    <div class="input-field">
                        <label for="TituloAtividade">Título</label>
                        <input type="text" id="TituloAtividade" name="TituloAtividade" placeholder="Título da Atividade">
                    </div>

                    <div class="input-field tipo">
                        <select name="EsporteAtividade" class="EsporteAtividade" required>
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
                        <select name="EstiloAtividade" class="EstiloAtividade" required>
                            <option class="select" disabled selected>Estilo da Atividade:</option>
                            <option value="Leve">Leve</option>
                            <option value="Moderado">Moderado</option>
                            <option value="Intenso">Intenso</option>
                    </div>

                    <div class="input-field">
                        <label for="DataHoraAtividade">Data e Hora</label>
                        <input type="text" id="DataAtividade" name="DataAtividade" placeholder="dd/mm/yyyy">
                        <input type="time" id="HoraAtividade" name="HoraAtividade">
                    </div>

                    <div class="input-field">
                        <label for="duracao_horas">Duração</label>
                        <input type="number" id="duracao_horas" name="duracao_horas" min="0" max="23" placeholder="hh">
                        <input type="number" id="duracao_minutos" name="duracao_minutos" min="0" max="59" placeholder="mm">
                        <input type="number" id="duracao_segundos" name="duracao_segundos" min="0" max="59" placeholder="ss">
                        <i class="uil uil-stopwatch icon"></i>
                    </div>

                    <div class="input-field">
                        <label for="DistanciaAtividade">Distância</label>
                        <input type="number" id="DistanciaAtividade" name="DistanciaAtividade" step="0.01" placeholder="Distância">
                        <select name="UnidadeDistanciaAtividade" id="UnidadeDistanciaAtividade">
                            <option value="quilometros" selected>quilômetros</option>
                            <option value="metros">metros</option>
                            <option value="milhas">milhas</option>
                            <option value="jardas">jardas</option>
                        </select>
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
            <div class="col-sm-12 atividades textcenter">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="atividades_fisicas">
                                <a href='editatividade.php?id=<?php echo $row['id']; ?>' title='Editar' class="uil uil-pen icon"></a>
                                <h3><?php echo htmlspecialchars($row['EsporteAtividade']); ?></h3>
                                <p>Data: <?php echo htmlspecialchars(formatar_data($row['DataHoraAtividade'])); ?></p>
                                <?php if ($row['HoraAtividade'] != 0): ?>
                                    <p>Hora: <?php echo htmlspecialchars($row['$HoraAtividade']); ?></p>
                                <?php else: ?>
                                    <p>Hora: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['DuracaoAtividade'] != 0): ?>
                                    <p>Duração: <?php echo htmlspecialchars($row['$durAtividade']); ?> minutos</p>
                                <?php else: ?>
                                    <p>Duração: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['dis'] != 0): ?>
                                    <p>Distância: <?php echo htmlspecialchars($row['dis']); ?> km</p>
                                <?php else: ?>
                                    <p>Distância: não informado</p>
                                <?php endif; ?>
                                <?php if ($row['calorias']): ?>
                                    <p>Gasto Calórico: ≈ <?php echo htmlspecialchars($row['calorias']); ?> cal</p>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <footer class="textcenter footer">
            <p>Feito Por Bruno Evaristo Pinheiro - 2024</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/atividades.js"></script>
</body>

</html>