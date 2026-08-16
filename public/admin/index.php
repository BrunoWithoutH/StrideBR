<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

require_once dirname(__DIR__, 2) . '/src/includes/admin.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'toggle_flag') {
            if (!stridebr_has_role('admin')) throw new RuntimeException('Apenas admins podem alterar feature flags.');
            $key = trim((string) ($_POST['chave'] ?? ''));
            $enabled = ($_POST['ativo'] ?? '') === '1';
            if ($enabled && in_array($key, ['auth.email_verification.enabled', 'auth.email_verification.required', 'auth.password_reset.enabled'], true)) {
                $mailFrom = trim((string) (getenv('STRIDEBR_MAIL_FROM') ?: ''));
                if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Configure STRIDEBR_MAIL_FROM antes de ativar recursos de e-mail.');
                }
            }
            $stmt = $pdo->prepare('UPDATE feature_flags SET ativo = :ativo, atualizado_por = :actor, data_atualizacao = NOW() WHERE chave = :chave');
            $stmt->bindValue(':ativo', $enabled, PDO::PARAM_BOOL);
            $stmt->bindValue(':actor', $idUsuario);
            $stmt->bindValue(':chave', $key);
            $stmt->execute();
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Feature flag não encontrada.');
            if ($key === 'auth.email_verification.required' && $enabled) { $pdo->prepare("UPDATE feature_flags SET ativo=TRUE, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave='auth.email_verification.enabled'")->execute([':actor'=>$idUsuario]); }
            if ($key === 'auth.email_verification.enabled' && !$enabled) { $pdo->prepare("UPDATE feature_flags SET ativo=FALSE, atualizado_por=:actor, data_atualizacao=NOW() WHERE chave='auth.email_verification.required'")->execute([':actor'=>$idUsuario]); }
            stridebr_admin_audit($pdo, $idUsuario, 'feature_flag.update', 'feature_flag', $key, ['ativo' => $enabled]);
            stridebr_flash('success', 'Feature flag atualizada.');
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
        header('Location: /admin/index.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof RuntimeException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível executar a ação administrativa.';
    }
}

$metricQueries = [
    'users' => 'SELECT count(*) FROM usuarios',
    'active24' => "SELECT count(*) FROM usuarios WHERE ultimologin >= NOW() - INTERVAL '24 hours'",
    'new7' => "SELECT count(*) FROM usuarios WHERE dataregistrousuario >= NOW() - INTERVAL '7 days'",
    'activities7' => "SELECT count(*) FROM registros_atividade WHERE data_criacao >= NOW() - INTERVAL '7 days'",
    'schedules' => 'SELECT count(*) FROM cronogramas WHERE ativo = TRUE',
    'workouts' => 'SELECT count(*) FROM treinos_cronograma',
    'exercises' => 'SELECT count(*) FROM exercicios WHERE ativo = TRUE',
    'activeSessions' => "SELECT count(*) FROM sessoes_treino WHERE status = 'ativo'",
    'accesses30' => "SELECT count(*) FROM acessos_usuario WHERE data_acesso >= NOW() - INTERVAL '30 days'",
    'uniqueIps30' => "SELECT count(DISTINCT ip) FROM acessos_usuario WHERE data_acesso >= NOW() - INTERVAL '30 days' AND ip IS NOT NULL",
    'feedbackNew' => "SELECT count(*) FROM feedbacks WHERE status = 'novo'",
];
$metrics = [];
foreach ($metricQueries as $key => $sql) $metrics[$key] = (int) $pdo->query($sql)->fetchColumn();
$dbSize = (int) $pdo->query('SELECT pg_database_size(current_database())')->fetchColumn();
$flags = $pdo->query('SELECT chave, ativo, descricao, data_atualizacao FROM feature_flags ORDER BY chave')->fetchAll();
$recentUsers = $pdo->query("SELECT idusuario, COALESCE(NULLIF(nome_exibicao,''), nomeusuario) AS nome_exibicao, username, emailusuario, papelusuario, statususuario, ultimologin, ipultimologin, dataregistrousuario FROM usuarios ORDER BY COALESCE(ultimologin, dataregistrousuario) DESC LIMIT 15")->fetchAll();
$audit = $pdo->query("SELECT l.acao, l.alvo_tipo, l.alvo_id, l.detalhes, l.ip, l.data_criacao, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario, 'Sistema') AS ator FROM admin_audit_log l LEFT JOIN usuarios u ON u.idusuario = l.idator ORDER BY l.data_criacao DESC LIMIT 20")->fetchAll();
$recentAccess = stridebr_has_role('owner') ? $pdo->query("SELECT a.ip, a.user_agent, a.data_acesso, COALESCE(NULLIF(u.nome_exibicao,''), u.nomeusuario, 'Usuário removido') AS nome_exibicao, u.username FROM acessos_usuario a LEFT JOIN usuarios u ON u.idusuario = a.idusuario ORDER BY a.data_acesso DESC LIMIT 30")->fetchAll() : [];
$flashes = stridebr_take_flashes();
function adminBytes(int $bytes): string { $units=['B','KB','MB','GB','TB']; $i=0; $value=(float)$bytes; while ($value>=1024 && $i<count($units)-1){$value/=1024;$i++;} return number_format($value,$i===0?0:1,',','.') . ' ' . $units[$i]; }
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><title>Administração | StrideBR</title></head><body class="admin-body"><div class="container-fluid"><?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?><main class="main-content"><div class="admin-shell">
<?php echo stridebr_admin_nav('dashboard'); ?><div class="admin-heading"><div><span class="eyebrow">StrideBR</span><h1>Administração</h1><p>Visão operacional do site, usuários e recursos em liberação.</p></div><span class="admin-role"><?php echo stridebr_e(stridebr_user_role()); ?></span></div>
<?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
<section class="admin-metrics">
<article><strong><?php echo $metrics['users']; ?></strong><span>Usuários</span><small><?php echo $metrics['new7']; ?> novos em 7 dias</small></article>
<article><strong><?php echo $metrics['active24']; ?></strong><span>Ativos em 24h</span><small>por último login</small></article>
<article><strong><?php echo $metrics['activities7']; ?></strong><span>Atividades / 7 dias</span><small><?php echo $metrics['activeSessions']; ?> treino(s) em andamento</small></article>
<article><strong><?php echo $metrics['schedules']; ?></strong><span>Cronogramas</span><small><?php echo $metrics['workouts']; ?> treinos planejados</small></article>
<article><strong><?php echo $metrics['exercises']; ?></strong><span>Exercícios ativos</span><small>base + usuários</small></article>
<article><strong><?php echo $metrics['accesses30']; ?></strong><span>Acessos / 30 dias</span><small><?php echo $metrics['uniqueIps30']; ?> IPs únicos</small></article>
<article><strong><?php echo $metrics['feedbackNew']; ?></strong><span>Feedback novo</span><small><a href="/admin/feedback.php">abrir fila</a></small></article>
<article><strong><?php echo stridebr_e(adminBytes($dbSize)); ?></strong><span>Banco</span><small><?php echo stridebr_e((string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)); ?></small></article>
</section>

<div class="admin-grid">
<section class="admin-card"><div class="admin-card-heading"><div><h2>Feature flags</h2><p>Ligue recursos gradualmente sem novo deploy.</p></div></div><div class="flag-list"><?php foreach ($flags as $flag): ?><article><div><strong><?php echo stridebr_e($flag['chave']); ?></strong><small><?php echo stridebr_e($flag['descricao'] ?? ''); ?></small></div><?php if (stridebr_has_role('admin')): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="toggle_flag"><input type="hidden" name="chave" value="<?php echo stridebr_e($flag['chave']); ?>"><input type="hidden" name="ativo" value="<?php echo stridebr_db_bool($flag['ativo']) ? '0' : '1'; ?>"><button type="submit" class="flag-toggle<?php echo stridebr_db_bool($flag['ativo']) ? ' is-on' : ''; ?>" aria-label="Alternar <?php echo stridebr_e($flag['chave']); ?>"><span></span></button></form><?php else: ?><span class="flag-state"><?php echo stridebr_db_bool($flag['ativo']) ? 'Ativo' : 'Inativo'; ?></span><?php endif; ?></article><?php endforeach; ?></div></section>

<section class="admin-card"><div class="admin-card-heading"><div><h2>Saúde</h2><p>Indicadores que conseguimos medir direto do app.</p></div></div><?php $mailReady = filter_var((string) (getenv('STRIDEBR_MAIL_FROM') ?: ''), FILTER_VALIDATE_EMAIL) !== false; ?><div class="health-list"><div><span class="health-dot ok"></span><strong>Aplicação</strong><span>Online</span></div><div><span class="health-dot ok"></span><strong>PostgreSQL</strong><span>Conectado</span></div><div><span class="health-dot ok"></span><strong>Schema</strong><span>stridebr</span></div><div><span class="health-dot <?php echo $mailReady ? 'ok' : ''; ?>"></span><strong>E-mail transacional</strong><span><?php echo $mailReady ? 'Configurado' : 'Pendente'; ?></span></div></div><p class="admin-note">Erros de PHP/Apache continuam nos logs do servidor. Uma central de erros pode entrar depois.</p></section>
</div>

<section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Usuários recentes</h2><p>Últimos acessos e contas novas. IP só aparece para o owner.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Usuário</th><th>Papel</th><th>Último login</th><?php if (stridebr_has_role('owner')): ?><th>IP</th><th>Acesso</th><?php endif; ?></tr></thead><tbody><?php foreach ($recentUsers as $user): ?><tr><td><strong><?php echo stridebr_e($user['nome_exibicao']); ?></strong><small><?php echo $user['username'] ? '@'.stridebr_e($user['username']) : stridebr_e($user['emailusuario']); ?></small></td><td><?php echo stridebr_e($user['papelusuario']); ?><small><?php echo stridebr_e($user['statususuario']); ?></small></td><td><?php echo stridebr_e($user['ultimologin'] ?? '—'); ?></td><?php if (stridebr_has_role('owner')): ?><td><code><?php echo stridebr_e($user['ipultimologin'] ?? '—'); ?></code></td><td><?php echo stridebr_e($user['dataregistrousuario']); ?><br><a href="/admin/user.php?id=<?php echo rawurlencode($user['idusuario']); ?>">Gerenciar</a></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>

<?php if (stridebr_has_role('owner')): ?><section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Acessos recentes</h2><p>Histórico de login retido por até 90 dias. Localização geográfica ainda não é inferida.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Quando</th><th>Usuário</th><th>IP</th><th>Dispositivo</th></tr></thead><tbody><?php if ($recentAccess === []): ?><tr><td colspan="4">Nenhum acesso registrado ainda.</td></tr><?php endif; ?><?php foreach ($recentAccess as $row): ?><tr><td><?php echo stridebr_e($row['data_acesso']); ?></td><td><strong><?php echo stridebr_e($row['nome_exibicao']); ?></strong><small><?php echo $row['username'] ? '@'.stridebr_e($row['username']) : ''; ?></small></td><td><code><?php echo stridebr_e((string) ($row['ip'] ?? '—')); ?></code></td><td class="admin-user-agent"><?php echo stridebr_e((string) ($row['user_agent'] ?? '—')); ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>

<section class="admin-card admin-table-card"><div class="admin-card-heading"><div><h2>Auditoria administrativa</h2><p>Alterações sensíveis feitas no painel.</p></div></div><div class="admin-table-wrap"><table><thead><tr><th>Quando</th><th>Ator</th><th>Ação</th><th>Alvo</th><?php if (stridebr_has_role('owner')): ?><th>IP</th><?php endif; ?></tr></thead><tbody><?php if ($audit === []): ?><tr><td colspan="5">Nenhuma ação registrada ainda.</td></tr><?php endif; ?><?php foreach ($audit as $row): ?><tr><td><?php echo stridebr_e($row['data_criacao']); ?></td><td><?php echo stridebr_e($row['ator']); ?></td><td><code><?php echo stridebr_e($row['acao']); ?></code></td><td><?php echo stridebr_e(trim(($row['alvo_tipo'] ?? '').' '.($row['alvo_id'] ?? '')) ?: '—'); ?></td><?php if (stridebr_has_role('owner')): ?><td><code><?php echo stridebr_e((string) ($row['ip'] ?? '—')); ?></code></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
</div></main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
