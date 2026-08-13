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
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
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
<body>
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
                    <a class="secondary-button" href="/user/bibliotecaexercicios.php">Biblioteca de exercícios</a>
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
                <section class="schedule-toolbar">
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
                    <form method="POST" onsubmit="return confirm('Excluir este cronograma e todos os treinos dele?');">
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_schedule">
                        <input type="hidden" name="idcronograma" value="<?php echo stridebr_e($idSelecionado); ?>">
                        <button type="submit" class="danger-button">Excluir cronograma</button>
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

                <section class="calendar-view" data-calendar-view="week">
                    <div class="week-calendar">
                        <div class="time-column">
                            <div class="calendar-corner"></div>
                            <div class="time-track">
                                <?php for ($hour = 0; $hour < 24; $hour++): ?>
                                    <span style="top: <?php echo $hour * 48; ?>px"><?php echo sprintf('%02d:00', $hour); ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php foreach ($dias as $dayIndex => $dayName): ?>
                            <div class="day-column">
                                <div class="day-header"><?php echo stridebr_e($dayName); ?></div>
                                <div class="day-track">
                                    <?php for ($hour = 0; $hour < 24; $hour++): ?><div class="hour-line" style="top: <?php echo $hour * 48; ?>px"></div><?php endfor; ?>
                                    <?php foreach ($segments[$dayIndex] ?? [] as $segment): ?>
                                        <?php
                                        $top = $segment['inicio'] / 60 * 48;
                                        $height = max(24, ($segment['fim'] - $segment['inicio']) / 60 * 48);
                                        $item = $segment['treino'];
                                        ?>
                                        <a class="workout-card<?php echo $segment['continuidade'] ? ' is-continuation' : ''; ?>" href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($item['idtreino']); ?>" style="top: <?php echo number_format($top, 2, '.', ''); ?>px; height: <?php echo number_format($height, 2, '.', ''); ?>px" title="Abrir exercícios">
                                            <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                            <span><?php echo $segment['continuidade'] ? 'continuação · ' : ''; ?><?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' +1' : ''; ?></span>
                                            <?php if ($height >= 70 && !empty($item['descricao'])): ?><small><?php echo stridebr_e($item['descricao']); ?></small><?php endif; ?>
                                        </a>
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
                                            <div>
                                                <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                                <span><?php echo stridebr_e(substr($item['hora_inicio'], 0, 5)); ?>–<?php echo stridebr_e(substr($item['hora_fim'], 0, 5)); ?><?php echo stridebr_db_bool($item['termina_dia_seguinte']) ? ' do dia seguinte' : ''; ?></span>
                                            </div>
                                            <div class="agenda-actions">
                                                <a class="secondary-button" href="/user/exercicioscronograma.php?idtreino=<?php echo urlencode($item['idtreino']); ?>">Exercícios</a>
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
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="/assets/js/cronogramas.js"></script>
</body>
</html>
