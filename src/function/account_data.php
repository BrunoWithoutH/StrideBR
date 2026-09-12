<?php

declare(strict_types=1);

function accountTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) return false;
    try {
        $stmt = $pdo->prepare("SELECT to_regclass('stridebr.' || :t) IS NOT NULL");
        $stmt->execute([':t' => $table]);
        return stridebr_db_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return false;
    }
}

function accountFetch(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function accountExportData(PDO $pdo, string $userId): array
{
    $profileStmt = $pdo->prepare(
        'SELECT idusuario,nomeusuario,nome_exibicao,username,emailusuario,foneusuario,datanascimentousuario,dataregistrousuario,statususuario,fotousuario,generousuario,pronomesusuario,biousuario,pesousuario,alturausuario,objetivousuario,visibilidadeperfil,verificado,email_verificado_em,ultimologin,preferenciasusuario,descobrivel,modo_treinador,onboarding_concluido,termos_versao,privacidade_versao,termos_aceitos_em FROM usuarios WHERE idusuario=:usuario LIMIT 1'
    );
    $profileStmt->execute([':usuario' => $userId]);
    $profile = $profileStmt->fetch();
    if (!$profile) throw new RuntimeException('Conta não encontrada.');

    $export = [
        'schema' => 'stridebr.account-export.v1',
        'exportado_em' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'aviso' => 'Este arquivo contém dados da sua conta. Guarde-o em local seguro.',
        'perfil' => $profile,
    ];

    $simple = [
        'modalidades_usuario' => ['SELECT mu.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM modalidades_usuario mu LEFT JOIN modalidades m ON m.idmodalidade=mu.idmodalidade WHERE mu.idusuario=:usuario ORDER BY m.nome', true],
        'equipamentos' => ['SELECT * FROM equipamentos_usuario WHERE idusuario=:usuario ORDER BY data_criacao', true],
        'metas' => ['SELECT * FROM metas_usuario WHERE idusuario=:usuario ORDER BY data_criacao', true],
        'benchmarks' => ['SELECT b.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,e.nome AS exercicio_nome FROM benchmarks_usuario b JOIN modalidades m ON m.idmodalidade=b.idmodalidade LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio WHERE b.idusuario=:usuario ORDER BY b.data_resultado,b.data_criacao', true],
        'competicoes' => ['SELECT c.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,e.titulo AS evento_titulo,e.slug AS evento_slug FROM competicoes_usuario c LEFT JOIN modalidades m ON m.idmodalidade=c.idmodalidade_principal LEFT JOIN eventos_esportivos e ON e.idevento=c.idevento WHERE c.idusuario=:usuario ORDER BY c.data_inicio,c.data_criacao', true],
        'amizades_enviadas' => ['SELECT * FROM amizades WHERE idusuario_solicitante=:usuario ORDER BY data_criacao', true],
        'amizades_recebidas' => ['SELECT * FROM amizades WHERE idusuario_destino=:usuario ORDER BY data_criacao', true],
        'compartilhamentos_enviados' => ['SELECT * FROM cronograma_compartilhamentos WHERE idusuario_origem=:usuario ORDER BY data_criacao', true],
        'compartilhamentos_recebidos' => ['SELECT * FROM cronograma_compartilhamentos WHERE idusuario_destino=:usuario ORDER BY data_criacao', true],
        'feedbacks' => ['SELECT idfeedback,tipo,titulo,mensagem,pagina AS contexto,status,prioridade,notas_admin,anonimo,criado_em,atualizado_em FROM feedbacks WHERE idusuario=:usuario ORDER BY criado_em', true],
        'eventos_salvos' => ['SELECT es.*,e.titulo,e.slug,e.data_inicio FROM eventos_salvos es JOIN eventos_esportivos e ON e.idevento=es.idevento WHERE es.idusuario=:usuario ORDER BY es.data_criacao', true],
        'vinculos_como_treinador' => ['SELECT * FROM vinculos_treinador_atleta WHERE idtreinador=:usuario ORDER BY data_criacao', true],
        'vinculos_como_atleta' => ['SELECT * FROM vinculos_treinador_atleta WHERE idatleta=:usuario ORDER BY data_criacao', true],
        'treinos_agendados' => ['SELECT * FROM treinos_agendados WHERE idatleta=:usuario ORDER BY data_treino', true],
    ];
    foreach ($simple as $key => [$sql]) {
        $tableName = match ($key) {
            'equipamentos' => 'equipamentos_usuario',
            'metas' => 'metas_usuario',
            'benchmarks' => 'benchmarks_usuario',
            'competicoes' => 'competicoes_usuario',
            'amizades_enviadas', 'amizades_recebidas' => 'amizades',
            'compartilhamentos_enviados', 'compartilhamentos_recebidos' => 'cronograma_compartilhamentos',
            'feedbacks' => 'feedbacks',
            'eventos_salvos' => 'eventos_salvos',
            'vinculos_como_treinador', 'vinculos_como_atleta' => 'vinculos_treinador_atleta',
            'treinos_agendados' => 'treinos_agendados',
            default => $key,
        };
        $export[$key] = accountTableExists($pdo, $tableName) ? accountFetch($pdo, $sql, [':usuario' => $userId]) : [];
    }

    if (accountTableExists($pdo, 'metas_conclusoes')) {
        $export['metas_conclusoes'] = accountFetch($pdo, 'SELECT c.* FROM metas_conclusoes c JOIN metas_usuario m ON m.idmeta=c.idmeta WHERE m.idusuario=:usuario ORDER BY c.atingida_em', [':usuario' => $userId]);
    } else {
        $export['metas_conclusoes'] = [];
    }

    $schedules = accountFetch($pdo, 'SELECT * FROM cronogramas WHERE idusuario=:usuario ORDER BY data_criacao', [':usuario' => $userId]);
    $export['cronogramas'] = [];
    foreach ($schedules as $schedule) {
        $scheduleId = (string) $schedule['idcronograma'];
        $item = ['cronograma' => $schedule];
        $item['treinos'] = accountFetch($pdo, 'SELECT * FROM treinos_cronograma WHERE idcronograma=:id ORDER BY dia_semana,hora_inicio,ordem', [':id' => $scheduleId]);
        foreach ($item['treinos'] as &$workout) {
            $workoutId = (string) $workout['idtreino'];
            $workout['exercicios'] = accountFetch($pdo, 'SELECT * FROM treinos_exercicios WHERE idtreino=:id ORDER BY ordem', [':id' => $workoutId]);
            if (accountTableExists($pdo, 'campos_treino_exercicio')) $workout['campos_personalizados'] = accountFetch($pdo, 'SELECT * FROM campos_treino_exercicio WHERE idtreino=:id ORDER BY ordem', [':id' => $workoutId]);
        }
        unset($workout);
        $export['cronogramas'][] = $item;
    }

    $activities = accountFetch($pdo, 'SELECT ra.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM registros_atividade ra LEFT JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idusuario=:usuario ORDER BY ra.data_inicio', [':usuario' => $userId]);
    $export['atividades'] = [];
    foreach ($activities as $activity) {
        $activityId = (string) $activity['idregistro'];
        $item = ['atividade' => $activity];
        $item['valores'] = accountFetch($pdo, 'SELECT va.*,cm.nome AS campo_nome,cm.slug AS campo_slug FROM valores_atividade va LEFT JOIN campos_modelo cm ON cm.idcampo=va.idcampo WHERE va.idregistro=:id ORDER BY va.idvalor', [':id' => $activityId]);
        if (accountTableExists($pdo, 'unidades_atividade')) {
            $item['unidades'] = accountFetch($pdo, 'SELECT * FROM unidades_atividade WHERE idregistro=:id ORDER BY ordem,idunidade_atividade', [':id' => $activityId]);
            if (accountTableExists($pdo, 'rotas_unidades_atividade')) {
                foreach ($item['unidades'] as &$unit) {
                    $unit['rota'] = accountFetch($pdo, 'SELECT * FROM rotas_unidades_atividade WHERE idunidade_atividade=:id', [':id' => (string) $unit['idunidade_atividade']]);
                }
                unset($unit);
            }
        }
        if (accountTableExists($pdo, 'rotas_atividade')) $item['rota'] = accountFetch($pdo, 'SELECT * FROM rotas_atividade WHERE idregistro=:id', [':id' => $activityId]);
        if (accountTableExists($pdo, 'registros_atividade_equipamentos')) $item['equipamentos'] = accountFetch($pdo, 'SELECT * FROM registros_atividade_equipamentos WHERE idregistro=:id', [':id' => $activityId]);
        $export['atividades'][] = $item;
    }

    if (accountTableExists($pdo, 'sessoes_treino')) {
        $sessions = accountFetch($pdo, 'SELECT * FROM sessoes_treino WHERE idusuario=:usuario ORDER BY data_inicio', [':usuario' => $userId]);
        foreach ($sessions as &$session) {
            if (accountTableExists($pdo, 'sessoes_treino_exercicios')) {
                $session['exercicios'] = accountFetch($pdo, 'SELECT * FROM sessoes_treino_exercicios WHERE idsessao=:id ORDER BY ordem', [':id' => $session['idsessao']]);
                if (accountTableExists($pdo, 'sessoes_treino_series')) {
                    foreach ($session['exercicios'] as &$exercise) {
                        $exercise['series'] = accountFetch($pdo, 'SELECT * FROM sessoes_treino_series WHERE idsessao_exercicio=:id ORDER BY numero', [':id' => $exercise['idsessao_exercicio']]);
                    }
                    unset($exercise);
                }
            }
        }
        unset($session);
        $export['sessoes_treino'] = $sessions;
    } else {
        $export['sessoes_treino'] = [];
    }

    return $export;
}
