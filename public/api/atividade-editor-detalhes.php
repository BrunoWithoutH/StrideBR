<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_presenter.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') stridebr_session_release();

try {
    $startedAt = microtime(true);
    $includeSupport = (string) ($_GET['support'] ?? '1') !== '0';
    $requestedModel = trim((string) ($_GET['model'] ?? ''));
    if ($requestedModel === '') throw new InvalidArgumentException('Selecione um formato de atividade.');

    $modalidadeModelo = atividadeBuscarCatalogoModelo($pdo, $idUsuario, $requestedModel);
    if ($modalidadeModelo === null) throw new InvalidArgumentException('O formato selecionado não está disponível.');
    $catalogoModelo = [$modalidadeModelo];
    $catalogo = atividadeListarModalidadesCatalogoLeve($pdo, $idUsuario);

    $camposPorModelo = atividadeBuscarCamposModelos($pdo, [$requestedModel]);
    $modelosDetalhados = atividadeMontarModelosDetalhados($catalogoModelo, $camposPorModelo);
    $equipamentos = $includeSupport ? atividadeListarEquipamentosLeve($pdo, $idUsuario) : [];
    $treinosUsuario = $includeSupport ? cronogramaListarTreinosUsuario($pdo, $idUsuario) : [];
    $exerciciosBiblioteca = $includeSupport ? cronogramaListarExerciciosBiblioteca($pdo, $idUsuario) : [];
    stridebr_timing_measure('editor_data', $startedAt, 'Dados do editor de atividade');

    $formModelo = $requestedModel;
    $repeatRecord = [];
    ob_start();
    require dirname(__DIR__, 2) . '/src/layout/activity_model_panels.php';
    $modelsHtml = (string) ob_get_clean();

    $workouts = array_map(static fn(array $item): array => [
        'id' => (string) ($item['idtreino'] ?? ''),
        'title' => (string) ($item['titulo'] ?? ''),
        'code' => (string) ($item['codigo'] ?? ''),
        'focus' => (string) ($item['foco'] ?? ''),
        'schedule' => (string) ($item['cronograma_nome'] ?? ''),
    ], $treinosUsuario);
    $exercises = array_map(static fn(array $item): array => [
        'id' => (string) ($item['idexercicio'] ?? ''),
        'name' => (string) ($item['nome'] ?? ''),
    ], $exerciciosBiblioteca);
    $equipment = array_map(static fn(array $item): array => [
        'id' => (string) ($item['idequipamento'] ?? ''),
        'name' => (string) ($item['nome'] ?? ''),
    ], $equipamentos);

    echo json_encode([
        'ok' => true,
        'models_html' => $modelsHtml,
        'workouts' => $workouts,
        'equipment' => $equipment,
        'exercises' => $exercises,
        'support_loaded' => $includeSupport,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if (!$e instanceof InvalidArgumentException) error_log('StrideBR activity editor details API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar os campos do registro.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
