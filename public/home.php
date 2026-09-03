<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/dashboard.php';
require_once dirname(__DIR__) . '/src/function/cronograma.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__) . '/src/layout/sport_picker.php';

$user = stridebr_display_name();
$errors = [];
$metasDisponiveis = dashboardMetasDisponiveis($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'criar_meta') {
            dashboardCriarMeta($pdo, $idUsuario, $_POST);
            stridebr_flash('success', 'Meta criada.');
        } elseif ($action === 'arquivar_meta') {
            $idMeta = trim((string) ($_POST['idmeta'] ?? ''));
            if ($idMeta === '' || !dashboardArquivarMeta($pdo, $idUsuario, $idMeta)) {
                throw new InvalidArgumentException('Meta não encontrada.');
            }
            stridebr_flash('success', 'Meta removida.');
        }
        header('Location: /home.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Não foi possível atualizar o painel.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) {
            error_log($e->getMessage());
        }
    }
}

$activityOverview = dashboardVisaoAtividades($pdo, $idUsuario);
$resumo = $activityOverview['resumo'];
$dias = $activityOverview['dias'];
$recentes = dashboardAtividadesRecentes($pdo, $idUsuario, 5);
$proximos = dashboardTreinosProximos($pdo, $idUsuario, 5);
$metas = $metasDisponiveis ? dashboardListarMetas($pdo, $idUsuario) : [];
$goalModalidades = $metasDisponiveis ? dashboardListarModalidades($pdo, $idUsuario) : [];
$dashboardPrefs = dashboardPreferenciasHome($pdo, $idUsuario);
$contextoHoje = dashboardContextoHoje($pdo, $idUsuario);
$flashes = stridebr_take_flashes();

$maxDia = 1.0;
foreach ($dias as $dia) {
    $maxDia = max($maxDia, (float) $dia['duracao_s']);
}

$tz = new DateTimeZone('America/Sao_Paulo');
$hoje = new DateTimeImmutable('today', $tz);
$amanha = $hoje->modify('+1 day');
$weekdayLabels = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$todayLabel = $weekdayLabels[(int) $hoje->format('w')] . ', ' . $hoje->format('d/m');
if (in_array((string) ($contextoHoje['state'] ?? ''), ['rest', 'free'], true) && $recentes !== []) {
    $latestToday = new DateTimeImmutable((string) $recentes[0]['data_inicio']);
    if ($latestToday->setTimezone($tz)->format('Y-m-d') === $hoje->format('Y-m-d')) {
        $contextoHoje['state'] = 'activity';
        $contextoHoje['activity'] = $recentes[0];
    }
}
$dashboardLabels = ['progress' => 'Progresso da semana', 'goals' => 'Metas', 'upcoming' => 'Próximos treinos', 'recent' => 'Atividades recentes'];
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/dashboard.css')); ?>">
    <title>Painel | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content dashboard-page" data-dashboard-root data-dashboard-csrf="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
        <div class="dashboard-shell">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <header class="dashboard-heading">
                <div>
                    <span class="dashboard-eyebrow">Início</span>
                    <h1>Olá, <?php echo stridebr_e($user); ?></h1>
                    <p>Treinos, metas e atividades.</p>
                </div>
                <div class="dashboard-heading-actions">
                    <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php">Abrir cronograma</a>
                    <a class="dashboard-button dashboard-button-secondary" href="/user/gravar-atividade.php?quick=corrida&autostart=1">▶ Corrida rápida</a>
                    <a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?new=1">+ Registrar atividade física</a>
                </div>
            </header>

            <section class="dashboard-today" aria-labelledby="dashboard-today-title" data-dashboard-today>
                <div class="dashboard-today-date">
                    <span class="dashboard-eyebrow">Hoje</span>
                    <strong><?php echo stridebr_e($todayLabel); ?></strong>
                </div>
                <div class="dashboard-today-main">
                    <?php if ($contextoHoje['state'] === 'active'):
                        $active = $contextoHoje['active'];
                        $activeStart = !empty($active['data_inicio']) ? new DateTimeImmutable((string) $active['data_inicio']) : null;
                    ?>
                        <span class="dashboard-today-status is-active"><i></i> Em andamento</span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e((string) ($active['titulo_snapshot'] ?? 'Treino')); ?></h2>
                        <p><?php echo $activeStart ? 'Começou às ' . stridebr_e($activeStart->setTimezone($tz)->format('H:i')) : 'Seu treino está em andamento.'; ?></p>
                    <?php elseif ($contextoHoje['state'] === 'planned'):
                        $primaryToday = $contextoHoje['primary'];
                        $todayTime = trim((string) ($primaryToday['hora_inicio'] ?? ''));
                        $todayMeta = trim((string) ($primaryToday['cronograma_nome'] ?? ''));
                    ?>
                        <span class="dashboard-today-status">Planejado</span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e((string) ($primaryToday['titulo'] ?? 'Treino')); ?></h2>
                        <p><?php echo $todayTime !== '' ? stridebr_e($todayTime) : 'Hoje'; ?><?php echo $todayMeta !== '' ? ' · ' . stridebr_e($todayMeta) : ''; ?></p>
                    <?php elseif ($contextoHoje['state'] === 'activity'):
                        $todayActivity = $contextoHoje['activity'];
                        $todayActivityTitle = trim((string) ($todayActivity['titulo'] ?? '')) ?: (string) ($todayActivity['modalidade_nome'] ?? 'Atividade');
                    ?>
                        <span class="dashboard-today-status is-complete">Registrado</span>
                        <h2 id="dashboard-today-title"><?php echo stridebr_e($todayActivityTitle); ?></h2>

                    <?php elseif ($contextoHoje['state'] === 'completed'): ?>
                        <span class="dashboard-today-status is-complete">Concluído</span>
                        <h2 id="dashboard-today-title">Treino de hoje concluído</h2>

                    <?php elseif ($contextoHoje['state'] === 'rest'): ?>
                        <span class="dashboard-today-status is-rest">Descanso</span>
                        <h2 id="dashboard-today-title">Hoje é dia de descanso</h2>

                    <?php else: ?>
                        <span class="dashboard-today-status">Hoje</span>
                        <h2 id="dashboard-today-title">Nenhum treino planejado hoje</h2>

                    <?php endif; ?>
                </div>
                <div class="dashboard-today-actions">
                    <?php if ($contextoHoje['state'] === 'active'): ?>
                        <button type="button" class="dashboard-button dashboard-button-primary" data-open-workout-session>Continuar treino</button>
                    <?php elseif ($contextoHoje['state'] === 'planned' && !empty($contextoHoje['primary'])):
                        $primaryToday = $contextoHoje['primary'];
                    ?>
                        <?php if (($primaryToday['kind'] ?? '') === 'scheduled'): ?>
                            <button type="button" class="dashboard-button dashboard-button-primary" data-dashboard-start-scheduled="<?php echo stridebr_e((string) $primaryToday['idagendamento']); ?>">Iniciar treino</button>
                        <?php else: ?>
                            <button type="button" class="dashboard-button dashboard-button-primary"
                                data-dashboard-start-workout="<?php echo stridebr_e((string) $primaryToday['idtreino']); ?>"
                                data-occurrence-origin="<?php echo stridebr_e((string) ($primaryToday['data_original'] ?? $contextoHoje['date'])); ?>"
                                data-occurrence-planned="<?php echo stridebr_e((string) ($primaryToday['data_treino'] ?? $contextoHoje['date'])); ?>"
                                data-occurrence-time="<?php echo stridebr_e((string) ($primaryToday['hora_inicio'] ?? '')); ?>">Iniciar treino</button>
                        <?php endif; ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php">Ver agenda</a>
                    <?php elseif ($contextoHoje['state'] === 'activity'): ?>
                        <a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?highlight=<?php echo rawurlencode((string) ($contextoHoje['activity']['idregistro'] ?? '')); ?>">Ver atividade</a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1">Registrar outra</a>
                    <?php elseif ($contextoHoje['state'] === 'completed'): ?>
                        <?php $completedToday = $contextoHoje['completed'][0] ?? null; ?>
                        <?php if ($completedToday && !empty($completedToday['idregistro'])): ?><a class="dashboard-button dashboard-button-primary" href="/user/atividades.php?highlight=<?php echo rawurlencode((string) $completedToday['idregistro']); ?>">Ver atividade</a><?php endif; ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1">Registrar outra</a>
                    <?php elseif ($contextoHoje['state'] === 'rest'): ?>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php">Ver próximo treino</a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1">Registrar atividade</a>
                    <?php else: ?>
                        <a class="dashboard-button dashboard-button-primary" href="/user/gravar-atividade.php">Iniciar atividade</a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/atividades.php?new=1">Registrar manualmente</a>
                        <a class="dashboard-button dashboard-button-secondary" href="/user/cronogramatreinos.php">Adicionar treino</a>
                    <?php endif; ?>
                </div>
                <?php if (count($contextoHoje['items'] ?? []) > 1): ?>
                    <div class="dashboard-today-list" aria-label="Outros itens de hoje">
                        <?php foreach ($contextoHoje['items'] as $item): ?>
                            <span class="dashboard-today-chip<?php echo !empty($item['concluido']) ? ' is-complete' : ''; ?>"><strong><?php echo stridebr_e((string) ($item['titulo'] ?? 'Treino')); ?></strong><small><?php echo stridebr_e((string) ($item['hora_inicio'] ?? '')); ?><?php echo !empty($item['concluido']) ? ' · concluído' : ''; ?></small></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="dashboard-section-heading">
                <div><span class="dashboard-eyebrow">Seu painel</span><h2>Acompanhe</h2></div>
                <button type="button" class="dashboard-customize-trigger" data-dashboard-customize-open>Personalizar</button>
            </div>

            <div class="dashboard-modules" data-dashboard-modules>
                <?php foreach ($dashboardPrefs['order'] as $module): ?>
                    <?php $moduleHidden = in_array($module, $dashboardPrefs['hidden'], true); ?>
                    <?php if ($module === 'progress'): ?>
                        <section class="dashboard-panel dashboard-module is-wide" data-dashboard-module="progress"<?php echo $moduleHidden ? ' hidden' : ''; ?>>
                            <div class="dashboard-panel-heading">
                                <div><h2>Progresso da semana</h2><p>Últimos 7 dias.</p></div>
                                <a href="/user/atividades.php">Ver atividades</a>
                            </div>
                            <div class="dashboard-stat-strip is-embedded" aria-label="Resumo da semana">
                                <article><span>Atividades físicas</span><strong><?php echo $resumo['atividades']; ?></strong><small>esta semana</small></article>
                                <article><span>Tempo</span><strong><?php echo stridebr_e(dashboardFormatarDuracao((float) $resumo['duracao_s'])); ?></strong><small>registrado</small></article>
                                <article><span>Distância</span><strong><?php echo stridebr_e(dashboardFormatarNumero((float) $resumo['distancia_km'])); ?> km</strong><small>acumulada</small></article>
                                <article><span>Elevação</span><strong><?php echo stridebr_e(dashboardFormatarNumero((float) $resumo['elevacao_m'], 0)); ?> m</strong><small>acumulada</small></article>
                            </div>
                            <div class="dashboard-progress-layout">
                                <div class="dashboard-week-chart" aria-label="Tempo de treino nos últimos sete dias">
                                    <?php foreach ($dias as $dia):
                                        $percent = $dia['duracao_s'] > 0 ? max(8, ((float) $dia['duracao_s'] / $maxDia) * 100) : 2;
                                    ?>
                                        <div class="dashboard-day<?php echo $dia['hoje'] ? ' is-today' : ''; ?>" title="<?php echo stridebr_e(dashboardFormatarDuracao((float) $dia['duracao_s'])); ?>">
                                            <div class="dashboard-day-track"><span style="height: <?php echo number_format($percent, 2, '.', ''); ?>%"></span></div>
                                            <strong><?php echo stridebr_e($dia['rotulo']); ?></strong>
                                            <small><?php echo $dia['atividades']; ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="dashboard-progress-copy">
                                    <strong><?php echo (int) $resumo['atividades']; ?> atividade<?php echo (int) $resumo['atividades'] === 1 ? '' : 's'; ?> nesta semana</strong>

                                    <?php if ($metasDisponiveis && $metas !== []): ?><a href="/user/metas.php">Acompanhar metas</a><?php else: ?><a href="/user/atividades.php">Abrir histórico</a><?php endif; ?>
                                </div>
                            </div>
                        </section>
                    <?php elseif ($module === 'goals'): ?>
                        <section class="dashboard-panel dashboard-goals-panel dashboard-module is-wide" data-dashboard-module="goals"<?php echo $moduleHidden ? ' hidden' : ''; ?>>
                            <div class="dashboard-panel-heading">
                                <div><h2>Metas</h2></div>
                                <?php if ($metasDisponiveis): ?><div class="dashboard-panel-actions"><a href="/user/metas.php">Gerenciar</a><button class="dashboard-link-button" type="button" data-goal-open>+ Nova meta</button></div><?php endif; ?>
                            </div>
                            <?php if (!$metasDisponiveis): ?>
                                <div class="dashboard-empty compact"><strong>Metas prontas para ativar</strong><span>Rode a migration <code>20260903_v1_rc.sql</code>.</span></div>
                            <?php elseif ($metas === []): ?>
                                <div class="dashboard-empty"><strong>Crie uma meta.</strong><span>Distância, tempo, frequência, carga por exercício ou elevação.</span><button type="button" data-goal-open>+ Criar primeira meta</button></div>
                            <?php else: ?>
                                <div class="dashboard-goal-list">
                                    <?php foreach (array_slice($metas, 0, 5) as $meta):
                                        $alvo = (float) $meta['valor_alvo'];
                                        $progresso = (float) $meta['progresso'];
                                        $unidade = dashboardMetaUnidade((string) $meta['metrica']);
                                        $tituloMeta = dashboardMetaTitulo($meta);
                                        $inteiro = in_array((string) $meta['metrica'], ['atividades', 'dias_ativos'], true);
                                        $restante = (float) ($meta['restante'] ?? 0);
                                    ?>
                                        <article class="dashboard-goal-row<?php echo !empty($meta['atingida']) ? ' is-complete' : ''; ?>">
                                            <div class="dashboard-goal-icon">
                                                <?php if (!empty($meta['modalidade_slug'])): ?><?php echo stridebr_sport_icon_html((string) $meta['modalidade_slug'], 'sport-icon'); ?><?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="1"></circle></svg><?php endif; ?>
                                            </div>
                                            <div class="dashboard-goal-body">
                                                <div class="dashboard-goal-title"><strong><?php echo stridebr_e($tituloMeta); ?></strong><span><?php echo stridebr_e(dashboardMetaPrazoLabel($meta)); ?></span></div>
                                                <div class="dashboard-progress"><span style="width: <?php echo number_format((float) $meta['percentual'], 2, '.', ''); ?>%"></span></div>
                                                <div class="dashboard-goal-meta"><span><?php if (!empty($meta['atingida'])): ?>Meta atingida<?php else: ?>Faltam <?php echo stridebr_e(dashboardFormatarNumero($restante, $inteiro ? 0 : 1)); ?> <?php echo stridebr_e($unidade); ?><?php endif; ?> · <?php echo stridebr_e(dashboardFormatarNumero($progresso, $inteiro ? 0 : 1)); ?> / <?php echo stridebr_e(dashboardFormatarNumero($alvo, $inteiro ? 0 : 1)); ?></span><strong><?php echo (int) round((float) $meta['percentual_real']); ?>%</strong></div>
                                            </div>
                                            <form method="POST" class="dashboard-goal-remove" data-confirm="Remover esta meta do painel?">
                                                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="arquivar_meta"><input type="hidden" name="idmeta" value="<?php echo stridebr_e((string) $meta['idmeta']); ?>"><button type="submit" aria-label="Remover meta">×</button>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($module === 'upcoming'): ?>
                        <section class="dashboard-panel dashboard-module" data-dashboard-module="upcoming"<?php echo $moduleHidden ? ' hidden' : ''; ?>>
                            <div class="dashboard-panel-heading"><div><h2>Próximos treinos</h2></div><a href="/user/cronogramatreinos.php">Cronogramas</a></div>
                            <?php if ($proximos === []): ?>
                                <div class="dashboard-empty compact"><strong>Nada agendado</strong><span>Adicione um treino ao cronograma para ele aparecer aqui.</span><a class="secondary-button compact" href="/user/cronogramatreinos.php">Adicionar treino</a></div>
                            <?php else: ?>
                                <div class="dashboard-schedule-list">
                                    <?php foreach ($proximos as $treino):
                                        $data = $treino['proxima_data'];
                                        $quando = $data->format('Y-m-d') === $hoje->format('Y-m-d') ? 'Hoje' : ($data->format('Y-m-d') === $amanha->format('Y-m-d') ? 'Amanhã' : strtoupper(['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][(int) $data->format('w')]) . ' ' . $data->format('d/m'));
                                        $corTreino = trim((string) ($treino['cor'] ?? ''));
                                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corTreino)) $corTreino = '#65759d';
                                    ?>
                                        <a class="dashboard-schedule-row" href="/user/cronogramatreinos.php"><span class="dashboard-schedule-mark" style="--schedule-color: <?php echo stridebr_e($corTreino); ?>"></span><span class="dashboard-schedule-time"><strong><?php echo stridebr_e($quando); ?></strong><small><?php echo stridebr_e(substr((string) $treino['hora_inicio'], 0, 5)); ?></small></span><span class="dashboard-schedule-info"><strong><?php echo stridebr_e((string) $treino['titulo']); ?></strong><small><?php echo stridebr_e((string) $treino['cronograma_nome']); ?></small></span><span aria-hidden="true">›</span></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($module === 'recent'): ?>
                        <section class="dashboard-panel dashboard-module" data-dashboard-module="recent"<?php echo $moduleHidden ? ' hidden' : ''; ?>>
                            <div class="dashboard-panel-heading"><div><h2>Atividades físicas recentes</h2></div><a href="/user/atividades.php">Histórico</a></div>
                            <?php if ($recentes === []): ?>
                                <div class="dashboard-empty compact"><strong>Nenhuma atividade registrada.</strong><a class="secondary-button compact" href="/user/atividades.php?new=1#nova-atividade">Registrar atividade</a></div>
                            <?php else: ?>
                                <div class="dashboard-activity-list">
                                    <?php foreach ($recentes as $atividade):
                                        $distanciaKm = ((float) $atividade['distancia_m']) / 1000;
                                        $duracao = (float) $atividade['duracao_s'];
                                        $titulo = trim((string) $atividade['titulo']) ?: (string) $atividade['modalidade_nome'];
                                    ?>
                                        <a class="dashboard-activity-row" href="/user/atividades.php#atividade-<?php echo rawurlencode((string) $atividade['idregistro']); ?>"><span class="dashboard-activity-icon"><?php echo stridebr_sport_icon_html((string) $atividade['modalidade_slug'], 'sport-icon'); ?></span><span class="dashboard-activity-info"><strong><?php echo stridebr_e($titulo); ?></strong><small><?php echo stridebr_e((new DateTimeImmutable((string) $atividade['data_inicio']))->format('d/m · H:i')); ?> · <?php echo stridebr_e((string) $atividade['modalidade_nome']); ?></small></span><span class="dashboard-activity-values"><?php if ($distanciaKm > 0): ?><strong><?php echo stridebr_e(dashboardFormatarNumero($distanciaKm)); ?> km</strong><?php endif; ?><?php if ($duracao > 0): ?><small><?php echo stridebr_e(dashboardFormatarDuracao($duracao)); ?></small><?php endif; ?></span><span aria-hidden="true">›</span></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <section class="dashboard-quickbar" aria-label="Acessos rápidos">
                <strong>Acessos rápidos</strong>
                <a href="/user/biblioteca.php?tab=exercicios">Exercícios</a>
                <a href="/user/equipamentos.php">Equipamentos</a>
                <a href="/user/ferramentastreino.php">Ferramentas</a>
                <a href="/calendario.php">Eventos</a>
                <a href="/user/settings.php">Configurações</a>
            </section>
        </div>
    </main>
</div>

<dialog class="dashboard-dialog dashboard-customize-dialog" data-dashboard-customize-dialog>
    <div class="dashboard-customize-form">
        <div class="dashboard-dialog-heading"><div><span class="dashboard-eyebrow">Seu painel</span><h2>Personalizar início</h2><p>Hoje permanece no topo.</p></div><button type="button" data-dashboard-customize-close aria-label="Fechar">×</button></div>
        <div class="dashboard-customize-list" data-dashboard-customize-list>
            <?php foreach ($dashboardPrefs['order'] as $module): ?>
                <div class="dashboard-customize-item" data-dashboard-customize-item="<?php echo stridebr_e($module); ?>">
                    <label><input type="checkbox" data-dashboard-module-visible<?php echo !in_array($module, $dashboardPrefs['hidden'], true) ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e($dashboardLabels[$module]); ?></strong><small><?php echo match ($module) { 'progress' => 'Resumo e ritmo dos últimos sete dias', 'goals' => 'Objetivos e andamento', 'upcoming' => 'O que vem no cronograma', default => 'Seus registros mais recentes' }; ?></small></span></label>
                    <div class="dashboard-customize-move"><button type="button" data-dashboard-move="up" aria-label="Mover para cima">↑</button><button type="button" data-dashboard-move="down" aria-label="Mover para baixo">↓</button></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="dashboard-dialog-actions"><button type="button" class="dashboard-button dashboard-button-secondary" data-dashboard-customize-reset>Restaurar padrão</button><span></span><button type="button" class="dashboard-button dashboard-button-secondary" data-dashboard-customize-close>Cancelar</button><button type="button" class="dashboard-button dashboard-button-primary" data-dashboard-customize-save>Salvar painel</button></div>
    </div>
</dialog>

<?php if ($metasDisponiveis): ?>
<dialog class="dashboard-dialog" data-goal-dialog>
    <form method="POST" class="dashboard-goal-form">
        <?php echo stridebr_csrf_field(); ?>
        <input type="hidden" name="action" value="criar_meta">
        <div class="dashboard-dialog-heading"><div><span class="dashboard-eyebrow">Objetivo</span><h2>Nova meta</h2></div><button type="button" data-goal-close aria-label="Fechar">×</button></div>
        <div class="dashboard-goal-section">
            <div class="dashboard-goal-section-title"><span>1</span><strong>O que acompanhar</strong></div>
            <div class="dashboard-goal-sport-field"><span class="form-field-label">Esporte</span><?php echo sportPickerRenderSelect($goalModalidades, ['name' => 'idmodalidade', 'empty_label' => 'Todos os esportes', 'native_attributes' => ['data-goal-sport' => true]]); ?></div>
            <div class="dashboard-goal-form-grid">
                <label>Métrica
                    <select name="metrica" data-goal-metric>
                        <option value="distancia">Distância</option>
                        <option value="duracao">Tempo</option>
                        <option value="atividades">Atividades</option>
                        <option value="elevacao">Elevação</option>
                        <option value="dias_ativos">Dias ativos</option>
                        <option value="carga_maxima">Carga máxima em exercício</option>
                    </select>
                </label>
                <label class="goal-exercise-field" data-goal-exercise-field hidden>Exercício
                    <select name="idexercicio" data-goal-exercise>
                        <option value="">Escolha um exercício</option>
                    </select>
                    <small>Ex.: 200 kg no leg press. O progresso usa as séries concluídas.</small>
                </label>
                <label>Alvo
                    <span class="dashboard-target-input"><input name="valor_alvo" inputmode="decimal" autocomplete="off" required placeholder="20"><span data-goal-unit>km</span></span>
                </label>
            </div>
        </div>

        <div class="dashboard-goal-section">
            <div class="dashboard-goal-section-title"><span>2</span><strong>Prazo</strong></div>
            <label>Quando essa meta reinicia?
                <select name="periodo" data-goal-period>
                    <option value="continuo">Sem prazo</option>
                    <option value="personalizado">Até uma data</option>
                    <option value="semanal">Toda semana</option>
                    <option value="mensal">Todo mês</option>
                    <option value="anual">Todo ano</option>
                </select>
            </label>
            <div class="dashboard-goal-form-grid dashboard-custom-dates" data-goal-custom-dates hidden><label>Começa em<input type="date" name="data_inicio"></label><label>Atingir até<input type="date" name="data_fim"></label></div>
        </div>

        <label>Nome da meta <small>opcional</small><input name="nome" maxlength="80" placeholder="Ex.: Correr 20 km"></label>
        <div class="dashboard-goal-summary" data-goal-summary aria-live="polite"></div>
        <div class="dashboard-dialog-actions"><button type="button" class="dashboard-button dashboard-button-secondary" data-goal-close>Cancelar</button><button type="submit" class="dashboard-button dashboard-button-primary">Criar meta</button></div>
    </form>
</dialog>
<?php endif; ?>

<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/dashboard.js')); ?>"></script>
</body>
</html>
