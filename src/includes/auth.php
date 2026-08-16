<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function stridebr_auth_create_token(PDO $pdo, string $userId, string $email, string $type, int $minutes): string
{
    if (!in_array($type, ['verificar_email', 'redefinir_senha'], true)) {
        throw new InvalidArgumentException('Tipo de token inválido.');
    }

    $cooldown = $pdo->prepare("SELECT 1 FROM auth_tokens WHERE idusuario = :usuario AND tipo = :tipo AND criado_em > NOW() - INTERVAL '2 minutes' LIMIT 1");
    $cooldown->execute([':usuario' => $userId, ':tipo' => $type]);
    if ($cooldown->fetchColumn()) {
        throw new RuntimeException('Aguarde alguns minutos antes de solicitar outro link.');
    }

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :usuario AND tipo = :tipo AND usado_em IS NULL');
        $invalidate->execute([':usuario' => $userId, ':tipo' => $type]);

        $stmt = $pdo->prepare("INSERT INTO auth_tokens (idtoken, idusuario, tipo, token_hash, email_destino, expira_em, solicitacao_ip) VALUES (:id, :usuario, :tipo, :hash, :email, NOW() + (:minutos || ' minutes')::interval, CAST(:ip AS inet))");
        $ip = stridebr_client_ip();
        $stmt->bindValue(':id', stridebr_generate_id());
        $stmt->bindValue(':usuario', $userId);
        $stmt->bindValue(':tipo', $type);
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':minutos', (string) max(1, $minutes));
        $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        $pdo->commit();
        $pdo->exec("DELETE FROM auth_tokens WHERE (usado_em IS NOT NULL AND usado_em < NOW() - INTERVAL '7 days') OR expira_em < NOW() - INTERVAL '7 days'");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $raw;
}

function stridebr_auth_find_token(PDO $pdo, string $raw, string $type): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT t.idtoken, t.idusuario, t.email_destino, t.expira_em, t.usado_em, u.emailusuario, u.nomeusuario, u.nome_exibicao, u.statususuario FROM auth_tokens t JOIN usuarios u ON u.idusuario = t.idusuario WHERE t.token_hash = :hash AND t.tipo = :tipo LIMIT 1');
    $stmt->execute([':hash' => hash('sha256', $raw), ':tipo' => $type]);
    $token = $stmt->fetch();
    if (!$token || $token['usado_em'] !== null || strtotime((string) $token['expira_em']) <= time()) {
        return null;
    }
    return $token;
}

function stridebr_send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = trim((string) (getenv('STRIDEBR_MAIL_FROM') ?: ''));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $fromName = trim((string) (getenv('STRIDEBR_MAIL_FROM_NAME') ?: 'StrideBR'));
    $fromName = preg_replace('/[\r\n]+/', ' ', $fromName) ?? 'StrideBR';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: StrideBR',
    ];
    if (function_exists('mb_encode_mimeheader')) { $subject = mb_encode_mimeheader($subject, 'UTF-8'); }
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function stridebr_send_verification_email(PDO $pdo, string $userId, string $email, string $name): bool
{
    $token = stridebr_auth_create_token($pdo, $userId, $email, 'verificar_email', 1440);
    $url = stridebr_app_url() . '/verify-email.php?token=' . rawurlencode($token);
    $body = "Olá, {$name}.\n\nConfirme seu e-mail no StrideBR usando o link abaixo:\n{$url}\n\nO link expira em 24 horas. Se você não criou essa conta, ignore esta mensagem.";
    return stridebr_send_mail($email, 'Confirme seu e-mail no StrideBR', $body);
}

function stridebr_send_password_reset_email(PDO $pdo, string $userId, string $email, string $name): bool
{
    $token = stridebr_auth_create_token($pdo, $userId, $email, 'redefinir_senha', 30);
    $url = stridebr_app_url() . '/reset-password.php?token=' . rawurlencode($token);
    $body = "Olá, {$name}.\n\nRecebemos uma solicitação para redefinir sua senha do StrideBR. Use o link abaixo:\n{$url}\n\nO link expira em 30 minutos e só pode ser usado uma vez. Se você não pediu a redefinição, ignore esta mensagem.";
    return stridebr_send_mail($email, 'Redefinição de senha do StrideBR', $body);
}
