<?php
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../../src/config/pg_config.php');
require_once __DIR__ . "/../../vendor/autoload.php"; 
use Hidehalo\Nanoid\Client;

session_start();

if (isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario'])) {
    $estalogado = true;
    $user = $_SESSION['NomeUsuario'];
} else {
    $_SESSION['previous_page'] = "../../public/user/atividades.php";
    header('Location: ../login.php');
    exit;
}

$EmailUsuario = $_SESSION['EmailUsuario'];
$SenhaUsuario = $_SESSION['SenhaUsuario'];
$NomeUsuario = $_SESSION['NomeUsuario'] ?? '';

$stmtUser = $pdo->prepare('SELECT idusuario FROM usuarios WHERE idusuario = :id');
$stmtUser->execute(['id' => $IdUsuario = $_SESSION['IdUsuario']]);
$userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    echo "Erro: Usuário não encontrado.";
    exit;
}

$IdUsuario = $userRow['idusuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $EsporteAtividade = $_POST['EsporteAtividade'] ?? null;
    $RitmoAtividade = $_POST['RitmoAtividade'] ?? null;
    $DataAtividade = $_POST['DataAtividade'] ?? null;
    $HoraAtividade = $_POST['HoraAtividade'] ?? '00:00';
    
    $DuracaoH = isset($_POST['duracao_horas']) && $_POST['duracao_horas'] !== '' ? intval($_POST['duracao_horas']) : 0;
    $DuracaoM = isset($_POST['duracao_minutos']) && $_POST['duracao_minutos'] !== '' ? intval($_POST['duracao_minutos']) : 0;
    $DuracaoS = isset($_POST['duracao_segundos']) && $_POST['duracao_segundos'] !== '' ? intval($_POST['duracao_segundos']) : 0;

    $DuracaoTotalSeg = $DuracaoH * 3600 + $DuracaoM * 60 + $DuracaoS;
    $DuracaoTotalMin = $DuracaoTotalSeg / 60;

    $Distancia = !empty($_POST['DistanciaAtividade']) ? $_POST['DistanciaAtividade'] : null;
    $Peso = !empty($_POST['Peso']) ? $_POST['Peso'] : null;
    $TituloAtividade = $_POST['TituloAtividade'] ?? $EsporteAtividade;
    $Elevacao = !empty($_POST['ElevacaoAtividade']) ? $_POST['ElevacaoAtividade'] : null;

    $dateObj = DateTime::createFromFormat('Y-m-d', $DataAtividade);
    if (!$dateObj) {
        echo "<div class='alert alert-danger'>Data inválida.</div>";
        exit;
    }
    $DataAtividade = $dateObj->format('Y-m-d');

    $HoraAtividade = $HoraAtividade . ':00';

    $Calorias = null;
    if ($Distancia && $Peso && $DuracaoTotalMin) {
        $VelocidadeMedia = ($Distancia / $DuracaoTotalMin) * 60;
        $Calorias = round($VelocidadeMedia * $Peso * 0.0175 * $DuracaoTotalMin);
    }
    $client = new Client();
    $idAtividade = $client->generateId(16);

    $stmtInsert = $pdo->prepare("
        INSERT INTO atividades
            (idatividade, idusuario, tituloatividade, esporteatividade, ritmoatividade, dataatividade, horaatividade, duracaoatividade, distanciaatividade, pesoinseridoatividade, elevacaoatividade, caloriasatividade) 
        VALUES 
            (:idatividade, :idusuario, :titulo, :esporte, :ritmo, :data, :hora, :duracao, :distancia, :peso, :elevacao, :calorias)
    ");

    $executado = $stmtInsert->execute([
        'idatividade' => $idAtividade,
        'idusuario' => $IdUsuario,
        'titulo' => $TituloAtividade,
        'esporte' => $EsporteAtividade,
        'ritmo' => $RitmoAtividade,
        'data' => $DataAtividade,
        'hora' => $HoraAtividade,
        'duracao' => $DuracaoTotalSeg ?: null,
        'distancia' => $Distancia,
        'peso' => $Peso,
        'elevacao' => $Elevacao,
        'calorias' => $Calorias,
    ]);


    if ($executado) {
        header("Location: atividades.php");
        exit;
    } else {
        echo "<div class='alert alert-danger'>Erro ao inserir atividade.</div>";
    }
}

function formatar_data($data) {
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('d/m/Y') : $data;
}

$stmtFetch = $pdo->prepare("SELECT * FROM atividades WHERE idusuario = :id ORDER BY dataatividade DESC");
$stmtFetch->execute(['id' => $IdUsuario]);
$result = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

$logado = $estalogado ? $NomeUsuario : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <!-- <link rel="stylesheet" href="../assets/css/atividades.css"> -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <title>Suas Atividades | StrideBR</title>
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
        <div class="row textcenter">
            <h1 class="textcenter">Suas Atividades</h1>
            <?php if (count($result) === 0): ?>
                <p>Opa! Você ainda não possui atividades registradas.</p>
            <?php endif; ?>
            <button class="addbutton">Registrar atividade manualmente</button>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="atividades textcenter">
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
                                <optgroup label="Arremessos e Lançamentos">
                                    <option value="Arremesso de peso">Arremesso de peso</option>
                                    <option value="Lançamento de disco">Lançamento de disco</option>
                                    <option value="Lançamento de dardo">Lançamento de dardo</option>
                                    <option value="Lançamento de martelo">Lançamento de martelo</option>
                                </optgroup>
                                    <option value="outro">outro</option>
                            </select>
                            <i class="uil uil-grid icon"></i>
                        </div>

                        <div class="input-field" id="field-distancia" style="display:none">
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

                        <div class="input-field" id="field-duracao" style="display:none">
                            <label for="duracao_horas">Duração</label>
                            <div class="duracao-inputs">
                                <input type="number" id="duracao_horas" name="duracao_horas" min="0" max="23" placeholder="hh"s>
                                <input type="number" id="duracao_minutos" name="duracao_minutos" min="0" max="59" placeholder="mm">
                                <input type="number" id="duracao_segundos" name="duracao_segundos" min="0" max="59" placeholder="ss">
                            </div>
                            <i class="uil uil-stopwatch icon"></i>
                        </div>

                        <div class="input-field" id="field-elevacao" style="display:none">
                            <label for="ElevaçãoAtividade">Elevação</label>
                            <input type="number" id="ElevacaoAtividade" name="ElevacaoAtividade" step="0.1" placeholder="Elevação">
                            <select name="UnidadeElevacaoAtividade" id="UnidadeElevacaoAtividade">
                                <option value="metros" selected>metros</option>
                                <option value="pés">pés</option>
                            </select>
                            <i class="uil uil-arrow-growth icon"></i>
                        </div>

                        <div class="input-field">
                            <label for="DataAtividade">Data e Hora</label>
                            <input type="date" id="DataAtividade" name="DataAtividade" value="<?php echo date('Y-m-d'); ?>" required>
                            <input type="time" id="HoraAtividade" name="HoraAtividade" value="<?php echo date('H:i'); ?>" required>
                            <i class="uil uil-clock-three icon"></i>
                        </div>

                        <div class="input-field ritmo">
                            <select name="RitmoAtividade" class="RitmoAtividade" required>
                                <option class="select" disabled selected>Ritmo da Atividade:</option>
                                <option value="Leve">Leve</option>
                                <option value="Moderado">Moderado</option>
                                <option value="Intenso">Intenso</option>
                            </select>
                            <i class="uil uil-wind icon"></i>
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
        </div>
        <div class="row">
            <div class="col-sm-12 atividades textcenter">
                <?php if (count($result) > 0): ?>
                    <?php foreach ($result as $row): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="atividades_fisicas">
                                <a href='editatividade.php?id=<?php echo $row['idatividade']; ?>' title='Editar' class="uil uil-pen icon"></a>

                                <h3><?php echo htmlspecialchars($row['esporteatividade']); ?></h3>

                                <?php if (!empty($row['tituloatividade'])): ?>
                                    <h4><?php echo htmlspecialchars($row['tituloatividade']); ?></h4>
                                <?php endif; ?>

                                <?php if (!empty($row['dataatividade'])): ?>
                                    <p><i class="uil uil-calendar-alt icon"></i>
                                        <?php echo htmlspecialchars(formatar_data($row['dataatividade'])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($row['horaatividade'])): ?>
                                    <p><i class="uil uil-clock icon"></i>
                                        <?php
                                        $hora = explode(':', $row['horaatividade']);
                                        echo htmlspecialchars($hora[0] . ':' . $hora[1]);
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <?php 
                                if (!empty($row['duracaoatividade'])) {
                                    $segundos = intval($row['duracaoatividade']);
                                    $h = floor($segundos / 3600);
                                    $m = floor(($segundos % 3600) / 60);
                                    $s = $segundos % 60;
                                    $duracao_formatada = sprintf("%02d:%02d:%02d", $h, $m, $s);
                                    echo "<p>Duração: {$duracao_formatada}</p>";
                                }
                                ?>

                                <?php if (!empty($row['distanciaatividade'])): ?>
                                    <p>Distância: <?php echo htmlspecialchars($row['distanciaatividade']); ?> km</p>
                                <?php endif; ?>

                                <?php if (!empty($row['elevacaoatividade'])): ?>
                                    <p>Elevação: <?php echo htmlspecialchars($row['elevacaoatividade']); ?> m</p>
                                <?php endif; ?>

                                <?php if (!empty($row['caloriasatividade'])): ?>
                                    <p>Gasto Calórico: ≈ <?php echo htmlspecialchars($row['caloriasatividade']); ?> cal</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <footer class="textcenter footer">
            <p>© 2024 StrideBR. Todos os direitos reservados.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/atividades.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/scripts.js"></script>
</body>

</html>