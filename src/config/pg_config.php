<?php

declare(strict_types=1);

$dbhost = getenv('STRIDEBR_DB_HOST') ?: 'localhost';
$dbport = getenv('STRIDEBR_DB_PORT') ?: '5432';
$dbname = getenv('STRIDEBR_DB_NAME') ?: 'stridebr';
$dbuser = getenv('STRIDEBR_DB_USER') ?: 'stridebr';
$dbpassword = getenv('STRIDEBR_DB_PASSWORD') ?: '';

try {
    $dsn = "pgsql:host={$dbhost};port={$dbport};dbname={$dbname}";

    $pdo = new PDO($dsn, $dbuser, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    $pdo->exec('SET search_path TO stridebr, public');

    if (function_exists('stridebr_is_logged_in') && stridebr_is_logged_in()) {
        try {
            $guard = $pdo->prepare("SELECT statususuario, sessao_versao, papelusuario, COALESCE(NULLIF(nome_exibicao, ''), nomeusuario) AS nome_exibicao, username, emailusuario, fotousuario, onboarding_concluido, termos_versao, privacidade_versao FROM usuarios WHERE idusuario = :id LIMIT 1");
            $guard->execute([':id' => (string) $_SESSION['IdUsuario']]);
            $sessionUser = $guard->fetch();
            $sessionVersion = (int) ($_SESSION['SessaoVersao'] ?? 1);
            if (!$sessionUser || $sessionUser['statususuario'] !== 'Ativo' || (int) $sessionUser['sessao_versao'] !== $sessionVersion) {
                stridebr_destroy_session();
                if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/function/')) {
                    http_response_code(401);
                    exit('Sessão inválida.');
                }
                header('Location: /login.php?session=invalid');
                exit;
            }
            $_SESSION['NomeExibicao'] = $sessionUser['nome_exibicao'];
            $_SESSION['Username'] = $sessionUser['username'] ?? null;
            $_SESSION['PapelUsuario'] = $sessionUser['papelusuario'] ?? 'user';
            $_SESSION['EmailUsuario'] = $sessionUser['emailusuario'];
            $_SESSION['FotoUsuario'] = $sessionUser['fotousuario'] ?: null;
            $_SESSION['OnboardingConcluido'] = stridebr_db_bool($sessionUser['onboarding_concluido'] ?? false);
            $currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
            $legalAllowed = in_array($currentPath, ['/accept-legal.php', '/function/logout.php', '/pages/legal/terms.php', '/pages/legal/privacy.php', '/pages/legal/cookies.php'], true);
            if (!$legalAllowed && stridebr_feature_enabled($pdo, 'legal.reaccept.required', false)) {
                $termsOutdated = (string) ($sessionUser['termos_versao'] ?? '') !== stridebr_terms_version();
                $privacyOutdated = (string) ($sessionUser['privacidade_versao'] ?? '') !== stridebr_privacy_version();
                if ($termsOutdated || $privacyOutdated) {
                    $_SESSION['LegalPending'] = true;
                    header('Location: /accept-legal.php');
                    exit;
                }
            }
        } catch (PDOException $guardError) {
            if ($guardError->getCode() !== '42703') {
                throw $guardError;
            }
        }
    }

} catch (PDOException $e) {
    error_log('StrideBR database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}
