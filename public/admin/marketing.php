<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('admin');
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';
require_once dirname(__DIR__, 2) . '/src/function/marketing.php';

$available = stridebr_marketing_schema_available($pdo);
$errors = [];
$typeLabels = [
    'offline' => 'Offline',
    'paid_social' => 'Social pago',
    'organic_social' => 'Social orgânico',
    'event' => 'Evento',
    'other' => 'Outro',
];
$statusLabels = [
    'planejada' => 'Planejada',
    'ativa' => 'Ativa',
    'encerrada' => 'Encerrada',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if (!$available) throw new RuntimeException('A migration de Marketing ainda não foi aplicada.');

        if ($action === 'save_campaign') {
            $campaignId = trim((string) ($_POST['idcampanha'] ?? ''));
            if ($campaignId === '') {
                $campaignId = stridebr_marketing_campaign_create($pdo, $idUsuario, $_POST);
                $auditAction = 'marketing.campaign.create';
            } else {
                stridebr_marketing_campaign_update($pdo, $campaignId, $_POST);
                $auditAction = 'marketing.campaign.update';
            }
            stridebr_admin_audit($pdo, $idUsuario, $auditAction, 'marketing_campaign', $campaignId, [
                'codigo' => stridebr_marketing_slug((string) ($_POST['codigo'] ?? '')),
                'status' => (string) ($_POST['status'] ?? ''),
            ]);
            stridebr_flash('success', 'Campanha salva.');
            header('Location: /admin/marketing.php?campaign=' . rawurlencode(stridebr_marketing_validate_slug((string) ($_POST['codigo'] ?? ''))));
            exit;
        }

        if ($action === 'save_placement') {
            $campaignId = trim((string) ($_POST['idcampanha'] ?? ''));
            $campaign = $campaignId !== '' ? $pdo->prepare('SELECT idcampanha,codigo FROM marketing_campanhas WHERE idcampanha=:id LIMIT 1') : null;
            if (!$campaign) throw new InvalidArgumentException('Campanha inválida.');
            $campaign->execute([':id' => $campaignId]);
            $campaignRow = $campaign->fetch();
            if (!$campaignRow) throw new InvalidArgumentException('Campanha não encontrada.');

            $placementId = trim((string) ($_POST['idplacement'] ?? ''));
            if ($placementId === '') {
                $placementId = stridebr_marketing_placement_create($pdo, $campaignId, $_POST);
                $auditAction = 'marketing.placement.create';
            } else {
                stridebr_marketing_placement_update($pdo, $placementId, $campaignId, $_POST);
                $auditAction = 'marketing.placement.update';
            }
            stridebr_admin_audit($pdo, $idUsuario, $auditAction, 'marketing_placement', $placementId, [
                'campanha' => $campaignId,
                'codigo' => stridebr_marketing_slug((string) ($_POST['codigo'] ?? '')),
            ]);
            stridebr_flash('success', 'Placement salvo.');
            header('Location: /admin/marketing.php?campaign=' . rawurlencode((string) $campaignRow['codigo']) . '#placements');
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            $errors[] = 'Esse código já está em uso. Escolha outro.';
        } else {
            error_log('StrideBR admin marketing DB error: ' . $e->getCode());
            $errors[] = 'Não foi possível salvar os dados de Marketing.';
        }
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar Marketing.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log('StrideBR admin marketing error: ' . get_class($e));
    }
}

$overview = ['acessos'=>0,'atribuidos'=>0,'diretos'=>0,'signup_iniciados'=>0,'signup_concluidos'=>0,'ativacoes'=>0,'conversao_cadastro'=>0.0,'conversao_ativacao'=>0.0];
$campaigns = [];
$selectedCampaign = null;
$selectedMetrics = $overview;
$placements = [];
$editingPlacement = null;
$campaignCode = stridebr_marketing_slug((string) ($_GET['campaign'] ?? ''));

if ($available) {
    $overview = stridebr_marketing_metrics($pdo);
    $campaigns = stridebr_marketing_campaigns_with_metrics($pdo);
    if ($campaignCode !== '') {
        $selectedCampaign = stridebr_marketing_lookup_campaign($pdo, $campaignCode);
        if ($selectedCampaign) {
            $selectedMetrics = stridebr_marketing_metrics($pdo, (string) $selectedCampaign['idcampanha']);
            $placements = stridebr_marketing_placements_with_metrics($pdo, (string) $selectedCampaign['idcampanha']);
            $editPlacementId = trim((string) ($_GET['edit_placement'] ?? ''));
            if ($editPlacementId !== '') {
                foreach ($placements as $placement) {
                    if ((string) $placement['idplacement'] === $editPlacementId) {
                        $editingPlacement = $placement;
                        break;
                    }
                }
            }
        } else {
            $errors[] = 'Campanha não encontrada.';
        }
    }
}

$showNewCampaign = isset($_GET['new']);
$showCampaignEditor = $showNewCampaign || $selectedCampaign !== null;
$showPlacementEditor = $selectedCampaign !== null && (isset($_GET['new_placement']) || $editingPlacement !== null);
$appUrl = rtrim(stridebr_app_url(), '/');
$percent = static fn(float $value): string => number_format($value, 1, ',', '.') . '%';
$ratio = static function (int $numerator, int $denominator) use ($percent): string {
    return $denominator > 0 ? $percent(($numerator / $denominator) * 100) : '—';
};
$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Marketing | StrideBR Admin</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/admin.css')); ?>">
</head>
<body class="admin-body">
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="admin-shell marketing-admin">
    <?php echo stridebr_admin_nav('marketing'); ?>
    <div class="admin-heading">
        <div><span class="eyebrow">Administração</span><h1>Marketing</h1><p>Atribuição first-party de campanhas, QR Codes e conversão até a primeira atividade.</p></div>
        <?php if ($available): ?><a class="primary-action" href="/admin/marketing.php?new=1#campaign-editor">+ Nova campanha</a><?php endif; ?>
    </div>
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) $flash['type']); ?>"><?php echo stridebr_e((string) $flash['message']); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

    <?php if (!$available): ?>
        <section class="admin-card"><h2>Migration pendente</h2><p>Aplique <code>20260909_marketing_attribution.sql</code> para habilitar campanhas e atribuição.</p></section>
    <?php else: ?>
        <section class="marketing-metrics" aria-label="Visão geral de aquisição">
            <article><span>Entradas</span><strong><?php echo $overview['acessos']; ?></strong></article>
            <article><span>Atribuídas</span><strong><?php echo $overview['atribuidos']; ?></strong></article>
            <article><span>Diretas/desconhecidas</span><strong><?php echo $overview['diretos']; ?></strong></article>
            <article><span>Cadastro iniciado</span><strong><?php echo $overview['signup_iniciados']; ?></strong></article>
            <article><span>Cadastro concluído</span><strong><?php echo $overview['signup_concluidos']; ?></strong></article>
            <article><span>Ativados</span><strong><?php echo $overview['ativacoes']; ?></strong></article>
            <article><span>Visita → cadastro</span><strong><?php echo $percent((float) $overview['conversao_cadastro']); ?></strong></article>
            <article><span>Cadastro → ativação</span><strong><?php echo $percent((float) $overview['conversao_ativacao']); ?></strong></article>
        </section>

        <?php if ($showCampaignEditor): ?>
        <?php $campaignForm = $showNewCampaign ? [] : ($selectedCampaign ?? []); ?>
        <section class="admin-card" id="campaign-editor">
            <div class="admin-card-heading"><div><h2><?php echo $campaignForm ? 'Campanha' : 'Nova campanha'; ?></h2><p>Status e período não atribuem tráfego sozinhos: a origem precisa vir de QR/placement ou UTM.</p></div><?php if ($showNewCampaign): ?><a class="secondary-action compact" href="/admin/marketing.php">Fechar</a><?php endif; ?></div>
            <form method="post" class="marketing-form">
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_campaign">
                <?php if ($campaignForm): ?><input type="hidden" name="idcampanha" value="<?php echo stridebr_e((string) $campaignForm['idcampanha']); ?>"><?php endif; ?>
                <label class="marketing-wide">Nome<input name="nome" maxlength="140" required value="<?php echo stridebr_e((string) ($campaignForm['nome'] ?? '')); ?>" placeholder="Ex.: JIFSul 2026 — Awareness"></label>
                <label>Código<input name="codigo" maxlength="80" required pattern="[a-z0-9_-]{2,80}" value="<?php echo stridebr_e((string) ($campaignForm['codigo'] ?? '')); ?>" placeholder="jifsul_2026_awareness"></label>
                <label>Tipo<select name="tipo"><?php foreach ($typeLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo ($campaignForm['tipo'] ?? 'other') === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label>
                <label>Status<select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo ($campaignForm['status'] ?? 'planejada') === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label>
                <label>Início <small>opcional</small><input type="date" name="inicio" value="<?php echo stridebr_e((string) ($campaignForm['inicio'] ?? '')); ?>"></label>
                <label>Fim <small>opcional</small><input type="date" name="fim" value="<?php echo stridebr_e((string) ($campaignForm['fim'] ?? '')); ?>"></label>
                <label class="marketing-wide">Notas <small>opcional</small><textarea name="descricao" maxlength="3000" rows="3"><?php echo stridebr_e((string) ($campaignForm['descricao'] ?? '')); ?></textarea></label>
                <div class="marketing-form-actions marketing-wide"><button class="primary-action" type="submit">Salvar campanha</button></div>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($selectedCampaign): ?>
        <section class="admin-card marketing-campaign-summary">
            <div class="admin-card-heading"><div><h2><?php echo stridebr_e((string) $selectedCampaign['nome']); ?></h2><p><code><?php echo stridebr_e((string) $selectedCampaign['codigo']); ?></code> · <?php echo stridebr_e($typeLabels[(string) $selectedCampaign['tipo']] ?? (string) $selectedCampaign['tipo']); ?> · <?php echo stridebr_e($statusLabels[(string) $selectedCampaign['status']] ?? (string) $selectedCampaign['status']); ?></p></div><a class="secondary-action compact" href="/admin/marketing.php">Todas as campanhas</a></div>
            <div class="marketing-inline-metrics">
                <span><strong><?php echo $selectedMetrics['acessos']; ?></strong> acessos</span>
                <span><strong><?php echo $selectedMetrics['signup_iniciados']; ?></strong> signup iniciado</span>
                <span><strong><?php echo $selectedMetrics['signup_concluidos']; ?></strong> signup concluído</span>
                <span><strong><?php echo $selectedMetrics['ativacoes']; ?></strong> ativados</span>
                <span><strong><?php echo $percent((float) $selectedMetrics['conversao_cadastro']); ?></strong> visita → cadastro</span>
            </div>
        </section>

        <section class="admin-card admin-table-card" id="placements">
            <div class="admin-card-heading"><div><h2>Placements / origens</h2><p>O QR aponta para a URL curta first-party. O destino final permanece interno ao StrideBR.</p></div><a class="primary-action compact" href="/admin/marketing.php?campaign=<?php echo rawurlencode((string) $selectedCampaign['codigo']); ?>&new_placement=1#placement-editor">+ Novo placement</a></div>
            <?php if ($placements === []): ?><p class="admin-empty">Nenhum placement nesta campanha.</p><?php else: ?>
            <div class="admin-table-wrap marketing-placement-table"><table>
                <thead><tr><th>Placement</th><th>Link / QR</th><th>Acessos</th><th>Signup iniciado</th><th>Signup concluído</th><th>Ativados</th><th>Conversão</th><th></th></tr></thead>
                <tbody><?php foreach ($placements as $placement): ?>
                    <?php $shortUrl = $appUrl . '/r/' . (string) $placement['codigo']; ?>
                    <tr>
                        <td data-label="Placement"><strong><?php echo stridebr_e((string) $placement['nome']); ?></strong><small><code><?php echo stridebr_e((string) $placement['codigo']); ?></code><?php echo stridebr_db_bool($placement['ativo']) ? '' : ' · inativo'; ?></small></td>
                        <td data-label="Link / QR"><div class="marketing-link-cell" data-marketing-qr="<?php echo stridebr_e($shortUrl); ?>"><div class="marketing-qr" data-qr-preview aria-hidden="true"></div><div><code><?php echo stridebr_e($shortUrl); ?></code><div class="marketing-row-actions"><button type="button" class="secondary-action compact" data-copy-short-url="<?php echo stridebr_e($shortUrl); ?>">Copiar link</button><button type="button" class="secondary-action compact" data-download-qr="<?php echo stridebr_e((string) $placement['codigo']); ?>">Baixar QR SVG</button></div></div></div></td>
                        <td data-label="Acessos"><?php echo (int) $placement['acessos']; ?></td>
                        <td data-label="Signup iniciado"><?php echo (int) $placement['signup_iniciados']; ?></td>
                        <td data-label="Signup concluído"><?php echo (int) $placement['signup_concluidos']; ?></td>
                        <td data-label="Ativados"><?php echo (int) $placement['ativacoes']; ?></td>
                        <td data-label="Conversão"><?php echo $ratio((int) $placement['signup_concluidos'], (int) $placement['acessos']); ?></td>
                        <td data-label=""><a class="secondary-action compact" href="/admin/marketing.php?campaign=<?php echo rawurlencode((string) $selectedCampaign['codigo']); ?>&edit_placement=<?php echo rawurlencode((string) $placement['idplacement']); ?>#placement-editor">Editar</a></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div><?php endif; ?>
        </section>

        <?php if ($showPlacementEditor): ?>
        <?php $placementForm = $editingPlacement ?? []; ?>
        <section class="admin-card" id="placement-editor">
            <div class="admin-card-heading"><div><h2><?php echo $placementForm ? 'Editar placement' : 'Novo placement'; ?></h2><p>Use um código curto e estável: ele fará parte do QR impresso.</p></div><a class="secondary-action compact" href="/admin/marketing.php?campaign=<?php echo rawurlencode((string) $selectedCampaign['codigo']); ?>#placements">Fechar</a></div>
            <form method="post" class="marketing-form">
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save_placement"><input type="hidden" name="idcampanha" value="<?php echo stridebr_e((string) $selectedCampaign['idcampanha']); ?>">
                <?php if ($placementForm): ?><input type="hidden" name="idplacement" value="<?php echo stridebr_e((string) $placementForm['idplacement']); ?>"><?php endif; ?>
                <label class="marketing-wide">Nome<input name="nome" maxlength="160" required value="<?php echo stridebr_e((string) ($placementForm['nome'] ?? '')); ?>" placeholder="Ex.: Story Brand 01"></label>
                <label>Código<input name="codigo" maxlength="80" required pattern="[a-z0-9_-]{2,80}" value="<?php echo stridebr_e((string) ($placementForm['codigo'] ?? '')); ?>" placeholder="story_brand_01"></label>
                <label>Subtipo <small>opcional</small><input name="subtipo" maxlength="60" value="<?php echo stridebr_e((string) ($placementForm['subtipo'] ?? '')); ?>" placeholder="story, reel, cartaz..."></label>
                <label class="marketing-wide">Destino interno<input name="destino" maxlength="500" required value="<?php echo stridebr_e((string) ($placementForm['destino'] ?? '/')); ?>" placeholder="/"><small>Somente caminhos internos, como <code>/</code> ou <code>/calendario.php</code>.</small></label>
                <label class="marketing-wide">Observação <small>opcional</small><textarea name="observacao" maxlength="3000" rows="3"><?php echo stridebr_e((string) ($placementForm['observacao'] ?? '')); ?></textarea></label>
                <label class="marketing-check"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1"<?php echo !array_key_exists('ativo', $placementForm) || stridebr_db_bool($placementForm['ativo']) ? ' checked' : ''; ?>> Placement ativo</label>
                <div class="marketing-form-actions marketing-wide"><button class="primary-action" type="submit">Salvar placement</button></div>
            </form>
        </section>
        <?php endif; ?>
        <?php else: ?>
        <section class="admin-card admin-table-card">
            <div class="admin-card-heading"><div><h2>Campanhas</h2><p><?php echo count($campaigns); ?> campanha(s) cadastrada(s).</p></div></div>
            <?php if ($campaigns === []): ?><p class="admin-empty">Nenhuma campanha cadastrada.</p><?php else: ?>
            <div class="admin-table-wrap marketing-campaign-table"><table><thead><tr><th>Campanha</th><th>Tipo</th><th>Status</th><th>Acessos</th><th>Signup concluído</th><th>Ativados</th><th>Conversão</th><th></th></tr></thead><tbody>
                <?php foreach ($campaigns as $campaign): ?><tr>
                    <td data-label="Campanha"><strong><?php echo stridebr_e((string) $campaign['nome']); ?></strong><small><code><?php echo stridebr_e((string) $campaign['codigo']); ?></code></small></td>
                    <td data-label="Tipo"><?php echo stridebr_e($typeLabels[(string) $campaign['tipo']] ?? (string) $campaign['tipo']); ?></td>
                    <td data-label="Status"><span class="status-pill<?php echo $campaign['status'] === 'ativa' ? ' is-ok' : ''; ?>"><?php echo stridebr_e($statusLabels[(string) $campaign['status']] ?? (string) $campaign['status']); ?></span></td>
                    <td data-label="Acessos"><?php echo (int) $campaign['acessos']; ?></td>
                    <td data-label="Signup concluído"><?php echo (int) $campaign['signup_concluidos']; ?></td>
                    <td data-label="Ativados"><?php echo (int) $campaign['ativacoes']; ?></td>
                    <td data-label="Conversão"><?php echo $ratio((int) $campaign['signup_concluidos'], (int) $campaign['acessos']); ?></td>
                    <td data-label=""><a class="secondary-action compact" href="/admin/marketing.php?campaign=<?php echo rawurlencode((string) $campaign['codigo']); ?>">Abrir</a></td>
                </tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</div></main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<?php if ($available && $selectedCampaign): ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/vendor/qrcode-generator.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/admin-marketing.js')); ?>"></script>
<?php endif; ?>
</body></html>
