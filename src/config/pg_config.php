<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/environment.php';
if (!stridebr_is_development()) {
    foreach (['HOST', 'NAME', 'USER', 'PASSWORD'] as $key) {
        if (trim((string) getenv('STRIDEBR_DB_' . $key)) === '') throw new RuntimeException('Missing required database configuration');
    }
}

$dbhost = getenv('STRIDEBR_DB_HOST') ?: 'localhost';
$dbport = getenv('STRIDEBR_DB_PORT') ?: '5432';
$dbname = getenv('STRIDEBR_DB_NAME') ?: 'stridebr';
$dbuser = getenv('STRIDEBR_DB_USER') ?: 'stridebr';
$dbpassword = getenv('STRIDEBR_DB_PASSWORD') ?: '';
$dbSslMode = getenv('STRIDEBR_DB_SSLMODE') ?: 'prefer';
if (!in_array($dbSslMode, ['disable','allow','prefer','require','verify-ca','verify-full'], true)) throw new RuntimeException('Invalid database SSL mode');
foreach ([$dbhost, $dbport, $dbname] as $value) {
    if (preg_match('/[;\s]/', $value)) throw new RuntimeException('Invalid database configuration');
}
$dbConnectTimeout = max(2, min(20, (int) (getenv('STRIDEBR_DB_CONNECT_TIMEOUT') ?: 6)));
$dbStatementTimeout = max(3000, min(60000, (int) (getenv('STRIDEBR_DB_STATEMENT_TIMEOUT_MS') ?: 15000)));
$dbLockTimeout = max(1000, min(30000, (int) (getenv('STRIDEBR_DB_LOCK_TIMEOUT_MS') ?: 5000)));

try {
    $dbConnectStartedAt = microtime(true);
    $dsn = "pgsql:host={$dbhost};port={$dbport};dbname={$dbname};sslmode={$dbSslMode};connect_timeout={$dbConnectTimeout}";

    $pdo = new PDO($dsn, $dbuser, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'; SET search_path TO stridebr, public; SET statement_timeout TO '{$dbStatementTimeout}ms'; SET lock_timeout TO '{$dbLockTimeout}ms'");
    if (function_exists('stridebr_timing_measure')) stridebr_timing_measure('db_connect', $dbConnectStartedAt, 'Conexão PostgreSQL');

    if (function_exists('stridebr_is_logged_in') && stridebr_is_logged_in()) {
        $currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $legalAllowed = in_array($currentPath, ['/accept-legal.php', '/function/logout.php', '/pages/legal/terms.php', '/pages/legal/privacy.php', '/pages/legal/cookies.php'], true);
        if (!$legalAllowed && empty($_SESSION['OwnerImpersonation']) && !empty($_SESSION['LegalPending'])) {
            header('Location: /accept-legal.php');
            exit;
        }

        $guardTtl = max(0, min(300, (int) (getenv('STRIDEBR_SESSION_GUARD_TTL') ?: 60)));
        $guardCheckedAt = (int) ($_SESSION['SessionGuardCheckedAt'] ?? 0);
        $guardDue = $guardTtl === 0 || $guardCheckedAt === 0 || (time() - $guardCheckedAt) >= $guardTtl;

        if ($guardDue) {
            $guardStartedAt = microtime(true);
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
                if (!stridebr_session_register($pdo, (string) $_SESSION['IdUsuario'])) {
                    stridebr_destroy_session();
                    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/function/')) {
                        http_response_code(401);
                        exit('Sessão encerrada.');
                    }
                    header('Location: /login.php?session=invalid');
                    exit;
                }
                $_SESSION['SessionGuardCheckedAt'] = time();

                if (!$legalAllowed && empty($_SESSION['OwnerImpersonation']) && stridebr_feature_enabled($pdo, 'legal.reaccept.required', false)) {
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
            if (function_exists('stridebr_timing_measure')) stridebr_timing_measure('session_guard', $guardStartedAt, 'Validação da sessão');
        }
    }

} catch (PDOException $e) {
    error_log('StrideBR database connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (function_exists('stridebr_is_production') && stridebr_is_production()) {
        $errorPage = dirname(__DIR__, 2) . '/public/errors/500.php';
        if (is_file($errorPage)) {
            require $errorPage;
            exit;
        }
    }
    exit('Não foi possível conectar ao banco de dados.');
}
