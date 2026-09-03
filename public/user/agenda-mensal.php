<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/treinador.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';

if (!stridebr_feature_enabled($pdo, 'monthly_calendar.enabled', false)) {
    stridebr_error_document(404);
}

$targetId = trim((string) ($_GET['atleta'] ?? $idUsuario));
if ($targetId === '') {
    $targetId = $idUsuario;
}
$isSelf = $targetId === $idUsuario;
$trainerLink = [];
if (!$isSelf) {
    if (!stridebr_feature_enabled($pdo, 'trainer.enabled', false)) {
        stridebr_error_document(403);
    }
    $trainerLink = treinadorVinculoAceito($pdo, $idUsuario, $targetId);
    if ($trainerLink === [] || !stridebr_db_bool($trainerLink['pode_ver_cronograma'] ?? false)) {
        stridebr_error_document(403);
    }
}

$targetUserStmt = $pdo->prepare("SELECT idusuario, username, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, preferenciasusuario FROM usuarios WHERE idusuario = :id AND statususuario = 'Ativo' LIMIT 1");
$targetUserStmt->execute([':id' => $targetId]);
$targetUser = $targetUserStmt->fetch();
if (!$targetUser) {
    stridebr_error_document(404);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    if (!$isSelf) {
        stridebr_error_document(403);
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_personal') {
            $date = trim((string) ($_POST['data_treino'] ?? ''));
            $time = trim((string) ($_POST['hora_inicio'] ?? ''));
            $title = trim((string) ($_POST['titulo'] ?? ''));
            $description = trim((string) ($_POST['descricao'] ?? ''));
            $durationRaw = trim((string) ($_POST['duracao_prevista_min'] ?? ''));
            $sourceWorkout = trim((string) ($_POST['idtreino_origem'] ?? ''));

            if (!treinadorDataValida($date, true)) {
                throw new InvalidArgumentException('Data inválida. Use até 31 dias no passado ou os próximos dois anos.');
            }
            if (!treinadorHoraValida($time)) {
                throw new InvalidArgumentException('Horário inválido.');
            }
            if (stridebr_length($description) > 5000) {
                throw new InvalidArgumentException('A descrição deve ter no máximo 5.000 caracteres.');
            }
            $duration = null;
            if ($durationRaw !== '') {
                if (filter_var($durationRaw, FILTER_VALIDATE_INT) === false || (int) $durationRaw < 1 || (int) $durationRaw > 1440) {
                    throw new InvalidArgumentException('Duração prevista inválida.');
                }
                $duration = (int) $durationRaw;
            }

            $originCronograma = null;
            $originWorkout = null;
            $sourceExercises = [];
            if ($sourceWorkout !== '') {
                $source = cronogramaBuscarTreino($pdo, $sourceWorkout, $idUsuario);
                if ($source === []) {
                    throw new RuntimeException('Treino de origem não encontrado.');
                }
                $originCronograma = $source['idcronograma'];
                $originWorkout = $source['idtreino'];
                if ($title === '') {
                    $title = (string) $source['titulo'];
                }
                if ($time === '') {
                    $time = substr((string) $source['hora_inicio'], 0, 5);
                }
                if ($description === '' && !empty($source['descricao'])) {
                    $description = (string) $source['descricao'];
                }
                $sourceExercises = cronogramaListarTreinoExercicios($pdo, $sourceWorkout, $idUsuario);
            }

            if ($title === '' || stridebr_length($title) > 120) {
                throw new InvalidArgumentException('Informe um título de até 120 caracteres.');
            }

            $idAgendamento = stridebr_generate_id();
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare("INSERT INTO treinos_agendados (idagendamento, idatleta, idcriador, idcronograma_origem, idtreino_origem, data_treino, hora_inicio, duracao_prevista_min, titulo, descricao, origem, status, publicado_em) VALUES (:id, :atleta, :criador, :cronograma, :treino, :data, :hora, :duracao, :titulo, :descricao, 'usuario', 'publicado', NOW())");
                $insert->execute([
                    ':id' => $idAgendamento,
                    ':atleta' => $idUsuario,
                    ':criador' => $idUsuario,
                    ':cronograma' => $originCronograma,
                    ':treino' => $originWorkout,
                    ':data' => $date,
                    ':hora' => $time !== '' ? $time : null,
                    ':duracao' => $duration,
                    ':titulo' => $title,
                    ':descricao' => $description !== '' ? $description : null,
                ]);
                if ($sourceExercises !== []) {
                    $insertExercise = $pdo->prepare('INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio, idagendamento, idexercicio, nome_snapshot, series, repeticoes, carga, descanso, observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem) VALUES (:id, :agendamento, :exercicio, :nome, :series, :repeticoes, :carga, :descanso, :observacoes, :duracao, :distancia, :intensidade, :rpe, :rir, :tempo_execucao, :cadencia, :ordem)');
                    foreach ($sourceExercises as $index => $exercise) {
                        $insertExercise->execute([
                            ':id' => stridebr_generate_id(),
                            ':agendamento' => $idAgendamento,
                            ':exercicio' => $exercise['idexercicio'] ?: null,
                            ':nome' => $exercise['nome_snapshot'],
                            ':series' => $exercise['series'],
                            ':repeticoes' => $exercise['repeticoes'] ?: null,
                            ':carga' => $exercise['carga'] ?: null,
                            ':descanso' => $exercise['descanso'] ?: null,
                            ':observacoes' => $exercise['observacoes'] ?: null,
                            ':duracao' => $exercise['duracao'] ?: null,
                            ':distancia' => $exercise['distancia'] ?: null,
                            ':intensidade' => $exercise['intensidade'] ?: null,
                            ':rpe' => $exercise['rpe'] !== null && $exercise['rpe'] !== '' ? $exercise['rpe'] : null,
                            ':rir' => $exercise['rir'] !== null && $exercise['rir'] !== '' ? $exercise['rir'] : null,
                            ':tempo_execucao' => $exercise['tempo_execucao'] ?: null,
                            ':cadencia' => $exercise['cadencia'] ?: null,
                            ':ordem' => $index + 1,
                        ]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            stridebr_flash('success', 'Treino adicionado à agenda mensal.');
        } elseif ($action === 'cancel_scheduled') {
            treinadorCancelarPrescricao($pdo, $idUsuario, (string) ($_POST['idagendamento'] ?? ''));
            stridebr_flash('success', 'Treino agendado cancelado.');
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }

        $redirectMonth = trim((string) ($_POST['month'] ?? ''));
        $location = '/user/agenda-mensal.php';
        if (preg_match('/^\d{4}-\d{2}$/', $redirectMonth)) {
            $location .= '?month=' . rawurlencode($redirectMonth);
        }
        header('Location: ' . $location);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar a agenda.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log('StrideBR monthly agenda failed: ' . $e->getMessage());
        }
    }
}

$monthRaw = trim((string) ($_GET['month'] ?? ''));
$monthStart = DateTimeImmutable::createFromFormat('!Y-m', $monthRaw);
if (!$monthStart || $monthStart->format('Y-m') !== $monthRaw) {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthEnd = $monthStart->modify('last day of this month');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$monthKey = $monthStart->format('Y-m');
$monthNames = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$monthTitle = $monthNames[(int) $monthStart->format('n')] . ' de ' . $monthStart->format('Y');

$scheduleFilter = trim((string) ($_GET['cronograma'] ?? ''));
$schedulesStmt = $pdo->prepare('SELECT idcronograma, nome FROM cronogramas WHERE idusuario = :usuario AND ativo = TRUE ORDER BY data_atualizacao DESC');
$schedulesStmt->execute([':usuario' => $targetId]);
$schedules = $schedulesStmt->fetchAll();
$validScheduleIds = array_map(static fn(array $row): string => (string) $row['idcronograma'], $schedules);
if ($scheduleFilter !== '' && !in_array($scheduleFilter, $validScheduleIds, true)) {
    $scheduleFilter = '';
}

$workoutSql = "SELECT tc.idtreino, tc.idcronograma, tc.titulo, tc.descricao, tc.dia_semana, tc.hora_inicio, tc.hora_fim, tc.termina_dia_seguinte, c.nome AS cronograma_nome FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma = tc.idcronograma WHERE c.idusuario = :usuario AND c.ativo = TRUE";
$params = [':usuario' => $targetId];
if ($scheduleFilter !== '') {
    $workoutSql .= ' AND c.idcronograma = :cronograma';
    $params[':cronograma'] = $scheduleFilter;
}
$workoutSql .= ' ORDER BY tc.dia_semana, tc.hora_inicio, tc.ordem';
$workoutStmt = $pdo->prepare($workoutSql);
$workoutStmt->execute($params);
$recurringWorkouts = $workoutStmt->fetchAll();
$recurringOccurrences = cronogramaListarOcorrenciasConciliadas($pdo, $targetId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), $scheduleFilter !== '' ? $scheduleFilter : null);
$recurringOccurrencesByDate = [];
foreach ($recurringOccurrences as $occurrence) {
    $recurringOccurrencesByDate[(string) $occurrence['data_treino']][] = $occurrence;
}

$scheduledStmt = $pdo->prepare("SELECT ta.*, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario) AS criador_nome, u.username AS criador_username, (SELECT COUNT(*) FROM treinos_agendados_exercicios tae WHERE tae.idagendamento = ta.idagendamento) AS exercicios_total FROM treinos_agendados ta LEFT JOIN usuarios u ON u.idusuario = ta.idcriador WHERE ta.idatleta = :atleta AND ta.data_treino BETWEEN :inicio AND :fim AND ta.status <> 'cancelado' AND (ta.status IN ('publicado','concluido') OR ta.idcriador = :viewer) ORDER BY ta.data_treino, ta.hora_inicio NULLS LAST, ta.data_criacao");
$scheduledStmt->execute([
    ':atleta' => $targetId,
    ':inicio' => $monthStart->format('Y-m-d'),
    ':fim' => $monthEnd->format('Y-m-d'),
    ':viewer' => $idUsuario,
]);
$scheduled = $scheduledStmt->fetchAll();
$scheduledByDate = [];
foreach ($scheduled as $item) {
    $scheduledByDate[(string) $item['data_treino']][] = $item;
}
$recurringCompleted = count(array_filter($recurringOccurrences, static fn(array $item): bool => !empty($item['concluido'])));
$scheduledCompleted = count(array_filter($scheduled, static fn(array $item): bool => (string) ($item['status'] ?? '') === 'concluido'));
$monthTotal = count($recurringOccurrences) + count($scheduled);
$monthCompleted = $recurringCompleted + $scheduledCompleted;

$prefs = is_array($targetUser['preferenciasusuario'] ?? null) ? $targetUser['preferenciasusuario'] : (json_decode((string) ($targetUser['preferenciasusuario'] ?? '{}'), true) ?: []);
$mondayFirst = ($prefs['week_start'] ?? 'sunday') === 'monday';
$dayNames = $mondayFirst ? ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] : ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$firstWeekday = (int) $monthStart->format('w');
$leading = $mondayFirst ? ($firstWeekday + 6) % 7 : $firstWeekday;
$totalDays = (int) $monthEnd->format('j');
$trailing = (7 - (($leading + $totalDays) % 7)) % 7;
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$currentMonthKey = (new DateTimeImmutable('first day of this month'))->format('Y-m');
$selectedScheduleName = '';
foreach ($schedules as $schedule) {
    if ((string) $schedule['idcronograma'] === $scheduleFilter) { $selectedScheduleName = (string) $schedule['nome']; break; }
}
$returnScheduleId = $scheduleFilter !== '' ? $scheduleFilter : ((string) ($schedules[0]['idcronograma'] ?? ''));
$returnScheduleUrl = $returnScheduleId !== '' ? '/user/cronogramatreinos.php?id=' . rawurlencode($returnScheduleId) . '&view=month&month=' . rawurlencode($monthKey) : '/user/cronogramatreinos.php';
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Agenda mensal | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell monthly-shell">
            <div class="page-heading monthly-heading"><div><span class="eyebrow">Planejamento</span><h1 data-monthly-page-title><?php echo $selectedScheduleName !== '' ? stridebr_e($selectedScheduleName) : 'Agenda mensal'; ?></h1><p><?php echo $isSelf ? 'Veja recorrências e treinos marcados em datas específicas no mesmo calendário.' : 'Agenda de ' . stridebr_e(stridebr_person_name_for_display((string) $targetUser['nome_exibicao'], (string) ($targetUser['username'] ?? ''), 'Atleta', 60)) . '.'; ?></p></div></div>
            <nav class="planning-subnav monthly-planning-subnav" aria-label="Navegação de planejamento">
                <a href="/user/cronogramatreinos.php<?php echo !$isSelf ? '?atleta=' . rawurlencode($targetId) : ''; ?>">Cronogramas</a>
                <a class="is-active" href="/user/agenda-mensal.php<?php echo !$isSelf ? '?atleta=' . rawurlencode($targetId) : ''; ?>">Agenda mensal</a>
                <?php if (stridebr_feature_enabled($pdo, 'trainer.enabled', false)): ?><a href="/user/treinador.php<?php echo !$isSelf ? '?atleta=' . rawurlencode($targetId) : ''; ?>">Treinador e atletas</a><?php endif; ?>
                <?php if ($selectedScheduleName !== ''): ?><a class="planning-subnav-context" href="<?php echo stridebr_e($returnScheduleUrl); ?>">Abrir cronograma selecionado →</a><?php endif; ?>
            </nav>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <div data-monthly-dynamic data-month-key="<?php echo stridebr_e($monthKey); ?>" data-is-self="<?php echo $isSelf ? '1' : '0'; ?>">
            <section class="monthly-toolbar content-card" aria-label="Controles do calendário">
                <div class="monthly-nav">
                    <a class="monthly-nav-button" data-month-nav href="?month=<?php echo rawurlencode($prevMonth); ?><?php echo !$isSelf ? '&atleta=' . rawurlencode($targetId) : ''; ?><?php echo $scheduleFilter !== '' ? '&cronograma=' . rawurlencode($scheduleFilter) : ''; ?>" aria-label="Mês anterior">‹</a>
                    <div class="monthly-title-block"><strong><?php echo stridebr_e($monthTitle); ?></strong><span><?php echo $monthTotal; ?> treino(s) · <?php echo $monthCompleted; ?> realizado(s)</span><small data-monthly-load-status hidden>Atualizando…</small></div>
                    <a class="monthly-nav-button" data-month-nav href="?month=<?php echo rawurlencode($nextMonth); ?><?php echo !$isSelf ? '&atleta=' . rawurlencode($targetId) : ''; ?><?php echo $scheduleFilter !== '' ? '&cronograma=' . rawurlencode($scheduleFilter) : ''; ?>" aria-label="Próximo mês">›</a>
                    <?php if ($monthKey !== $currentMonthKey): ?><a class="secondary-button monthly-today-button" data-month-nav href="?month=<?php echo rawurlencode($currentMonthKey); ?><?php echo !$isSelf ? '&atleta=' . rawurlencode($targetId) : ''; ?><?php echo $scheduleFilter !== '' ? '&cronograma=' . rawurlencode($scheduleFilter) : ''; ?>">Hoje</a><?php endif; ?>
                </div>
                <form method="GET" class="monthly-filter">
                    <?php if (!$isSelf): ?><input type="hidden" name="atleta" value="<?php echo stridebr_e($targetId); ?>"><?php endif; ?>
                    <input type="hidden" name="month" value="<?php echo stridebr_e($monthKey); ?>">
                    <label><span>Cronograma</span><select name="cronograma"><option value="">Todos</option><?php foreach ($schedules as $schedule): ?><option value="<?php echo stridebr_e($schedule['idcronograma']); ?>"<?php echo $scheduleFilter === $schedule['idcronograma'] ? ' selected' : ''; ?>><?php echo stridebr_e($schedule['nome']); ?></option><?php endforeach; ?></select></label>
                </form>
            </section>

            <div class="monthly-meta-row">
                <div class="monthly-legend" aria-label="Legenda">
                    <span><i class="is-recurring"></i>Rotina</span>
                    <span><i class="is-completed"></i>Realizado</span>
                    <span><i class="is-scheduled"></i>Data específica</span>
                    <span><i class="is-trainer"></i>Treinador</span>
                </div>
                <div class="monthly-summary-pills" aria-label="Resumo do mês">
                    <span><strong><?php echo count($recurringOccurrences); ?></strong> da rotina</span>
                    <?php if ($scheduled !== []): ?><span><strong><?php echo count($scheduled); ?></strong> específicos</span><?php endif; ?>
                </div>
            </div>

            <?php if ($isSelf): ?>
                <details class="monthly-add content-card">
                    <summary><span>Adicionar treino em data específica</span><small>Use para algo fora da rotina semanal</small></summary>
                    <form method="POST" class="monthly-add-form">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="create_personal"><input type="hidden" name="month" value="<?php echo stridebr_e($monthKey); ?>">
                        <label>Copiar de um treino semanal<select name="idtreino_origem"><option value="">Não copiar</option><?php foreach ($recurringWorkouts as $workout): ?><option value="<?php echo stridebr_e($workout['idtreino']); ?>"><?php echo stridebr_e($workout['cronograma_nome'] . ' · ' . $workout['titulo']); ?></option><?php endforeach; ?></select></label>
                        <label>Título<input type="text" name="titulo" maxlength="120" placeholder="Se copiar um treino, pode deixar vazio"></label>
                        <label>Data<input type="date" name="data_treino" value="<?php echo stridebr_e(max($today, $monthStart->format('Y-m-d'))); ?>" required></label>
                        <label class="time24-field-label">Hora<div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="Escolher horário">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span>Horário · 24 h</span><button type="button" data-time24-now>Agora</button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_inicio" value="" data-time24-value></div></label>
                        <label>Duração prevista (min)<input type="number" name="duracao_prevista_min" min="1" max="1440" inputmode="numeric"></label>
                        <label class="monthly-add-wide">Descrição<textarea name="descricao" maxlength="5000" rows="2"></textarea></label>
                        <button type="submit" class="primary-button">Adicionar à agenda</button>
                    </form>
                </details>
            <?php endif; ?>

            <section class="monthly-calendar" aria-label="Calendário mensal">
                <?php foreach ($dayNames as $dayName): ?><div class="monthly-weekday"><?php echo stridebr_e($dayName); ?></div><?php endforeach; ?>
                <?php for ($blank = 0; $blank < $leading; $blank++): ?><div class="monthly-day is-outside" aria-hidden="true"></div><?php endfor; ?>
                <?php for ($day = 1; $day <= $totalDays; $day++): ?>
                    <?php
                    $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day);
                    $dateKey = $date->format('Y-m-d');
                    $dayRecurring = $recurringOccurrencesByDate[$dateKey] ?? [];
                    $dayScheduled = $scheduledByDate[$dateKey] ?? [];
                    ?>
                    <article class="monthly-day<?php echo $dateKey === $today ? ' is-today' : ''; ?>">
                        <header><strong><?php echo $day; ?></strong><?php if ($dateKey === $today): ?><span>Hoje</span><?php endif; ?></header>
                        <div class="monthly-events">
                            <?php foreach ($dayScheduled as $item): ?>
                                <?php $scheduledDone = (string) ($item['status'] ?? '') === 'concluido'; ?>
                                <div class="monthly-event is-scheduled<?php echo $item['origem'] === 'treinador' ? ' is-trainer' : ''; ?><?php echo $scheduledDone ? ' is-completed' : ''; ?>">
                                    <div class="monthly-event-kicker"><span><?php echo $scheduledDone ? '✓ ' : ''; ?><?php echo $item['hora_inicio'] ? stridebr_e(substr((string) $item['hora_inicio'], 0, 5)) : 'Por data'; ?></span><em><?php echo $item['origem'] === 'treinador' ? 'Treinador' : 'Específico'; ?></em></div>
                                    <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                    <?php if ($item['origem'] === 'treinador' && $item['criador_nome']): ?><small>por <?php echo stridebr_e($item['criador_nome']); ?></small><?php endif; ?>
                                    <?php if ((int) $item['exercicios_total'] > 0): ?><small><?php echo (int) $item['exercicios_total']; ?> exercício(s)</small><?php endif; ?>
                                    <?php if ($isSelf && $item['status'] === 'publicado'): ?><div class="monthly-event-actions"><button type="button" data-start-scheduled-workout="<?php echo stridebr_e($item['idagendamento']); ?>">Iniciar</button><form method="POST" data-confirm="Cancelar este treino agendado?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="cancel_scheduled"><input type="hidden" name="idagendamento" value="<?php echo stridebr_e($item['idagendamento']); ?>"><input type="hidden" name="month" value="<?php echo stridebr_e($monthKey); ?>"><button type="submit" aria-label="Cancelar">×</button></form></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($dayRecurring as $workout): ?>
                                <?php
                                $completed = !empty($workout['concluido']);
                                $shifted = $completed && !empty($workout['realizado_fora_planejado']);
                                $plannedDate = (string) ($workout['data_planejada'] ?? $workout['data_original'] ?? '');
                                $plannedTime = substr((string) ($workout['hora_planejada'] ?? $workout['hora_inicio'] ?? ''), 0, 5);
                                $realizedDate = (string) ($workout['data_realizada'] ?? '');
                                $realizedTime = substr((string) ($workout['hora_realizada'] ?? $workout['hora_inicio'] ?? ''), 0, 5);
                                $activityId = trim((string) ($workout['idregistro'] ?? ''));
                                ?>
                                <div class="monthly-event is-recurring<?php echo $completed ? ' is-completed' : ''; ?><?php echo $shifted ? ' is-shifted' : ''; ?>">
                                    <div class="monthly-event-kicker"><span><?php echo $completed ? '✓ ' : ''; ?><?php echo stridebr_e(substr((string) $workout['hora_inicio'], 0, 5)); ?></span><em><?php echo stridebr_e($workout['cronograma_nome']); ?></em></div>
                                    <strong><?php if (!empty($workout['codigo'])): ?><b><?php echo stridebr_e((string) $workout['codigo']); ?></b><?php endif; ?><?php echo stridebr_e($workout['titulo']); ?></strong>
                                    <?php if (!empty($workout['foco'])): ?><small><?php echo stridebr_e((string) $workout['foco']); ?></small><?php endif; ?>
                                    <?php if ($shifted && $plannedDate !== ''): ?><?php if ($isSelf && $activityId !== ''): ?><button type="button" class="monthly-plan-note" data-edit-schedule-history data-activity-id="<?php echo stridebr_e($activityId); ?>" data-planned-date="<?php echo stridebr_e($plannedDate); ?>" data-planned-time="<?php echo stridebr_e($plannedTime); ?>" data-realized-date="<?php echo stridebr_e($realizedDate); ?>" data-realized-time="<?php echo stridebr_e($realizedTime); ?>">Planejado <?php echo stridebr_e((new DateTimeImmutable($plannedDate))->format('d/m')); ?></button><?php else: ?><small class="monthly-plan-note">Planejado <?php echo stridebr_e((new DateTimeImmutable($plannedDate))->format('d/m')); ?></small><?php endif; ?><?php endif; ?>
                                    <?php if ($isSelf): ?><div class="monthly-event-actions is-link-row"><?php if ($completed && $activityId !== ''): ?><a href="/user/atividades.php?highlight=<?php echo rawurlencode($activityId); ?>">Ver atividade</a><button type="button" class="monthly-inline-action" data-edit-schedule-history data-activity-id="<?php echo stridebr_e($activityId); ?>" data-planned-date="<?php echo stridebr_e($plannedDate); ?>" data-planned-time="<?php echo stridebr_e($plannedTime); ?>" data-realized-date="<?php echo stridebr_e($realizedDate); ?>" data-realized-time="<?php echo stridebr_e($realizedTime); ?>">Ajustar datas</button><?php else: ?><a href="/user/cronogramatreinos.php?id=<?php echo rawurlencode((string) $workout['idcronograma']); ?>&treino=<?php echo rawurlencode((string) $workout['idtreino']); ?>">Abrir treino</a><?php endif; ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endfor; ?>
                <?php for ($blank = 0; $blank < $trailing; $blank++): ?><div class="monthly-day is-outside" aria-hidden="true"></div><?php endfor; ?>
            </section>
            </div>

            <?php if ($isSelf): ?>
            <div class="planned-date-modal" data-planned-date-modal hidden>
                <button type="button" class="planned-date-backdrop" data-close-planned-date aria-label="Fechar"></button>
                <section class="planned-date-dialog schedule-history-dialog" role="dialog" aria-modal="true" aria-labelledby="planned-date-title">
                    <header><div><span class="eyebrow">Planejado × realizado</span><h2 id="planned-date-title">Corrigir datas e horários</h2><p>Edite as datas e horários deste registro.</p></div><button type="button" class="icon-button" data-close-planned-date aria-label="Fechar">×</button></header>
                    <form data-planned-date-form>
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="action" value="set_occurrence_history">
                        <input type="hidden" name="idregistro" data-planned-activity-id>
                        <div class="schedule-history-grid">
                            <fieldset><legend>Planejado</legend><label>Data<input type="date" name="data_planejada" data-planned-date-input required></label><label class="time24-field-label">Hora<div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="Escolher horário">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span>Horário · 24 h</span><button type="button" data-time24-now>Agora</button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_planejada" data-time24-value data-planned-time-input></div></label></fieldset>
                            <fieldset><legend>Realizado</legend><label>Data<input type="date" name="data_realizada" data-realized-date-input required></label><label class="time24-field-label">Hora<div class="time24-control" data-time24><div class="time24-input-row"><input type="text" inputmode="numeric" maxlength="2" required data-time24-hours><span class="time24-separator">:</span><input type="text" inputmode="numeric" maxlength="2" required data-time24-minutes><button type="button" class="time24-toggle" data-time24-toggle aria-label="Escolher horário">⌄</button></div><div class="time24-menu" data-time24-menu hidden><div class="time24-menu-head"><span>Horário · 24 h</span><button type="button" data-time24-now>Agora</button></div><div class="time24-hours-grid" data-time24-hours-grid></div><div class="time24-minutes-grid" data-time24-minutes-grid></div></div><input type="hidden" name="hora_realizada" data-time24-value data-realized-time-input></div></label></fieldset>
                        </div>
                        <footer><button type="button" class="secondary-button" data-close-planned-date>Cancelar</button><button type="submit" class="primary-button">Salvar correção</button></footer>
                    </form>
                </section>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/time24.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/trainer.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/agenda-mensal.js')); ?>"></script>
</body>
</html>
