<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

return function (PDO $pdo): void {
    putenv('STRIDEBR_INTEGRATIONS_SECRET=synthetic-only-credential-for-tests-12345678');
    putenv('STRAVA_CLIENT_ID=fixture-id'); putenv('STRAVA_CLIENT_SECRET=fixture-secret');
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/strava_activity.json'), true, 512, JSON_THROW_ON_ERROR);
    $calls = [];
    $GLOBALS['stridebr_integrations_http_mock'] = static function ($method, $url, $options) use (&$calls, &$fixture): array {
        $calls[] = $url;
        if (str_contains($url, '/activities/')) return ['json'=>$fixture, 'headers'=>[]];
        if (str_ends_with($url, '/athlete')) return ['json'=>['id'=>42]];
        throw new RuntimeException('Unexpected webhook request');
    };
    try {
        $user = alphaTestUser($pdo, 'strava_webhook');
        stridebr_integrations_save_token($pdo, $user, 'strava', ['access_token'=>'webhook-access','refresh_token'=>'webhook-refresh','expires_at'=>time()+7200,'athlete'=>['id'=>42]]);
        $event = stridebr_strava_webhook_event(['aspect_type'=>'create','event_time'=>1700000000,'object_id'=>(int)$fixture['id'],'object_type'=>'activity','owner_id'=>42,'subscription_id'=>123]);
        $event['signature_verified'] = true; stridebr_strava_webhook_enqueue($pdo, $event); stridebr_strava_webhook_enqueue($pdo, $event);
        AlphaTest::same(1, (int)$pdo->query("SELECT count(*) FROM integracao_webhook_eventos WHERE owner_external_id='42'")->fetchColumn(), 'queue fingerprint idempotency');
        $row = $pdo->query("SELECT * FROM integracao_webhook_eventos WHERE owner_external_id='42'")->fetch();
        AlphaTest::same('complete', stridebr_strava_webhook_process($pdo, $row), 'create processes');
        AlphaTest::assert(!array_filter($calls, static fn($url)=>str_contains($url,'/athlete/activities')), 'webhook never lists activities');
        AlphaTest::assert((bool)array_filter($calls, static fn($url)=>str_contains($url,'/activities/'.$fixture['id'])), 'webhook fetches exact activity');
        AlphaTest::same(1, (int)$pdo->query("SELECT count(*) FROM registros_atividade WHERE idusuario='{$user}' AND origem_provedor='strava'")->fetchColumn(), 'create persists once');
        $update = $event; $update['aspect_type']='update'; $update['event_time']++; $update['updates']=['title'=>'Changed','type'=>'Run','private'=>'true']; $update['fingerprint']=hash('sha256',json_encode(array_diff_key($update,['fingerprint'=>true,'signature_verified'=>true])));
        $update['updates']=json_encode($update['updates']); stridebr_strava_webhook_process($pdo,$update);
        $saved=$pdo->query("SELECT titulo,visibilidade FROM registros_atividade WHERE idusuario='{$user}' AND origem_provedor='strava'")->fetch();
        AlphaTest::same((string)$fixture['name'], $saved['titulo'], 'update uses fetched authoritative title'); AlphaTest::same('privado',$saved['visibilidade'],'private true protects local visibility');
        stridebr_integrations_provider_cooldown_set($pdo,'strava',time()+300); $before=count($calls);
        try { stridebr_strava_webhook_process($pdo,$row); } catch (StridebrIntegrationError $e) { AlphaTest::same('rate_limit',$e->internalCode,'provider cooldown defers webhook'); }
        AlphaTest::same($before,count($calls),'cooldown prevents another API call');
        $unknown=$event; $unknown['owner_external_id']='999999'; AlphaTest::same('ignored',stridebr_strava_webhook_process($pdo,$unknown),'unknown owner ignored');

        // A real 429 defers this item and establishes the shared provider cooldown.
        stridebr_integrations_provider_cooldown_set($pdo, 'strava', time() - 1);
        $rateUser = alphaTestUser($pdo, 'strava_webhook_rate');
        stridebr_integrations_save_token($pdo, $rateUser, 'strava', ['access_token'=>'rate-access','refresh_token'=>'rate-refresh','expires_at'=>time()+7200,'athlete'=>['id'=>43]]);
        $GLOBALS['stridebr_integrations_http_mock'] = static fn() => ['status'=>429, 'headers'=>['retry-after'=>['120']], 'json'=>[]];
        $rateEvent = stridebr_strava_webhook_event(['aspect_type'=>'create','event_time'=>1700000010,'object_id'=>(int)$fixture['id']+1,'object_type'=>'activity','owner_id'=>43,'subscription_id'=>123]);
        $rateEvent['signature_verified'] = true;
        try { stridebr_strava_webhook_process($pdo, $rateEvent); throw new RuntimeException('429 deveria adiar evento'); }
        catch (StridebrIntegrationError $error) { AlphaTest::same('rate_limit', $error->internalCode, '429 preserva retry do webhook'); }
        AlphaTest::assert(stridebr_integrations_provider_cooldown($pdo, 'strava') >= time() + 899, '429 estabelece cooldown compartilhado');

        // A 401 must stop future sync attempts for only the matching Strava connection.
        stridebr_integrations_provider_cooldown_set($pdo, 'strava', time() - 1);
        $reauthUser = alphaTestUser($pdo, 'strava_webhook_reauth');
        stridebr_integrations_save_token($pdo, $reauthUser, 'strava', ['access_token'=>'reauth-access','refresh_token'=>'reauth-refresh','expires_at'=>time()+7200,'athlete'=>['id'=>44]]);
        $GLOBALS['stridebr_integrations_http_mock'] = static fn() => ['status'=>401, 'headers'=>[], 'json'=>[]];
        $reauthEvent = stridebr_strava_webhook_event(['aspect_type'=>'create','event_time'=>1700000011,'object_id'=>(int)$fixture['id']+2,'object_type'=>'activity','owner_id'=>44,'subscription_id'=>123]);
        $reauthEvent['signature_verified'] = true;
        try { stridebr_strava_webhook_process($pdo, $reauthEvent); throw new RuntimeException('401 deveria pedir reautorização'); }
        catch (StridebrIntegrationError $error) { AlphaTest::same('reauthorize', $error->internalCode, '401 marca reauthorize'); }
        $reauthState = $pdo->prepare("SELECT status, metadados->'sync'->>'reauthorize' AS reauthorize FROM integracoes_usuario WHERE idusuario=:user AND provedor='strava'");
        $reauthState->execute([':user'=>$reauthUser]); $reauthState = $reauthState->fetch();
        AlphaTest::same('erro', $reauthState['status'], '401 marca somente a conexão em erro'); AlphaTest::same('true', $reauthState['reauthorize'], '401 persiste reauthorize');

        // A signed delete only touches the imported record of the resolved owner.
        $delete = $event; $delete['aspect_type'] = 'delete'; $delete['event_time']++; $delete['fingerprint'] = hash('sha256', 'signed-delete-' . $user);
        AlphaTest::same('complete', stridebr_strava_webhook_process($pdo, $delete), 'delete assinado processa');
        AlphaTest::same(1, (int)$pdo->query("SELECT count(*) FROM registros_atividade WHERE idusuario='{$user}' AND origem_provedor='strava' AND excluido_em IS NOT NULL")->fetchColumn(), 'delete não remove registro manual ou de outro provider');

        // A signed deauthorization affects only the owner resolved from usuario_externo_id.
        $deauthUser = alphaTestUser($pdo, 'strava_webhook_deauth');
        stridebr_integrations_save_token($pdo, $deauthUser, 'strava', ['access_token'=>'deauth-access','refresh_token'=>'deauth-refresh','expires_at'=>time()+7200,'athlete'=>['id'=>45]]);
        $deauth = stridebr_strava_webhook_event(['aspect_type'=>'update','event_time'=>1700000012,'object_id'=>45,'object_type'=>'athlete','owner_id'=>45,'subscription_id'=>123,'updates'=>['authorized'=>'false']]);
        $deauth['signature_verified'] = true; AlphaTest::same('complete', stridebr_strava_webhook_process($pdo, $deauth), 'deauth assinado processa');
        $deauthState = $pdo->prepare("SELECT status, access_token_enc FROM integracoes_usuario WHERE idusuario=:user AND provedor='strava'"); $deauthState->execute([':user'=>$deauthUser]); $deauthState=$deauthState->fetch();
        AlphaTest::same('revogado', $deauthState['status'], 'deauth revoga conexão resolvida'); AlphaTest::same(null, $deauthState['access_token_enc'], 'deauth remove token local');

        $revoke = null;
        $GLOBALS['stridebr_integrations_http_mock'] = static function ($method, $url, $options) use (&$revoke): array { $revoke=[$method,$url,$options]; return ['status'=>200,'json'=>[]]; };
        stridebr_integrations_disconnect($pdo,$user,'strava');
        AlphaTest::assert(is_array($revoke) && $revoke[0] === 'POST' && str_contains($revoke[1], '/oauth/revoke'), 'disconnect calls current revoke endpoint');
        AlphaTest::assert((bool)array_filter($revoke[2]['headers'], static fn($header)=>str_starts_with($header,'Authorization: Basic ')) && isset($revoke[2]['body']['token']), 'revoke uses Basic auth and form token');
    } finally { unset($GLOBALS['stridebr_integrations_http_mock']); }
};
