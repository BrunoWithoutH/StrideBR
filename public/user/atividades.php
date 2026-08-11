<?php
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
session_start();

if (isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario'])) {
    $estalogado = true;
    $user = $_SESSION['NomeUsuario'];
    if (isset($_SESSION['FotoUsuario'])) {
        $foto = true;
    } else {
        $foto = false;
    }
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
    $TituloAtividade = trim((string) ($_POST['TituloAtividade'] ?? '')) ?: ($EsporteAtividade ?? 'Atividade');

    $dateObj = DateTime::createFromFormat('Y-m-d', $DataAtividade);
    if (!$dateObj) {
        echo "<div class='alert alert-danger'>Data inválida.</div>";
        exit;
    }

    $modeloInfo = atividadeModeloPorEsporte($pdo, $EsporteAtividade ?? '');
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
        atividadeSalvarRegistro($pdo, $payload);
        header('Location: atividades.php');
        exit;
    } catch (Throwable $e) {
        echo "<div class='alert alert-danger'>Erro ao inserir atividade: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

function formatar_data($data)
{
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('d/m/Y') : $data;
}

$registros = atividadeListarRegistros($pdo, $IdUsuario);
$modelosAtividade = atividadeGarantirModelosPadrao($pdo);

$logado = $estalogado ? $NomeUsuario : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/atividades.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Suas Atividades | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <?php require_once dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
        <div class="main-content">
            <div class="row textcenter">
                <h1 class="textcenter">Suas Atividades</h1>
                <?php if (count($registros) === 0): ?>
                    <p>Opa! Você ainda não possui atividades registradas.</p>
                <?php endif; ?>
                <button class="addbutton">Registrar atividade manualmente</button>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="atividades textcenter">
                    <form class="AtividadeForm" id="formulario" action="" method="POST">
                        <span class="title">Registrar atividade</span>

                        <div class="input-field">
                            <input type="text" id="TituloAtividade" name="TituloAtividade" placeholder="Título da Atividade">
                            <i class="uil uil-edit-alt"></i>
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

                        <div class="activity-field-groups">
                            <?php foreach ($modelosAtividade as $slug => $modelo): ?>
                                <div class="activity-field-group" id="activity-group-<?php echo htmlspecialchars($slug); ?>" data-activity-group="<?php echo htmlspecialchars($slug); ?>" style="display:none">
                                    <div class="text-muted small mb-2"><?php echo htmlspecialchars($modelo['nome']); ?></div>
                                    <?php foreach ($modelo['fields'] as $field): echo atividadeRenderizarCampo($field, ''); endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="input-field">
                            <label for="DataAtividade">Data e Hora</label>
                            <input type="date" id="DataAtividade" name="DataAtividade" value="<?php echo date('Y-m-d'); ?>" required>
                            <i class="uil uil-calendar-alt icon"></i>
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
                <?php if (count($registros) > 0): ?>
                    <?php foreach ($registros as $row): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="atividades_fisicas">
                                <a href='user/editatividade.php?id=<?php echo rawurlencode($row['idregistro']); ?>' title='Editar' class="uil uil-pen icon"></a>
                                <a href='#' title='Excluir' onclick="openDeleteConfirm('<?php echo htmlspecialchars($row['idregistro']); ?>')" class="uil uil-trash-alt icon delete-icon"></a>

                                <h3><?php echo htmlspecialchars($row['nome_modelo'] ?? 'Atividade'); ?></h3>

                                <?php if (!empty($row['titulo'])): ?>
                                    <h4><?php echo htmlspecialchars($row['titulo']); ?></h4>
                                <?php endif; ?>

                                <?php if (!empty($row['data_inicio'])): ?>
                                    <p><i class="uil uil-calendar-alt"></i>
                                        <?php echo htmlspecialchars(date('d/m/Y', strtotime($row['data_inicio']))); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($row['data_inicio'])): ?>
                                    <p><i class="uil uil-clock"></i>
                                        <?php echo htmlspecialchars(date('H:i', strtotime($row['data_inicio']))); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($row['values']['duracao'])): ?>
                                    <?php $segundos = (int) $row['values']['duracao']; $h = intdiv($segundos, 3600); $m = intdiv($segundos % 3600, 60); $s = $segundos % 60; ?>
                                    <p>Duração: <?php echo sprintf('%02d:%02d:%02d', $h, $m, $s); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($row['values']['distancia'])): ?>
                                    <p>Distância: <?php echo htmlspecialchars((string) $row['values']['distancia']); ?> km</p>
                                <?php endif; ?>

                                <?php if (!empty($row['values']['elevacao'])): ?>
                                    <p>Elevação: <?php echo htmlspecialchars((string) $row['values']['elevacao']); ?> m</p>
                                <?php endif; ?>

                                <?php if (!empty($row['values']['calorias'])): ?>
                                    <p>Gasto Calórico: ≈ <?php echo htmlspecialchars((string) $row['values']['calorias']); ?> cal</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <h2>Excluir Atividade</h2>
                <span class="delete-modal-close" onclick="closeDeleteConfirm()">&times;</span>
            </div>
            <div class="delete-modal-body">
                <p>Tem certeza que deseja excluir esta atividade? Esta ação não pode ser desfeita.</p>
            </div>
            <div class="delete-modal-footer">
                <button class="delete-modal-cancel" onclick="closeDeleteConfirm()">Cancelar</button>
                <button class="delete-modal-confirm" onclick="confirmDelete()">Excluir</button>
            </div>
        </div>
    </div>
    
    <?php require_once dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="/assets/js/atividades.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/scripts.js"></script>
    <script>
        let atividadeIdToDelete = null;

        function openDeleteConfirm(atividadeId) {
            event.preventDefault();
            atividadeIdToDelete = atividadeId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteConfirm() {
            document.getElementById('deleteModal').style.display = 'none';
            atividadeIdToDelete = null;
        }

        function confirmDelete() {
            if (atividadeIdToDelete) {
                window.location.href = 'function/apagaratividade.php?id=' + atividadeIdToDelete;
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteConfirm();
            }
        }
    </script>
</body>

</html>