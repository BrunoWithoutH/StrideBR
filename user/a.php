<?php
session_start();
include_once("../function/config.php");

// Verifica se o usuário está logado
if (!isset($_SESSION['EmailUsuario'])) {
    $_SESSION['previous_page'] = "../user/atividades.php";
    header('Location: ../login.php');
    exit;
}

$email = $_SESSION['EmailUsuario'];
$sql = "SELECT IdUsuario FROM usuarios WHERE EmailUsuario = '$email'";
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
        $DuracaoAtividade = $conexao->real_escape_string($DuracaoAtividade);
        $DistanciaAtividade = $conexao->real_escape_string($DistanciaAtividade);
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
    <link rel="icon" type="image/png" href="favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../css/atividades.css">
    <link rel="stylesheet" href="../css/style.css">
    <title>Suas Atividades</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="../index.php"><img src="../images/StrideBRLogo.png" alt="StrideBR" class="logoSTBR"></a>
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
                            <!-- opções de atividades -->
                        </select>
                        <i class="uil uil-grid icon"></i>
                    </div>

                    <div class="input-field estilo">
                        <select name="EstiloAtividade" class="EstiloAtividade" required>
                            <option class="select" disabled selected>Estilo da Atividade:</option>
                            <!-- opções de estilos -->
                        </select>
                    </div>

                    <div class="input-field">
                        <label for="DataHoraAtividade">Data e Hora</label>
                        <input type="text" id="DataAtividade" name="DataAtividade" placeholder="dd/mm/yyyy" required>
                        <input type="time" id="HoraAtividade" name="HoraAtividade">
                    </div>

                    <div class="input-field">
                        <label for="DuracaoAtividade">Duração (em minutos)</label>
                        <input type="number" id="DuracaoAtividade" name="DuracaoAtividade">
                    </div>

                    <div class="input-field">
                        <label for="DistanciaAtividade">Distância (em km)</label>
                        <input type="number" id="DistanciaAtividade" name="DistanciaAtividade">
                    </div>

                    <div class="input-field">
                        <label for="Peso">Peso (kg)</label>
                        <input type="number" id="Peso" name="Peso">
                    </div>

                    <div class="input-field">
                        <button type="submit" class="submitbtn">Registrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12 atividades">
                <?php
                if ($result->num_rows > 0) {
                    $cont = 0;
                    echo "<div class='row'>";
                    while ($row = $result->fetch_assoc()) {
                        if ($cont > 0 && $cont % 4 === 0) {
                            echo "</div><div class='row'>";
                        }

                        echo "<div class='col-sm-3 atividade'>";
                        echo "<h3>" . htmlspecialchars($row['EsporteAtividade']) . "</h3>";
                        echo "<p>Data: " . formatar_data($row['DataAtividade']) . "</p>";
                        echo "<p>Hora: " . htmlspecialchars($row['HoraAtividade']) . "</p>";
                        echo "<p>Duração: " . htmlspecialchars($row['DuracaoAtividade']) . " min</p>";
                        echo "<p>Distância: " . htmlspecialchars($row['DistanciaAtividade']) . " km</p>";
                        echo "<p>Calorias: " . htmlspecialchars($row['CaloriasAtividade']) . " kcal</p>";
                        echo "</div>";

                        $cont++;
                    }
                    echo "</div>";
                } else {
                    echo "<p>Nenhuma atividade registrada ainda.</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+hFbPCEnKP3PPPPYgRzMD+E3Jpi5RaVvjOggqM/fZ40uU" crossorigin="anonymous"></script>
</body>

</html>