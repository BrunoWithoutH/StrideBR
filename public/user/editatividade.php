<?php
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

if (!isset($_SESSION['EmailUsuario']) && !isset($_SESSION['IdUsuario'])) {
    header('Location: ../login.php');
    exit;
}

$IdUsuario = $_SESSION['IdUsuario'];

if (!isset($_GET['id'])) {
    echo "Erro: ID de atividade não especificado.";
    exit;
}
$idatividade = $_GET['id'];

// Obter dados da atividade
$stmt = $pdo->prepare("SELECT * FROM atividades WHERE idatividade = :id AND idusuario = :usuario_id");
$stmt->execute([
    ':id' => $idatividade,
    ':usuario_id' => $IdUsuario
]);
$atividade = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atividade) {
    echo "Erro: Atividade não encontrada.";
    exit;
}

function formatar_data_input($data) {
    return $data;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EsporteAtividade = $_POST['EsporteAtividade'] ?? $atividade['esporteatividade'];
    $DataAtividade = $_POST['DataAtividade'] ?? $atividade['dataatividade'];
    $HoraAtividade = $_POST['HoraAtividade'] ?? $atividade['horaatividade'];
    
    $DuracaoH = isset($_POST['duracao_horas']) && $_POST['duracao_horas'] !== '' ? intval($_POST['duracao_horas']) : 0;
    $DuracaoM = isset($_POST['duracao_minutos']) && $_POST['duracao_minutos'] !== '' ? intval($_POST['duracao_minutos']) : 0;
    $DuracaoS = isset($_POST['duracao_segundos']) && $_POST['duracao_segundos'] !== '' ? intval($_POST['duracao_segundos']) : 0;

    $DuracaoTotalSeg = $DuracaoH * 3600 + $DuracaoM * 60 + $DuracaoS;
    $DuracaoTotalMin = $DuracaoTotalSeg / 60;

    $Distancia = !empty($_POST['DistanciaAtividade']) ? $_POST['DistanciaAtividade'] : null;
    $Peso = !empty($_POST['Peso']) ? $_POST['Peso'] : null;
    $Elevacao = !empty($_POST['ElevacaoAtividade']) ? $_POST['ElevacaoAtividade'] : null;
    $TituloAtividade = $_POST['TituloAtividade'] ?? $atividade['tituloatividade'];
    $RitmoAtividade = $_POST['RitmoAtividade'] ?? $atividade['ritmoatividade'];

    $Calorias = null;
    if ($Distancia && $Peso && $DuracaoTotalMin) {
        $VelocidadeMedia = ($Distancia / $DuracaoTotalMin) * 60;
        $Calorias = round($VelocidadeMedia * $Peso * 0.0175 * $DuracaoTotalMin);
    }

    $update = $pdo->prepare("UPDATE atividades SET
        tituloatividade = :titulo,
        esporteatividade = :esporte,
        ritmoatividade = :ritmo,
        dataatividade = :data,
        horaatividade = :hora,
        duracaoatividade = :duracao,
        distanciaatividade = :distancia,
        elevacaoatividade = :elevacao,
        pesoinseridoatividade = :peso,
        caloriasatividade = :calorias
        WHERE idatividade = :id AND idusuario = :usuario_id");

    $ok = $update->execute([
        ':titulo' => $TituloAtividade,
        ':esporte' => $EsporteAtividade,
        ':ritmo' => $RitmoAtividade,
        ':data' => $DataAtividade,
        ':hora' => $HoraAtividade,
        ':duracao' => $DuracaoTotalSeg ?: null,
        ':distancia' => $Distancia,
        ':elevacao' => $Elevacao,
        ':peso' => $Peso,
        ':calorias' => $Calorias,
        ':id' => $idatividade,
        ':usuario_id' => $IdUsuario
    ]);

    if ($ok) {
        header("Location: atividades.php");
        exit;
    } else {
        echo "Erro ao atualizar a atividade.";
    }
}

$estalogado = isset($_SESSION['EmailUsuario']) && isset($_SESSION['IdUsuario']);
$user = $estalogado ? $_SESSION['NomeUsuario'] : null;
$foto = isset($_SESSION['FotoUsuario']) ? true : false;

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <base href="/stridebr/public/">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="assets/css/atividades.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Editar Atividade | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <?php
        require_once dirname(__DIR__, 2) . '/src/layout/header.php';
        ?>
        <div class="row textcenter">
            <h1 class="textcenter">Editar Atividade</h1>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="atividades textcenter">
                    <form class="AtividadeForm" style="display: block;" id="formulario" action="" method="POST">
                        <span class="title">Editar atividade</span>

                        <div class="input-field">
                            <label for="TituloAtividade">Título</label>
                            <input type="text" id="TituloAtividade" name="TituloAtividade" placeholder="Título da Atividade" value="<?php echo htmlspecialchars($atividade['tituloatividade'] ?? ''); ?>">
                        </div>

                        <div class="input-field tipo">
                            <select name="EsporteAtividade" class="EsporteAtividade" required>
                                <option class="select" disabled>Tipo de Atividade:</option>
                                <optgroup label="Caminhada e Corrida">
                                    <option value="Caminhada" <?php echo ($atividade['esporteatividade'] == 'Caminhada') ? 'selected' : ''; ?>>Caminhada</option>
                                    <option value="Corrida" <?php echo ($atividade['esporteatividade'] == 'Corrida') ? 'selected' : ''; ?>>Corrida</option>
                                    <option value="Marcha Atlética" <?php echo ($atividade['esporteatividade'] == 'Marcha Atlética') ? 'selected' : ''; ?>>Marcha Atlética</option>
                                    <option value="Trilha" <?php echo ($atividade['esporteatividade'] == 'Trilha') ? 'selected' : ''; ?>>Trilha</option>
                                </optgroup>
                                <optgroup label="Ciclismo">
                                    <option value="Ciclismo" <?php echo ($atividade['esporteatividade'] == 'Ciclismo') ? 'selected' : ''; ?>>Ciclismo</option>
                                    <option value="Mountain Bike" <?php echo ($atividade['esporteatividade'] == 'Mountain Bike') ? 'selected' : ''; ?>>Mountain Bike</option>
                                    <option value="Downhill" <?php echo ($atividade['esporteatividade'] == 'Downhill') ? 'selected' : ''; ?>>Downhill</option>
                                    <option value="BMX" <?php echo ($atividade['esporteatividade'] == 'BMX') ? 'selected' : ''; ?>>BMX</option>
                                </optgroup>
                                <optgroup label="Esportes de Natação">
                                    <option value="Nado de peito" <?php echo ($atividade['esporteatividade'] == 'Nado de peito') ? 'selected' : ''; ?>>Nado de peito</option>
                                    <option value="Nado de costas" <?php echo ($atividade['esporteatividade'] == 'Nado de costas') ? 'selected' : ''; ?>>Nado de costas</option>
                                    <option value="Nado borboleta" <?php echo ($atividade['esporteatividade'] == 'Nado borboleta') ? 'selected' : ''; ?>>Nado borboleta</option>
                                </optgroup>
                                <optgroup label="Esportes de raquete">
                                    <option value="Tênis" <?php echo ($atividade['esporteatividade'] == 'Tênis') ? 'selected' : ''; ?>>Tênis</option>
                                    <option value="Tênis de mesa" <?php echo ($atividade['esporteatividade'] == 'Tênis de mesa') ? 'selected' : ''; ?>>Tênis de mesa</option>
                                    <option value="Badminton" <?php echo ($atividade['esporteatividade'] == 'Badminton') ? 'selected' : ''; ?>>Badminton</option>
                                    <option value="Padel" <?php echo ($atividade['esporteatividade'] == 'Padel') ? 'selected' : ''; ?>>Padel</option>
                                    <option value="Beach Tennis" <?php echo ($atividade['esporteatividade'] == 'Beach Tennis') ? 'selected' : ''; ?>>Beach Tennis</option>
                                </optgroup>
                                <option value="outro" <?php echo ($atividade['esporteatividade'] == 'outro') ? 'selected' : ''; ?>>outro</option>
                            </select>
                            <i class="uil uil-grid icon"></i>
                        </div>

                        <div class="input-field" id="field-distancia">
                            <label for="DistanciaAtividade">Distância</label>
                            <input type="number" id="DistanciaAtividade" name="DistanciaAtividade" step="0.01" placeholder="Distância" value="<?php echo htmlspecialchars($atividade['distanciaatividade'] ?? ''); ?>">
                            <select name="UnidadeDistanciaAtividade" id="UnidadeDistanciaAtividade">
                                <option value="quilometros" selected>quilômetros</option>
                                <option value="metros">metros</option>
                                <option value="milhas">milhas</option>
                                <option value="jardas">jardas</option>
                            </select>
                            <i class="uil uil-ruler icon"></i>
                        </div>

                        <div class="input-field" id="field-duracao">
                            <label for="duracao_horas">Duração</label>
                            <div class="duracao-inputs">
                                <input type="number" id="duracao_horas" name="duracao_horas" min="0" max="23" placeholder="hh">
                                <input type="number" id="duracao_minutos" name="duracao_minutos" min="0" max="59" placeholder="mm">
                                <input type="number" id="duracao_segundos" name="duracao_segundos" min="0" max="59" placeholder="ss">
                            </div>
                            <i class="uil uil-stopwatch icon"></i>
                        </div>

                        <div class="input-field" id="field-elevacao">
                            <label for="ElevacaoAtividade">Elevação</label>
                            <input type="number" id="ElevacaoAtividade" name="ElevacaoAtividade" step="0.1" placeholder="Elevação" value="<?php echo htmlspecialchars($atividade['elevacaoatividade'] ?? ''); ?>">
                            <select name="UnidadeElevacaoAtividade" id="UnidadeElevacaoAtividade">
                                <option value="metros" selected>metros</option>
                                <option value="pés">pés</option>
                            </select>
                            <i class="uil uil-arrow-growth icon"></i>
                        </div>

                        <div class="input-field">
                            <label for="DataAtividade">Data e Hora</label>
                            <input type="date" id="DataAtividade" name="DataAtividade" value="<?php echo htmlspecialchars($atividade['dataatividade'] ?? ''); ?>" required>
                            <input type="time" id="HoraAtividade" name="HoraAtividade" value="<?php echo htmlspecialchars(substr($atividade['horaatividade'] ?? '', 0, 5)); ?>" required>
                            <i class="uil uil-clock-three icon"></i>
                        </div>

                        <div class="input-field ritmo">
                            <select name="RitmoAtividade" class="RitmoAtividade" required>
                                <option class="select" disabled>Ritmo da Atividade:</option>
                                <option value="Leve" <?php echo ($atividade['ritmoatividade'] == 'Leve') ? 'selected' : ''; ?>>Leve</option>
                                <option value="Moderado" <?php echo ($atividade['ritmoatividade'] == 'Moderado') ? 'selected' : ''; ?>>Moderado</option>
                                <option value="Intenso" <?php echo ($atividade['ritmoatividade'] == 'Intenso') ? 'selected' : ''; ?>>Intenso</option>
                            </select>
                            <i class="uil uil-wind icon"></i>
                        </div>

                        <div class="checkbox-text">
                            <div class="checkbox-content">
                                <input type="checkbox" id="checkPeso" onclick="togglePesoInput()" <?php echo ($atividade['caloriasatividade'] ? 'checked' : ''); ?>>
                                <label for="checkPeso" class="text">Mostrar gasto calórico aproximado</label>
                            </div>
                        </div>

                        <div class="input-field" id="pesoField" style="<?php echo ($atividade['caloriasatividade'] ? '' : 'display: none;'); ?>">
                            <input type="text" id="Peso" name="Peso" placeholder="Insira seu peso" value="<?php echo htmlspecialchars($atividade['pesoinseridoatividade'] ?? ''); ?>">
                            <i class="uil uil-weight icon"></i>
                        </div>

                        <div class="input-field button">
                            <button type="submit" class="submit">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
        require_once dirname(__DIR__, 2) . '/src/layout/footer.php';
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/atividades.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/scripts.js"></script>
</body>

</html>