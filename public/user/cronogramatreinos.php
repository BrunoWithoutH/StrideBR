<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma_compartilhar.php';
$friendsEnabled = stridebr_feature_enabled($pdo, 'friends.enabled', false);
$workoutSessionsEnabled = stridebr_feature_enabled($pdo, 'workout_sessions.enabled', false);
$errors = [];
$dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];


function cronogramaNomeImportado(PDO $pdo, string $idUsuario, string $base): string
{
    $base = trim($base) !== '' ? trim($base) : 'Cronograma importado';
    $candidate = $base;
    $n = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND lower(nome) = lower(:nome) LIMIT 1');
        $stmt->execute([':usuario' => $idUsuario, ':nome' => $candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
        $candidate = $base . ' (' . $n++ . ')';
    }
}

function cronogramaExportData(PDO $pdo, string $idUsuario, string $idCronograma): array
{
    $cronograma = cronogramaBuscar($pdo, $idCronograma, $idUsuario);
    if ($cronograma === []) throw new RuntimeException('Cronograma não encontrado.');
    $treinos = cronogramaListarTreinos($pdo, $idCronograma, $idUsuario);
    foreach ($treinos as &$treino) {
        $treino['exercicios'] = cronogramaListarTreinoExercicios($pdo, (string) $treino['idtreino'], $idUsuario);
    }
    unset($treino);
    return [
        'format' => 'stridebr-schedule',
        'version' => 1,
        'cronograma' => [
            'nome' => $cronograma['nome'],
            'descricao' => $cronograma['descricao'] ?? null,
        ],
        'treinos' => $treinos,
    ];
}

function cronogramaImportData(PDO $pdo, string $idUsuario, array $data): string
{
    if (($data['format'] ?? '') !== 'stridebr-schedule' || (int) ($data['version'] ?? 0) !== 1 || !is_array($data['cronograma'] ?? null) || !is_array($data['treinos'] ?? null)) {
        throw new InvalidArgumentException('Arquivo de cronograma inválido ou incompatível.');
    }

    if (count($data['treinos']) > 200) {
        throw new InvalidArgumentException('O arquivo possui treinos demais para uma única importação.');
    }

    $nome = cronogramaNomeImportado($pdo, $idUsuario, (string) ($data['cronograma']['nome'] ?? 'Cronograma importado'));
    $pdo->beginTransaction();

    try {
        $idCronograma = cronogramaCriar($pdo, $idUsuario, $nome, $data['cronograma']['descricao'] ?? null);
        $totalExercicios = 0;

        foreach ($data['treinos'] as $treino) {
            if (!is_array($treino)) continue;

            $payload = [
                'idcronograma' => $idCronograma,
                'titulo' => (string) ($treino['titulo'] ?? ''),
                'descricao' => $treino['descricao'] ?? null,
                'dia_semana' => $treino['dia_semana'] ?? 1,
                'hora_inicio' => substr((string) ($treino['hora_inicio'] ?? '18:00'), 0, 5),
                'hora_fim' => substr((string) ($treino['hora_fim'] ?? '19:00'), 0, 5),
            ];

            if (stridebr_db_bool($treino['termina_dia_seguinte'] ?? false)) {
                $payload['termina_dia_seguinte'] = '1';
            }

            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, $payload);
            $rows = [];

            foreach (($treino['exercicios'] ?? []) as $exercicio) {
                if (!is_array($exercicio)) continue;

                $totalExercicios++;
                if ($totalExercicios > 2000) {
                    throw new InvalidArgumentException('O arquivo possui exercícios demais para uma única importação.');
                }

                $rows[] = [
                    'nome' => (string) ($exercicio['nome_snapshot'] ?? $exercicio['nome'] ?? ''),
                    'series' => $exercicio['series'] ?? '',
                    'repeticoes' => $exercicio['repeticoes'] ?? '',
                    'carga' => $exercicio['carga'] ?? '',
                    'bloco' => $exercicio['bloco'] ?? '',
                    'cluster' => $exercicio['cluster'] ?? '',
                    'descanso' => $exercicio['descanso'] ?? '',
                    'observacoes' => $exercicio['observacoes'] ?? '',
                ];
            }

            if ($rows !== []) {
                cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, $rows, []);
            }
        }

        $pdo->commit();
        return $idCronograma;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_schedule') {
            $idNovo = cronogramaCriar($pdo, $idUsuario, (string) ($_POST['nome'] ?? ''), $_POST['descricao'] ?? null);
            stridebr_flash('success', 'Cronograma criado.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idNovo));
            exit;
        }
        if ($action === 'delete_schedule') {
            $id = (string) ($_POST['idcronograma'] ?? '');
            if (!cronogramaExcluir($pdo, $id, $idUsuario)) {
                throw new RuntimeException('Cronograma não encontrado.');
            }
            stridebr_flash('success', 'Cronograma excluído.');
            header('Location: /user/cronogramatreinos.php');
            exit;
        }
        if ($action === 'update_schedule_visibility') {
            $id = (string) ($_POST['idcronograma'] ?? '');
            $visibility = (string) ($_POST['visibilidade'] ?? 'privado');
            if (!in_array($visibility, ['privado', 'amigos', 'publico'], true)) throw new InvalidArgumentException('Visibilidade inválida.');
            $stmt = $pdo->prepare('UPDATE cronogramas SET visibilidade = :visibilidade, data_atualizacao = NOW() WHERE idcronograma = :id AND idusuario = :usuario');
            $stmt->execute([':visibilidade' => $visibility, ':id' => $id, ':usuario' => $idUsuario]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Cronograma não encontrado.');
            stridebr_flash('success', 'Privacidade do cronograma atualizada.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($id));
            exit;
        }
        if ($action === 'save_workout') {
            $idTreino = trim((string) ($_POST['idtreino'] ?? '')) ?: null;
            $saved = cronogramaSalvarTreino($pdo, $idUsuario, $_POST, $idTreino);
            stridebr_flash('success', $idTreino ? 'Treino atualizado.' : 'Treino adicionado.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode((string) $_POST['idcronograma']) . '&treino=' . urlencode($saved));
            exit;
        }
        if ($action === 'delete_workout') {
            $idCronograma = (string) ($_POST['idcronograma'] ?? '');
            if (!cronogramaExcluirTreino($pdo, (string) ($_POST['idtreino'] ?? ''), $idUsuario)) {
                throw new RuntimeException('Treino não encontrado.');
            }
            stridebr_flash('success', 'Treino removido do cronograma.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idCronograma));
            exit;
        }
        if ($action === 'share_snapshot') {
            if (!$friendsEnabled) throw new RuntimeException('O compartilhamento com amigos está temporariamente desativado.');
            $idCronograma = trim((string) ($_POST['idcronograma'] ?? ''));
            $destino = trim((string) ($_POST['idusuario_destino'] ?? ''));
            $friend = $pdo->prepare("SELECT 1 FROM amizades WHERE status = 'aceita' AND ((idusuario_solicitante = :me1 AND idusuario_destino = :dest1) OR (idusuario_solicitante = :dest2 AND idusuario_destino = :me2)) LIMIT 1");
            $friend->execute([':me1' => $idUsuario, ':dest1' => $destino, ':dest2' => $destino, ':me2' => $idUsuario]);
            if (!$friend->fetchColumn()) throw new RuntimeException('Esse usuário não está na sua lista de amigos.');
            $snapshot = compartilhamentoCronogramaSnapshot($pdo, $idUsuario, $idCronograma);
            $stmt = $pdo->prepare("INSERT INTO cronograma_compartilhamentos (idcompartilhamento, idcronograma_origem, idusuario_origem, idusuario_destino, tipo, snapshot) VALUES (:id, :cronograma, :origem, :destino, 'snapshot', CAST(:snapshot AS jsonb))");
            $stmt->execute([
                ':id' => stridebr_generate_id(), ':cronograma' => $idCronograma, ':origem' => $idUsuario, ':destino' => $destino,
                ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            stridebr_flash('success', 'Cronograma enviado como uma cópia.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idCronograma));
            exit;
        }
        if ($action === 'import_schedule') {
            if (!isset($_FILES['schedule_file']) || !is_array($_FILES['schedule_file'])) {
                throw new InvalidArgumentException('Selecione um arquivo .json exportado pelo StrideBR.');
            }
            if ((int) ($_FILES['schedule_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($_FILES['schedule_file']['tmp_name'] ?? ''))) {
                throw new InvalidArgumentException('Não foi possível receber o arquivo selecionado.');
            }
            if ((int) ($_FILES['schedule_file']['size'] ?? 0) <= 0 || (int) $_FILES['schedule_file']['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('O arquivo deve ter no máximo 2 MB.');
            }
            $raw = file_get_contents((string) $_FILES['schedule_file']['tmp_name']);
            if ($raw === false || trim($raw) === '') {
                throw new InvalidArgumentException('O arquivo está vazio ou não pôde ser lido.');
            }
            try {
                $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException('O arquivo não contém um JSON válido.');
            }
            if (!is_array($data)) {
                throw new InvalidArgumentException('Arquivo de cronograma inválido.');
            }
            $idImportado = cronogramaImportData($pdo, $idUsuario, $data);
            stridebr_flash('success', 'Cronograma importado como uma cópia privada.');
            header('Location: /user/cronogramatreinos.php?id=' . urlencode($idImportado));
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'], $_GET['id'])) {
    $exportId = (string) $_GET['id'];
    try {
        $data = cronogramaExportData($pdo, $idUsuario, $exportId);
        $safe = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($data['cronograma']['nome'] ?? 'cronograma')) ?: 'cronograma';
        if ($_GET['export'] === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $safe . '.stridebr.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $safe . '.csv"');
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Dia', 'Início', 'Fim', 'Treino', 'Exercício', 'Séries', 'Repetições', 'Carga', 'Bloco', 'Cluster', 'Descanso', 'Observações'], ';');
            foreach ($data['treinos'] as $treino) {
                $exercicios = $treino['exercicios'] ?: [[]];
                foreach ($exercicios as $exercicio) {
                    fputcsv($out, [
                        $dias[(int) $treino['dia_semana']] ?? $treino['dia_semana'],
                        substr((string) $treino['hora_inicio'], 0, 5), substr((string) $treino['hora_fim'], 0, 5), $treino['titulo'],
                        $exercicio['nome_snapshot'] ?? '', $exercicio['series'] ?? '', $exercicio['repeticoes'] ?? '', $exercicio['carga'] ?? '',
                        $exercicio['bloco'] ?? '', $exercicio['cluster'] ?? '', $exercicio['descanso'] ?? '', $exercicio['observacoes'] ?? '',
                    ], ';');
                }
            }
            fclose($out);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Cronograma não encontrado.');
    }
}

$cronogramas = cronogramaListar($pdo, $idUsuario);
$idSelecionado = (string) ($_GET['id'] ?? '');
if ($idSelecionado === '' && $cronogramas !== []) {
    $idSelecionado = $cronogramas[0]['idcronograma'];
}
$cronograma = $idSelecionado !== '' ? cronogramaBuscar($pdo, $idSelecionado, $idUsuario) : [];
if ($cronograma === [] && $cronogramas !== []) {
    $cronograma = $cronogramas[0];
    $idSelecionado = $cronograma['idcronograma'];
}
$treinos = $cronograma !== [] ? cronogramaListarTreinos($pdo, $idSelecionado, $idUsuario) : [];
$exerciciosPorTreino = [];
foreach ($treinos as $treino) {
    $exerciciosPorTreino[$treino['idtreino']] = cronogramaListarTreinoExercicios($pdo, (string) $treino['idtreino'], $idUsuario);
}
$friends = [];
if ($friendsEnabled) {
    $friendsStmt = $pdo->prepare("SELECT u.idusuario, COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario) AS nome_exibicao, u.username FROM amizades a JOIN usuarios u ON u.idusuario = CASE WHEN a.idusuario_solicitante = :me_case THEN a.idusuario_destino ELSE a.idusuario_solicitante END WHERE a.status = 'aceita' AND (a.idusuario_solicitante = :me_left OR a.idusuario_destino = :me_right) ORDER BY nome_exibicao");
    $friendsStmt->execute([':me_case' => $idUsuario, ':me_left' => $idUsuario, ':me_right' => $idUsuario]);
    $friends = $friendsStmt->fetchAll();
}
$treinoEdicao = [];
$idTreinoEdicao = (string) ($_GET['treino'] ?? '');
if ($idTreinoEdicao !== '') {
    $candidate = cronogramaBuscarTreino($pdo, $idTreinoEdicao, $idUsuario);
    if ($candidate !== [] && $candidate['idcronograma'] === $idSelecionado) {
        $treinoEdicao = $candidate;
    }
}

$segments = [];
foreach ($treinos as $treino) {
    $start = ((int) substr($treino['hora_inicio'], 0, 2)) * 60 + (int) substr($treino['hora_inicio'], 3, 2);
    $end = ((int) substr($treino['hora_fim'], 0, 2)) * 60 + (int) substr($treino['hora_fim'], 3, 2);
    $day = (int) $treino['dia_semana'];
    if (stridebr_db_bool($treino['termina_dia_seguinte'])) {
        $segments[$day][] = ['treino' => $treino, 'inicio' => $start, 'fim' => 1440, 'continua' => true, 'continuidade' => false];
        if ($end > 0) {
            $next = ($day + 1) % 7;
            $segments[$next][] = ['treino' => $treino, 'inicio' => 0, 'fim' => $end, 'continua' => false, 'continuidade' => true];
        }
    } else {
        $segments[$day][] = ['treino' => $treino, 'inicio' => $start, 'fim' => $end, 'continua' => false, 'continuidade' => false];
    }
}

$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
    <title>Cronogramas | StrideBR</title>
</head>
<body class="schedule-body">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content cronograma-page">
        <div class="schedule-shell">
            <div class="schedule-heading">
                <div>
                    <h1>Cronogramas</h1>
                    <p>Organize semanas independentes e abra cada treino para montar seus exercícios.</p>
                </div>
                <div class="schedule-heading-actions">
                    <a class="secondary-button" href="/user/bibliotecaexercicios.php">Biblioteca</a>
                    <button type="button" class="secondary-button" data-discover-schedules>Descobrir</button>
                    <button type="button" class="primary-button" data-open-schedule-create>+ Novo</button>
                </div>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="schedule-create-panel" data-schedule-create hidden>
                <div class="schedule-create-header">
                    <div>
                        <span class="eyebrow">Novo cronograma</span>
                        <h2 data-schedule-create-title>Como você quer começar?</h2>
                    </div>
                    <button type="button" class="icon-button" data-close-schedule-create aria-label="Fechar">×</button>
                </div>

                <div class="schedule-create-options" data-schedule-create-options>
                    <button type="button" class="schedule-create-option" data-schedule-create-mode="blank">
                        <span class="schedule-create-option-icon" aria-hidden="true">+</span>
                        <strong>Criar do zero</strong>
                        <small>Comece com um cronograma vazio e adicione seus treinos.</small>
                    </button>
                    <button type="button" class="schedule-create-option" data-schedule-create-mode="import">
                        <span class="schedule-create-option-icon" aria-hidden="true">↑</span>
                        <strong>Importar cronograma</strong>
                        <small>Use um arquivo <code>.stridebr.json</code> exportado pelo StrideBR.</small>
                    </button>
                </div>

                <div class="schedule-create-form" data-schedule-create-form="blank" hidden>
                    <form method="POST" class="compact-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="create_schedule">
                        <label>Nome
                            <input type="text" name="nome" maxlength="120" placeholder="Ex.: Corrida 5 km" required>
                        </label>
                        <label>Descrição
                            <input type="text" name="descricao" maxlength="300" placeholder="Opcional">
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="primary-button">Criar cronograma</button>
                            <button type="button" class="secondary-button" data-schedule-create-back>Voltar</button>
                        </div>
                    </form>
                </div>

                <div class="schedule-create-form" data-schedule-create-form="import" hidden>
                    <form method="POST" enctype="multipart/form-data" class="schedule-import-create-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="import_schedule">
                        <label class="schedule-file-picker">
                            <span>Arquivo do StrideBR</span>
                            <input type="file" name="schedule_file" accept="application/json,.json,.stridebr.json" required data-schedule-import-file>
                            <small>Máximo de 2 MB. A importação cria uma cópia privada e independente.</small>
                        </label>
                        <div class="schedule-import-preview" data-schedule-import-preview hidden>
                            <div>
                                <span>Nome</span>
                                <strong data-import-preview-name>—</strong>
                            </div>
                            <div>
                                <span>Treinos</span>
                                <strong data-import-preview-workouts>0</strong>
                            </div>
                            <div>
                                <span>Exercícios</span>
                                <strong data-import-preview-exercises>0</strong>
                            </div>
                        </div>
                        <p class="schedule-import-error" data-schedule-import-error hidden></p>
                        <div class="form-actions">
                            <button type="submit" class="primary-button" data-schedule-import-submit disabled>Importar e criar</button>
                            <button type="button" class="secondary-button" data-schedule-create-back>Voltar</button>
                        </div>
                    </form>
                </div>
            </section>

            <?php if ($cronogramas === []): ?>
                <section class="empty-state">
                    <h2>Crie seu primeiro cronograma</h2>
                    <p>Um cronograma pode representar academia, corrida, calistenia ou qualquer outra rotina semanal.</p>
                    <div class="form-actions">
                        <button type="button" class="primary-button" data-open-schedule-create>Criar cronograma</button>
                        <button type="button" class="secondary-button" data-open-schedule-import>Importar arquivo</button>
                    </div>
                </section>
            <?php else: ?>
                <section class="schedule-toolbar" data-schedule-toolbar>
                    <label class="schedule-select-label"><span>Cronograma</span>
                        <select data-schedule-selector>
                            <?php foreach ($cronogramas as $item): ?>
                                <option value="<?php echo stridebr_e($item['idcronograma']); ?>"<?php echo $item['idcronograma'] === $idSelecionado ? ' selected' : ''; ?>><?php echo stridebr_e($item['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="view-switch" role="group" aria-label="Visualização">
                        <button type="button" class="view-button is-active" data-view="week">Semana</button>
                        <button type="button" class="view-button" data-view="agenda">Agenda</button>
                    </div>
                    <button type="button" class="primary-button schedule-new-workout" data-new-workout>+ Treino</button>
                    <div class="schedule-toolbar-spacer"></div>
                    <div class="zoom-controls" data-zoom-controls aria-label="Zoom da semana">
                        <button type="button" class="view-button" data-zoom-out aria-label="Diminuir zoom">−</button>
                        <span data-zoom-label>100%</span>
                        <button type="button" class="view-button" data-zoom-in aria-label="Aumentar zoom">+</button>
                        <button type="button" class="view-button zoom-fit-button" data-zoom-fit title="Ajustar à área visível">Ajustar</button>
                    </div>
                    <details class="schedule-actions-menu">
                        <summary class="secondary-button icon-only-button" aria-label="Mais ações" title="Mais ações">•••</summary>
                        <div class="schedule-actions-content">
                            <strong>Compartilhar e arquivos</strong>
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=json">Exportar arquivo StrideBR</a>
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=csv">Exportar planilha CSV</a>
                            <button type="button" data-print-schedule>Imprimir / PDF</button>
                            <?php if ($friendsEnabled): ?><button type="button" data-open-share>Compartilhar com amigo</button><?php endif; ?>
                            <button type="button" data-open-schedule-import>Importar cronograma</button>
                            <hr>
                            <strong>Privacidade</strong>
                            <form method="POST" class="schedule-visibility-form">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="update_schedule_visibility">
                                <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                <select name="visibilidade" onchange="this.form.submit()" aria-label="Visibilidade do cronograma">
                                    <option value="privado"<?php echo ($cronograma['visibilidade'] ?? 'privado') === 'privado' ? ' selected' : ''; ?>>Privado</option>
                                    <option value="amigos"<?php echo ($cronograma['visibilidade'] ?? '') === 'amigos' ? ' selected' : ''; ?>>Amigos</option>
                                    <option value="publico"<?php echo ($cronograma['visibilidade'] ?? '') === 'publico' ? ' selected' : ''; ?>>Público</option>
                                </select>
                            </form>
                            <hr>
                            <form method="POST" onsubmit="return confirm('Excluir este cronograma e todos os treinos dele?');">
                                <?php echo stridebr_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                <button type="submit" class="menu-danger">Excluir cronograma</button>
                            </form>
                        </div>
                    </details>
                </section>

                <?php if ($friendsEnabled): ?>
                <section class="schedule-share-panel" data-share-panel hidden>
                    <div><strong>Compartilhar uma cópia</strong><p>Seu amigo recebe exatamente esta versão. Mudanças futuras no seu cronograma não alteram a cópia dele.</p></div>
                    <?php if ($friends === []): ?>
                        <div class="share-empty">Adicione um amigo primeiro. <a href="/user/amigos.php">Ir para Amigos</a></div>
                    <?php else: ?>
                        <form method="POST" class="compact-form">
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="action" value="share_snapshot">
                            <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                            <label>Enviar para<select name="idusuario_destino" required><?php foreach ($friends as $friend): ?><option value="<?php echo stridebr_e($friend['idusuario']); ?>"><?php echo stridebr_e($friend['nome_exibicao']); ?><?php echo $friend['username'] ? ' (@' . stridebr_e($friend['username']) . ')' : ''; ?></option><?php endforeach; ?></select></label>
                            <button type="submit" class="primary-button">Enviar cópia</button>
                            <button type="button" class="secondary-button" data-close-share>Cancelar</button>
                        </form>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="workout-editor<?php echo $treinoEdicao !== [] ? ' is-open' : ''; ?>" data-workout-editor>
                    <div class="editor-title-row">
                        <h2 data-editor-title><?php echo $treinoEdicao !== [] ? 'Editar treino' : 'Novo treino'; ?></h2>
                        <button type="button" class="icon-button" data-close-workout aria-label="Fechar">×</button>
                    </div>
                    <form method="POST" class="workout-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="save_workout">
                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                        <input type="hidden" name="idtreino" value="<?php echo stridebr_e($treinoEdicao['idtreino'] ?? ''); ?>" data-workout-id>
                        <label>Título
                            <input type="text" name="titulo" maxlength="120" value="<?php echo stridebr_e($treinoEdicao['titulo'] ?? ''); ?>" placeholder="Ex.: Academia — Peito" required>
                        </label>
                        <label>Dia
                            <select name="dia_semana" required>
                                <?php foreach ($dias as $index => $dia): ?>
                                    <option value="<?php echo $index; ?>"<?php echo (int) ($treinoEdicao['dia_semana'] ?? 1) === $index ? ' selected' : ''; ?>><?php echo stridebr_e($dia); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Início
                            <input type="time" name="hora_inicio" value="<?php echo stridebr_e(isset($treinoEdicao['hora_inicio']) ? substr($treinoEdicao['hora_inicio'], 0, 5) : '18:00'); ?>" required>
                        </label>
                        <label>Fim
                            <input type="time" name="hora_fim" value="<?php echo stridebr_e(isset($treinoEdicao['hora_fim']) ? substr($treinoEdicao['hora_fim'], 0, 5) : '19:00'); ?>" required>
                        </label>
                        <label class="check-label">
                            <input type="checkbox" name="termina_dia_seguinte" value="1"<?php echo stridebr_db_bool($treinoEdicao['termina_dia_seguinte'] ?? false) ? ' checked' : ''; ?>>
                            Termina no dia seguinte
                        </label>
                        <label class="editor-description">Descrição
                            <textarea name="descricao" rows="2" placeholder="Opcional"><?php echo stridebr_e($treinoEdicao['descricao'] ?? ''); ?></textarea>
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="primary-button">Salvar treino</button>
                            <?php if ($treinoEdicao !== []): ?>
                                <a class="secondary-button" href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($treinoEdicao['idtreino']); ?>">Editar exercícios</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="calendar-view" data-calendar-view="week" data-calendar-scroll>
                    <div class="week-calendar" data-week-calendar>
                        <div class="time-column">
                            <div class="calendar-corner"></div>
                            <div class="time-track">
                                <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                    <span style="--hour: <?php echo $hour; ?>"><?php echo sprintf('%02d:00', $hour); ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php foreach ($dias as $dayIndex => $dayName): ?>
                            <div class="day-column">
                                <div class="day-header"><?php echo stridebr_e($dayName); ?></div>
                                <div class="day-track">
                                    <?php for ($hour = 0; $hour < 24; $hour++): ?><div class="hour-line" style="--hour: <?php echo $hour; ?>"></div><?php endfor; ?>
                                    <?php foreach ($segments[$dayIndex] ?? [] as $segment): ?>
                                        <?php
                                        $duration = max(30, $segment['fim'] - $segment['inicio']);
                                        $item = $segment['treino'];
                                        $exerciseCount = count($exerciciosPorTreino[$item['idtreino']] ?? []);
                                        ?>
                                        <button type="button" class="workout-card<?php echo $segment['continuidade'] ? ' is-continuation' : ''; ?>" data-preview-workout="<?php echo stridebr_e($item['idtreino']); ?>" style="--start-min: <?php echo (int) $segment['inicio']; ?>; --duration-min: <?php echo (int) $duration; ?>" title="Ver treino">
                                            <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                            <span><?php echo $segment['continuidade'] ? 'continuação · ' : ''; ?><?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' +1' : ''; ?></span>
                                            <?php if ($exerciseCount > 0): ?><small><?php echo $exerciseCount; ?> exercício<?php echo $exerciseCount === 1 ? '' : 's'; ?></small><?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="agenda-view" data-calendar-view="agenda" hidden>
                    <?php if ($treinos === []): ?>
                        <div class="empty-state compact"><p>Nenhum treino neste cronograma.</p></div>
                    <?php else: ?>
                        <?php foreach ($dias as $dayIndex => $dayName): ?>
                            <?php $dayWorkouts = array_values(array_filter($treinos, fn(array $t): bool => (int) $t['dia_semana'] === $dayIndex)); ?>
                            <?php if ($dayWorkouts !== []): ?>
                                <div class="agenda-day">
                                    <h2><?php echo stridebr_e($dayName); ?></h2>
                                    <?php foreach ($dayWorkouts as $item): ?>
                                        <article class="agenda-card">
                                            <button type="button" class="agenda-card-main" data-preview-workout="<?php echo stridebr_e($item['idtreino']); ?>">
                                                <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                                <span><?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' do dia seguinte' : ''; ?></span>
                                            </button>
                                            <div class="agenda-actions">
                                                <button type="button" class="secondary-button" data-preview-workout="<?php echo stridebr_e($item['idtreino']); ?>">Prévia</button>
                                                <a class="secondary-button" href="/user/cronogramatreinos.php?id=<?php echo urlencode($idSelecionado); ?>&treino=<?php echo urlencode($item['idtreino']); ?>">Editar</a>
                                                <form method="POST" onsubmit="return confirm('Remover este treino?');">
                                                    <?php echo stridebr_csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_workout">
                                                    <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                                    <input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>">
                                                    <button class="danger-button" type="submit">Remover</button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <div class="workout-preview" data-workout-preview-modal hidden>
                    <div class="workout-preview-backdrop" data-close-preview></div>
                    <section class="workout-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="workout-preview-title">
                        <button type="button" class="icon-button workout-preview-close" data-close-preview aria-label="Fechar">×</button>
                        <?php foreach ($treinos as $item): ?>
                            <?php $previewExercises = $exerciciosPorTreino[$item['idtreino']] ?? []; ?>
                            <div class="workout-preview-content" data-workout-preview-content="<?php echo stridebr_e($item['idtreino']); ?>" hidden>
                                <div class="workout-preview-heading">
                                    <span><?php echo stridebr_e($dias[(int) $item['dia_semana']]); ?> · <?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' +1' : ''; ?></span>
                                    <h2 id="workout-preview-title"><?php echo stridebr_e($item['titulo']); ?></h2>
                                    <?php if (!empty($item['descricao'])): ?><p><?php echo stridebr_e($item['descricao']); ?></p><?php endif; ?>
                                </div>
                                <div class="workout-preview-exercises">
                                    <?php if ($previewExercises === []): ?>
                                        <p class="preview-empty">Esse treino ainda não tem exercícios.</p>
                                    <?php else: ?>
                                        <?php foreach ($previewExercises as $i => $exercise): ?>
                                            <article class="preview-exercise">
                                                <span class="preview-exercise-number"><?php echo $i + 1; ?></span>
                                                <div>
                                                    <strong><?php echo stridebr_e($exercise['nome_snapshot']); ?></strong>
                                                    <div class="preview-exercise-meta">
                                                        <?php if ($exercise['series'] !== null): ?><span><?php echo stridebr_e((string) $exercise['series']); ?> séries</span><?php endif; ?>
                                                        <?php if (!empty($exercise['repeticoes'])): ?><span><?php echo stridebr_e($exercise['repeticoes']); ?> reps</span><?php endif; ?>
                                                        <?php if (!empty($exercise['carga'])): ?><span><?php echo stridebr_e($exercise['carga']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['descanso'])): ?><span>Descanso: <?php echo stridebr_e($exercise['descanso']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['bloco'])): ?><span>Bloco: <?php echo stridebr_e($exercise['bloco']); ?></span><?php endif; ?>
                                                        <?php if (!empty($exercise['cluster'])): ?><span><?php echo stridebr_e($exercise['cluster']); ?></span><?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($exercise['observacoes'])): ?><small><?php echo stridebr_e($exercise['observacoes']); ?></small><?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="workout-preview-actions">
                                    <?php if ($workoutSessionsEnabled): ?><button type="button" class="primary-button" data-start-workout="<?php echo stridebr_e($item['idtreino']); ?>">Iniciar treino</button><?php endif; ?>
                                    <a class="secondary-button" href="/user/cronogramatreinos.php?id=<?php echo urlencode($idSelecionado); ?>&treino=<?php echo urlencode($item['idtreino']); ?>">Editar treino</a>
                                    <a class="secondary-button" href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($item['idtreino']); ?>">Editar exercícios</a>
                                    <form method="POST" class="preview-delete-form" onsubmit="return confirm('Remover este treino do cronograma?');">
                                        <?php echo stridebr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_workout">
                                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                                        <input type="hidden" name="idtreino" value="<?php echo stridebr_e($item['idtreino']); ?>">
                                        <button type="submit" class="danger-button">Excluir treino</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/cronogramas.js')); ?>"></script>
</body>
</html>
