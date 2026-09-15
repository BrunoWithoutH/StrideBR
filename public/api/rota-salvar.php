<?php

declare(strict_types=1);

require_once dirname(__DIR__,2).'/src/includes/errors.php';
require_once dirname(__DIR__,2).'/src/includes/app.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
$idUsuario=stridebr_require_login();
require_once dirname(__DIR__,2).'/src/config/pg_config.php';
require_once dirname(__DIR__,2).'/src/function/routes.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método não permitido.']);exit;}
try{
    stridebr_verify_csrf();
    $route=routeSavedCreateFromActivity($pdo,$idUsuario,trim((string)($_POST['id']??'')),trim((string)($_POST['nome']??''))?:null);
    echo json_encode(['ok'=>true,'data'=>['id'=>(string)$route['idrota_salva'],'name'=>(string)$route['nome']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){error_log('Save route: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Não foi possível salvar a rota.'],JSON_UNESCAPED_UNICODE);}
