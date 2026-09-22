<?php

declare(strict_types=1);

function treinadorVinculoLower(string $value): string
{
    if (function_exists('stridebr_lower')) return stridebr_lower($value);
    if (function_exists('stridebr_api_lower')) return stridebr_api_lower($value);
    return strtolower($value);
}

function treinadorVinculoUsernameValido(string $username): bool
{
    if (function_exists('stridebr_username_is_valid')) return stridebr_username_is_valid($username);
    if (in_array($username, [
        'admin', 'administrator', 'moderator', 'owner', 'root', 'system', 'sistema',
        'stridebr', 'official', 'oficial', 'support', 'suporte', 'security', 'seguranca',
        'api', 'login', 'logout', 'signup', 'settings', 'feedback', 'null', 'undefined',
    ], true)) return false;
    return preg_match('/^(?!.*[._-]{2})[a-z0-9][a-z0-9._-]{1,38}[a-z0-9]$/', $username) === 1;
}

function treinadorVinculoGerarId(): string
{
    if (function_exists('stridebr_generate_id')) return stridebr_generate_id();
    if (function_exists('stridebr_api_id')) return stridebr_api_id();
    $bytes = random_bytes(18);
    return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, 21);
}

function treinadorUsuario(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare("SELECT idusuario, username, COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) AS nome_exibicao, fotousuario, modo_treinador, descobrivel, statususuario FROM usuarios WHERE idusuario = :id LIMIT 1");
    $stmt->execute([':id' => $idUsuario]);
    return $stmt->fetch() ?: [];
}

function treinadorBuscarUsername(PDO $pdo, string $username, string $idAtual, bool $exigirTreinador = false): array
{
    $username = treinadorVinculoLower(ltrim(trim($username), '@'));
    if (!treinadorVinculoUsernameValido($username)) {
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
    $username = ltrim(treinadorVinculoLower($termo), '@');
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

    $id = treinadorVinculoGerarId();
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
