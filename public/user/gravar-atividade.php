<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/gps_web.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';

$modalidades = gpsWebRouteModalities($pdo, $idUsuario);
$requested = trim((string) ($_GET['modalidade'] ?? $_GET['quick'] ?? 'corrida'));
$selected = gpsWebFindModality($modalidades, $requested);
$defaults = atividadePadroesUsuario($pdo, $idUsuario);
$autostart = isset($_GET['autostart']) && (string) $_GET['autostart'] !== '0';
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
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/gps-recorder.css')); ?>">
    <title>Gravar com GPS | StrideBR</title>
</head>
<body class="gps-page">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content gps-recorder-page"
          data-gps-recorder
          data-csrf-token="<?php echo stridebr_e(stridebr_csrf_token()); ?>"
          data-save-endpoint="/api/gps-salvar.php"
          data-autostart="<?php echo $autostart ? '1' : '0'; ?>">
        <section class="gps-setup" data-gps-setup>
            <div class="gps-page-heading">
                <div>
                    <span class="eyebrow">GPS Web</span>
                    <h1>Gravar atividade</h1>
                </div>
                <a href="/user/atividades.php" class="activity-toolbar-link">Voltar às atividades</a>
            </div>

            <div class="gps-web-warning" role="note">
                <strong>GPS Web é uma estimativa.</strong>
                <p>A precisão varia conforme o aparelho e o navegador. Tela bloqueada ou segundo plano podem interromper pontos. Revise os dados antes de salvar.</p>
            </div>

            <div class="gps-restore" data-gps-restore hidden>
                <div><strong>Há uma gravação não finalizada neste navegador.</strong><span data-gps-restore-summary></span></div>
                <div><button type="button" class="gps-secondary" data-gps-resume>Continuar</button><button type="button" class="gps-quiet-danger" data-gps-discard-saved>Descartar</button></div>
            </div>

            <div class="gps-setup-grid">
                <section class="content-card gps-start-card">
                    <div class="gps-section-heading"><div><span class="eyebrow">Atividade</span><h2>O que você vai gravar?</h2></div><span class="gps-web-badge">WEB</span></div>
                    <div class="gps-field gps-sport-picker-field"><span>Esporte</span>
                        <?php echo sportPickerRenderSelect($modalidades, [
                            'name' => 'gps_sport',
                            'selected' => (string) ($selected['idmodalidade'] ?? ''),
                            'placeholder' => 'Escolha o esporte',
                            'native_attributes' => ['data-gps-sport' => true],
                        ]); ?>
                    </div>
                    <div class="gps-quick-row" aria-label="Atalhos rápidos">
                        <?php foreach ($modalidades as $modalidade): ?>
                            <?php if (!in_array((string) $modalidade['slug'], ['corrida', 'caminhada', 'ciclismo'], true)) continue; ?>
                            <button type="button" class="gps-quick-sport" data-gps-quick-sport="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>"><?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'gps-quick-sport-icon'); ?><span><?php echo stridebr_e((string) $modalidade['nome']); ?></span></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="gps-goal-block">
                        <div class="gps-section-label">Meta opcional</div>
                        <div class="gps-goal-grid">
                            <label><input type="radio" name="gps_goal_type" value="none" checked><span>Sem meta</span></label>
                            <label><input type="radio" name="gps_goal_type" value="distance"><span>Distância</span></label>
                            <label><input type="radio" name="gps_goal_type" value="time"><span>Tempo</span></label>
                        </div>
                        <div class="gps-goal-value" data-gps-goal-value hidden>
                            <input type="number" min="0.1" step="0.1" inputmode="decimal" data-gps-goal-number>
                            <select data-gps-goal-unit><option value="km">km</option></select>
                        </div>
                        <label class="gps-check" data-gps-autostop-wrap hidden><input type="checkbox" data-gps-autostop checked><span><strong>Finalizar automaticamente</strong></span></label>
                    </div>

                    <label class="gps-check"><input type="checkbox" data-gps-wakelock checked><span><strong>Manter a tela ligada</strong><small>Quando disponível no navegador.</small></span></label>

                    <button type="button" class="gps-start-button" data-gps-start>Iniciar</button>
                </section>

                <aside class="content-card gps-expect-card">
                    <span class="eyebrow">O que fica salvo</span>
                    <h2>Primeiro no aparelho, depois no servidor</h2>
                    <ul>
                        <li>tempo e pausas;</li>
                        <li>pontos GPS aceitos e precisão informada pelo aparelho;</li>
                        <li>distância calculada com filtro contra saltos e ruído;</li>
                        <li>elevação do dispositivo quando disponível;</li>
                        <li>voltas/trechos marcados durante a atividade.</li>
                    </ul>
                    <p>Se a internet cair, a gravação continua no navegador. Quando você salvar, o StrideBR envia o resultado. Se o navegador for encerrado ou suspenso pelo sistema, podem existir lacunas — isso será sinalizado na revisão.</p>
                </aside>
            </div>
        </section>

        <section class="gps-live" data-gps-live hidden>
            <header class="gps-live-header">
                <div><span class="gps-recording-dot"></span><strong data-gps-live-sport>Atividade</strong><small>GPS Web</small></div>
                <div class="gps-live-statuses"><span data-gps-network>Online</span><span data-gps-quality data-quality="waiting">GPS aguardando</span><span data-gps-accuracy>— m</span></div>
            </header>
            <div class="gps-live-warning" data-gps-live-warning>Para obter o melhor resultado possível no navegador, mantenha esta página visível e a tela ligada. Tela bloqueada ou segundo plano podem interromper pontos GPS.</div>

            <div class="gps-live-layout">
                <div class="gps-live-metrics">
                    <article class="gps-primary-metric"><span>Tempo</span><strong data-gps-time>00:00:00</strong></article>
                    <article><span>Distância</span><strong data-gps-distance>0,00 km</strong></article>
                    <article><span data-gps-pace-label>Ritmo</span><strong data-gps-pace>— /km</strong></article>
                    <article><span>Elevação</span><strong data-gps-elevation>— m</strong></article>
                    <article><span>Precisão atual</span><strong data-gps-accuracy-large>—</strong></article>
                    <article data-gps-goal-card hidden><span>Meta</span><strong data-gps-goal-progress>—</strong></article>
                </div>
                <div class="gps-map-shell">
                    <div class="gps-map" data-gps-map aria-label="Mapa da gravação"></div>
                    <div class="gps-map-fallback" data-gps-map-fallback><strong>Mapa opcional</strong><span>A gravação GPS continua mesmo se os mapas não carregarem.</span></div>
                </div>
            </div>

            <div class="gps-lap-strip" data-gps-lap-strip><span>Trecho atual</span><strong data-gps-current-lap>1</strong><small data-gps-current-lap-metrics>0,00 km · 00:00</small></div>
            <div class="gps-live-controls">
                <button type="button" class="gps-control secondary" data-gps-pause>Pausar</button>
                <button type="button" class="gps-control secondary" data-gps-lap>Marcar trecho</button>
                <button type="button" class="gps-control finish" data-gps-finish>Finalizar</button>
            </div>
        </section>

        <section class="gps-review" data-gps-review hidden>
            <div class="gps-page-heading">
                <div><span class="eyebrow">Revisão</span><h1>Confira antes de salvar</h1></div>
                <span class="gps-review-quality" data-gps-review-quality></span>
            </div>
            <div class="gps-review-warning" data-gps-review-warning hidden></div>
            <div class="gps-review-grid">
                <form class="content-card gps-review-form" data-gps-save-form>
                    <div class="gps-review-fields">
                        <label class="gps-field is-wide">Título<input type="text" maxlength="255" data-gps-review-title placeholder="Ex.: Corrida de domingo"></label>
                        <label class="gps-field">Distância (km)<input type="number" min="0" step="0.01" inputmode="decimal" data-gps-review-distance></label>
                        <label class="gps-field">Tempo<input type="text" inputmode="numeric" placeholder="00:45:20" data-gps-review-duration></label>
                        <label class="gps-field">Elevação positiva (m)<input type="number" min="0" step="1" inputmode="decimal" data-gps-review-elevation></label>
                        <label class="gps-field">Quem pode ver<select data-gps-review-visibility><option value="privado"<?php echo $defaults['visibility'] === 'privado' ? ' selected' : ''; ?>>Só eu</option><option value="amigos"<?php echo $defaults['visibility'] === 'amigos' ? ' selected' : ''; ?>>Amigos</option><option value="publico"<?php echo $defaults['visibility'] === 'publico' ? ' selected' : ''; ?>>Público</option></select></label>
                        <label class="gps-field">Como foi?<select data-gps-review-effort><option value="">Não informar</option><option value="2">Muito leve</option><option value="4">Leve</option><option value="6">Moderado</option><option value="8">Difícil</option><option value="10">Muito difícil</option></select></label>
                        <label class="gps-field">Ocultar início da rota (m)<input type="number" min="0" max="10000" step="50" value="<?php echo (int) $defaults['hide_route_start_m']; ?>" data-gps-review-hide-start></label>
                        <label class="gps-field">Ocultar fim da rota (m)<input type="number" min="0" max="10000" step="50" value="<?php echo (int) $defaults['hide_route_end_m']; ?>" data-gps-review-hide-end></label>
                        <label class="gps-field is-wide">Observações<textarea rows="3" maxlength="5000" data-gps-review-notes placeholder="Como foi a atividade?"></textarea></label>
                    </div>
                    <div class="gps-save-state" data-gps-save-state aria-live="polite"></div>
                    <div class="gps-review-actions"><button type="button" class="gps-secondary" data-gps-back-live>Voltar</button><button type="submit" class="gps-start-button">Salvar atividade</button></div>
                </form>
                <aside class="content-card gps-review-side">
                    <span class="eyebrow">Qualidade da gravação</span>
                    <div class="gps-quality-summary" data-gps-quality-summary></div>
                    <div class="gps-review-map" data-gps-review-map></div>
                    <div class="gps-segments-review"><div class="gps-section-heading"><div><span class="eyebrow">Trechos</span><h2>Voltas marcadas</h2></div></div><div data-gps-segments></div></div>
                    <p class="gps-review-note">Depois de salvar, você ainda pode abrir <strong>Editar atividade</strong> e corrigir dados manualmente. A rota gravada é mantida separada das métricas que você ajustar.</p>
                </aside>
            </div>
        </section>
    </main>
    <?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/gps-recorder.js')); ?>" defer></script>
</body>
</html>
