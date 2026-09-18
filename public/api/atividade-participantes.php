<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_stream_service.php';
require_once dirname(__DIR__, 2) . '/src/function/notificacoes.php';
require_once dirname(__DIR__, 2) . '/src/function/shared_activity_service.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: private, no-store');
try {
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET'; $input=$method==='GET'?$_GET:(json_decode((string)file_get_contents('php://input'),true) ?: $_POST);
    $activityId=trim((string)($input['id']??$input['activity_id']??'')); if($activityId==='') throw new InvalidArgumentException(stridebr_t('activity.participants.invalid_activity'));
    if($method==='GET'){ stridebr_session_release(); echo json_encode(['ok'=>true,'data'=>sharedActivityParticipants($pdo,$idUsuario,$activityId)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
    $_POST['csrf_token'] = $input['csrf_token'] ?? ''; stridebr_verify_csrf(); $action=trim((string)($input['action']??''));
    if($action==='invite') $result=sharedActivityInvite($pdo,$idUsuario,$activityId,trim((string)($input['user_id']??'')));
    elseif($action==='accept'||$action==='decline') $result=sharedActivityRespond($pdo,$idUsuario,$activityId,$action==='accept'?'accepted':'declined');
    elseif($action==='remove') $result=['removed'=>sharedActivityRemove($pdo,$idUsuario,$activityId,trim((string)($input['user_id']??$idUsuario)))];
    else throw new InvalidArgumentException(stridebr_t('activity.participants.invalid_action'));
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) { if (!$e instanceof InvalidArgumentException) error_log('StrideBR participants API: ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()); http_response_code($e instanceof InvalidArgumentException?422:500); echo json_encode(['ok'=>false,'error'=>$e instanceof InvalidArgumentException?$e->getMessage():stridebr_t('activity.participants.update_failed')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
