<?php
return function (PDO $pdo): void {
    AlphaTest::assert(stridebr_password_is_valid_length('12345678'), 'Senha mínima válida foi rejeitada');
    AlphaTest::assert(!stridebr_password_is_valid_length(str_repeat('x', 129)), 'Senha excessiva foi aceita');
    AlphaTest::assert(stridebr_username_is_valid('atleta_01'), 'Username válido foi rejeitado');
    AlphaTest::assert(!stridebr_username_is_valid('admin'), 'Username reservado foi aceito');

    $authFlags = $pdo->query("SELECT chave, ativo FROM feature_flags WHERE chave IN ('auth.email_verification.enabled','auth.email_verification.required','auth.password_reset.enabled')")->fetchAll(PDO::FETCH_KEY_PAIR);
    AlphaTest::assert(stridebr_db_bool($authFlags['auth.email_verification.enabled'] ?? false), 'Verificação de e-mail deveria estar habilitada para a release');
    AlphaTest::assert(stridebr_db_bool($authFlags['auth.email_verification.required'] ?? false), 'Confirmação de e-mail deveria estar obrigatória quando o envio estiver configurado');
    AlphaTest::assert(stridebr_db_bool($authFlags['auth.password_reset.enabled'] ?? false), 'Recuperação de senha deveria estar habilitada para a release');

    $previousTransport = getenv('STRIDEBR_MAIL_TRANSPORT');
    putenv('STRIDEBR_MAIL_TRANSPORT=mail'); // Explicit legacy transport is development-only.
    $previousFrom = getenv('STRIDEBR_MAIL_FROM');
    putenv('STRIDEBR_MAIL_FROM=nao-e-email');
    AlphaTest::assert(!stridebr_mail_is_configured(), 'Configuração de e-mail inválida foi aceita');
    putenv('STRIDEBR_MAIL_FROM=no-reply@example.test');
    AlphaTest::assert(stridebr_mail_is_configured(), 'Configuração de e-mail válida não foi reconhecida');
    if ($previousFrom === false) putenv('STRIDEBR_MAIL_FROM'); else putenv('STRIDEBR_MAIL_FROM=' . $previousFrom);
    if ($previousTransport === false) putenv('STRIDEBR_MAIL_TRANSPORT'); else putenv('STRIDEBR_MAIL_TRANSPORT=' . $previousTransport);
    $scope = 'alpha_test_login';
    stridebr_auth_limit_record_failure($pdo, $scope, 'person@example.invalid', 2, 300, 60);
    stridebr_auth_limit_record_failure($pdo, $scope, 'person@example.invalid', 2, 300, 60);
    AlphaTest::assert(stridebr_auth_limit_is_blocked($pdo, $scope, 'person@example.invalid'), 'Rate limit persistente não bloqueou');
    stridebr_auth_limit_clear($pdo, $scope, 'person@example.invalid');
};
