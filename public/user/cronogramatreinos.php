<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
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
    $nome = cronogramaNomeImportado($pdo, $idUsuario, (string) ($data['cronograma']['nome'] ?? 'Cronograma importado'));
    $idCronograma = cronogramaCriar($pdo, $idUsuario, $nome, $data['cronograma']['descricao'] ?? null);
    try {
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
            if (stridebr_db_bool($treino['termina_dia_seguinte'] ?? false)) $payload['termina_dia_seguinte'] = '1';
            $idTreino = cronogramaSalvarTreino($pdo, $idUsuario, $payload);
            $rows = [];
            foreach (($treino['exercicios'] ?? []) as $exercicio) {
                if (!is_array($exercicio)) continue;
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
            if ($rows !== []) cronogramaSalvarExercicios($pdo, $idTreino, $idUsuario, $rows, []);
        }
        return $idCronograma;
    } catch (Throwable $e) {
        cronogramaExcluir($pdo, $idCronograma, $idUsuario);
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
        if ($action === 'import_schedule') {
            if (!isset($_FILES['schedule_file']) || !is_uploaded_file($_FILES['schedule_file']['tmp_name'])) {
                throw new InvalidArgumentException('Selecione um arquivo .json exportado pelo StrideBR.');
            }
            if ((int) $_FILES['schedule_file']['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('O arquivo é grande demais.');
            }
            $raw = file_get_contents($_FILES['schedule_file']['tmp_name']);
            $data = json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
            $idImportado = cronogramaImportData($pdo, $idUsuario, $data);
            stridebr_flash('success', 'Cronograma importado.');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/cronogramas.css">
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
                    <button type="button" class="primary-button" data-open-schedule-create>Novo cronograma</button>
                </div>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="schedule-create-panel" data-schedule-create hidden>
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
                        <button type="submit" class="primary-button">Criar</button>
                        <button type="button" class="secondary-button" data-close-schedule-create>Cancelar</button>
                    </div>
                </form>
            </section>

            <?php if ($cronogramas === []): ?>
                <section class="empty-state">
                    <h2>Crie seu primeiro cronograma</h2>
                    <p>Um cronograma pode representar academia, corrida, calistenia ou qualquer outra rotina semanal.</p>
                    <button type="button" class="primary-button" data-open-schedule-create>Criar cronograma</button>
                </section>
            <?php else: ?>
                <section class="schedule-toolbar" data-schedule-toolbar>
                    <label class="schedule-select-label">Cronograma
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
                    <button type="button" class="primary-button" data-new-workout>Novo treino</button>
                    <button type="button" class="secondary-button" data-discover-schedules>Descobrir cronogramas</button>
                    <div class="zoom-controls" data-zoom-controls aria-label="Zoom da semana">
                        <button type="button" class="view-button" data-zoom-out aria-label="Diminuir zoom">−</button>
                        <span data-zoom-label>100%</span>
                        <button type="button" class="view-button" data-zoom-in aria-label="Aumentar zoom">+</button>
                        <button type="button" class="view-button" data-zoom-fit>Ajustar</button>
                    </div>
                    <details class="schedule-export-menu">
                        <summary class="secondary-button">Exportar</summary>
                        <div class="schedule-export-content">
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=json">Arquivo StrideBR</a>
                            <a href="?id=<?php echo urlencode($idSelecionado); ?>&export=csv">Planilha CSV</a>
                            <button type="button" data-print-schedule>Imprimir / PDF</button>
                        </div>
                    </details>
                    <button type="button" class="secondary-button" data-open-import>Importar</button>
                    <form method="POST" onsubmit="return confirm('Excluir este cronograma e todos os treinos dele?');">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_schedule">
                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                        <button type="submit" class="danger-button">Excluir</button>
                    </form>
                </section>

                <section class="schedule-import-panel" data-import-panel hidden>
                    <div>
                        <strong>Importar cronograma</strong>
                        <p>Use um arquivo <code>.stridebr.json</code> exportado pelo StrideBR. O cronograma será criado como uma cópia nova.</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="compact-form">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="import_schedule">
                        <input type="file" name="schedule_file" accept="application/json,.json,.stridebr.json" required>
                        <button type="submit" class="primary-button">Importar</button>
                        <button type="button" class="secondary-button" data-close-import>Cancelar</button>
                    </form>
                </section>

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
                                    <a class="secondary-button" href="/user/cronogramatreinos.php?id=<?php echo urlencode($idSelecionado); ?>&treino=<?php echo urlencode($item['idtreino']); ?>">Editar treino</a>
                                    <a class="primary-button" href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($item['idtreino']); ?>">Editar exercícios</a>
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
<script src="/assets/js/cronogramas.js"></script>
</body>
</html>
