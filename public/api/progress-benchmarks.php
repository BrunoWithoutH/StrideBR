<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmark_goals.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

stridebr_verify_csrf();
$returnTo = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), '/user/progresso.php');

try {
    $action = stridebr_lower(trim((string) ($_POST['action'] ?? 'create')));
    if ($action === 'delete') {
        $benchmarkId = trim((string) ($_POST['idbenchmark'] ?? ''));
        if ($benchmarkId === '' || !benchmarkDelete($pdo, $idUsuario, $benchmarkId)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.not_found'));
        benchmarkGoalSyncForUser($pdo, $idUsuario);
        stridebr_flash('success', stridebr_t('benchmarks.flash.deleted'));
        header('Location: ' . $returnTo, true, 303);
        exit;
    }

    $type = stridebr_lower(trim((string) ($_POST['benchmark_type'] ?? '')));
    $payload = [
        'tipo' => $type,
        'idmodalidade' => trim((string) ($_POST['idmodalidade'] ?? '')),
        'data_resultado' => trim((string) ($_POST['data_resultado'] ?? '')),
        'contexto' => trim((string) ($_POST['contexto'] ?? '')),
        'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
        'idcompeticao' => trim((string) ($_POST['idcompeticao'] ?? '')),
        'origem' => 'manual',
        'oficialidade' => 'nao_aplicavel',
    ];

    if ($type === 'one_rm') {
        $payload['valor_canonico'] = benchmarkParseDecimal($_POST['carga_kg'] ?? null);
        $payload['idexercicio'] = trim((string) ($_POST['idexercicio'] ?? ''));
        $payload['metodo'] = 'medido';
    } elseif ($type === 'ftp') {
        $payload['valor_canonico'] = benchmarkParseDecimal($_POST['ftp_w'] ?? null);
        $protocol = stridebr_lower(trim((string) ($_POST['protocolo'] ?? '')));
        if (!in_array($protocol, ['', 'ramp', '20min', 'informado', 'outro'], true)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_protocol'));
        $payload['protocolo'] = $protocol;
        $payload['metodo'] = in_array($protocol, ['ramp', '20min'], true) ? 'calculado' : 'informado';
    } elseif ($type === 'css') {
        $payload['valor_canonico'] = benchmarkParseClockSeconds($_POST['css_value'] ?? null);
        $payload['metodo'] = 'informado';
    } elseif ($type === 'distance_time') {
        $distanceKm = benchmarkParseDecimal($_POST['distance_km'] ?? null);
        $payload['distancia_m'] = $distanceKm !== null ? $distanceKm * 1000 : null;
        $payload['valor_canonico'] = benchmarkParseClockSeconds($_POST['time_value'] ?? null);
        $payload['metodo'] = 'medido';
        $payload = benchmarkApplyReportedOfficialInput($payload, isset($_POST['reported_official']));
        if (isset($_POST['reported_official']) && trim((string) ($payload['idcompeticao'] ?? '')) === '') {
            $existingEvidence = null;
            $postedBenchmarkId = trim((string) ($_POST['idbenchmark'] ?? ''));
            if ($postedBenchmarkId !== '') {
                $existingBenchmark = benchmarkGet($pdo, $idUsuario, $postedBenchmarkId);
                if (is_array($existingBenchmark)) $existingEvidence = benchmarkActivityEvidence($pdo, $idUsuario, $existingBenchmark['idregistro'] ?? null, $existingBenchmark['idunidade_atividade'] ?? null);
            }
            if (trim((string) ($existingEvidence['competition_id'] ?? '')) === '') throw new InvalidArgumentException(stridebr_t('competitions.error.official_competition_required'));
        }
    } else {
        throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_type'));
    }

    $benchmarkId = trim((string) ($_POST['idbenchmark'] ?? ''));
    if ($action === 'update') {
        if ($benchmarkId === '') throw new InvalidArgumentException(stridebr_t('benchmarks.error.not_found'));
        benchmarkUpdate($pdo, $idUsuario, $benchmarkId, $payload);
        benchmarkGoalSyncForUser($pdo, $idUsuario);
        stridebr_flash('success', stridebr_t('benchmarks.flash.updated'));
    } elseif ($action === 'create') {
        benchmarkCreate($pdo, $idUsuario, $payload);
        benchmarkGoalSyncForUser($pdo, $idUsuario);
        stridebr_flash('success', stridebr_t('benchmarks.flash.created'));
    } else {
        throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_action'));
    }

    header('Location: ' . $returnTo, true, 303);
    exit;
} catch (Throwable $error) {
    if (!$error instanceof InvalidArgumentException) error_log('StrideBR benchmarks: ' . $error->getMessage());
    stridebr_flash('danger', $error instanceof InvalidArgumentException ? $error->getMessage() : stridebr_t('benchmarks.error.save_failed'));
    header('Location: ' . $returnTo, true, 303);
    exit;
}
