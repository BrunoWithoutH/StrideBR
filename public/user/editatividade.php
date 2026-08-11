<?php
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

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

$atividade = atividadeCarregarRegistro($pdo, $idatividade, $IdUsuario);

if (!$atividade) {
    echo "Erro: Atividade não encontrada.";
    exit;
}

$esporteSelecionado = 'outro';
if (($atividade['slug_modelo'] ?? '') === 'corrida-caminhada') {
    $esporteSelecionado = 'Corrida';
} elseif (($atividade['slug_modelo'] ?? '') === 'ciclismo') {
    $esporteSelecionado = 'Ciclismo';
} elseif (($atividade['slug_modelo'] ?? '') === 'natacao') {
    $esporteSelecionado = 'Nado de peito';
} elseif (($atividade['slug_modelo'] ?? '') === 'raquete') {
    $esporteSelecionado = 'Tênis';
} elseif (($atividade['slug_modelo'] ?? '') === 'lancamento') {
    $esporteSelecionado = 'Arremesso de peso';
}

$modelosAtividade = atividadeGarantirModelosPadrao($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EsporteAtividade = $_POST['EsporteAtividade'] ?? $esporteSelecionado;
    $DataAtividade = $_POST['DataAtividade'] ?? date('Y-m-d');
    $HoraAtividade = $_POST['HoraAtividade'] ?? '00:00';
    $TituloAtividade = trim((string) ($_POST['TituloAtividade'] ?? '')) ?: ($EsporteAtividade ?? 'Atividade');
    $RitmoAtividade = $_POST['RitmoAtividade'] ?? null;

    $dateObj = DateTime::createFromFormat('Y-m-d', $DataAtividade);
    if (!$dateObj) {
        echo "<div class='alert alert-danger'>Data inválida.</div>";
        exit;
    }

    $modeloInfo = atividadeModeloPorEsporte($pdo, $EsporteAtividade);
    $fieldList = atividadeBuscarCamposModelo($pdo, $modeloInfo['idmodelo'] ?? '');
    $unitValues = [];

    foreach ($fieldList as $field) {
        $slug = $field['slug'] ?? '';
        $unitValues[$slug] = $_POST[$slug] ?? null;
    }

    $payload = [
        'idusuario' => $IdUsuario,
        'idmodalidade' => $modeloInfo['idmodalidade'] ?? null,
        'idmodelo' => $modeloInfo['idmodelo'] ?? null,
        'titulo' => $TituloAtividade,
        'observacoes' => trim((string) ($RitmoAtividade ?? '')),
        'data_inicio' => $dateObj->format('Y-m-d') . ' ' . $HoraAtividade,
        'data_fim' => null,
        'status' => 'ativo',
        'unit_observacoes' => null,
        'field_list' => $fieldList,
        'unit_values' => $unitValues,
    ];

    try {
        atividadeSalvarRegistro($pdo, $payload, $idatividade);
        header('Location: atividades.php');
        exit;
    } catch (Throwable $e) {
        echo "<div class='alert alert-danger'>Erro ao atualizar a atividade: " . htmlspecialchars($e->getMessage()) . "</div>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/atividades.css">
    <link rel="stylesheet" href="/assets/css/style.css">
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
                            <input type="text" id="TituloAtividade" name="TituloAtividade" placeholder="Título da Atividade" value="<?php echo htmlspecialchars($atividade['titulo'] ?? ''); ?>">
                        </div>

                        <div class="input-field tipo">
                            <select name="EsporteAtividade" class="EsporteAtividade" required>
                                <option class="select" disabled>Tipo de Atividade:</option>
                                <optgroup label="Caminhada e Corrida">
                                    <option value="Caminhada" <?php echo ($esporteSelecionado === 'Caminhada') ? 'selected' : ''; ?>>Caminhada</option>
                                    <option value="Corrida" <?php echo ($esporteSelecionado === 'Corrida') ? 'selected' : ''; ?>>Corrida</option>
                                    <option value="Marcha Atlética" <?php echo ($esporteSelecionado === 'Marcha Atlética') ? 'selected' : ''; ?>>Marcha Atlética</option>
                                    <option value="Trilha" <?php echo ($esporteSelecionado === 'Trilha') ? 'selected' : ''; ?>>Trilha</option>
                                </optgroup>
                                <optgroup label="Ciclismo">
                                    <option value="Ciclismo" <?php echo ($esporteSelecionado === 'Ciclismo') ? 'selected' : ''; ?>>Ciclismo</option>
                                    <option value="Mountain Bike" <?php echo ($esporteSelecionado === 'Mountain Bike') ? 'selected' : ''; ?>>Mountain Bike</option>
                                    <option value="Downhill" <?php echo ($esporteSelecionado === 'Downhill') ? 'selected' : ''; ?>>Downhill</option>
                                    <option value="BMX" <?php echo ($esporteSelecionado === 'BMX') ? 'selected' : ''; ?>>BMX</option>
                                </optgroup>
                                <optgroup label="Esportes de Natação">
                                    <option value="Nado de peito" <?php echo ($esporteSelecionado === 'Nado de peito') ? 'selected' : ''; ?>>Nado de peito</option>
                                    <option value="Nado de costas" <?php echo ($esporteSelecionado === 'Nado de costas') ? 'selected' : ''; ?>>Nado de costas</option>
                                    <option value="Nado borboleta" <?php echo ($esporteSelecionado === 'Nado borboleta') ? 'selected' : ''; ?>>Nado borboleta</option>
                                </optgroup>
                                <optgroup label="Esportes de raquete">
                                    <option value="Tênis" <?php echo ($esporteSelecionado === 'Tênis') ? 'selected' : ''; ?>>Tênis</option>
                                    <option value="Tênis de mesa" <?php echo ($esporteSelecionado === 'Tênis de mesa') ? 'selected' : ''; ?>>Tênis de mesa</option>
                                    <option value="Badminton" <?php echo ($esporteSelecionado === 'Badminton') ? 'selected' : ''; ?>>Badminton</option>
                                    <option value="Padel" <?php echo ($esporteSelecionado === 'Padel') ? 'selected' : ''; ?>>Padel</option>
                                    <option value="Beach Tennis" <?php echo ($esporteSelecionado === 'Beach Tennis') ? 'selected' : ''; ?>>Beach Tennis</option>
                                </optgroup>
                                <option value="outro" <?php echo ($esporteSelecionado === 'outro') ? 'selected' : ''; ?>>outro</option>
                            </select>
                            <i class="uil uil-grid icon"></i>
                        </div>

                        <div class="activity-field-groups">
                            <?php foreach ($modelosAtividade as $slug => $modelo): ?>
                                <div class="activity-field-group" id="activity-group-<?php echo htmlspecialchars($slug); ?>" data-activity-group="<?php echo htmlspecialchars($slug); ?>" style="display:none">
                                    <div class="text-muted small mb-2"><?php echo htmlspecialchars($modelo['nome']); ?></div>
                                    <?php foreach ($modelo['fields'] as $field): echo atividadeRenderizarCampo($field, (string) ($atividade['values'][$field['slug']] ?? '')); endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="input-field">
                            <label for="DataAtividade">Data e Hora</label>
                            <input type="date" id="DataAtividade" name="DataAtividade" value="<?php echo htmlspecialchars(substr($atividade['data_inicio'] ?? '', 0, 10)); ?>" required>
                            <input type="time" id="HoraAtividade" name="HoraAtividade" value="<?php echo htmlspecialchars(substr($atividade['data_inicio'] ?? '', 11, 5)); ?>" required>
                            <i class="uil uil-clock-three icon"></i>
                        </div>

                        <div class="input-field ritmo">
                            <select name="RitmoAtividade" class="RitmoAtividade" required>
                                <option class="select" disabled>Ritmo da Atividade:</option>
                                <option value="Leve" <?php echo (($atividade['values']['ritmo'] ?? '') === 'Leve') ? 'selected' : ''; ?>>Leve</option>
                                <option value="Moderado" <?php echo (($atividade['values']['ritmo'] ?? '') === 'Moderado') ? 'selected' : ''; ?>>Moderado</option>
                                <option value="Intenso" <?php echo (($atividade['values']['ritmo'] ?? '') === 'Intenso') ? 'selected' : ''; ?>>Intenso</option>
                            </select>
                            <i class="uil uil-wind icon"></i>
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
    <script src="/assets/js/atividades.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/scripts.js"></script>
</body>

</html>