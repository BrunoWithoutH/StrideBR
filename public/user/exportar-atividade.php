<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_file_exchange.php';

try {
    $id = trim((string) ($_GET['id'] ?? ''));
    $format = strtolower(trim((string) ($_GET['format'] ?? 'gpx')));
    if ($id === '' || !in_array($format, ['gpx', 'tcx', 'json', 'original', 'fit'], true)) throw new InvalidArgumentException('Exportação inválida.');
    $data = atividadeArquivoExportData($pdo, $idUsuario, $id);
    $title = (string) ($data['record']['titulo'] ?: $data['record']['modalidade_nome']);
    $asciiTitle = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title) : $title;
    $slug = preg_replace('/[^a-z0-9_-]+/i', '-', $asciiTitle);
    $slug = trim(strtolower((string) $slug), '-') ?: 'atividade';

    if ($format === 'original' || $format === 'fit') {
        $import = $data['import'];
        if (!$import || $import['arquivo_original'] === null) throw new InvalidArgumentException('O arquivo original desta atividade não está armazenado.');
        if ($format === 'fit' && strtolower((string) $import['formato']) !== 'fit') throw new InvalidArgumentException('Esta atividade não possui um FIT original.');
        $extension = strtolower((string) $import['formato']);
        $mime = match ($extension) {'gpx', 'tcx' => 'application/xml', default => 'application/octet-stream'};
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $slug . '-original.' . $extension . '"');
        header('X-Content-Type-Options: nosniff');
        echo is_resource($import['arquivo_original']) ? stream_get_contents($import['arquivo_original']) : (string) $import['arquivo_original'];
        exit;
    }

    if ($format === 'gpx') {
        $content = atividadeArquivoExportGpx($data);
        if (!str_contains($content, '<trkpt ')) throw new InvalidArgumentException('Esta atividade não possui rota GPS para exportar em GPX.');
        $mime = 'application/gpx+xml';
        $extension = 'gpx';
    } elseif ($format === 'tcx') {
        $content = atividadeArquivoExportTcx($data);
        $mime = 'application/vnd.garmin.tcx+xml';
        $extension = 'tcx';
    } else {
        $content = json_encode([
            'schema' => 'stridebr.activity-export.v1',
            'exported_at' => gmdate(DATE_ATOM),
            'activity' => $data['record'],
            'route' => $data['route'],
            'streams' => $data['series'],
            'source' => $data['import'] ? [
                'format' => $data['import']['formato'],
                'file_name' => $data['import']['nome_arquivo'],
                'sha256' => $data['import']['sha256'],
                'device' => json_decode((string) $data['import']['dispositivo'], true),
            ] : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $mime = 'application/json';
        $extension = 'json';
    }

    header('Content-Type: ' . $mime . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.' . $extension . '"');
    header('X-Content-Type-Options: nosniff');
    echo $content;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    error_log('StrideBR activity export: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Não foi possível exportar esta atividade.';
}
