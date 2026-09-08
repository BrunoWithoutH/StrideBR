<?php

declare(strict_types=1);
require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/mail.php';

/** Status only; never return credentials, addresses, hosts or provider responses. */
function stridebr_configuration_check(bool $filesystem = true): array
{
    $errors = []; $status = [];
    try { $env = stridebr_app_env(); $status['APP_ENV'] = $env; }
    catch (Throwable) { $env = 'invalid'; $errors[] = 'APP_ENV'; $status['APP_ENV'] = 'invalid'; }
    try { stridebr_app_url(); $status['APP_URL'] = 'configured'; }
    catch (Throwable) { $errors[] = 'APP_URL'; $status['APP_URL'] = 'missing/invalid'; }
    $published = $env !== 'development';
    $dbValid = true;
    foreach (['HOST','NAME','USER','PASSWORD'] as $part) if (trim((string) getenv('STRIDEBR_DB_' . $part)) === '') $dbValid = false;
    if (!in_array((string) (getenv('STRIDEBR_DB_SSLMODE') ?: 'prefer'), ['disable','allow','prefer','require','verify-ca','verify-full'], true)) $dbValid = false;
    $port = filter_var(getenv('STRIDEBR_DB_PORT') ?: '5432', FILTER_VALIDATE_INT);
    if (!$port || $port < 1 || $port > 65535) $dbValid = false;
    foreach (['HOST','NAME','USER'] as $part) if (preg_match('/[;\s]/', (string) getenv('STRIDEBR_DB_' . $part))) $dbValid = false;
    if ($published && in_array(strtolower((string) getenv('STRIDEBR_DB_PASSWORD')), ['stridebr_dev','postgres','password','changeme','stridebr','stride'], true)) $dbValid = false;
    $status['DB'] = $dbValid ? 'configured' : 'missing/invalid';
    if ($published && !$dbValid) $errors[] = 'DB';
    $mail = false;
    try { $mail = stridebr_mail_is_configured(); } catch (Throwable) {}
    $status['MAIL'] = $mail ? 'configured' : 'not configured';
    // Public signup and password recovery are part of Web 1.0.
    if ($published && !$mail) $errors[] = 'MAIL';
    $supportValid = filter_var(trim((string) getenv('STRIDEBR_SUPPORT_EMAIL')), FILTER_VALIDATE_EMAIL) !== false;
    $status['SUPPORT_EMAIL'] = $supportValid ? 'configured' : 'missing/invalid';
    if ($published && !$supportValid) $errors[] = 'SUPPORT_EMAIL';
    $proxies = trim((string) getenv('STRIDEBR_TRUSTED_PROXIES'));
    $status['TRUSTED_PROXIES'] = $proxies === '' ? 'not configured' : 'configured';
    foreach (array_filter(array_map('trim', explode(',', $proxies))) as $cidr) {
        if (!stridebr_ip_in_cidr(explode('/', $cidr)[0], $cidr) || preg_match('#/0+$#', $cidr)) { $errors[] = 'TRUSTED_PROXIES'; $status['TRUSTED_PROXIES'] = 'invalid'; }
    }
    $status['MAPS'] = getenv('STRIDEBR_MAPS_ARCGIS_KEY') ? 'configured' : 'optional/not configured';
    foreach (['GOOGLE_OAUTH','STRAVA','POLAR','FITBIT','SUUNTO'] as $provider) {
        $configured = getenv($provider . '_CLIENT_ID') && getenv($provider . '_CLIENT_SECRET');
        if ($provider === 'GOOGLE_OAUTH') $configured = $configured && stridebr_env_enabled('GOOGLE_OAUTH_ENABLED');
        else $configured = $configured && strlen((string) getenv('STRIDEBR_INTEGRATIONS_SECRET')) >= 32;
        if ($provider === 'SUUNTO') $configured = $configured && getenv('SUUNTO_SUBSCRIPTION_KEY');
        $status[$provider] = $configured ? 'configured' : 'optional/not configured';
    }
    $googleRedirect = trim((string) getenv('GOOGLE_OAUTH_REDIRECT_URI'));
    if ($googleRedirect !== '') {
        try { if ($googleRedirect !== stridebr_app_url() . '/auth/google-callback.php') $errors[] = 'GOOGLE_CALLBACK'; }
        catch (Throwable) { $errors[] = 'GOOGLE_CALLBACK'; }
    }
    $status['GARMIN'] = 'unavailable/not validated';
    $status['ADS'] = stridebr_env_enabled('STRIDEBR_ADS_ENABLED') ? 'enabled/check provider configuration' : 'disabled';
    $status['DONATIONS'] = stridebr_env_enabled('STRIDEBR_DONATION_ENABLED') ? 'enabled/check provider configuration' : 'disabled';
    if ($filesystem) {
        $path = dirname(__DIR__, 2) . '/public/uploads';
        $status['UPLOADS'] = is_dir($path) && is_writable($path) ? 'writable' : 'not writable';
        if ($published && $status['UPLOADS'] !== 'writable') $errors[] = 'UPLOADS';
    }
    return ['ok' => $errors === [], 'status' => $status, 'errors' => array_values(array_unique($errors))];
}

function stridebr_database_ready(): bool
{
    try {
        foreach (['HOST','NAME','USER','PASSWORD'] as $key) if (!getenv('STRIDEBR_DB_' . $key)) return false;
        foreach (['HOST','NAME'] as $key) if (preg_match('/[;\s]/', (string) getenv('STRIDEBR_DB_' . $key))) return false;
        $port = (string) (getenv('STRIDEBR_DB_PORT') ?: '5432');
        $ssl = (string) (getenv('STRIDEBR_DB_SSLMODE') ?: 'prefer');
        if (!ctype_digit($port) || !in_array($ssl, ['disable','allow','prefer','require','verify-ca','verify-full'], true)) return false;
        $pdo = new PDO('pgsql:host=' . getenv('STRIDEBR_DB_HOST') . ';port=' . $port . ';dbname=' . getenv('STRIDEBR_DB_NAME') . ';sslmode=' . $ssl . ';connect_timeout=3', (string) getenv('STRIDEBR_DB_USER'), (string) getenv('STRIDEBR_DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("SET statement_timeout = '3000ms'");
        $applied = $pdo->query('SELECT version FROM public.stridebr_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $file) if (!in_array(basename($file), $applied, true)) return false;
        return (bool) $pdo->query("SELECT to_regclass('stridebr.usuarios') IS NOT NULL")->fetchColumn();
    } catch (Throwable) { return false; }
}
