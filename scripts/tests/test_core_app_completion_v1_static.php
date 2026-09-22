<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$router = $read('public/api/v1/index.php');
$sports = $read('src/function/api_sports.php');
$progress = $read('src/function/progress_service.php');
$progressApi = $read('src/function/api_progress.php');
$mobile = $read('src/function/api_mobile_completion.php');
$api = $read('src/function/api_v1.php');
$people = $read('src/function/people_service.php');
$peopleApi = $read('src/function/api_people.php');
$trainer = $read('src/function/treinador_vinculos.php');
$preview = $read('src/function/workout_preview_service.php');
$previewHttp = $read('public/api/cronograma-treino-preview.php');
$previewJs = $read('public/assets/js/cronogramas.js');
$openapi = $read('docs/api/openapi.yaml');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($router, "route === 'sports'"), 'Router precisa expor GET /sports.');
$assert(str_contains($sports, 'ativo = TRUE'), 'Catálogo deve excluir modalidades inativas.');
$assert(str_contains($sports, 'idusuario IS NULL OR idusuario = :user'), 'Catálogo custom precisa ser owner-scoped.');
$assert(str_contains($sports, "'family'"), 'Catálogo precisa expor family.');
$assert(str_contains($sports, "'route_capable'"), 'Catálogo precisa expor route_capable.');
$assert(str_contains($progress, 'ativo = TRUE'), 'Progress deve resolver apenas modalidade ativa.');
$assert(str_contains($progress, 'idusuario IS NULL OR idusuario = :user'), 'Progress custom precisa ser owner-scoped.');
$assert(str_contains($progress, "progress_sport_id"), 'Adherence precisa carregar ID canônico da modalidade.');
$assert(str_contains($progressApi, 'progressResolveSport($pdo, $userId'), 'Progress API precisa usar assinatura owner-scoped.');
$assert(str_contains($mobile, "'can_edit_metrics' => \$structural"), 'Activity manual editável precisa expor capability de métricas.');
$assert(str_contains($mobile, 'stridebr_api_mobile_activity_update_distance'), 'PATCH Activity precisa editar distância manual pelo domínio canônico.');
$assert(str_contains($mobile, "array_key_exists('strength_exercises'"), 'PATCH Activity manual strength precisa aceitar séries realizadas.');
$assert(str_contains($mobile, "if_version é obrigatório"), 'PATCH Activity precisa exigir if_version para evitar last-write-wins.');
$assert(str_contains($openapi, 'required: [if_version]'), 'OpenAPI precisa marcar if_version como obrigatório no PATCH Activity.');
$assert(str_contains($mobile, "'actual_repetitions'"), 'Execution summary precisa expor actual_repetitions.');
$sessionService=file_get_contents($root.'/src/function/workout_session_service.php');
$assert(!str_contains($sessionService, 'sessaoPreencherDefaults('), 'Marcar set/exercício/sessão concluído não pode copiar targets para actuals.');
$assert(str_contains($mobile, "'actual_load'"), 'Execution summary precisa expor actual_load.');
$assert(str_contains($mobile, "'activity_id'"), 'Execution summary precisa expor activity_id.');
$assert(str_contains($mobile, "'execution_mode'=>'quick_register'"), 'Execution summary precisa distinguir Quick Register.');
$assert(str_contains($api, "'strength_exercises'"), 'Activity Detail precisa expor strength_exercises.');
$assert(str_contains($router, "route === 'people/search'"), 'Router precisa expor busca de Pessoas.');
$assert(str_contains($router, "route === 'people/friends'"), 'Router precisa expor Amigos.');
$assert(str_contains($router, "route === 'people/coaching'"), 'Router precisa expor Treinador/Atleta.');
$assert(str_contains($people, "'display_name'"), 'DTO de pessoa precisa expor display_name.');
$assert(!str_contains($people, "'email' =>"), 'DTO de pessoa não pode expor email.');
$assert(!str_contains($people, "'phone' =>"), 'DTO de pessoa não pode expor telefone.');
$assert(str_contains($people, 'can_edit_permissions'), 'Coaching precisa expor capability de permissões.');
$assert(str_contains($people, "idatleta'] !== \$viewerId"), 'Somente atleta pode alterar permissões.');
$assert(str_contains($peopleApi, "friends.enabled") && str_contains($peopleApi, "trainer.enabled"), 'People API precisa respeitar feature flags.');
$assert(str_contains($trainer, 'function treinadorCriarConvite'), 'API e Web precisam compartilhar domínio de vínculos.');
$assert(str_contains($preview, "'preview_mode' => 'performed'"), 'Preview precisa suportar performed.');
$assert(str_contains($preview, "? 'missed' : 'planned'"), 'Preview precisa distinguir missed de planned.');
$assert(str_contains($preview, 'ra.idusuario=:user AND ra.idtreino_cronograma=:workout'), 'Preview performed precisa validar owner e workout.');
$assert(str_contains($previewHttp, "'preview_mode' => \$preview['preview_mode']"), 'Endpoint de preview precisa expor preview_mode.');
$assert(str_contains($previewJs, 'data.preview_mode') && str_contains($previewJs, 'preview-performed-sets'), 'UI precisa renderizar preview contextual performed.');
$assert(str_contains($previewJs, "params.set('activity_id', previewActivityId)"), 'UI precisa enviar activity_id contextual para validação.');
$assert(str_contains($openapi, '/sports:') && str_contains($openapi, '/people/search:'), 'OpenAPI precisa documentar Sports e People API.');

foreach (['emailusuario','foneusuario','datanascimentousuario'] as $private) {
    $assert(!preg_match("/'(?:email|phone|birth_date)'\\s*=>.*{$private}/", $people), 'People DTO não pode mapear dado privado.');
}

echo "Core App Completion V1 static: {$assertions} assertions\n";
