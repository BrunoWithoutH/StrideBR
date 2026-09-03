<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('admin');
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';

$root = dirname(__DIR__, 2);
$migrationDirectory = $root . '/src/database/migrations';
$migrationFiles = array_map('basename', glob($migrationDirectory . '/*.sql') ?: []);
sort($migrationFiles, SORT_STRING);
$historyAvailable = false;
$applied = [];
try {
    $historyAvailable = stridebr_db_bool($pdo->query("SELECT to_regclass('public.stridebr_schema_migrations') IS NOT NULL")->fetchColumn());
    if ($historyAvailable) {
        $rows = $pdo->query('SELECT version, applied_at FROM public.stridebr_schema_migrations ORDER BY version')->fetchAll();
        foreach ($rows as $row) $applied[(string) $row['version']] = (string) $row['applied_at'];
    }
} catch (Throwable $e) {
    error_log('StrideBR diagnostics migration history failed: ' . $e->getMessage());
}
$pending = array_values(array_diff($migrationFiles, array_keys($applied)));
$unknownHistory = array_values(array_diff(array_keys($applied), $migrationFiles));

$dbInfo = ['database' => '—', 'server' => '—', 'size' => '—', 'search_path' => '—'];
try {
    $dbInfo['database'] = (string) $pdo->query('SELECT current_database()')->fetchColumn();
    $dbInfo['server'] = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $dbInfo['size'] = (string) $pdo->query("SELECT pg_size_pretty(pg_database_size(current_database()))")->fetchColumn();
    $dbInfo['search_path'] = (string) $pdo->query('SHOW search_path')->fetchColumn();
} catch (Throwable $e) {
    error_log('StrideBR diagnostics database info failed: ' . $e->getMessage());
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || stridebr_lower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$appUrl = stridebr_app_url();
$appUrlHttps = str_starts_with(stridebr_lower($appUrl), 'https://');
$environment = trim((string) (getenv('STRIDEBR_APP_ENV') ?: 'development'));
$mailReady = stridebr_mail_is_configured();
$uploads = [
    ['label' => 'Uploads', 'path' => $root . '/public/uploads'],
    ['label' => 'Avatares', 'path' => $root . '/public/uploads/avatars'],
    ['label' => 'Eventos', 'path' => $root . '/public/uploads/events'],
];
foreach ($uploads as &$directory) {
    $path = $directory['path'];
    $parent = is_dir($path) ? $path : dirname($path);
    $directory['exists'] = is_dir($path);
    $directory['writable'] = is_dir($parent) && is_writable($parent);
}
unset($directory);

$extensions = [
    ['name' => 'pdo_pgsql', 'required' => true, 'ok' => extension_loaded('pdo_pgsql')],
    ['name' => 'mbstring', 'required' => true, 'ok' => extension_loaded('mbstring')],
    ['name' => 'fileinfo', 'required' => true, 'ok' => extension_loaded('fileinfo')],
    ['name' => 'openssl', 'required' => true, 'ok' => extension_loaded('openssl')],
    ['name' => 'intl', 'required' => false, 'ok' => extension_loaded('intl')],
    ['name' => 'GD ou Imagick', 'required' => false, 'ok' => extension_loaded('gd') || extension_loaded('imagick')],
];

$flagKeys = [
    'registration.enabled',
    'registration.invite_only.enabled',
    'auth.email_verification.enabled',
    'auth.email_verification.required',
    'auth.password_reset.enabled',
    'events.enabled',
    'friends.enabled',
    'workout_sessions.enabled',
    'feedback.enabled',
];
$flags = [];
try {
    $placeholders = implode(',', array_fill(0, count($flagKeys), '?'));
    $stmt = $pdo->prepare("SELECT chave, ativo FROM feature_flags WHERE chave IN ({$placeholders}) ORDER BY chave");
    $stmt->execute($flagKeys);
    foreach ($stmt->fetchAll() as $row) $flags[(string) $row['chave']] = stridebr_db_bool($row['ativo']);
} catch (Throwable $e) {
    error_log('StrideBR diagnostics flags failed: ' . $e->getMessage());
}

$criticalChecks = [
    $environment === 'production',
    $https,
    $appUrlHttps,
    $mailReady,
    $historyAvailable,
    $pending === [],
    extension_loaded('pdo_pgsql'),
    extension_loaded('mbstring'),
    extension_loaded('fileinfo'),
    extension_loaded('openssl'),
    is_writable($root . '/public/uploads'),
];
$ready = !in_array(false, $criticalChecks, true);
$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <title>Diagnóstico | StrideBR Admin</title>
</head>
<body class="admin-body">
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="admin-shell">
    <?php echo stridebr_admin_nav('diagnostics'); ?>
    <div class="admin-heading diagnostics-heading"><div><span class="eyebrow">Pré-release</span><h1>Diagnóstico</h1><p>Um painel rápido para conferir produção, banco, migrations, e-mail, uploads e extensões antes de publicar.</p></div><span class="release-readiness <?php echo $ready ? 'is-ready' : 'has-warning'; ?>"><?php echo $ready ? 'Pronto nos checks automáticos' : 'Há itens para revisar'; ?></span></div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>

    <section class="diagnostics-grid">
        <article class="admin-card diagnostics-card"><div class="admin-card-heading"><div><h2>Aplicação</h2><p>Configuração que deve estar correta em produção.</p></div></div><div class="diagnostic-list">
            <div><span class="health-dot <?php echo $environment === 'production' ? 'ok' : ''; ?>"></span><strong>Ambiente</strong><span><?php echo stridebr_e($environment); ?></span></div>
            <div><span class="health-dot <?php echo $https ? 'ok' : ''; ?>"></span><strong>HTTPS desta requisição</strong><span><?php echo $https ? 'sim' : 'não'; ?></span></div>
            <div><span class="health-dot <?php echo $appUrlHttps ? 'ok' : ''; ?>"></span><strong>STRIDEBR_APP_URL</strong><span><?php echo stridebr_e($appUrl); ?></span></div>
            <div><span class="health-dot ok"></span><strong>Versão</strong><span><?php echo stridebr_e(stridebr_version()); ?> · <?php echo stridebr_e(stridebr_build()); ?></span></div>
            <div><span class="health-dot <?php echo $mailReady ? 'ok' : ''; ?>"></span><strong>E-mail</strong><span><?php echo $mailReady ? stridebr_e((string) getenv('STRIDEBR_MAIL_FROM')) : 'não configurado'; ?></span></div>
        </div></article>

        <article class="admin-card diagnostics-card"><div class="admin-card-heading"><div><h2>PostgreSQL</h2><p>Conexão e estado do schema atual.</p></div></div><dl class="diagnostic-definition-list"><div><dt>Banco</dt><dd><?php echo stridebr_e($dbInfo['database']); ?></dd></div><div><dt>PostgreSQL</dt><dd><?php echo stridebr_e($dbInfo['server']); ?></dd></div><div><dt>Tamanho</dt><dd><?php echo stridebr_e($dbInfo['size']); ?></dd></div><div><dt>search_path</dt><dd><code><?php echo stridebr_e($dbInfo['search_path']); ?></code></dd></div></dl></article>

        <article class="admin-card diagnostics-card diagnostics-span-full"><div class="admin-card-heading"><div><h2>Migrations</h2><p>Compara os arquivos presentes no deploy com <code>public.stridebr_schema_migrations</code>.</p></div><span class="status-pill <?php echo $historyAvailable && $pending === [] ? 'is-ok' : ''; ?>"><?php echo count($applied); ?>/<?php echo count($migrationFiles); ?> registradas</span></div>
            <?php if (!$historyAvailable): ?><div class="diagnostic-warning"><strong>Histórico indisponível.</strong><span>Crie <code>public.stridebr_schema_migrations</code> e registre/aplique as migrations antes da release.</span></div>
            <?php elseif ($pending !== []): ?><div class="diagnostic-warning"><strong><?php echo count($pending); ?> migration(s) pendente(s).</strong><div class="diagnostic-code-list"><?php foreach ($pending as $name): ?><code><?php echo stridebr_e($name); ?></code><?php endforeach; ?></div></div>
            <?php else: ?><div class="diagnostic-success"><strong>Nenhuma migration pendente.</strong><span>O histórico contém todos os arquivos atuais do repositório.</span></div><?php endif; ?>
            <?php if ($unknownHistory !== []): ?><div class="diagnostic-warning subtle"><strong>Entradas no histórico sem arquivo local:</strong><div class="diagnostic-code-list"><?php foreach ($unknownHistory as $name): ?><code><?php echo stridebr_e($name); ?></code><?php endforeach; ?></div></div><?php endif; ?>
        </article>

        <article class="admin-card diagnostics-card"><div class="admin-card-heading"><div><h2>Uploads</h2><p>Pastas usadas por arquivos criados em produção.</p></div></div><div class="diagnostic-list"><?php foreach ($uploads as $directory): ?><div><span class="health-dot <?php echo $directory['writable'] ? 'ok' : ''; ?>"></span><strong><?php echo stridebr_e($directory['label']); ?></strong><span><?php echo $directory['writable'] ? ($directory['exists'] ? 'gravável' : 'pode ser criada') : 'sem permissão de escrita'; ?></span></div><?php endforeach; ?></div><p class="admin-note">O deploy deve preservar <code>public/uploads/</code>; não use um <code>rsync --delete</code> cru nessa pasta.</p></article>

        <article class="admin-card diagnostics-card"><div class="admin-card-heading"><div><h2>PHP</h2><p>Extensões importantes para os fluxos atuais.</p></div></div><div class="diagnostic-list"><?php foreach ($extensions as $extension): ?><div><span class="health-dot <?php echo $extension['ok'] ? 'ok' : ($extension['required'] ? 'bad' : ''); ?>"></span><strong><?php echo stridebr_e($extension['name']); ?></strong><span><?php echo $extension['ok'] ? 'disponível' : ($extension['required'] ? 'obrigatória' : 'opcional'); ?></span></div><?php endforeach; ?></div><dl class="diagnostic-definition-list compact"><div><dt>PHP</dt><dd><?php echo stridebr_e(PHP_VERSION); ?></dd></div><div><dt>upload_max_filesize</dt><dd><?php echo stridebr_e((string) ini_get('upload_max_filesize')); ?></dd></div><div><dt>post_max_size</dt><dd><?php echo stridebr_e((string) ini_get('post_max_size')); ?></dd></div><div><dt>memory_limit</dt><dd><?php echo stridebr_e((string) ini_get('memory_limit')); ?></dd></div></dl></article>

        <article class="admin-card diagnostics-card diagnostics-span-full"><div class="admin-card-heading"><div><h2>Flags importantes</h2><p>Estado efetivo de recursos que costumam mudar antes da publicação.</p></div></div><div class="diagnostic-flags"><?php foreach ($flagKeys as $key): ?><div><code><?php echo stridebr_e($key); ?></code><span class="status-pill <?php echo !empty($flags[$key]) ? 'is-ok' : ''; ?>"><?php echo array_key_exists($key, $flags) ? (!empty($flags[$key]) ? 'ligada' : 'desligada') : 'ausente'; ?></span></div><?php endforeach; ?></div></article>
    </section>

    <section class="admin-card diagnostics-next"><div><h2>Antes de chamar de 1.0</h2><p>Esse painel cobre infraestrutura, não substitui o smoke test em celular/desktop e a restauração de um backup em banco separado.</p></div><div><a class="secondary-action" href="/pages/extras/changelog.php">Ver build atual</a><a class="primary-action" href="/admin/index.php">Voltar ao painel</a></div></section>
</div></main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
