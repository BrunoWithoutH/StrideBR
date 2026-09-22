<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') stridebr_session_release();

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/workout_preview_service.php';

try {
    $idTreino = trim((string) ($_GET['idtreino'] ?? ''));
    if ($idTreino === '') throw new InvalidArgumentException(stridebr_t('schedule.validation.workout_required'));
    $treino = cronogramaBuscarTreino($pdo, $idTreino, $idUsuario);
    if ($treino === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    $preview = workoutPreviewData($pdo, $idUsuario, $idTreino, [
        'occurrence_original' => (string) ($_GET['occurrence_original'] ?? ''),
        'planned_date' => (string) ($_GET['planned_date'] ?? ''),
        'activity_id' => (string) ($_GET['activity_id'] ?? ''),
    ]);
    $dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    echo json_encode([
        'ok' => true,
        'preview_mode' => $preview['preview_mode'],
        'activity_id' => $preview['activity_id'],
        'actual' => $preview['actual'],
        'workout' => [
            'idtreino' => (string) $treino['idtreino'],
            'idcronograma' => (string) $treino['idcronograma'],
            'titulo' => (string) $treino['titulo'],
            'codigo' => (string) ($treino['codigo'] ?? ''),
            'foco' => (string) ($treino['foco'] ?? ''),
            'idmodalidade' => (string) ($treino['idmodalidade'] ?? ''),
            'pacer_plan_id' => (string) ($treino['idpacerplan'] ?? ''),
            'route_id' => (string) ($treino['idrota_salva'] ?? ''),
            'descricao' => (string) ($treino['descricao'] ?? ''),
            'dia' => $dias[(int) ($treino['dia_semana'] ?? 0)] ?? '',
            'hora_inicio' => substr((string) ($treino['hora_inicio'] ?? ''), 0, 5),
            'hora_fim' => substr((string) ($treino['hora_fim'] ?? ''), 0, 5),
            'termina_dia_seguinte' => stridebr_db_bool($treino['termina_dia_seguinte'] ?? false),
            'dia_semana' => (int) ($treino['dia_semana'] ?? 0),
            'vigencia_inicio' => (string) ($treino['vigencia_inicio'] ?? ''),
            'vigencia_fim' => (string) ($treino['vigencia_fim'] ?? ''),
            'duracao_minutos' => cronogramaDuracaoMinutos($treino),
            'idtreino_modelo' => (string) ($treino['idtreino_modelo'] ?? ''),
            'biblioteca_disponivel' => cronogramaBibliotecaDisponivel($pdo),
        ],
        'exercises' => $preview['exercises'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException|RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Workout preview API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível carregar o treino.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
