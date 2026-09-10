<?php

declare(strict_types=1);

ini_set('display_errors', '0');
require_once dirname(__DIR__, 3) . '/src/includes/environment.php';
require_once dirname(__DIR__, 3) . '/src/function/api_v1.php';

try {
    require dirname(__DIR__, 3) . '/src/config/pg_config.php';
    $path = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''), '/');
    $path = preg_replace('#^api/v1/?#', '', $path) ?? '';
    $parts = array_values(array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));
    $route = implode('/', $parts);

    if ($route === 'health' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        stridebr_api_response(200, ['data'=>['status'=>'ok', 'api_version'=>'v1']]);
    }
    if ($route === 'meta' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        stridebr_api_response(200, ['data'=>['api_version'=>'v1', 'server_time'=>(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)]]);
    }
    if ($route === 'auth/login') {
        stridebr_api_require_method('POST');
        stridebr_api_response(200, ['data'=>stridebr_api_login($pdo, stridebr_api_json_input())]);
    }
    if ($route === 'auth/refresh') {
        stridebr_api_require_method('POST');
        stridebr_api_response(200, ['data'=>stridebr_api_refresh($pdo, stridebr_api_json_input())]);
    }
    if ($route === 'auth/logout') {
        stridebr_api_require_method('POST');
        $user = stridebr_api_user($pdo);
        $pdo->prepare('UPDATE api_sessoes SET revogado_em = COALESCE(revogado_em, NOW()) WHERE idsessao = :id')->execute([':id'=>$user['idsessao']]);
        stridebr_api_response(204);
    }
    if ($route === 'me') {
        stridebr_api_require_method('GET');
        stridebr_api_response(200, ['data'=>stridebr_api_user_payload(stridebr_api_user($pdo))]);
    }
    if ($route === 'activities') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
        $where = ['ra.idusuario = :user', 'ra.excluido_em IS NULL']; $params = [':user'=>$user['idusuario']];
        $sport = trim((string) ($_GET['sport'] ?? ''));
        if ($sport !== '') { $where[] = 'm.slug = :sport'; $params[':sport'] = $sport; }
        $from = trim((string) ($_GET['from'] ?? '')); if ($from !== '') { try { $from = (new DateTimeImmutable($from))->format(DateTimeInterface::ATOM); } catch (Throwable) { stridebr_api_error(422, 'validation_error', 'Parâmetro from inválido.', ['from'=>'Use data ISO 8601.']); } $where[] = 'ra.data_inicio >= :from'; $params[':from'] = $from; }
        $to = trim((string) ($_GET['to'] ?? '')); if ($to !== '') { try { $to = (new DateTimeImmutable($to))->format(DateTimeInterface::ATOM); } catch (Throwable) { stridebr_api_error(422, 'validation_error', 'Parâmetro to inválido.', ['to'=>'Use data ISO 8601.']); } $where[] = 'ra.data_inicio < :to'; $params[':to'] = $to; }
        $q = trim((string) ($_GET['q'] ?? '')); if ($q !== '') { $where[] = 'ra.titulo ILIKE :q'; $params[':q'] = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%'; }
        $condition = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade = ra.idmodalidade WHERE $condition"); $count->execute($params); $total=(int)$count->fetchColumn();
        $stmt=$pdo->prepare("SELECT ra.idregistro, ra.idmodalidade, ra.titulo, ra.data_inicio, ra.data_fim, ra.status, ra.visibilidade, ra.origem, ra.esforco_percebido, m.nome AS modalidade_nome, m.slug AS modalidade_slug FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE $condition ORDER BY ra.data_inicio DESC, ra.idregistro DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key=>$value) $stmt->bindValue($key, $value); $stmt->bindValue(':limit',$limit,PDO::PARAM_INT); $stmt->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT); $stmt->execute();
        stridebr_api_response(200, ['data'=>array_map('stridebr_api_activity_summary',$stmt->fetchAll()), 'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'total_pages'=>(int)ceil($total/$limit)]]);
    }
    if (count($parts) === 2 && $parts[0] === 'activities') {
        stridebr_api_require_method('GET');
        $user = stridebr_api_user($pdo);
        $detail = stridebr_api_activity_detail($pdo, $parts[1], (string)$user['idusuario']);
        if ($detail === []) stridebr_api_error(404, 'not_found', 'Atividade não encontrada.');
        stridebr_api_response(200, ['data'=>$detail]);
    }
    stridebr_api_error(404, 'not_found', 'Endpoint não encontrado.');
} catch (Throwable $e) {
    error_log('StrideBR API v1 failure: ' . get_class($e));
    stridebr_api_error(500, 'internal_error', 'Não foi possível concluir a requisição.');
}
