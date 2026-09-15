<?php

declare(strict_types=1);

function routeSavedList(PDO $pdo, string $userId, bool $includeArchived = false): array
{
    $sql = "SELECT r.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,
        COALESCE((SELECT COUNT(*) FROM rotas_salvas_atividades rsa JOIN registros_atividade ra ON ra.idregistro=rsa.idregistro WHERE rsa.idrota_salva=r.idrota_salva AND ra.excluido_em IS NULL),0) AS atividades_count,
        (SELECT MAX(ra.data_inicio) FROM rotas_salvas_atividades rsa JOIN registros_atividade ra ON ra.idregistro=rsa.idregistro WHERE rsa.idrota_salva=r.idrota_salva AND ra.excluido_em IS NULL) AS ultima_atividade
        FROM rotas_salvas r LEFT JOIN modalidades m ON m.idmodalidade=r.idmodalidade
        WHERE r.idusuario=:usuario";
    if (!$includeArchived) $sql .= ' AND r.arquivada=FALSE';
    $sql .= ' ORDER BY r.arquivada,r.data_ultima_utilizacao DESC NULLS LAST,r.data_atualizacao DESC,r.nome';
    $stmt=$pdo->prepare($sql);$stmt->execute([':usuario'=>$userId]);return $stmt->fetchAll();
}

function routeSavedGet(PDO $pdo, string $userId, string $routeId): ?array
{
    $stmt=$pdo->prepare("SELECT r.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,p.name AS pacer_nome,ra.titulo AS atividade_origem_titulo,ra.data_inicio AS atividade_origem_data
        FROM rotas_salvas r LEFT JOIN modalidades m ON m.idmodalidade=r.idmodalidade LEFT JOIN pacer_plans p ON p.idplan=r.idpacerplan LEFT JOIN registros_atividade ra ON ra.idregistro=r.idatividade_origem
        WHERE r.idrota_salva=:id AND r.idusuario=:usuario LIMIT 1");
    $stmt->execute([':id'=>$routeId,':usuario'=>$userId]);$row=$stmt->fetch();
    if(!is_array($row))return null;
    $history=$pdo->prepare("SELECT ra.idregistro,ra.titulo,ra.data_inicio,m.nome AS modalidade_nome,m.slug AS modalidade_slug,rota.distancia_metros,rota.ganho_elevacao_m
        FROM rotas_salvas_atividades rsa JOIN registros_atividade ra ON ra.idregistro=rsa.idregistro JOIN modalidades m ON m.idmodalidade=ra.idmodalidade LEFT JOIN rotas_atividade rota ON rota.idregistro=ra.idregistro
        WHERE rsa.idrota_salva=:id AND ra.idusuario=:usuario AND ra.excluido_em IS NULL ORDER BY ra.data_inicio DESC LIMIT 100");
    $history->execute([':id'=>$routeId,':usuario'=>$userId]);$row['atividades']=$history->fetchAll();
    return $row;
}

function routeSavedCreateFromActivity(PDO $pdo, string $userId, string $activityId, ?string $name = null): array
{
    $stmt=$pdo->prepare("SELECT ra.idregistro,ra.titulo,ra.idmodalidade,ra.data_inicio,m.nome AS modalidade_nome,r.coordenadas,r.distancia_metros,r.ganho_elevacao_m,r.perda_elevacao_m,r.elevacao_min_m,r.elevacao_max_m,r.perfil_elevacao
        FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade JOIN rotas_atividade r ON r.idregistro=ra.idregistro
        WHERE ra.idregistro=:atividade AND ra.idusuario=:usuario AND ra.excluido_em IS NULL LIMIT 1");
    $stmt->execute([':atividade'=>$activityId,':usuario'=>$userId]);$source=$stmt->fetch();
    if(!is_array($source))throw new InvalidArgumentException('Esta atividade não possui uma rota disponível.');
    $existing=$pdo->prepare('SELECT r.idrota_salva FROM rotas_salvas_atividades rsa JOIN rotas_salvas r ON r.idrota_salva=rsa.idrota_salva WHERE rsa.idregistro=:atividade AND r.idusuario=:usuario LIMIT 1');
    $existing->execute([':atividade'=>$activityId,':usuario'=>$userId]);$existingId=trim((string)$existing->fetchColumn());
    if($existingId!=='')return routeSavedGet($pdo,$userId,$existingId) ?? throw new RuntimeException('Não foi possível carregar a rota.');
    $resolvedName=trim((string)$name);
    if($resolvedName==='')$resolvedName=trim((string)($source['titulo']??''));
    if($resolvedName==='')$resolvedName='Rota ' . (new DateTimeImmutable((string)$source['data_inicio']))->format('d/m/Y');
    if(stridebr_length($resolvedName)>140)$resolvedName=mb_substr($resolvedName,0,140);
    $id=stridebr_generate_id();
    $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO rotas_salvas (idrota_salva,idusuario,nome,idmodalidade,idatividade_origem,coordenadas,distancia_m,ganho_elevacao_m,perda_elevacao_m,elevacao_min_m,elevacao_max_m,perfil_elevacao,privacidade,data_ultima_utilizacao)
            VALUES (:id,:usuario,:nome,:modalidade,:atividade,CAST(:coordenadas AS jsonb),:distancia,:ganho,:perda,:min,:max,CAST(:perfil AS jsonb),'privado',:ultima)");
        $insert->execute([':id'=>$id,':usuario'=>$userId,':nome'=>$resolvedName,':modalidade'=>$source['idmodalidade'],':atividade'=>$activityId,':coordenadas'=>is_string($source['coordenadas'])?$source['coordenadas']:json_encode($source['coordenadas'],JSON_UNESCAPED_SLASHES),':distancia'=>$source['distancia_metros'],':ganho'=>$source['ganho_elevacao_m'],':perda'=>$source['perda_elevacao_m'],':min'=>$source['elevacao_min_m'],':max'=>$source['elevacao_max_m'],':perfil'=>is_string($source['perfil_elevacao']??null)?$source['perfil_elevacao']:json_encode($source['perfil_elevacao']??null,JSON_UNESCAPED_SLASHES),':ultima'=>$source['data_inicio']]);
        $pdo->prepare("INSERT INTO rotas_salvas_atividades (idrota_salva,idregistro,origem) VALUES (:rota,:atividade,'source') ON CONFLICT (idregistro) DO NOTHING")->execute([':rota'=>$id,':atividade'=>$activityId]);
        if($owns)$pdo->commit();
    }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    return routeSavedGet($pdo,$userId,$id) ?? throw new RuntimeException('Não foi possível salvar a rota.');
}

function routeSavedUpdate(PDO $pdo, string $userId, string $routeId, array $input): array
{
    $current=routeSavedGet($pdo,$userId,$routeId);if($current===null)throw new InvalidArgumentException('Rota não encontrada.');
    $name=trim((string)($input['nome']??$current['nome']));if($name===''||stridebr_length($name)>140)throw new InvalidArgumentException('Informe um nome de rota com até 140 caracteres.');
    $privacy=trim((string)($input['privacidade']??$current['privacidade']));if(!in_array($privacy,['privado','nao_listado','publico'],true))$privacy='privado';
    $pacer=trim((string)($input['idpacerplan']??$current['idpacerplan']??''));
    if($pacer!==''){
        $check=$pdo->prepare('SELECT 1 FROM pacer_plans WHERE idplan=:id AND idusuario=:usuario AND status<>\'archived\' LIMIT 1');$check->execute([':id'=>$pacer,':usuario'=>$userId]);if(!$check->fetchColumn())throw new InvalidArgumentException('Pacer Plan inválido.');
    }
    $stmt=$pdo->prepare('UPDATE rotas_salvas SET nome=:nome,privacidade=:privacidade,idpacerplan=:pacer,data_atualizacao=NOW() WHERE idrota_salva=:id AND idusuario=:usuario');
    $stmt->execute([':nome'=>$name,':privacidade'=>$privacy,':pacer'=>$pacer!==''?$pacer:null,':id'=>$routeId,':usuario'=>$userId]);
    return routeSavedGet($pdo,$userId,$routeId) ?? throw new RuntimeException('Não foi possível atualizar a rota.');
}

function routeSavedArchive(PDO $pdo, string $userId, string $routeId, bool $archived = true): bool
{
    $stmt=$pdo->prepare('UPDATE rotas_salvas SET arquivada=:arquivada,data_atualizacao=NOW() WHERE idrota_salva=:id AND idusuario=:usuario');
    $stmt->bindValue(':arquivada',$archived,PDO::PARAM_BOOL);$stmt->bindValue(':id',$routeId);$stmt->bindValue(':usuario',$userId);$stmt->execute();return $stmt->rowCount()>0;
}

function routeSavedValidateForWorkout(PDO $pdo, string $userId, ?string $routeId, ?string $modalityId = null): ?string
{
    $routeId=trim((string)$routeId);if($routeId==='')return null;
    $stmt=$pdo->prepare('SELECT idrota_salva,idmodalidade FROM rotas_salvas WHERE idrota_salva=:id AND idusuario=:usuario AND arquivada=FALSE LIMIT 1');$stmt->execute([':id'=>$routeId,':usuario'=>$userId]);$row=$stmt->fetch();
    if(!is_array($row))throw new InvalidArgumentException('Rota indisponível.');
    $modalityId=trim((string)$modalityId);if($modalityId!==''&&trim((string)($row['idmodalidade']??''))!==''&&(string)$row['idmodalidade']!==$modalityId)throw new InvalidArgumentException('A rota não é compatível com a modalidade do treino.');
    return $routeId;
}
function routeSavedLinkActivityFromWorkout(PDO $pdo, string $userId, string $activityId, ?string $workoutId): ?string
{
    $workoutId = trim((string) $workoutId);
    if ($workoutId === '' || !stridebr_db_table_exists($pdo, 'rotas_salvas_atividades')) return null;
    $stmt = $pdo->prepare("SELECT tc.idrota_salva FROM treinos_cronograma tc JOIN cronogramas c ON c.idcronograma=tc.idcronograma JOIN rotas_salvas r ON r.idrota_salva=tc.idrota_salva WHERE tc.idtreino=:treino AND c.idusuario=:usuario AND r.idusuario=:usuario AND r.arquivada=FALSE LIMIT 1");
    $stmt->execute([':treino'=>$workoutId,':usuario'=>$userId]);
    $routeId = trim((string) $stmt->fetchColumn());
    if ($routeId === '') return null;
    $link = $pdo->prepare("INSERT INTO rotas_salvas_atividades (idrota_salva,idregistro,origem) VALUES (:rota,:atividade,'workout') ON CONFLICT (idregistro) DO UPDATE SET idrota_salva=EXCLUDED.idrota_salva,origem='workout'");
    $link->execute([':rota'=>$routeId,':atividade'=>$activityId]);
    $pdo->prepare('UPDATE rotas_salvas SET data_ultima_utilizacao=GREATEST(COALESCE(data_ultima_utilizacao,\'epoch\'::timestamptz),NOW()),data_atualizacao=NOW() WHERE idrota_salva=:rota AND idusuario=:usuario')->execute([':rota'=>$routeId,':usuario'=>$userId]);
    return $routeId;
}

