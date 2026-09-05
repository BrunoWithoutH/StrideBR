<?php

declare(strict_types=1);

function notificacaoCriar(PDO $pdo, string $idUsuario, string $tipo, string $titulo, string $mensagem = '', string $url = '', array $dados = []): string
{
    if (!stridebr_feature_enabled($pdo, 'notifications.enabled', true)) return '';
    $tipo = trim($tipo);
    $titulo = trim($titulo);
    if ($idUsuario === '' || $tipo === '' || $titulo === '') return '';
    $id = stridebr_generate_id();
    try {
        $stmt = $pdo->prepare('INSERT INTO notificacoes (idnotificacao, idusuario, tipo, titulo, mensagem, url, dados) VALUES (:id, :usuario, :tipo, :titulo, :mensagem, :url, CAST(:dados AS jsonb))');
        $stmt->execute([
            ':id' => $id,
            ':usuario' => $idUsuario,
            ':tipo' => substr($tipo, 0, 50),
            ':titulo' => substr($titulo, 0, 160),
            ':mensagem' => trim($mensagem) !== '' ? $mensagem : null,
            ':url' => trim($url) !== '' ? $url : null,
            ':dados' => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        return $id;
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') return '';
        throw $e;
    }
}

function notificacaoListar(PDO $pdo, string $idUsuario, int $limite = 50): array
{
    $limite = max(1, min(100, $limite));
    try {
        $stmt = $pdo->prepare("SELECT idnotificacao, tipo, titulo, mensagem, url, dados, lida_em, data_criacao FROM notificacoes WHERE idusuario = :usuario ORDER BY data_criacao DESC LIMIT {$limite}");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') return [];
        throw $e;
    }
}


function notificacaoApresentar(array $notificacao): array
{
    $tipo = (string) ($notificacao['tipo'] ?? '');
    $dados = is_array($notificacao['dados'] ?? null) ? $notificacao['dados'] : json_decode((string) ($notificacao['dados'] ?? ''), true);
    if (!is_array($dados)) $dados = [];
    $map = [
        'amizade_solicitacao' => ['notification.friend_request.title', 'notification.friend_request.message'],
        'amizade_aceita' => ['notification.friend_accepted.title', 'notification.friend_accepted.message'],
        'cronograma_sincronizado_aceito' => ['notification.schedule_accepted.title', 'notification.schedule_accepted.message'],
        'cronograma_sincronizado_alterado' => ['notification.schedule_updated.title', 'notification.schedule_updated.message'],
        'treinador_convite' => ['notification.coach_invite.title', 'notification.coach_invite.message'],
        'treinador_solicitacao' => ['notification.athlete_request.title', 'notification.athlete_request.message'],
        'treinador_vinculo_aceito' => ['notification.coach_link_accepted.title', 'notification.coach_link_accepted.message'],
        'treino_prescrito' => ['notification.prescription.title', 'notification.prescription.message'],
        'treino_feedback' => ['notification.feedback.title', 'notification.feedback.message'],
    ];
    if ($tipo === 'cronograma_sincronizado_convite') {
        $nome = trim((string) ($dados['schedule_name'] ?? ''));
        return [
            'titulo' => stridebr_t('notification.schedule_invite.title'),
            'mensagem' => $nome !== '' ? stridebr_t('notification.schedule_invite.message', ['name' => $nome]) : stridebr_t('notification.schedule_invite.message_generic'),
        ];
    }
    if (isset($map[$tipo])) {
        return ['titulo' => stridebr_t($map[$tipo][0]), 'mensagem' => stridebr_t($map[$tipo][1])];
    }
    return ['titulo' => (string) ($notificacao['titulo'] ?? ''), 'mensagem' => (string) ($notificacao['mensagem'] ?? '')];
}

function notificacaoContarNaoLidas(PDO $pdo, string $idUsuario): int
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE idusuario = :usuario AND lida_em IS NULL');
        $stmt->execute([':usuario' => $idUsuario]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') return 0;
        throw $e;
    }
}

function notificacaoMarcarLida(PDO $pdo, string $idUsuario, string $idNotificacao): bool
{
    $stmt = $pdo->prepare('UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE idnotificacao = :id AND idusuario = :usuario');
    $stmt->execute([':id' => $idNotificacao, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function notificacaoMarcarTodasLidas(PDO $pdo, string $idUsuario): int
{
    $stmt = $pdo->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE idusuario = :usuario AND lida_em IS NULL');
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->rowCount();
}

function notificacaoCronogramaSincronizadoAlterado(PDO $pdo, string $idDono, string $idCronograma, string $mensagem = 'O cronograma foi atualizado.', ?string $url = null): int
{
    if ($idDono === '' || $idCronograma === '' || !stridebr_feature_enabled($pdo, 'notifications.enabled', true)) return 0;
    try {
        $schedule = $pdo->prepare('SELECT nome FROM cronogramas WHERE idcronograma = :cronograma AND idusuario = :dono LIMIT 1');
        $schedule->execute([':cronograma' => $idCronograma, ':dono' => $idDono]);
        $nome = trim((string) ($schedule->fetchColumn() ?: 'Cronograma'));
        $viewers = $pdo->prepare("SELECT DISTINCT idusuario_destino FROM cronograma_compartilhamentos WHERE idcronograma_origem = :cronograma AND idusuario_origem = :dono AND tipo = 'sincronizado' AND status = 'aceito'");
        $viewers->execute([':cronograma' => $idCronograma, ':dono' => $idDono]);
        $targetUrl = $url ?? ('/user/cronograma-sincronizado.php?id=' . rawurlencode($idCronograma));
        $count = 0;
        foreach ($viewers->fetchAll(PDO::FETCH_COLUMN) as $destino) {
            $destino = (string) $destino;
            if ($destino === '' || $destino === $idDono) continue;
            $recent = $pdo->prepare("SELECT idnotificacao FROM notificacoes WHERE idusuario = :usuario AND tipo = 'cronograma_sincronizado_alterado' AND lida_em IS NULL AND dados->>'schedule_id' = :cronograma AND data_criacao >= NOW() - INTERVAL '2 minutes' ORDER BY data_criacao DESC LIMIT 1");
            $recent->execute([':usuario' => $destino, ':cronograma' => $idCronograma]);
            $idRecent = (string) ($recent->fetchColumn() ?: '');
            if ($idRecent !== '') {
                $update = $pdo->prepare('UPDATE notificacoes SET titulo = :titulo, mensagem = :mensagem, url = :url, data_criacao = NOW() WHERE idnotificacao = :id AND idusuario = :usuario');
                $update->execute([
                    ':titulo' => 'Cronograma atualizado',
                    ':mensagem' => $mensagem . ' · ' . $nome,
                    ':url' => $targetUrl,
                    ':id' => $idRecent,
                    ':usuario' => $destino,
                ]);
            } else {
                notificacaoCriar($pdo, $destino, 'cronograma_sincronizado_alterado', 'Cronograma atualizado', $mensagem . ' · ' . $nome, $targetUrl, ['schedule_id' => $idCronograma]);
            }
            $count++;
        }
        return $count;
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42P01', '42703'], true)) return 0;
        throw $e;
    }
}
