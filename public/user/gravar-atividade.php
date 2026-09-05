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

    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/gps-recorder.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('gps.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
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
                    <h1><?php echo stridebr_e(stridebr_t('gps.record_activity')); ?></h1>
                </div>
                <a href="/user/atividades.php" class="activity-toolbar-link"><?php echo stridebr_e(stridebr_t('gps.back_activities')); ?></a>
            </div>

            <div class="gps-web-warning" role="note">
                <strong><?php echo stridebr_e(stridebr_t('gps.estimate_title')); ?></strong>
                <p><?php echo stridebr_e(stridebr_t('gps.estimate_help')); ?></p>
            </div>

            <div class="gps-restore" data-gps-restore hidden>
                <div><strong><?php echo stridebr_e(stridebr_t('gps.restore_title')); ?></strong><span data-gps-restore-summary></span></div>
                <div><button type="button" class="gps-secondary" data-gps-resume><?php echo stridebr_e(stridebr_t('common.continue')); ?></button><button type="button" class="gps-quiet-danger" data-gps-discard-saved><?php echo stridebr_e(stridebr_t('gps.discard')); ?></button></div>
            </div>

            <div class="gps-setup-grid">
                <section class="content-card gps-start-card">
                    <div class="gps-section-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('gps.activity_kicker')); ?></span><h2><?php echo stridebr_e(stridebr_t('gps.what_record')); ?></h2></div><span class="gps-web-badge">WEB</span></div>
                    <div class="gps-field gps-sport-picker-field"><span><?php echo stridebr_e(stridebr_t('common.sport')); ?></span>
                        <?php echo sportPickerRenderSelect($modalidades, [
                            'name' => 'gps_sport',
                            'selected' => (string) ($selected['idmodalidade'] ?? ''),
                            'placeholder' => stridebr_t('gps.choose_sport'),
                            'native_attributes' => ['data-gps-sport' => true],
                        ]); ?>
                    </div>
                    <div class="gps-quick-row" aria-label="<?php echo stridebr_e(stridebr_t('gps.quick_shortcuts')); ?>">
                        <?php foreach ($modalidades as $modalidade): ?>
                            <?php if (!in_array((string) $modalidade['slug'], ['corrida', 'caminhada', 'ciclismo'], true)) continue; ?>
                            <button type="button" class="gps-quick-sport" data-gps-quick-sport="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>"><?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'gps-quick-sport-icon'); ?><span><?php echo stridebr_e(stridebr_sport_name((string) ($modalidade['slug'] ?? ''), (string) $modalidade['nome'])); ?></span></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="gps-goal-block">
                        <div class="gps-section-label"><?php echo stridebr_e(stridebr_t('gps.optional_goal')); ?></div>
                        <div class="gps-goal-grid">
                            <label><input type="radio" name="gps_goal_type" value="none" checked><span><?php echo stridebr_e(stridebr_t('gps.no_goal')); ?></span></label>
                            <label><input type="radio" name="gps_goal_type" value="distance"><span><?php echo stridebr_e(stridebr_t('activity.summary.distance')); ?></span></label>
                            <label><input type="radio" name="gps_goal_type" value="time"><span><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></span></label>
                        </div>
                        <div class="gps-goal-value" data-gps-goal-value hidden>
                            <input type="number" min="0.1" step="0.1" inputmode="decimal" data-gps-goal-number>
                            <select data-gps-goal-unit><option value="km">km</option></select>
                        </div>
                        <label class="gps-check" data-gps-autostop-wrap hidden><input type="checkbox" data-gps-autostop checked><span><strong><?php echo stridebr_e(stridebr_t('gps.finish_automatically')); ?></strong></span></label>
                    </div>


                    <button type="button" class="gps-start-button" data-gps-start><?php echo stridebr_e(stridebr_t('agenda.start')); ?></button>
                </section>

                <aside class="content-card gps-expect-card">
                    <span class="eyebrow"><?php echo stridebr_e(stridebr_t('gps.saved_data')); ?></span>
                    <h2><?php echo stridebr_e(stridebr_t('gps.device_then_server')); ?></h2>
                    <ul>
                        <li><?php echo stridebr_e(stridebr_t('gps.saved_time_pauses')); ?></li>
                        <li><?php echo stridebr_e(stridebr_t('gps.saved_points')); ?></li>
                        <li><?php echo stridebr_e(stridebr_t('gps.saved_distance')); ?></li>
                        <li><?php echo stridebr_e(stridebr_t('gps.saved_elevation')); ?></li>
                        <li><?php echo stridebr_e(stridebr_t('gps.saved_laps')); ?></li>
                    </ul>
                    <p><?php echo stridebr_e(stridebr_t('gps.offline_help')); ?></p>
                </aside>
            </div>
        </section>

        <section class="gps-live" data-gps-live hidden>
            <header class="gps-live-header">
                <div><span class="gps-recording-dot"></span><strong data-gps-live-sport><?php echo stridebr_e(stridebr_t('gps.activity')); ?></strong><small>GPS Web</small></div>
                <div class="gps-live-statuses"><span data-gps-network><?php echo stridebr_e(stridebr_t('gps.online')); ?></span><span data-gps-quality data-quality="waiting"><?php echo stridebr_e(stridebr_t('gps.waiting')); ?></span><span data-gps-accuracy>— m</span></div>
            </header>
            <div class="gps-live-warning" data-gps-live-warning><?php echo stridebr_e(stridebr_t('gps.live_warning')); ?></div>
            <div class="gps-wakelock-fallback" data-gps-wakelock-fallback hidden role="status"><?php echo stridebr_e(stridebr_t('gps.wake_lock_unavailable')); ?></div>

            <div class="gps-live-layout">
                <div class="gps-live-metrics">
                    <article class="gps-primary-metric"><span><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?></span><strong data-gps-time>00:00:00</strong></article>
                    <article><span><?php echo stridebr_e(stridebr_t('activity.summary.distance')); ?></span><strong data-gps-distance>0,00 km</strong></article>
                    <article><span data-gps-pace-label><?php echo stridebr_e(stridebr_t('gps.pace')); ?></span><strong data-gps-pace>— /km</strong></article>
                    <article><span><?php echo stridebr_e(stridebr_t('activity.summary.elevation')); ?></span><strong data-gps-elevation>— m</strong></article>
                    <article><span><?php echo stridebr_e(stridebr_t('gps.current_accuracy')); ?></span><strong data-gps-accuracy-large>—</strong></article>
                    <article data-gps-goal-card hidden><span><?php echo stridebr_e(stridebr_t('gps.goal')); ?></span><strong data-gps-goal-progress>—</strong></article>
                </div>
                <div class="gps-map-shell">
                    <div class="gps-map" data-gps-map aria-label="<?php echo stridebr_e(stridebr_t('gps.recording_map')); ?>"></div>
                    <div class="gps-map-fallback" data-gps-map-fallback><strong><?php echo stridebr_e(stridebr_t('gps.optional_map')); ?></strong><span><?php echo stridebr_e(stridebr_t('gps.map_fallback')); ?></span></div>
                </div>
            </div>

            <div class="gps-lap-strip" data-gps-lap-strip><span><?php echo stridebr_e(stridebr_t('gps.current_segment')); ?></span><strong data-gps-current-lap>1</strong><small data-gps-current-lap-metrics>0,00 km · 00:00</small></div>
            <div class="gps-live-controls" data-gps-live-controls>
                <button type="button" class="gps-control secondary" data-gps-pause><?php echo stridebr_e(stridebr_t('gps.pause')); ?></button>
                <button type="button" class="gps-control secondary" data-gps-lap><?php echo stridebr_e(stridebr_t('gps.mark_segment')); ?></button>
                <button type="button" class="gps-control secondary" data-gps-lock aria-label="<?php echo stridebr_e(stridebr_t('gps.lock_controls')); ?>"><?php echo stridebr_e(stridebr_t('gps.lock_controls')); ?></button>
                <button type="button" class="gps-control finish" data-gps-finish><?php echo stridebr_e(stridebr_t('gps.finish')); ?></button>
                <button type="button" class="gps-control danger" data-gps-discard-current><?php echo stridebr_e(stridebr_t('gps.cancel_delete')); ?></button>
            </div>
            <div class="gps-controls-lock-state" data-gps-controls-lock-state hidden role="status" aria-live="polite" aria-atomic="true">
                <div class="gps-controls-lock-label"><span aria-hidden="true">🔒</span><strong><?php echo stridebr_e(stridebr_t('gps.controls_locked')); ?></strong></div>
                <button type="button" class="gps-hold-unlock" data-gps-unlock aria-label="<?php echo stridebr_e(stridebr_t('gps.hold_unlock_aria')); ?>"><span><?php echo stridebr_e(stridebr_t('gps.hold_unlock')); ?></span><i aria-hidden="true"></i></button>
            </div>
        </section>

        <section class="gps-review" data-gps-review hidden>
            <div class="gps-page-heading">
                <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('gps.review')); ?></span><h1><?php echo stridebr_e(stridebr_t('gps.review_title')); ?></h1></div>
                <span class="gps-review-quality" data-gps-review-quality></span>
            </div>
            <div class="gps-review-warning" data-gps-review-warning hidden></div>
            <div class="gps-review-grid">
                <form class="content-card gps-review-form" data-gps-save-form>
                    <div class="gps-review-fields">
                        <label class="gps-field is-wide"><?php echo stridebr_e(stridebr_t('common.title')); ?><input type="text" maxlength="255" data-gps-review-title placeholder="<?php echo stridebr_e(stridebr_t('gps.title_placeholder')); ?>"></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('gps.distance_km')); ?><input type="number" min="0" step="0.01" inputmode="decimal" data-gps-review-distance></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('activity.summary.time')); ?><input type="text" inputmode="numeric" placeholder="00:45:20" data-gps-review-duration></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('gps.positive_elevation_m')); ?><input type="number" min="0" step="1" inputmode="decimal" data-gps-review-elevation></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('activity.who_can_see')); ?><select data-gps-review-visibility><option value="privado"<?php echo $defaults['visibility'] === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.only_me')); ?></option><option value="amigos"<?php echo $defaults['visibility'] === 'amigos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $defaults['visibility'] === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('gps.how_was_it')); ?><select data-gps-review-effort><option value=""><?php echo stridebr_e(stridebr_t('schedule.not_inform')); ?></option><option value="2"><?php echo stridebr_e(stridebr_t('gps.effort_very_light')); ?></option><option value="4"><?php echo stridebr_e(stridebr_t('schedule.light')); ?></option><option value="6"><?php echo stridebr_e(stridebr_t('schedule.moderate')); ?></option><option value="8"><?php echo stridebr_e(stridebr_t('gps.effort_hard')); ?></option><option value="10"><?php echo stridebr_e(stridebr_t('gps.effort_very_hard')); ?></option></select></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('settings.hide_route_start')); ?><input type="number" min="0" max="10000" step="50" value="<?php echo (int) $defaults['hide_route_start_m']; ?>" data-gps-review-hide-start></label>
                        <label class="gps-field"><?php echo stridebr_e(stridebr_t('settings.hide_route_end')); ?><input type="number" min="0" max="10000" step="50" value="<?php echo (int) $defaults['hide_route_end_m']; ?>" data-gps-review-hide-end></label>
                        <label class="gps-field is-wide"><?php echo stridebr_e(stridebr_t('activity.notes')); ?><textarea rows="3" maxlength="5000" data-gps-review-notes placeholder="<?php echo stridebr_e(stridebr_t('gps.notes_placeholder')); ?>"></textarea></label>
                    </div>
                    <div class="gps-save-state" data-gps-save-state aria-live="polite"></div>
                    <div class="gps-review-actions"><button type="button" class="gps-quiet-danger" data-gps-discard-review><?php echo stridebr_e(stridebr_t('gps.discard_recording')); ?></button><button type="button" class="gps-secondary" data-gps-back-live><?php echo stridebr_e(stridebr_t('common.back')); ?></button><button type="submit" class="gps-start-button"><?php echo stridebr_e(stridebr_t('activity.save')); ?></button></div>
                </form>
                <aside class="content-card gps-review-side">
                    <span class="eyebrow"><?php echo stridebr_e(stridebr_t('gps.recording_quality')); ?></span>
                    <div class="gps-quality-summary" data-gps-quality-summary></div>
                    <div class="gps-review-map" data-gps-review-map></div>
                    <div class="gps-segments-review"><div class="gps-section-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('gps.segments')); ?></span><h2><?php echo stridebr_e(stridebr_t('gps.marked_laps')); ?></h2></div></div><div data-gps-segments></div></div>
                    <p class="gps-review-note"><?php echo stridebr_e(stridebr_t('gps.review_note', ['edit' => stridebr_t('activity.edit_activity')])); ?></p>
                </aside>
            </div>
        </section>
    </main>
    <?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</div>
<?php echo stridebr_maps_runtime_script(); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/gps-recorder.js')); ?>" defer></script>
</body>
</html>
