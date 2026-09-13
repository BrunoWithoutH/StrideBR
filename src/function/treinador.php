<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once __DIR__ . '/atividade_modelo.php';
require_once __DIR__ . '/atividade_presenter.php';

function treinadorUsuario(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare("SELECT idusuario, username, COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) AS nome_exibicao, fotousuario, modo_treinador, descobrivel, statususuario FROM usuarios WHERE idusuario = :id LIMIT 1");
    $stmt->execute([':id' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function treinadorBuscarUsername(PDO $pdo, string $username, string $idAtual, bool $exigirTreinador = false): array
{
    $username = stridebr_lower(ltrim(trim($username), '@'));
    if (!stridebr_username_is_valid($username)) {
        return [];
    }
    $sql = "SELECT idusuario, username, COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) AS nome_exibicao, fotousuario, modo_treinador
              FROM usuarios
             WHERE lower(username) = lower(:username)
               AND idusuario <> :atual
               AND statususuario = 'Ativo'
               AND descobrivel = TRUE";
    if ($exigirTreinador) {
        $sql .= ' AND modo_treinador = TRUE';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username, ':atual' => $idAtual]);
    return $stmt->fetch() ?: [];
}

function treinadorBuscarPessoas(PDO $pdo, string $termo, string $idAtual, bool $exigirTreinador = false, int $limite = 8): array
{
    $termo = trim($termo);
    if ($termo === '') return [];
    $username = ltrim(stridebr_lower($termo), '@');
    $likeName = '%' . $termo . '%';
    $likeUsername = '%' . $username . '%';
    $sql = "SELECT idusuario, username, COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) AS nome_exibicao, fotousuario, modo_treinador
              FROM usuarios
             WHERE idusuario <> :atual
               AND statususuario = 'Ativo'
               AND descobrivel = TRUE
               AND (:treinador = FALSE OR modo_treinador = TRUE)
               AND (username ILIKE :username OR COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) ILIKE :nome)
             ORDER BY CASE WHEN lower(username) = lower(:exato) THEN 0 ELSE 1 END, nome_exibicao, username
             LIMIT :limite";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':atual', $idAtual);
    $stmt->bindValue(':treinador', $exigirTreinador, PDO::PARAM_BOOL);
    $stmt->bindValue(':username', $likeUsername);
    $stmt->bindValue(':nome', $likeName);
    $stmt->bindValue(':exato', $username);
    $stmt->bindValue(':limite', max(1, min(20, $limite)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function treinadorVinculo(PDO $pdo, string $idVinculo): array
{
    $stmt = $pdo->prepare('SELECT * FROM vinculos_treinador_atleta WHERE idvinculo = :id LIMIT 1');
    $stmt->execute([':id' => $idVinculo]);
    return $stmt->fetch() ?: [];
}

function treinadorVinculoAceito(PDO $pdo, string $idTreinador, string $idAtleta): array
{
    $stmt = $pdo->prepare("SELECT * FROM vinculos_treinador_atleta WHERE idtreinador = :treinador AND idatleta = :atleta AND status = 'aceito' LIMIT 1");
    $stmt->execute([':treinador' => $idTreinador, ':atleta' => $idAtleta]);
    return $stmt->fetch() ?: [];
}

function treinadorCriarConvite(PDO $pdo, string $idAtual, string $username, string $papelAtual): string
{
    if (!in_array($papelAtual, ['treinador', 'atleta'], true)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_invite'));
    }

    $atual = treinadorUsuario($pdo, $idAtual);
    if ($atual === []) {
        throw new RuntimeException(stridebr_t('planning.message.account_missing'));
    }

    if ($papelAtual === 'treinador') {
        if (!stridebr_db_bool($atual['modo_treinador'] ?? false)) {
            throw new RuntimeException(stridebr_t('planning.message.enable_invite'));
        }
        $destino = treinadorBuscarUsername($pdo, $username, $idAtual, false);
        if ($destino === []) {
            throw new RuntimeException(stridebr_t('planning.message.athlete_missing'));
        }
        $idTreinador = $idAtual;
        $idAtleta = (string) $destino['idusuario'];
        $solicitadoPor = 'treinador';
    } else {
        $destino = treinadorBuscarUsername($pdo, $username, $idAtual, true);
        if ($destino === []) {
            throw new RuntimeException(stridebr_t('planning.message.coach_missing'));
        }
        $idTreinador = (string) $destino['idusuario'];
        $idAtleta = $idAtual;
        $solicitadoPor = 'atleta';
    }

    $existing = $pdo->prepare("SELECT status FROM vinculos_treinador_atleta WHERE idtreinador = :treinador AND idatleta = :atleta AND status IN ('pendente', 'aceito') LIMIT 1");
    $existing->execute([':treinador' => $idTreinador, ':atleta' => $idAtleta]);
    $status = $existing->fetchColumn();
    if ($status === 'aceito') {
        throw new RuntimeException(stridebr_t('planning.message.link_active'));
    }
    if ($status === 'pendente') {
        throw new RuntimeException(stridebr_t('planning.message.invite_pending'));
    }

    $id = stridebr_generate_id();
    $stmt = $pdo->prepare("INSERT INTO vinculos_treinador_atleta (idvinculo, idtreinador, idatleta, solicitado_por) VALUES (:id, :treinador, :atleta, :solicitado_por)");
    $stmt->execute([
        ':id' => $id,
        ':treinador' => $idTreinador,
        ':atleta' => $idAtleta,
        ':solicitado_por' => $solicitadoPor,
    ]);
    return $id;
}

function treinadorResponderVinculo(PDO $pdo, string $idAtual, string $idVinculo, string $acao): void
{
    if (!in_array($acao, ['aceitar', 'recusar'], true)) {
        throw new InvalidArgumentException(stridebr_t('planning.message.invalid_action'));
    }
    $vinculo = treinadorVinculo($pdo, $idVinculo);
    if ($vinculo === [] || $vinculo['status'] !== 'pendente') {
        throw new RuntimeException(stridebr_t('planning.message.invite_missing'));
    }

    $podeResponder = ($vinculo['solicitado_por'] === 'treinador' && $vinculo['idatleta'] === $idAtual)
        || ($vinculo['solicitado_por'] === 'atleta' && $vinculo['idtreinador'] === $idAtual);
    if (!$podeResponder) {
        throw new RuntimeException(stridebr_t('planning.message.invite_forbidden'));
    }

    if ($vinculo['solicitado_por'] === 'atleta' && $acao === 'aceitar') {
        $user = treinadorUsuario($pdo, $idAtual);
        if (!stridebr_db_bool($user['modo_treinador'] ?? false)) {
            throw new RuntimeException(stridebr_t('planning.message.enable_accept'));
        }
    }

    $status = $acao === 'aceitar' ? 'aceito' : 'recusado';
    $stmt = $pdo->prepare("UPDATE vinculos_treinador_atleta
                             SET status = :status,
                                 data_atualizacao = NOW(),
                                 aceito_em = CASE WHEN :status_aceito = 'aceito' THEN NOW() ELSE aceito_em END,
                                 encerrado_em = CASE WHEN :status_recusado = 'recusado' THEN NOW() ELSE encerrado_em END
                           WHERE idvinculo = :id AND status = 'pendente'");
    $stmt->execute([
        ':status' => $status,
        ':status_aceito' => $status,
        ':status_recusado' => $status,
        ':id' => $idVinculo,
    ]);
}

function treinadorEncerrarVinculo(PDO $pdo, string $idAtual, string $idVinculo): void
{
    $vinculo = treinadorVinculo($pdo, $idVinculo);
    if ($vinculo === [] || $vinculo['status'] !== 'aceito' || !in_array($idAtual, [(string) $vinculo['idtreinador'], (string) $vinculo['idatleta']], true)) {
        throw new RuntimeException(stridebr_t('planning.message.link_missing'));
    }
    $stmt = $pdo->prepare("UPDATE vinculos_treinador_atleta SET status = 'encerrado', encerrado_em = NOW(), data_atualizacao = NOW() WHERE idvinculo = :id AND status = 'aceito'");
    $stmt->execute([':id' => $idVinculo]);
}

function treinadorAtualizarPermissoes(PDO $pdo, string $idAtleta, string $idVinculo, array $dados): void
{
    $vinculo = treinadorVinculo($pdo, $idVinculo);
    if ($vinculo === [] || $vinculo['status'] !== 'aceito' || $vinculo['idatleta'] !== $idAtleta) {
        throw new RuntimeException(stridebr_t('planning.message.link_missing'));
    }
    $stmt = $pdo->prepare('UPDATE vinculos_treinador_atleta SET pode_prescrever = :prescrever, pode_ver_cronograma = :cronograma, pode_ver_atividades = :atividades, pode_ver_feedback = :feedback, data_atualizacao = NOW() WHERE idvinculo = :id');
    $stmt->bindValue(':prescrever', isset($dados['pode_prescrever']), PDO::PARAM_BOOL);
    $stmt->bindValue(':cronograma', isset($dados['pode_ver_cronograma']), PDO::PARAM_BOOL);
    $stmt->bindValue(':atividades', isset($dados['pode_ver_atividades']), PDO::PARAM_BOOL);
    $stmt->bindValue(':feedback', isset($dados['pode_ver_feedback']), PDO::PARAM_BOOL);
    $stmt->bindValue(':id', $idVinculo);
    $stmt->execute();
}

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

    $rows = [];
    foreach (array_slice($exercicios, 0, 100) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $nome = trim((string) ($row['nome'] ?? ''));
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
        foreach (['repeticoes', 'carga', 'descanso'] as $field) {
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
            'nome' => $nome,
            'series' => $series,
            'repeticoes' => $fields['repeticoes'],
            'carga' => $fields['carga'],
            'descanso' => $fields['descanso'],
            'observacoes' => $observacoes !== '' ? $observacoes : null,
        ];
    }

    $id = stridebr_generate_id();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO treinos_agendados (idagendamento, idatleta, idcriador, idvinculo, data_treino, hora_inicio, duracao_prevista_min, titulo, descricao, origem, status, publicado_em) VALUES (:id, :atleta, :criador, :vinculo, :data, :hora, :duracao, :titulo, :descricao, 'treinador', :status, CASE WHEN :status_publicado = 'publicado' THEN NOW() ELSE NULL END)");
        $stmt->execute([
            ':id' => $id,
            ':atleta' => $idAtleta,
            ':criador' => $idTreinador,
            ':vinculo' => $vinculo['idvinculo'],
            ':data' => $data,
            ':hora' => $hora !== '' ? $hora : null,
            ':duracao' => $duracao,
            ':titulo' => $titulo,
            ':descricao' => $descricao !== '' ? $descricao : null,
            ':status' => $status,
            ':status_publicado' => $status,
        ]);
        $insert = $pdo->prepare('INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio, idagendamento, nome_snapshot, series, repeticoes, carga, descanso, observacoes, ordem) VALUES (:id, :agendamento, :nome, :series, :repeticoes, :carga, :descanso, :observacoes, :ordem)');
        foreach ($rows as $index => $row) {
            $insert->execute([
                ':id' => stridebr_generate_id(),
                ':agendamento' => $id,
                ':nome' => $row['nome'],
                ':series' => $row['series'],
                ':repeticoes' => $row['repeticoes'],
                ':carga' => $row['carga'],
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
    $exerciseStmt = $pdo->prepare('SELECT nome_snapshot, series, repeticoes, carga, descanso, observacoes FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');
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

    $rows = [];
    foreach (array_slice($exercicios, 0, 100) as $row) {
        if (!is_array($row)) continue;
        $nome = trim((string) ($row['nome'] ?? ''));
        if ($nome === '') continue;
        if (stridebr_length($nome) > 120) throw new InvalidArgumentException(stridebr_t('planning.message.exercise_long'));
        $seriesRaw = trim((string) ($row['series'] ?? ''));
        $series = null;
        if ($seriesRaw !== '') {
            if (filter_var($seriesRaw, FILTER_VALIDATE_INT) === false || (int) $seriesRaw < 1 || (int) $seriesRaw > 99) throw new InvalidArgumentException(stridebr_t('planning.message.invalid_sets'));
            $series = (int) $seriesRaw;
        }
        $values = [];
        foreach (['repeticoes', 'carga', 'descanso'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if (stridebr_length($value) > 40) throw new InvalidArgumentException(stridebr_t('planning.message.field_long'));
            $values[$field] = $value !== '' ? $value : null;
        }
        $observacoes = trim((string) ($row['observacoes'] ?? ''));
        if (stridebr_length($observacoes) > 1000) throw new InvalidArgumentException(stridebr_t('planning.message.notes_long'));
        $rows[] = ['nome'=>$nome,'series'=>$series,'repeticoes'=>$values['repeticoes'],'carga'=>$values['carga'],'descanso'=>$values['descanso'],'observacoes'=>$observacoes !== '' ? $observacoes : null];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE treinos_agendados SET data_treino=:data, hora_inicio=:hora, duracao_prevista_min=:duracao, titulo=:titulo, descricao=:descricao, status=:status, publicado_em=CASE WHEN :status_publicado='publicado' THEN COALESCE(publicado_em,NOW()) ELSE publicado_em END, data_atualizacao=NOW() WHERE idagendamento=:id AND idcriador=:treinador AND origem='treinador'");
        $stmt->execute([':data'=>$data,':hora'=>$hora !== '' ? $hora : null,':duracao'=>$duracao,':titulo'=>$titulo,':descricao'=>$descricao !== '' ? $descricao : null,':status'=>$status,':status_publicado'=>$status,':id'=>$idAgendamento,':treinador'=>$idTreinador]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException(stridebr_t('trainer.prescription_edit_forbidden'));
        $pdo->prepare('DELETE FROM treinos_agendados_exercicios WHERE idagendamento=:id')->execute([':id'=>$idAgendamento]);
        $insert = $pdo->prepare('INSERT INTO treinos_agendados_exercicios (idagendamento_exercicio, idagendamento, nome_snapshot, series, repeticoes, carga, descanso, observacoes, ordem) VALUES (:id,:agendamento,:nome,:series,:repeticoes,:carga,:descanso,:observacoes,:ordem)');
        foreach ($rows as $index => $row) {
            $insert->execute([':id'=>stridebr_generate_id(),':agendamento'=>$idAgendamento,':nome'=>$row['nome'],':series'=>$row['series'],':repeticoes'=>$row['repeticoes'],':carga'=>$row['carga'],':descanso'=>$row['descanso'],':observacoes'=>$row['observacoes'],':ordem'=>$index+1]);
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
