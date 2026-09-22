<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/atividade_presenter.php';
require_once __DIR__ . '/cronograma.php';

require_once __DIR__ . '/treinador_vinculos.php';

function treinadorDataValida(string $data, bool $permitirPassado = false): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    if (!$date || $date->format('Y-m-d') !== $data) {
        return false;
    }
    $today = new DateTimeImmutable('today');
    $min = $permitirPassado ? $today->modify('-31 days') : $today;
    $max = $today->modify('+730 days');
    return $date >= $min && $date <= $max;
}

function treinadorHoraValida(string $hora): bool
{
    if ($hora === '') {
        return true;
    }
    $time = DateTimeImmutable::createFromFormat('!H:i', $hora);
    return $time !== false && $time->format('H:i') === $hora;
}

function treinadorCriarPrescricao(PDO $pdo, string $idTreinador, string $idAtleta, array $dados, array $exercicios): string
{
    $user = treinadorUsuario($pdo, $idTreinador);
    if (!stridebr_db_bool($user['modo_treinador'] ?? false)) {
        throw new RuntimeException(stridebr_t('planning.message.enable_prescribe'));
    }
    $vinculo = treinadorVinculoAceito($pdo, $idTreinador, $idAtleta);
    if ($vinculo === [] || !stridebr_db_bool($vinculo['pode_prescrever'] ?? false)) {
        throw new RuntimeException(stridebr_t('planning.message.prescribe_forbidden'));
    }

    $titulo = trim((string) ($dados['titulo'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $data = trim((string) ($dados['data_treino'] ?? ''));
    $hora = trim((string) ($dados['hora_inicio'] ?? ''));
    $duracaoRaw = trim((string) ($dados['duracao_prevista_min'] ?? ''));
    $status = (string) ($dados['status'] ?? 'rascunho');

    if ($titulo === '' || stridebr_length($titulo) > 120) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_title'));
    }
    if (stridebr_length($descricao) > 5000) {
        throw new InvalidArgumentException(stridebr_t('planning.message.description_long'));
    }
    if (!treinadorDataValida($data)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_date'));
    }
    if (!treinadorHoraValida($hora)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    }
    $duracao = null;
    if ($duracaoRaw !== '') {
        if (filter_var($duracaoRaw, FILTER_VALIDATE_INT) === false || (int) $duracaoRaw < 1 || (int) $duracaoRaw > 1440) {
            throw new InvalidArgumentException(stridebr_t('planning.message.invalid_duration'));
        }
        $duracao = (int) $duracaoRaw;
    }
    if (!in_array($status, ['rascunho', 'publicado'], true)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_status'));
    }
    $idModalidade = cronogramaValidarModalidadeTreino($pdo, $idTreinador, $dados['idmodalidade'] ?? null);
    $distanciaRaw = str_replace(',', '.', trim((string) ($dados['distancia_prevista_m'] ?? '')));
    $distanciaPrevista = null;
    if ($distanciaRaw !== '') {
        if (!is_numeric($distanciaRaw) || (float) $distanciaRaw < 0 || (float) $distanciaRaw > 100000000) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_distance'));
        $distanciaPrevista = round((float) $distanciaRaw, 3);
    }

    $catalog = stridebr_exercise_catalog_for_user($pdo, $idTreinador);
    $rows = [];
    foreach (array_slice($exercicios, 0, 100) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $resolved = cronogramaResolverExercicioBiblioteca($pdo, $catalog, (string) ($row['idexercicio'] ?? ''), (string) ($row['nome'] ?? ''));
        $nome = $resolved['nome'];
        if ($nome === '') {
            continue;
        }
        if (stridebr_length($nome) > 120) {
            throw new InvalidArgumentException(stridebr_t('planning.message.exercise_long'));
        }
        $seriesRaw = trim((string) ($row['series'] ?? ''));
        $series = null;
        if ($seriesRaw !== '') {
            if (filter_var($seriesRaw, FILTER_VALIDATE_INT) === false || (int) $seriesRaw < 1 || (int) $seriesRaw > 99) {
                throw new InvalidArgumentException(stridebr_t('planning.message.invalid_sets'));
            }
            $series = (int) $seriesRaw;
        }
        $fields = [];
        foreach (['repeticoes', 'carga', 'duracao', 'distancia', 'descanso'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if (stridebr_length($value) > 40) {
                throw new InvalidArgumentException(stridebr_t('planning.message.field_long'));
            }
            $fields[$field] = $value !== '' ? $value : null;
        }
        $observacoes = trim((string) ($row['observacoes'] ?? ''));
        if (stridebr_length($observacoes) > 1000) {
            throw new InvalidArgumentException(stridebr_t('planning.message.notes_long'));
        }
        $rows[] = [
            'idexercicio' => $resolved['idexercicio'] !== '' ? $resolved['idexercicio'] : null,
            'nome' => $nome,
            'series' => $series,
            'repeticoes' => $fields['repeticoes'],
            'carga' => $fields['carga'],
            'duracao' => $fields['duracao'],
            'distancia' => $fields['distancia'],
            'descanso' => $fields['descanso'],
            'observacoes' => $observacoes !== '' ? $observacoes : null,
        ];
    }

    $id = stridebr_generate_id();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO treinos_agendados (idagendamento, idatleta, idcriador, idvinculo, idmodalidade, data_treino, hora_inicio, duracao_prevista_min, distancia_prevista_m, titulo, descricao, origem, status, publicado_em) VALUES (:id, :atleta, :criador, :vinculo, :modalidade, :data, :hora, :duracao, :distancia, :titulo, :descricao, 'treinador', :status, CASE WHEN :status_publicado = 'publicado' THEN NOW() ELSE NULL END)");
        $stmt->execute([
            ':id' => $id,
            ':atleta' => $idAtleta,
            ':criador' => $idTreinador,
            ':vinculo' => $vinculo['idvinculo'],
            ':modalidade' => $idModalidade,
            ':data' => $data,
            ':hora' => $hora !== '' ? $hora : null,
            ':duracao' => $duracao,
            ':distancia' => $distanciaPrevista,
            ':titulo' => $titulo,
            ':descricao' => $descricao !== '' ? $descricao : null,
            ':status' => $status,
            ':status_publicado' => $status,
        ]);
        $insert = $pdo->prepare('INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio, idagendamento, idexercicio, nome_snapshot, series, repeticoes, carga, duracao, distancia, descanso, observacoes, ordem) VALUES (:id, :agendamento, :exercicio, :nome, :series, :repeticoes, :carga, :duracao, :distancia, :descanso, :observacoes, :ordem)');
        foreach ($rows as $index => $row) {
            $insert->execute([
                ':id' => stridebr_generate_id(),
                ':agendamento' => $id,
                ':exercicio' => $row['idexercicio'],
                ':nome' => $row['nome'],
                ':series' => $row['series'],
                ':repeticoes' => $row['repeticoes'],
                ':carga' => $row['carga'],
                ':duracao' => $row['duracao'],
                ':distancia' => $row['distancia'],
                ':descanso' => $row['descanso'],
                ':observacoes' => $row['observacoes'],
                ':ordem' => $index + 1,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $id;
}

function treinadorPublicarPrescricao(PDO $pdo, string $idTreinador, string $idAgendamento): void
{
    $stmt = $pdo->prepare("UPDATE treinos_agendados ta
                             SET status = 'publicado', publicado_em = COALESCE(publicado_em, NOW()), data_atualizacao = NOW()
                           WHERE ta.idagendamento = :id
                             AND ta.idcriador = :criador
                             AND ta.origem = 'treinador'
                             AND ta.status = 'rascunho'
                             AND EXISTS (
                                 SELECT 1
                                   FROM vinculos_treinador_atleta v
                                   JOIN usuarios u ON u.idusuario = v.idtreinador
                                  WHERE v.idvinculo = ta.idvinculo
                                    AND v.idtreinador = :vinculo_treinador
                                    AND v.idatleta = ta.idatleta
                                    AND v.status = 'aceito'
                                    AND v.pode_prescrever = TRUE
                                    AND u.modo_treinador = TRUE
                                    AND u.statususuario = 'Ativo'
                             )");
    $stmt->execute([
        ':id' => $idAgendamento,
        ':criador' => $idTreinador,
        ':vinculo_treinador' => $idTreinador,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(stridebr_t('planning.message.publish_forbidden'));
    }
}

function treinadorCancelarPrescricao(PDO $pdo, string $idAtual, string $idAgendamento): void
{
    $stmt = $pdo->prepare("UPDATE treinos_agendados SET status = 'cancelado', data_atualizacao = NOW() WHERE idagendamento = :id AND (idcriador = :atual_criador OR idatleta = :atual_atleta) AND status IN ('rascunho', 'publicado')");
    $stmt->execute([':id' => $idAgendamento, ':atual_criador' => $idAtual, ':atual_atleta' => $idAtual]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(stridebr_t('planning.message.cancel_forbidden'));
    }
}

function treinadorPrescricao(PDO $pdo, string $idTreinador, string $idAgendamento): array
{
    $stmt = $pdo->prepare("SELECT ta.* FROM treinos_agendados ta WHERE ta.idagendamento=:id AND ta.idcriador=:treinador AND ta.origem='treinador' LIMIT 1");
    $stmt->execute([':id' => $idAgendamento, ':treinador' => $idTreinador]);
    $row = $stmt->fetch() ?: [];
    if ($row === []) return [];
    $exerciseStmt = $pdo->prepare('SELECT idexercicio, nome_snapshot, series, repeticoes, carga, duracao, distancia, descanso, observacoes FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');
    $exerciseStmt->execute([':id' => $idAgendamento]);
    $row['exercicios'] = $exerciseStmt->fetchAll();
    return $row;
}

function treinadorEditarPrescricao(PDO $pdo, string $idTreinador, string $idAgendamento, array $dados, array $exercicios): void
{
    $current = treinadorPrescricao($pdo, $idTreinador, $idAgendamento);
    if ($current === []) throw new RuntimeException(stridebr_t('trainer.prescription_edit_forbidden'));
    if (!in_array((string) ($current['status'] ?? ''), ['rascunho', 'publicado'], true) || (string) ($current['data_treino'] ?? '') < date('Y-m-d')) {
        throw new RuntimeException(stridebr_t('trainer.prescription_edit_forbidden'));
    }
    $user = treinadorUsuario($pdo, $idTreinador);
    $link = treinadorVinculoAceito($pdo, $idTreinador, (string) $current['idatleta']);
    if (!stridebr_db_bool($user['modo_treinador'] ?? false) || $link === [] || !stridebr_db_bool($link['pode_prescrever'] ?? false) || (string) ($current['idvinculo'] ?? '') !== (string) ($link['idvinculo'] ?? '')) {
        throw new RuntimeException(stridebr_t('trainer.prescription_edit_forbidden'));
    }

    $titulo = trim((string) ($dados['titulo'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $data = trim((string) ($dados['data_treino'] ?? ''));
    $hora = trim((string) ($dados['hora_inicio'] ?? ''));
    $duracaoRaw = trim((string) ($dados['duracao_prevista_min'] ?? ''));
    $requestedStatus = (string) ($dados['status'] ?? 'rascunho');
    if ($titulo === '' || stridebr_length($titulo) > 120) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_title'));
    if (stridebr_length($descricao) > 5000) throw new InvalidArgumentException(stridebr_t('planning.message.description_long'));
    if (!treinadorDataValida($data)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_date'));
    if (!treinadorHoraValida($hora)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    $duracao = null;
    if ($duracaoRaw !== '') {
        if (filter_var($duracaoRaw, FILTER_VALIDATE_INT) === false || (int) $duracaoRaw < 1 || (int) $duracaoRaw > 1440) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_duration'));
        $duracao = (int) $duracaoRaw;
    }
    if (!in_array($requestedStatus, ['rascunho', 'publicado'], true)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_status'));
    $status = (string) $current['status'] === 'publicado' ? 'publicado' : $requestedStatus;
    $idModalidade = cronogramaValidarModalidadeTreino($pdo, $idTreinador, $dados['idmodalidade'] ?? null);
    $distanciaRaw = str_replace(',', '.', trim((string) ($dados['distancia_prevista_m'] ?? '')));
    $distanciaPrevista = null;
    if ($distanciaRaw !== '') {
        if (!is_numeric($distanciaRaw) || (float) $distanciaRaw < 0 || (float) $distanciaRaw > 100000000) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_distance'));
        $distanciaPrevista = round((float) $distanciaRaw, 3);
    }

    $catalog = stridebr_exercise_catalog_for_user($pdo, $idTreinador);
    $rows = [];
    foreach (array_slice($exercicios, 0, 100) as $row) {
        if (!is_array($row)) continue;
        $resolved = cronogramaResolverExercicioBiblioteca($pdo, $catalog, (string) ($row['idexercicio'] ?? ''), (string) ($row['nome'] ?? ''));
        $nome = $resolved['nome'];
        if ($nome === '') continue;
        if (stridebr_length($nome) > 120) throw new InvalidArgumentException(stridebr_t('planning.message.exercise_long'));
        $seriesRaw = trim((string) ($row['series'] ?? ''));
        $series = null;
        if ($seriesRaw !== '') {
            if (filter_var($seriesRaw, FILTER_VALIDATE_INT) === false || (int) $seriesRaw < 1 || (int) $seriesRaw > 99) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_sets'));
            $series = (int) $seriesRaw;
        }
        $values = [];
        foreach (['repeticoes', 'carga', 'duracao', 'distancia', 'descanso'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if (stridebr_length($value) > 40) throw new InvalidArgumentException(stridebr_t('planning.message.field_long'));
            $values[$field] = $value !== '' ? $value : null;
        }
        $observacoes = trim((string) ($row['observacoes'] ?? ''));
        if (stridebr_length($observacoes) > 1000) throw new InvalidArgumentException(stridebr_t('planning.message.notes_long'));
        $rows[] = ['idexercicio'=>$resolved['idexercicio'] !== '' ? $resolved['idexercicio'] : null,'nome'=>$nome,'series'=>$series,'repeticoes'=>$values['repeticoes'],'carga'=>$values['carga'],'duracao'=>$values['duracao'],'distancia'=>$values['distancia'],'descanso'=>$values['descanso'],'observacoes'=>$observacoes !== '' ? $observacoes : null];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE treinos_agendados SET idmodalidade=:modalidade, data_treino=:data, hora_inicio=:hora, duracao_prevista_min=:duracao, distancia_prevista_m=:distancia, titulo=:titulo, descricao=:descricao, status=:status, publicado_em=CASE WHEN :status_publicado='publicado' THEN COALESCE(publicado_em,NOW()) ELSE publicado_em END, data_atualizacao=NOW() WHERE idagendamento=:id AND idcriador=:treinador AND origem='treinador'");
        $stmt->execute([':modalidade'=>$idModalidade,':data'=>$data,':hora'=>$hora !== '' ? $hora : null,':duracao'=>$duracao,':distancia'=>$distanciaPrevista,':titulo'=>$titulo,':descricao'=>$descricao !== '' ? $descricao : null,':status'=>$status,':status_publicado'=>$status,':id'=>$idAgendamento,':treinador'=>$idTreinador]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException(stridebr_t('trainer.prescription_edit_forbidden'));
        $pdo->prepare('DELETE FROM treinos_agendados_exercicios WHERE idagendamento=:id')->execute([':id'=>$idAgendamento]);
        $insert = $pdo->prepare('INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio, idagendamento, idexercicio, nome_snapshot, series, repeticoes, carga, duracao, distancia, descanso, observacoes, ordem) VALUES (:id,:agendamento,:exercicio,:nome,:series,:repeticoes,:carga,:duracao,:distancia,:descanso,:observacoes,:ordem)');
        foreach ($rows as $index => $row) {
            $insert->execute([':id'=>stridebr_generate_id(),':agendamento'=>$idAgendamento,':exercicio'=>$row['idexercicio'],':nome'=>$row['nome'],':series'=>$row['series'],':repeticoes'=>$row['repeticoes'],':carga'=>$row['carga'],':duracao'=>$row['duracao'],':distancia'=>$row['distancia'],':descanso'=>$row['descanso'],':observacoes'=>$row['observacoes'],':ordem'=>$index+1]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function treinadorAtividadeReadOnly(PDO $pdo, string $idTreinador, string $idAtleta, string $idRegistro): array
{
    $link = treinadorVinculoAceito($pdo, $idTreinador, $idAtleta);
    if ($link === [] || !stridebr_db_bool($link['pode_ver_atividades'] ?? false)) throw new RuntimeException(stridebr_t('trainer.activity_readonly_forbidden'));

    $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idAtleta);
    if ($registro === [] || (string) ($registro['status'] ?? '') !== 'concluido') throw new RuntimeException(stridebr_t('trainer.activity_readonly_forbidden'));

    $distanciaMetros = null;
    if (!empty($registro['usa_trechos'])) {
        $totais = atividadeTotaisCanonicosUnidades($registro);
        if (is_numeric($totais['distancia_m'] ?? null) && (float) $totais['distancia_m'] > 0) $distanciaMetros = (float) $totais['distancia_m'];
    } elseif (is_array($registro['rota'] ?? null) && is_numeric($registro['rota']['distancia_metros'] ?? null) && (float) $registro['rota']['distancia_metros'] > 0) {
        $distanciaMetros = (float) $registro['rota']['distancia_metros'];
    } else {
        $totais = atividadeTotaisCanonicosUnidades($registro);
        if (is_numeric($totais['distancia_m'] ?? null) && (float) $totais['distancia_m'] > 0) $distanciaMetros = (float) $totais['distancia_m'];
        if ($distanciaMetros === null) {
            foreach ($registro['campos'] ?? [] as $campo) {
                if (stridebr_lower((string) ($campo['slug'] ?? '')) !== 'distancia') continue;
                $valor = $registro['record_values'][(string) ($campo['idcampo'] ?? '')] ?? null;
                if (!is_numeric($valor) || (float) $valor <= 0) continue;
                $distanciaMetros = stridebr_lower(trim((string) ($campo['unidade_simbolo'] ?? 'km'))) === 'm' ? (float) $valor : (float) $valor * 1000;
                break;
            }
        }
    }

    return [
        'idregistro' => (string) $registro['idregistro'],
        'titulo' => trim((string) ($registro['titulo'] ?? '')) !== '' ? (string) $registro['titulo'] : (string) ($registro['modalidade_nome'] ?? ''),
        'data_inicio' => (string) $registro['data_inicio'],
        'data_fim' => $registro['data_fim'] !== null ? (string) $registro['data_fim'] : null,
        'distancia_metros' => $distanciaMetros,
        'observacoes' => $registro['observacoes'] !== null ? (string) $registro['observacoes'] : null,
        'modalidade_nome' => (string) ($registro['modalidade_nome'] ?? ''),
        'modalidade_slug' => (string) ($registro['modalidade_slug'] ?? ''),
    ];
}

function treinadorCronogramaReadOnly(PDO $pdo, string $idTreinador, string $idAtleta, string $idCronograma): array
{
    $link = treinadorVinculoAceito($pdo, $idTreinador, $idAtleta);
    if ($link === [] || !stridebr_db_bool($link['pode_ver_cronograma'] ?? false)) throw new RuntimeException(stridebr_t('trainer.schedule_readonly_forbidden'));
    $stmt = $pdo->prepare("SELECT idcronograma,nome,descricao,visibilidade,data_atualizacao FROM cronogramas WHERE idcronograma=:id AND idusuario=:atleta AND ativo=TRUE LIMIT 1");
    $stmt->execute([':id'=>$idCronograma, ':atleta'=>$idAtleta]);
    $row = $stmt->fetch() ?: [];
    if ($row === []) throw new RuntimeException(stridebr_t('trainer.schedule_readonly_forbidden'));
    $workouts = $pdo->prepare("SELECT idtreino,titulo AS nome,dia_semana,hora_inicio FROM treinos_cronograma WHERE idcronograma=:id ORDER BY dia_semana,hora_inicio,titulo LIMIT 40");
    $workouts->execute([':id'=>$idCronograma]);
    $row['treinos'] = $workouts->fetchAll();
    return $row;
}

function treinadorSalvarFeedback(PDO $pdo, string $idAtleta, string $idAgendamento, int $nota, string $feedback): void
{
    $feedback = trim($feedback);
    if ($nota < 1 || $nota > 5) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_rating'));
    }
    if (stridebr_length($feedback) > 2000) {
        throw new InvalidArgumentException(stridebr_t('planning.message.feedback_long'));
    }
    $stmt = $pdo->prepare("UPDATE treinos_agendados SET nota_atleta = :nota, feedback_atleta = :feedback, feedback_em = NOW(), data_atualizacao = NOW() WHERE idagendamento = :id AND idatleta = :atleta AND origem = 'treinador' AND status = 'concluido'");
    $stmt->execute([
        ':nota' => $nota,
        ':feedback' => $feedback !== '' ? $feedback : null,
        ':id' => $idAgendamento,
        ':atleta' => $idAtleta,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(stridebr_t('planning.message.completed_missing'));
    }
}

function treinadorWorkspaceAutorizar(PDO $pdo, string $idTreinador, string $idAtleta, ?string $permissao = null): array
{
    $trainer = treinadorUsuario($pdo, $idTreinador);
    $link = treinadorVinculoAceito($pdo, $idTreinador, $idAtleta);
    if ($trainer === [] || !stridebr_db_bool($trainer['modo_treinador'] ?? false) || $link === []) {
        throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    }
    if ($permissao !== null && !stridebr_db_bool($link[$permissao] ?? false)) {
        throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    }
    return $link;
}

function treinadorWorkspaceOverview(PDO $pdo, string $idTreinador): array
{
    $trainer = treinadorUsuario($pdo, $idTreinador);
    if ($trainer === [] || !stridebr_db_bool($trainer['modo_treinador'] ?? false)) {
        return ['stats'=>['athletes'=>0,'today'=>0,'completed'=>0,'attention'=>0],'attention'=>[],'today'=>[],'recent'=>[]];
    }
    $statsStmt = $pdo->prepare("WITH links AS (
        SELECT * FROM vinculos_treinador_atleta WHERE idtreinador=:trainer AND status='aceito'
    ), today_workouts AS (
        SELECT ta.* FROM treinos_agendados ta JOIN links v ON v.idvinculo=ta.idvinculo
        WHERE ta.idcriador=:trainer_workouts AND ta.data_treino=CURRENT_DATE AND ta.status <> 'cancelado'
    ), attention AS (
        SELECT ta.idagendamento
        FROM treinos_agendados ta JOIN links v ON v.idvinculo=ta.idvinculo
        WHERE ta.idcriador=:trainer_attention AND (
            (v.pode_ver_feedback=TRUE AND ta.feedback_em >= NOW()-INTERVAL '14 days')
            OR (ta.status='publicado' AND ta.data_treino < CURRENT_DATE AND ta.data_treino >= CURRENT_DATE-INTERVAL '7 days'
                AND NOT EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento AND st.status='concluido'))
        )
    )
    SELECT (SELECT COUNT(*) FROM links) AS athletes,
           (SELECT COUNT(*) FROM today_workouts) AS today,
           (SELECT COUNT(*) FROM today_workouts WHERE status='concluido' OR EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=today_workouts.idagendamento AND st.status='concluido')) AS completed,
           (SELECT COUNT(*) FROM attention) AS attention");
    $statsStmt->execute([':trainer'=>$idTreinador,':trainer_workouts'=>$idTreinador,':trainer_attention'=>$idTreinador]);
    $stats = $statsStmt->fetch() ?: [];

    $attentionStmt = $pdo->prepare("SELECT ta.idagendamento,ta.idatleta,ta.titulo,ta.data_treino,ta.feedback_atleta,ta.feedback_em,ta.status,
        COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS athlete_name,u.username,u.fotousuario,
        CASE
          WHEN v.pode_ver_feedback=TRUE AND ta.feedback_em >= NOW()-INTERVAL '14 days' THEN 'feedback'
          WHEN ta.status='publicado' AND ta.data_treino < CURRENT_DATE AND NOT EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento AND st.status='concluido') THEN 'missed'
          ELSE 'completed'
        END AS attention_type
      FROM treinos_agendados ta
      JOIN vinculos_treinador_atleta v ON v.idvinculo=ta.idvinculo AND v.status='aceito'
      JOIN usuarios u ON u.idusuario=ta.idatleta
     WHERE ta.idcriador=:trainer
       AND ((v.pode_ver_feedback=TRUE AND ta.feedback_em >= NOW()-INTERVAL '14 days')
         OR (ta.status='publicado' AND ta.data_treino < CURRENT_DATE AND ta.data_treino >= CURRENT_DATE-INTERVAL '7 days'
             AND NOT EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento AND st.status='concluido')))
     ORDER BY CASE WHEN ta.feedback_em IS NOT NULL THEN 0 ELSE 1 END, COALESCE(ta.feedback_em,ta.data_treino::timestamptz) DESC
     LIMIT 20");
    $attentionStmt->execute([':trainer'=>$idTreinador]);

    $todayStmt = $pdo->prepare("SELECT ta.idagendamento,ta.idatleta,ta.titulo,ta.hora_inicio,ta.status,
        COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS athlete_name,u.username
      FROM treinos_agendados ta
      JOIN vinculos_treinador_atleta v ON v.idvinculo=ta.idvinculo AND v.status='aceito'
      JOIN usuarios u ON u.idusuario=ta.idatleta
     WHERE ta.idcriador=:trainer AND ta.data_treino=CURRENT_DATE AND ta.status <> 'cancelado'
     ORDER BY ta.hora_inicio NULLS LAST, athlete_name LIMIT 30");
    $todayStmt->execute([':trainer'=>$idTreinador]);

    $recentStmt = $pdo->prepare("SELECT ra.idregistro,ra.idusuario,ra.titulo,ra.data_inicio,m.nome AS modalidade_nome,m.slug AS modalidade_slug,
        COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS athlete_name,u.username
      FROM vinculos_treinador_atleta v
      JOIN usuarios u ON u.idusuario=v.idatleta
      JOIN registros_atividade ra ON ra.idusuario=v.idatleta AND ra.status='concluido' AND ra.excluido_em IS NULL
      JOIN modalidades m ON m.idmodalidade=ra.idmodalidade
     WHERE v.idtreinador=:trainer AND v.status='aceito' AND v.pode_ver_atividades=TRUE
     ORDER BY ra.data_inicio DESC LIMIT 20");
    $recentStmt->execute([':trainer'=>$idTreinador]);

    return [
        'stats'=>[
            'athletes'=>(int)($stats['athletes'] ?? 0),
            'today'=>(int)($stats['today'] ?? 0),
            'completed'=>(int)($stats['completed'] ?? 0),
            'attention'=>(int)($stats['attention'] ?? 0),
        ],
        'attention'=>$attentionStmt->fetchAll(),
        'today'=>$todayStmt->fetchAll(),
        'recent'=>$recentStmt->fetchAll(),
    ];
}

function treinadorListarAtletasWorkspace(PDO $pdo, string $idTreinador, string $search = '', string $filter = 'all', int $page = 1, int $perPage = 50): array
{
    $trainer = treinadorUsuario($pdo, $idTreinador);
    if ($trainer === [] || !stridebr_db_bool($trainer['modo_treinador'] ?? false)) return ['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
    $search = trim($search);
    $filter = in_array($filter, ['all','today','attention','pending'], true) ? $filter : 'all';
    $page = max(1,$page); $perPage=max(1,min(50,$perPage)); $offset=($page-1)*$perPage;
    $where = "v.idtreinador=:trainer AND v.status IN ('aceito','pendente')";
    $params=[':trainer'=>$idTreinador];
    if ($search !== '') {
        $where .= " AND (COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) ILIKE :search OR u.username ILIKE :search)";
        $params[':search']='%'.ltrim($search,'@').'%';
    }
    if ($filter === 'today') $where .= " AND v.status='aceito' AND EXISTS (SELECT 1 FROM treinos_agendados tx WHERE tx.idvinculo=v.idvinculo AND tx.data_treino=CURRENT_DATE AND tx.status <> 'cancelado')";
    if ($filter === 'attention') $where .= " AND v.status='aceito' AND EXISTS (SELECT 1 FROM treinos_agendados tx WHERE tx.idvinculo=v.idvinculo AND tx.idcriador=v.idtreinador AND ((v.pode_ver_feedback AND tx.feedback_em IS NOT NULL AND tx.feedback_em>=NOW()-INTERVAL '14 days') OR (tx.status='publicado' AND tx.data_treino<CURRENT_DATE AND tx.data_treino>=CURRENT_DATE-INTERVAL '7 days' AND NOT EXISTS (SELECT 1 FROM sessoes_treino stx WHERE stx.idagendamento_origem=tx.idagendamento AND stx.status='concluido'))))";
    if ($filter === 'pending') $where .= " AND v.status='pendente'";
    $count=$pdo->prepare("SELECT COUNT(*) FROM vinculos_treinador_atleta v JOIN usuarios u ON u.idusuario=v.idatleta WHERE {$where}");
    $count->execute($params); $total=(int)$count->fetchColumn();
    $sql="SELECT v.*,u.username,COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario) AS nome_exibicao,u.fotousuario,
        COALESCE(week_stats.planned,0) AS week_planned,COALESCE(week_stats.completed,0) AS week_completed,
        next_workout.titulo AS next_title,next_workout.data_treino AS next_date,next_workout.hora_inicio AS next_time,
        CASE WHEN v.pode_ver_atividades THEN last_activity.data_inicio ELSE NULL END AS last_activity_at,
        CASE WHEN v.pode_ver_feedback THEN COALESCE(attention.feedback_count,0) ELSE NULL END AS feedback_count,
        COALESCE(attention.missed_count,0) AS missed_count
      FROM vinculos_treinador_atleta v
      JOIN usuarios u ON u.idusuario=v.idatleta
      LEFT JOIN LATERAL (
        SELECT COUNT(*) FILTER (WHERE ta.status <> 'cancelado') AS planned,
               COUNT(*) FILTER (WHERE ta.status='concluido' OR EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento AND st.status='concluido')) AS completed
          FROM treinos_agendados ta
         WHERE ta.idvinculo=v.idvinculo AND ta.data_treino BETWEEN date_trunc('week',CURRENT_DATE)::date AND (date_trunc('week',CURRENT_DATE)::date+6)
      ) week_stats ON TRUE
      LEFT JOIN LATERAL (
        SELECT ta.titulo,ta.data_treino,ta.hora_inicio FROM treinos_agendados ta
         WHERE ta.idvinculo=v.idvinculo AND ta.status='publicado' AND ta.data_treino>=CURRENT_DATE
         ORDER BY ta.data_treino,ta.hora_inicio NULLS LAST LIMIT 1
      ) next_workout ON v.status='aceito'
      LEFT JOIN LATERAL (
        SELECT ra.data_inicio FROM registros_atividade ra WHERE ra.idusuario=v.idatleta AND ra.status='concluido' AND ra.excluido_em IS NULL ORDER BY ra.data_inicio DESC LIMIT 1
      ) last_activity ON v.status='aceito' AND v.pode_ver_atividades
      LEFT JOIN LATERAL (
        SELECT COUNT(*) FILTER (WHERE ta.feedback_em IS NOT NULL AND ta.feedback_em>=NOW()-INTERVAL '14 days') AS feedback_count,
               COUNT(*) FILTER (WHERE ta.status='publicado' AND ta.data_treino<CURRENT_DATE AND ta.data_treino>=CURRENT_DATE-INTERVAL '7 days' AND NOT EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento AND st.status='concluido')) AS missed_count
          FROM treinos_agendados ta WHERE ta.idvinculo=v.idvinculo AND ta.idcriador=v.idtreinador
      ) attention ON v.status='aceito'
     WHERE {$where}
     ORDER BY CASE WHEN v.status='aceito' THEN 0 ELSE 1 END, nome_exibicao, u.username
     LIMIT :limit OFFSET :offset";
    $stmt=$pdo->prepare($sql);
    foreach($params as $key=>$value) $stmt->bindValue($key,$value);
    $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute();
    return ['items'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))];
}

function treinadorCalendarioAtleta(PDO $pdo, string $idTreinador, string $idAtleta, string $dataInicio, string $dataFim): array
{
    $link=treinadorWorkspaceAutorizar($pdo,$idTreinador,$idAtleta);
    $start=cronogramaValidarDataIso($dataInicio); $end=cronogramaValidarDataIso($dataFim);
    if($end<$start || $end->diff($start)->days>31) throw new InvalidArgumentException(stridebr_t('trainer.calendar_range_invalid'));
    $stmt=$pdo->prepare("SELECT ta.*,st.idsessao,st.status AS session_status,st.idregistro_atividade
      FROM treinos_agendados ta
      LEFT JOIN LATERAL (SELECT idsessao,status,idregistro_atividade FROM sessoes_treino WHERE idagendamento_origem=ta.idagendamento AND idusuario=ta.idatleta ORDER BY data_criacao DESC LIMIT 1) st ON TRUE
     WHERE ta.idatleta=:athlete AND ta.idcriador=:trainer AND ta.idvinculo=:link AND ta.data_treino BETWEEN :start AND :end AND ta.status <> 'cancelado'
     ORDER BY ta.data_treino,ta.hora_inicio NULLS LAST,ta.data_criacao");
    $stmt->execute([':athlete'=>$idAtleta,':trainer'=>$idTreinador,':link'=>$link['idvinculo'],':start'=>$dataInicio,':end'=>$dataFim]);
    $items=[];
    foreach($stmt->fetchAll() as $row){
        $authored=(string)($row['idcriador']??'')===$idTreinador && (string)($row['idvinculo']??'')===(string)$link['idvinculo'];
        $mutable=$authored && stridebr_db_bool($link['pode_prescrever']??false) && in_array((string)$row['status'],['rascunho','publicado'],true) && (string)$row['data_treino']>=date('Y-m-d') && empty($row['idsessao']);
        $row['workspace_source']=$authored?'coach':'scheduled';
        $row['capabilities']=['view'=>true,'edit'=>$mutable,'cancel'=>$mutable,'analyze'=>stridebr_db_bool($link['pode_ver_atividades']??false) && (!empty($row['idregistro_atividade']) || (string)$row['status']==='concluido')];
        $items[]=$row;
    }
    if(stridebr_db_bool($link['pode_ver_cronograma']??false)){
        foreach(cronogramaListarOcorrencias($pdo,$idAtleta,$dataInicio,$dataFim) as $occ){
            $occ['workspace_source']='athlete_schedule';
            $occ['capabilities']=['view'=>true,'edit'=>false,'cancel'=>false,'analyze'=>stridebr_db_bool($link['pode_ver_atividades']??false) && !empty($occ['concluido'])];
            $items[]=$occ;
        }
    }
    usort($items,static fn(array $a,array $b):int=>[(string)($a['data_treino']??''),(string)($a['hora_inicio']??''),(string)($a['titulo']??'')]<=>[(string)($b['data_treino']??''),(string)($b['hora_inicio']??''),(string)($b['titulo']??'')]);
    return $items;
}

function treinadorResumoAtleta(PDO $pdo, string $idTreinador, string $idAtleta, string $weekStart): array
{
    $link=treinadorWorkspaceAutorizar($pdo,$idTreinador,$idAtleta);
    $start=cronogramaValidarDataIso($weekStart); $end=$start->modify('+6 days')->format('Y-m-d');
    $calendar=treinadorCalendarioAtleta($pdo,$idTreinador,$idAtleta,$weekStart,$end);
    $planned=0;$completed=0;$strength=0;
    foreach($calendar as $item){
        if(($item['workspace_source']??'')==='athlete_schedule' || in_array((string)($item['status']??''),['publicado','concluido'],true)) $planned++;
        if((string)($item['status']??'')==='concluido' || !empty($item['concluido']) || (string)($item['session_status']??'')==='concluido') $completed++;
    }
    if(stridebr_db_bool($link['pode_ver_atividades']??false)){
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idusuario=:athlete AND ra.status='concluido' AND ra.excluido_em IS NULL AND ra.data_inicio::date BETWEEN :start AND :end AND m.familia_hub='strength'");
        $stmt->execute([':athlete'=>$idAtleta,':start'=>$weekStart,':end'=>$end]); $strength=(int)$stmt->fetchColumn();
    }
    return ['planned'=>$planned,'completed'=>$completed,'strength'=>$strength,'calendar'=>$calendar];
}

function treinadorListarBiblioteca(PDO $pdo,string $idTreinador): array
{
    $trainer=treinadorUsuario($pdo,$idTreinador);
    if($trainer===[] || !stridebr_db_bool($trainer['modo_treinador']??false)) return ['workouts'=>[],'schedules'=>[]];
    $workouts=cronogramaListarTreinosModelo($pdo,$idTreinador);
    $stmt=$pdo->prepare("SELECT c.idcronograma,c.nome,c.descricao,c.data_atualizacao,
        COUNT(tc.idtreino) AS workouts_total,
        MIN(tc.vigencia_inicio) AS source_start,
        MAX(tc.vigencia_fim) AS source_end,
        BOOL_AND(tc.vigencia_fim IS NOT NULL) FILTER (WHERE tc.idtreino IS NOT NULL) AS finite
      FROM cronogramas c LEFT JOIN treinos_cronograma tc ON tc.idcronograma=c.idcronograma
     WHERE c.idusuario=:trainer AND c.ativo=TRUE GROUP BY c.idcronograma ORDER BY c.data_atualizacao DESC,c.nome");
    $stmt->execute([':trainer'=>$idTreinador]);
    return ['workouts'=>$workouts,'schedules'=>$stmt->fetchAll()];
}

function treinadorAplicarTreinoModelo(PDO $pdo,string $idTreinador,string $idAtleta,string $idModelo,array $dados): string
{
    $link=treinadorWorkspaceAutorizar($pdo,$idTreinador,$idAtleta,'pode_prescrever');
    $model=cronogramaBuscarTreinoModelo($pdo,$idTreinador,$idModelo);
    if($model===[]) throw new RuntimeException(stridebr_t('library.workout_not_found'));
    $date=trim((string)($dados['data_treino']??'')); if(!treinadorDataValida($date)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_date'));
    $time=trim((string)($dados['hora_inicio']??'')); if(!treinadorHoraValida($time)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_time'));
    $status=(string)($dados['status']??'publicado'); if(!in_array($status,['rascunho','publicado'],true)) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_status'));
    $id=stridebr_generate_id();
    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO treinos_agendados (idagendamento,idatleta,idcriador,idvinculo,idtreino_modelo_origem,idmodalidade,data_treino,hora_inicio,titulo,descricao,origem,status,publicado_em) VALUES (:id,:athlete,:trainer,:link,:model,:sport,:date,:time,:title,:description,'treinador',:status,CASE WHEN :published='publicado' THEN NOW() ELSE NULL END)");
        $insert->execute([':id'=>$id,':athlete'=>$idAtleta,':trainer'=>$idTreinador,':link'=>$link['idvinculo'],':model'=>$idModelo,':sport'=>$model['idmodalidade']?:null,':date'=>$date,':time'=>$time!==''?$time:null,':title'=>$model['titulo'],':description'=>$model['descricao']?:null,':status'=>$status,':published'=>$status]);
        treinadorWorkspaceCopiarExerciciosAgendados($pdo,$id,$model['exercicios']??[]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return $id;
}

function treinadorWorkspaceCopiarExerciciosAgendados(PDO $pdo,string $idAgendamento,array $rows): void
{
    treinadorWorkspaceCopiarExerciciosAgendadosLote($pdo,[['idagendamento'=>$idAgendamento,'rows'=>$rows]]);
}

function treinadorWorkspaceCopiarExerciciosAgendadosLote(PDO $pdo,array $appointments): void
{
    $all=[];
    foreach($appointments as $appointment){
        $idAgendamento=trim((string)($appointment['idagendamento']??''));
        if($idAgendamento==='') continue;
        foreach(array_values((array)($appointment['rows']??[])) as $index=>$row){
            $all[]=[
                stridebr_generate_id(),$idAgendamento,trim((string)($row['idexercicio']??''))?:null,trim((string)($row['nome_snapshot']??$row['nome']??''))?:stridebr_t('common.exercise'),
                isset($row['series'])&&is_numeric($row['series'])?(int)$row['series']:null,trim((string)($row['repeticoes']??''))?:null,trim((string)($row['carga']??''))?:null,
                trim((string)($row['bloco']??''))?:null,trim((string)($row['cluster']??''))?:null,trim((string)($row['descanso']??''))?:null,trim((string)($row['observacoes']??''))?:null,
                trim((string)($row['duracao']??''))?:null,trim((string)($row['distancia']??''))?:null,trim((string)($row['intensidade']??''))?:null,
                isset($row['rpe'])&&is_numeric($row['rpe'])?(float)$row['rpe']:null,isset($row['rir'])&&is_numeric($row['rir'])?(float)$row['rir']:null,
                trim((string)($row['tempo_execucao']??''))?:null,trim((string)($row['cadencia']??''))?:null,trim((string)($row['tipo_passo']??''))?:'exercise',
                isset($row['repeticoes_bloco'])&&is_numeric($row['repeticoes_bloco'])?(int)$row['repeticoes_bloco']:null,trim((string)($row['alvo_tipo']??''))?:null,
                isset($row['alvo_min'])&&is_numeric($row['alvo_min'])?(float)$row['alvo_min']:null,isset($row['alvo_max'])&&is_numeric($row['alvo_max'])?(float)$row['alvo_max']:null,
                trim((string)($row['alvo_unidade']??''))?:null,isset($row['recuperacao_duracao_s'])&&is_numeric($row['recuperacao_duracao_s'])?(int)$row['recuperacao_duracao_s']:null,
                isset($row['recuperacao_distancia_m'])&&is_numeric($row['recuperacao_distancia_m'])?(float)$row['recuperacao_distancia_m']:null,isset($row['ordem'])&&is_numeric($row['ordem'])?(int)$row['ordem']:$index+1,
            ];
        }
    }
    if($all===[]) return;
    $columns='idagendamento_exercicio,idagendamento,idexercicio,nome_snapshot,series,repeticoes,carga,bloco,cluster,descanso,observacoes,duracao,distancia,intensidade,rpe,rir,tempo_execucao,cadencia,tipo_passo,repeticoes_bloco,alvo_tipo,alvo_min,alvo_max,alvo_unidade,recuperacao_duracao_s,recuperacao_distancia_m,ordem';
    foreach(array_chunk($all,200) as $chunk){
        $values=[];$params=[];
        foreach($chunk as $row){$values[]='('.implode(',',array_fill(0,27,'?')).')';array_push($params,...$row);}
        $pdo->prepare("INSERT INTO treinos_agendados_exercicios ({$columns}) VALUES ".implode(',',$values))->execute($params);
    }
}

function treinadorWorkspaceListarOcorrenciasFonte(PDO $pdo,string $idTreinador,string $idCronograma,DateTimeImmutable $start,DateTimeImmutable $end): array
{
    $items=[];$cursor=$start;
    while($cursor<=$end){
        $chunkEnd=$cursor->modify('+92 days'); if($chunkEnd>$end)$chunkEnd=$end;
        foreach(cronogramaListarOcorrencias($pdo,$idTreinador,$cursor->format('Y-m-d'),$chunkEnd->format('Y-m-d'),$idCronograma,true) as $row){
            $items[(string)$row['idtreino'].':'.(string)$row['data_original']]=$row;
        }
        $cursor=$chunkEnd->modify('+1 day');
    }
    $items=array_values($items); usort($items,static fn(array $a,array $b):int=>[$a['data_treino'],$a['hora_inicio'],$a['ordem']]<=>[$b['data_treino'],$b['hora_inicio'],$b['ordem']]); return $items;
}

function treinadorPreverAplicacaoCronograma(PDO $pdo,string $idTreinador,string $idAtleta,string $idCronograma,string $dataInicio,?string $dataFim=null): array
{
    $link=treinadorWorkspaceAutorizar($pdo,$idTreinador,$idAtleta,'pode_prescrever');
    $source=cronogramaBuscar($pdo,$idCronograma,$idTreinador); if($source===[]) throw new RuntimeException(stridebr_t('schedule.not_found'));
    $rangeStmt=$pdo->prepare("SELECT MIN(vigencia_inicio) AS source_start,MAX(vigencia_fim) AS source_end,BOOL_AND(vigencia_fim IS NOT NULL) AS finite,COUNT(*) AS workouts FROM treinos_cronograma WHERE idcronograma=:schedule");
    $rangeStmt->execute([':schedule'=>$idCronograma]);$range=$rangeStmt->fetch()?:[];
    if((int)($range['workouts']??0)<1 || empty($range['source_start'])) throw new RuntimeException(stridebr_t('trainer.plan_empty'));
    $targetStart=cronogramaValidarDataIso($dataInicio);$sourceStart=cronogramaValidarDataIso((string)$range['source_start']);
    $finite=stridebr_db_bool($range['finite']??false) && !empty($range['source_end']);
    if($dataFim!==null && trim($dataFim)!=='') $targetEnd=cronogramaValidarDataIso(trim($dataFim));
    elseif($finite){$sourceEnd=cronogramaValidarDataIso((string)$range['source_end']);$targetEnd=$targetStart->modify('+'.$sourceStart->diff($sourceEnd)->days.' days');}
    else throw new InvalidArgumentException(stridebr_t('trainer.plan_end_required'));
    if($targetEnd<$targetStart || $targetEnd->diff($targetStart)->days>365) throw new InvalidArgumentException(stridebr_t('trainer.plan_range_invalid'));
    $sourceEnd=$finite?cronogramaValidarDataIso((string)$range['source_end']):$sourceStart->modify('+'.$targetStart->diff($targetEnd)->days.' days');
    $sourceItems=treinadorWorkspaceListarOcorrenciasFonte($pdo,$idTreinador,$idCronograma,$sourceStart,$sourceEnd);
    $delta=(int)$sourceStart->diff($targetStart)->format('%r%a');$occ=[];
    foreach($sourceItems as $row){
        if((string)($row['status']??'')==='cancelado') continue;
        $shifted=(new DateTimeImmutable((string)$row['data_treino']))->modify(($delta>=0?'+':'').$delta.' days');
        if($shifted<$targetStart || $shifted>$targetEnd) continue;
        $row['source_date']=$row['data_treino'];$row['data_treino']=$shifted->format('Y-m-d');$occ[]=$row;
    }
    $conflicts=[];
    if($occ!==[]){
        $conflictStmt=$pdo->prepare("SELECT data_treino,COUNT(*) AS total FROM treinos_agendados WHERE idatleta=:athlete AND idcriador=:trainer AND idvinculo=:link AND status <> 'cancelado' AND data_treino BETWEEN :start AND :end GROUP BY data_treino");
        $conflictStmt->execute([':athlete'=>$idAtleta,':trainer'=>$idTreinador,':link'=>$link['idvinculo'],':start'=>$targetStart->format('Y-m-d'),':end'=>$targetEnd->format('Y-m-d')]);foreach($conflictStmt->fetchAll() as $r)$conflicts[(string)$r['data_treino']]=(int)$r['total'];
        if(stridebr_db_bool($link['pode_ver_cronograma']??false)){
            foreach(cronogramaListarOcorrencias($pdo,$idAtleta,$targetStart->format('Y-m-d'),$targetEnd->format('Y-m-d')) as $r)$conflicts[(string)$r['data_treino']]=($conflicts[(string)$r['data_treino']]??0)+1;
        }
    }
    foreach($occ as &$row)$row['conflict_count']=$conflicts[(string)$row['data_treino']]??0;unset($row);
    return ['source'=>$source,'link'=>$link,'start'=>$targetStart->format('Y-m-d'),'end'=>$targetEnd->format('Y-m-d'),'occurrences'=>$occ,'conflicts'=>$conflicts,'total'=>count($occ)];
}

function treinadorAplicarCronograma(PDO $pdo,string $idTreinador,string $idAtleta,string $idCronograma,string $dataInicio,?string $dataFim,string $status,string $idempotencyKey): array
{
    $status=in_array($status,['rascunho','publicado'],true)?$status:'publicado';$idempotencyKey=trim($idempotencyKey);
    if($idempotencyKey===''||strlen($idempotencyKey)>120) throw new InvalidArgumentException(stridebr_t('trainer.idempotency_required'));
    $preview=treinadorPreverAplicacaoCronograma($pdo,$idTreinador,$idAtleta,$idCronograma,$dataInicio,$dataFim);
    $hash=hash('sha256',json_encode([$idAtleta,$idCronograma,$preview['start'],$preview['end'],$status],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $existing=$pdo->prepare('SELECT * FROM aplicacoes_cronograma_treinador WHERE idtreinador=:trainer AND idempotency_key=:key LIMIT 1');$existing->execute([':trainer'=>$idTreinador,':key'=>$idempotencyKey]);$row=$existing->fetch();
    if($row){if(!hash_equals((string)$row['payload_hash'],$hash))throw new RuntimeException(stridebr_t('trainer.idempotency_conflict'));return $row;}
    $workoutIds=array_values(array_unique(array_map(static fn(array $r):string=>(string)$r['idtreino'],$preview['occurrences'])));$byWorkout=[];
    if($workoutIds!==[]){$marks=implode(',',array_fill(0,count($workoutIds),'?'));$ex=$pdo->prepare("SELECT * FROM treinos_exercicios WHERE idtreino IN ({$marks}) ORDER BY idtreino,ordem");$ex->execute($workoutIds);$catalog=stridebr_exercise_catalog_for_user($pdo,$idTreinador);foreach(cronogramaHidratarExerciciosPlanejados($pdo,$idTreinador,$ex->fetchAll(),$catalog) as $r)$byWorkout[(string)$r['idtreino']][]=$r;}
    $applicationId=stridebr_generate_id();$pdo->beginTransaction();
    try{
        $app=$pdo->prepare("INSERT INTO aplicacoes_cronograma_treinador (idaplicacao,idvinculo,idtreinador,idatleta,idcronograma_origem,nome_snapshot,data_inicio,data_fim,status,idempotency_key,payload_hash) VALUES (:id,:link,:trainer,:athlete,:schedule,:name,:start,:end,'ativo',:key,:hash)");
        $app->execute([':id'=>$applicationId,':link'=>$preview['link']['idvinculo'],':trainer'=>$idTreinador,':athlete'=>$idAtleta,':schedule'=>$idCronograma,':name'=>$preview['source']['nome'],':start'=>$preview['start'],':end'=>$preview['end'],':key'=>$idempotencyKey,':hash'=>$hash]);
        $values=[];$params=[];
        foreach($preview['occurrences'] as $occ){
            $duration=null;$start=substr((string)($occ['hora_inicio']??''),0,5);$end=substr((string)($occ['hora_fim']??''),0,5);if($start!==''&&$end!==''){$sm=((int)substr($start,0,2))*60+(int)substr($start,3,2);$em=((int)substr($end,0,2))*60+(int)substr($end,3,2);$duration=stridebr_db_bool($occ['termina_dia_seguinte']??false)?1440-$sm+$em:$em-$sm;if($duration<1)$duration=null;}
            $appointment=stridebr_generate_id();
            $values[]="(?,?,?,?,?,?,?,?,?,?,?,?,?,'treinador',?,".($status==='publicado'?'NOW()':'NULL').')';
            array_push($params,$appointment,$idAtleta,$idTreinador,$preview['link']['idvinculo'],$idCronograma,$occ['idtreino'],$applicationId,$occ['idmodalidade']?:null,$occ['data_treino'],$start!==''?$start:null,$duration,$occ['titulo'],trim((string)($occ['descricao']??''))?:null,$status);
        }
        $materialized=[];
        if($values!==[]){
            $sql="INSERT INTO treinos_agendados (idagendamento,idatleta,idcriador,idvinculo,idcronograma_origem,idtreino_origem,idaplicacao_cronograma,idmodalidade,data_treino,hora_inicio,duracao_prevista_min,titulo,descricao,origem,status,publicado_em) VALUES ".implode(',',$values)." ON CONFLICT (idaplicacao_cronograma,idtreino_origem,data_treino) WHERE idaplicacao_cronograma IS NOT NULL AND idtreino_origem IS NOT NULL DO NOTHING RETURNING idagendamento,idtreino_origem";
            $inserted=$pdo->prepare($sql);$inserted->execute($params);
            foreach($inserted->fetchAll() as $row){$materialized[]=['idagendamento'=>(string)$row['idagendamento'],'rows'=>$byWorkout[(string)$row['idtreino_origem']]??[]];}
        }
        treinadorWorkspaceCopiarExerciciosAgendadosLote($pdo,$materialized);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    $loaded=$pdo->prepare('SELECT * FROM aplicacoes_cronograma_treinador WHERE idaplicacao=:id');$loaded->execute([':id'=>$applicationId]);return $loaded->fetch()?:[];
}

function treinadorRemoverAplicacaoCronograma(PDO $pdo,string $idTreinador,string $idAplicacao): array
{
    $stmt=$pdo->prepare("SELECT a.*,v.pode_prescrever,v.status AS link_status FROM aplicacoes_cronograma_treinador a LEFT JOIN vinculos_treinador_atleta v ON v.idvinculo=a.idvinculo WHERE a.idaplicacao=:id AND a.idtreinador=:trainer LIMIT 1");$stmt->execute([':id'=>$idAplicacao,':trainer'=>$idTreinador]);$app=$stmt->fetch();
    if(!$app || $app['status']!=='ativo' || $app['link_status']!=='aceito' || !stridebr_db_bool($app['pode_prescrever']??false)) throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    $pdo->beginTransaction();try{
        $cancel=$pdo->prepare("UPDATE treinos_agendados ta SET status='cancelado',data_atualizacao=NOW() WHERE ta.idaplicacao_cronograma=:app AND ta.status IN ('rascunho','publicado') AND ta.data_treino>=CURRENT_DATE AND NOT EXISTS (SELECT 1 FROM sessoes_treino st WHERE st.idagendamento_origem=ta.idagendamento)");$cancel->execute([':app'=>$idAplicacao]);
        $pdo->prepare("UPDATE aplicacoes_cronograma_treinador SET status='removido',removido_em=NOW(),atualizado_em=NOW() WHERE idaplicacao=:id AND idtreinador=:trainer")->execute([':id'=>$idAplicacao,':trainer'=>$idTreinador]);$pdo->commit();
        return ['cancelled'=>$cancel->rowCount()];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function treinadorWorkspaceFormatarSet(array $values): string
{
    $parts = [];
    $load = $values['load'] ?? null;
    $repetitions = $values['repetitions'] ?? null;
    $duration = $values['duration_s'] ?? ($values['duration'] ?? null);
    $distance = $values['distance_m'] ?? ($values['distance'] ?? null);
    if ($load !== null && trim((string) $load) !== '') $parts[] = trim((string) $load) . (is_numeric($load) ? ' kg' : '');
    if ($repetitions !== null && trim((string) $repetitions) !== '') $parts[] = ($parts !== [] ? '× ' : '') . trim((string) $repetitions);
    if ($duration !== null && trim((string) $duration) !== '') {
        if (is_numeric($duration) && array_key_exists('duration_s', $values)) {
            $seconds = max(0, (int) round((float) $duration));
            $parts[] = $seconds >= 60 ? sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60) : $seconds . ' s';
        } else {
            $parts[] = trim((string) $duration);
        }
    }
    if ($distance !== null && trim((string) $distance) !== '') {
        if (is_numeric($distance) && array_key_exists('distance_m', $values)) {
            $meters = (float) $distance;
            $parts[] = $meters >= 1000 ? rtrim(rtrim(number_format($meters / 1000, 2, ',', ''), '0'), ',') . ' km' : rtrim(rtrim(number_format($meters, 1, ',', ''), '0'), ',') . ' m';
        } else {
            $parts[] = trim((string) $distance);
        }
    }
    return $parts !== [] ? implode(' · ', $parts) : '—';
}

function treinadorCompararPlanejadoRealizado(PDO $pdo,string $idTreinador,string $idAtleta,string $idAgendamento): array
{
    $link=treinadorWorkspaceAutorizar($pdo,$idTreinador,$idAtleta);
    $appointment=$pdo->prepare("SELECT * FROM treinos_agendados WHERE idagendamento=:id AND idatleta=:athlete AND idcriador=:trainer AND idvinculo=:link LIMIT 1");$appointment->execute([':id'=>$idAgendamento,':athlete'=>$idAtleta,':trainer'=>$idTreinador,':link'=>$link['idvinculo']]);$workout=$appointment->fetch();if(!$workout)throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    $planned=$pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');$planned->execute([':id'=>$idAgendamento]);$plannedRows=$planned->fetchAll();
    $actualRows=[];$activity=null;$session=null;
    if(stridebr_db_bool($link['pode_ver_atividades']??false)){
        $s=$pdo->prepare("SELECT * FROM sessoes_treino WHERE idagendamento_origem=:id AND idusuario=:athlete ORDER BY data_criacao DESC LIMIT 1");$s->execute([':id'=>$idAgendamento,':athlete'=>$idAtleta]);$session=$s->fetch()?:null;
        if($session){$q=$pdo->prepare("SELECT se.ordem,se.nome_snapshot,se.idexercicio,ss.numero,ss.concluida,ss.repeticoes_realizadas,ss.carga_realizada,ss.duracao_realizada_s,ss.distancia_realizada_m FROM sessoes_treino_exercicios se LEFT JOIN sessoes_treino_series ss ON ss.idsessao_exercicio=se.idsessao_exercicio WHERE se.idsessao=:session ORDER BY se.ordem,ss.numero");$q->execute([':session'=>$session['idsessao']]);foreach($q->fetchAll() as $r)$actualRows[(int)$r['ordem']][]=$r;if(!empty($session['idregistro_atividade'])){$activity=treinadorAtividadeReadOnly($pdo,$idTreinador,$idAtleta,(string)$session['idregistro_atividade']);$effort=$pdo->prepare('SELECT esforco_percebido FROM registros_atividade WHERE idregistro=:id AND idusuario=:athlete');$effort->execute([':id'=>$session['idregistro_atividade'],':athlete'=>$idAtleta]);$effortValue=$effort->fetchColumn();$activity['rpe']=$effortValue!==false?$effortValue:null;}}
    }
    $exercises=[];
    foreach($plannedRows as $row){$sets=[];$count=max(1,(int)($row['series']??1));$actual=$actualRows[(int)$row['ordem']]??[];for($i=1;$i<=$count;$i++){$a=$actual[$i-1]??[];$sets[]=['number'=>$i,'planned'=>['repetitions'=>$row['repeticoes']?:null,'load'=>$row['carga']?:null,'duration'=>$row['duracao']?:null,'distance'=>$row['distancia']?:null],'actual'=>['repetitions'=>$a['repeticoes_realizadas']??null,'load'=>$a['carga_realizada']??null,'duration_s'=>$a['duracao_realizada_s']??null,'distance_m'=>$a['distancia_realizada_m']??null,'completed'=>isset($a['concluida'])?stridebr_db_bool($a['concluida']):false]];}$exercises[]=['name'=>$row['nome_snapshot'],'sets'=>$sets];}
    $plannedDuration=is_numeric($workout['duracao_prevista_min']??null)?(int)$workout['duracao_prevista_min']*60:null;$actualDuration=null;if($session&&$session['data_fim']&&$session['data_inicio']){$actualDuration=max(0,(new DateTimeImmutable((string)$session['data_fim']))->getTimestamp()-(new DateTimeImmutable((string)$session['data_inicio']))->getTimestamp());}
    return ['workout'=>$workout,'can_analyze'=>stridebr_db_bool($link['pode_ver_atividades']??false),'exercises'=>$exercises,'endurance'=>['planned_duration_s'=>$plannedDuration,'actual_duration_s'=>$actualDuration,'planned_distance_m'=>is_numeric($workout['distancia_prevista_m']??null)?(float)$workout['distancia_prevista_m']:null,'actual_distance_m'=>$activity['distancia_metros']??null,'actual_rpe'=>$activity['rpe']??null],'activity'=>$activity];
}

function treinadorListarComentarios(PDO $pdo,string $idAtual,string $idAgendamento): array
{
    $auth=treinadorComentarioAutorizar($pdo,$idAtual,$idAgendamento,false);
    $stmt=$pdo->prepare("SELECT c.idcomentario,c.idautor,c.texto,c.criado_em,c.editado_em,COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario,'Usuário') AS autor_nome,u.username,CASE WHEN c.idautor=:trainer THEN 'coach' ELSE 'athlete' END AS autor_papel FROM comentarios_treino c LEFT JOIN usuarios u ON u.idusuario=c.idautor WHERE c.idagendamento=:workout AND c.excluido_em IS NULL ORDER BY c.criado_em,c.idcomentario");$stmt->execute([':trainer'=>$auth['appointment']['idcriador'],':workout'=>$idAgendamento]);return $stmt->fetchAll();
}

function treinadorComentarioAutorizar(PDO $pdo,string $idAtual,string $idAgendamento,bool $write=true): array
{
    $stmt=$pdo->prepare("SELECT ta.*,v.status AS link_status,v.pode_ver_feedback FROM treinos_agendados ta LEFT JOIN vinculos_treinador_atleta v ON v.idvinculo=ta.idvinculo WHERE ta.idagendamento=:id LIMIT 1");$stmt->execute([':id'=>$idAgendamento]);$a=$stmt->fetch();if(!$a)throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    $isAthlete=(string)$a['idatleta']===$idAtual;$isCoach=(string)($a['idcriador']??'')===$idAtual && (string)($a['link_status']??'')==='aceito' && stridebr_db_bool($a['pode_ver_feedback']??false);
    if(!$isAthlete&&!$isCoach)throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    if($isAthlete && !in_array((string)$a['status'],['publicado','concluido'],true))throw new RuntimeException(stridebr_t('trainer.workspace_forbidden'));
    return ['appointment'=>$a,'role'=>$isCoach?'coach':'athlete'];
}

function treinadorCriarComentario(PDO $pdo,string $idAtual,string $idAgendamento,string $texto): string
{
    $auth=treinadorComentarioAutorizar($pdo,$idAtual,$idAgendamento,true);$texto=trim($texto);if($texto===''||stridebr_length($texto)>2000)throw new InvalidArgumentException(stridebr_t('trainer.comment_invalid'));
    $id=stridebr_generate_id();$pdo->prepare('INSERT INTO comentarios_treino (idcomentario,idagendamento,idautor,idvinculo,texto) VALUES (:id,:workout,:author,:link,:text)')->execute([':id'=>$id,':workout'=>$idAgendamento,':author'=>$idAtual,':link'=>$auth['appointment']['idvinculo']?:null,':text'=>$texto]);return $id;
}
