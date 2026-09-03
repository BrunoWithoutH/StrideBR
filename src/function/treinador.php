<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

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
        throw new InvalidArgumentException('Tipo de convite inválido.');
    }

    $atual = treinadorUsuario($pdo, $idAtual);
    if ($atual === []) {
        throw new RuntimeException('Conta não encontrada.');
    }

    if ($papelAtual === 'treinador') {
        if (!stridebr_db_bool($atual['modo_treinador'] ?? false)) {
            throw new RuntimeException('Ative o modo treinador antes de convidar atletas.');
        }
        $destino = treinadorBuscarUsername($pdo, $username, $idAtual, false);
        if ($destino === []) {
            throw new RuntimeException('Atleta não encontrado ou indisponível para busca.');
        }
        $idTreinador = $idAtual;
        $idAtleta = (string) $destino['idusuario'];
        $solicitadoPor = 'treinador';
    } else {
        $destino = treinadorBuscarUsername($pdo, $username, $idAtual, true);
        if ($destino === []) {
            throw new RuntimeException('Treinador não encontrado ou indisponível para busca.');
        }
        $idTreinador = (string) $destino['idusuario'];
        $idAtleta = $idAtual;
        $solicitadoPor = 'atleta';
    }

    $existing = $pdo->prepare("SELECT status FROM vinculos_treinador_atleta WHERE idtreinador = :treinador AND idatleta = :atleta AND status IN ('pendente', 'aceito') LIMIT 1");
    $existing->execute([':treinador' => $idTreinador, ':atleta' => $idAtleta]);
    $status = $existing->fetchColumn();
    if ($status === 'aceito') {
        throw new RuntimeException('Esse vínculo já está ativo.');
    }
    if ($status === 'pendente') {
        throw new RuntimeException('Já existe um convite pendente entre vocês.');
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
        throw new InvalidArgumentException('Ação inválida.');
    }
    $vinculo = treinadorVinculo($pdo, $idVinculo);
    if ($vinculo === [] || $vinculo['status'] !== 'pendente') {
        throw new RuntimeException('Convite não encontrado.');
    }

    $podeResponder = ($vinculo['solicitado_por'] === 'treinador' && $vinculo['idatleta'] === $idAtual)
        || ($vinculo['solicitado_por'] === 'atleta' && $vinculo['idtreinador'] === $idAtual);
    if (!$podeResponder) {
        throw new RuntimeException('Você não pode responder esse convite.');
    }

    if ($vinculo['solicitado_por'] === 'atleta' && $acao === 'aceitar') {
        $user = treinadorUsuario($pdo, $idAtual);
        if (!stridebr_db_bool($user['modo_treinador'] ?? false)) {
            throw new RuntimeException('Ative o modo treinador antes de aceitar atletas.');
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
        throw new RuntimeException('Vínculo não encontrado.');
    }
    $stmt = $pdo->prepare("UPDATE vinculos_treinador_atleta SET status = 'encerrado', encerrado_em = NOW(), data_atualizacao = NOW() WHERE idvinculo = :id AND status = 'aceito'");
    $stmt->execute([':id' => $idVinculo]);
}

function treinadorAtualizarPermissoes(PDO $pdo, string $idAtleta, string $idVinculo, array $dados): void
{
    $vinculo = treinadorVinculo($pdo, $idVinculo);
    if ($vinculo === [] || $vinculo['status'] !== 'aceito' || $vinculo['idatleta'] !== $idAtleta) {
        throw new RuntimeException('Vínculo não encontrado.');
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
        throw new RuntimeException('Ative o modo treinador para prescrever treinos.');
    }
    $vinculo = treinadorVinculoAceito($pdo, $idTreinador, $idAtleta);
    if ($vinculo === [] || !stridebr_db_bool($vinculo['pode_prescrever'] ?? false)) {
        throw new RuntimeException('Esse atleta não autorizou prescrições.');
    }

    $titulo = trim((string) ($dados['titulo'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $data = trim((string) ($dados['data_treino'] ?? ''));
    $hora = trim((string) ($dados['hora_inicio'] ?? ''));
    $duracaoRaw = trim((string) ($dados['duracao_prevista_min'] ?? ''));
    $status = (string) ($dados['status'] ?? 'rascunho');

    if ($titulo === '' || stridebr_length($titulo) > 120) {
        throw new InvalidArgumentException('Informe um título de até 120 caracteres.');
    }
    if (stridebr_length($descricao) > 5000) {
        throw new InvalidArgumentException('A descrição deve ter no máximo 5.000 caracteres.');
    }
    if (!treinadorDataValida($data)) {
        throw new InvalidArgumentException('Escolha uma data entre hoje e os próximos dois anos.');
    }
    if (!treinadorHoraValida($hora)) {
        throw new InvalidArgumentException('Horário inválido.');
    }
    $duracao = null;
    if ($duracaoRaw !== '') {
        if (filter_var($duracaoRaw, FILTER_VALIDATE_INT) === false || (int) $duracaoRaw < 1 || (int) $duracaoRaw > 1440) {
            throw new InvalidArgumentException('Duração prevista inválida.');
        }
        $duracao = (int) $duracaoRaw;
    }
    if (!in_array($status, ['rascunho', 'publicado'], true)) {
        throw new InvalidArgumentException('Status da prescrição inválido.');
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
            throw new InvalidArgumentException('Um dos exercícios tem nome maior que 120 caracteres.');
        }
        $seriesRaw = trim((string) ($row['series'] ?? ''));
        $series = null;
        if ($seriesRaw !== '') {
            if (filter_var($seriesRaw, FILTER_VALIDATE_INT) === false || (int) $seriesRaw < 1 || (int) $seriesRaw > 99) {
                throw new InvalidArgumentException('Quantidade de séries inválida.');
            }
            $series = (int) $seriesRaw;
        }
        $fields = [];
        foreach (['repeticoes', 'carga', 'descanso'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if (stridebr_length($value) > 40) {
                throw new InvalidArgumentException('Um campo de exercício ultrapassou 40 caracteres.');
            }
            $fields[$field] = $value !== '' ? $value : null;
        }
        $observacoes = trim((string) ($row['observacoes'] ?? ''));
        if (stridebr_length($observacoes) > 1000) {
            throw new InvalidArgumentException('Observações de exercício devem ter no máximo 1.000 caracteres.');
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
        throw new RuntimeException('Prescrição não encontrada, já publicada ou sem permissão ativa do atleta.');
    }
}

function treinadorCancelarPrescricao(PDO $pdo, string $idAtual, string $idAgendamento): void
{
    $stmt = $pdo->prepare("UPDATE treinos_agendados SET status = 'cancelado', data_atualizacao = NOW() WHERE idagendamento = :id AND (idcriador = :atual_criador OR idatleta = :atual_atleta) AND status IN ('rascunho', 'publicado')");
    $stmt->execute([':id' => $idAgendamento, ':atual_criador' => $idAtual, ':atual_atleta' => $idAtual]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Treino agendado não encontrado ou não pode mais ser cancelado.');
    }
}

function treinadorSalvarFeedback(PDO $pdo, string $idAtleta, string $idAgendamento, int $nota, string $feedback): void
{
    $feedback = trim($feedback);
    if ($nota < 1 || $nota > 5) {
        throw new InvalidArgumentException('Escolha uma nota entre 1 e 5.');
    }
    if (stridebr_length($feedback) > 2000) {
        throw new InvalidArgumentException('O feedback deve ter no máximo 2.000 caracteres.');
    }
    $stmt = $pdo->prepare("UPDATE treinos_agendados SET nota_atleta = :nota, feedback_atleta = :feedback, feedback_em = NOW(), data_atualizacao = NOW() WHERE idagendamento = :id AND idatleta = :atleta AND origem = 'treinador' AND status = 'concluido'");
    $stmt->execute([
        ':nota' => $nota,
        ':feedback' => $feedback !== '' ? $feedback : null,
        ':id' => $idAgendamento,
        ':atleta' => $idAtleta,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Treino concluído não encontrado.');
    }
}
