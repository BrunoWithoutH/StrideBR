<?php

declare(strict_types=1);
require_once __DIR__ . '/environment.php';

function stridebr_mail_transport(): string { return strtolower(trim((string) (getenv('STRIDEBR_MAIL_TRANSPORT') ?: 'disabled'))); }
function stridebr_mail_is_configured(): bool
{
    if (!filter_var(trim((string) getenv('STRIDEBR_MAIL_FROM')), FILTER_VALIDATE_EMAIL)) return false;
    if (stridebr_mail_transport() === 'mail') return stridebr_is_development() && function_exists('mail');
    if (stridebr_mail_transport() !== 'smtp') return false;
    $host = trim((string) getenv('STRIDEBR_SMTP_HOST'));
    $port = filter_var(getenv('STRIDEBR_SMTP_PORT') ?: '587', FILTER_VALIDATE_INT);
    $encryption = strtolower((string) (getenv('STRIDEBR_SMTP_ENCRYPTION') ?: 'tls'));
    $auth = stridebr_env_enabled('STRIDEBR_SMTP_AUTH', true);
    return preg_match('/^[a-zA-Z0-9.-]+$/D', $host) === 1 && $port >= 1 && $port <= 65535
        && in_array($encryption, stridebr_is_development() ? ['tls','ssl','none'] : ['tls','ssl'], true)
        && (!$auth || (getenv('STRIDEBR_SMTP_USERNAME') && getenv('STRIDEBR_SMTP_PASSWORD')));
}
function stridebr_send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !stridebr_mail_is_configured()) {
        error_log('StrideBR mail: unavailable or invalid recipient');
        return false;
    }
    try {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        $mail->Timeout = 10;
        if (stridebr_mail_transport() === 'smtp') {
            $mail->isSMTP();
            $mail->Host = trim((string) getenv('STRIDEBR_SMTP_HOST'));
            $mail->Port = (int) (getenv('STRIDEBR_SMTP_PORT') ?: 587);
            $mail->SMTPAuth = stridebr_env_enabled('STRIDEBR_SMTP_AUTH', true);
            $mail->Username = (string) getenv('STRIDEBR_SMTP_USERNAME');
            $mail->Password = (string) getenv('STRIDEBR_SMTP_PASSWORD');
            $encryption = strtolower((string) (getenv('STRIDEBR_SMTP_ENCRYPTION') ?: 'tls'));
            $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
            $mail->SMTPAutoTLS = $encryption !== 'none';
        } else $mail->isMail();
        $mail->setFrom(trim((string) getenv('STRIDEBR_MAIL_FROM')), (string) (getenv('STRIDEBR_MAIL_FROM_NAME') ?: 'StrideBR'));
        $support = trim((string) getenv('STRIDEBR_SUPPORT_EMAIL'));
        if (filter_var($support, FILTER_VALIDATE_EMAIL) && strcasecmp($support, $mail->From) !== 0) {
            $mail->addReplyTo($support);
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Throwable $e) {
        // SMTP errors can contain credentials or message content: do not log them.
        error_log('StrideBR mail: delivery failed');
        return false;
    }
}
