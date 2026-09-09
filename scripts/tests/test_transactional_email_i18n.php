<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/i18n.php';

$assertions = 0;
$ok = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "✗ transactional email i18n: {$message}\n");
        exit(1);
    }
};

$expected = [
    'pt-BR' => [
        'auth.reset_email_body' => [
            'Olá, Bruno Sem H.',
            'Recebemos uma solicitação para redefinir sua senha do StrideBR.',
            'Seu código de redefinição é:',
            '091101',
            'Ele expira em 15 minutos e só pode ser usado uma vez.',
            'Se você não pediu a redefinição, ignore esta mensagem.',
        ],
        'account.email_change_notice_body' => [
            'O e-mail de acesso da sua conta StrideBR foi alterado para bruno@example.invalid.',
            'Se você não fez essa alteração, redefina sua senha e entre em contato com o suporte.',
        ],
    ],
    'en' => [
        'auth.reset_email_body' => [
            'Hello, Bruno Sem H.',
            'We received a request to reset your StrideBR password.',
            'Your reset code is:',
            '091101',
            'It expires in 15 minutes and can only be used once.',
            'If you did not request a password reset, ignore this message.',
        ],
        'account.email_change_notice_body' => [
            'The sign-in email for your StrideBR account was changed to bruno@example.invalid.',
            'If you did not make this change, reset your password and contact support.',
        ],
    ],
];
$replacements = [
    'auth.reset_email_body' => ['name' => 'Bruno Sem H', 'code' => '091101'],
    'account.email_change_notice_body' => ['email' => 'bruno@example.invalid'],
];

foreach ($expected as $locale => $messages) {
    foreach ($messages as $key => $paragraphs) {
        $body = stridebr_t_locale($locale, $key, $replacements[$key]);
        $context = "{$locale}: {$key}";
        foreach (['\\n', '\\r', '\\t'] as $escape) {
            $ok(!str_contains($body, $escape), "{$context}: sem escape literal {$escape}");
        }
        $ok(str_contains($body, "\n"), "{$context}: contém quebras reais");
        foreach ($replacements[$key] as $placeholder => $value) {
            $ok(str_contains($body, $value), "{$context}: substitui {$placeholder}");
            $ok(!str_contains($body, '{' . $placeholder . '}'), "{$context}: sem placeholder {$placeholder} restante");
        }
        $ok(explode("\n\n", $body) === $paragraphs, "{$context}: texto e parágrafos corretos");
    }
}

printf("✓ transactional email i18n: %d assertions; 2 messages/locale; PT-BR + EN\n", $assertions);
