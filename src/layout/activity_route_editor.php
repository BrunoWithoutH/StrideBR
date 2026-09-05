<?php
$activityRouteAllowed = !empty($activityRouteAllowed);
$activityRouteValue = isset($activityRouteValue) ? (string) $activityRouteValue : '';
$activityRouteHasPoints = $activityRouteValue !== '';
$activityRouteCompact = !empty($activityRouteCompact);
$activityRouteMode = isset($activityRouteMode) && (string) $activityRouteMode === 'circuit' ? 'circuit' : 'free';
$activityRouteLaps = max(1, (int) ($activityRouteLaps ?? 1));
$activityRouteBaseRaw = $activityRouteBase ?? '';
$activityRouteBase = is_array($activityRouteBaseRaw) ? (json_encode($activityRouteBaseRaw, JSON_UNESCAPED_SLASHES) ?: '') : (string) $activityRouteBaseRaw;
?>
<section class="activity-route-builder<?php echo $activityRouteHasPoints ? ' has-route' : ''; ?>" data-route-editor data-route-allowed="<?php echo $activityRouteAllowed ? '1' : '0'; ?>" data-route-has-points="<?php echo $activityRouteHasPoints ? '1' : '0'; ?>" data-route-compact="<?php echo $activityRouteCompact ? '1' : '0'; ?>"<?php echo $activityRouteAllowed ? '' : ' hidden'; ?>>
    <input type="hidden" name="rota_coordenadas" value="<?php echo stridebr_e($activityRouteValue); ?>" data-route-value>
    <input type="hidden" name="route_editor_mode" value="<?php echo stridebr_e($activityRouteMode); ?>" data-route-mode-value>
    <input type="hidden" name="route_editor_laps" value="<?php echo $activityRouteLaps; ?>" data-route-laps-value>
    <input type="hidden" name="route_editor_base" value="<?php echo stridebr_e($activityRouteBase); ?>" data-route-base-value>
    <input type="hidden" name="rota_metricas[distancia_metros]" value="" disabled data-route-metric="distancia_metros">
    <input type="hidden" name="rota_metricas[ganho_elevacao_m]" value="" disabled data-route-metric="ganho_elevacao_m">
    <input type="hidden" name="rota_metricas[perda_elevacao_m]" value="" disabled data-route-metric="perda_elevacao_m">
    <input type="hidden" name="rota_metricas[elevacao_min_m]" value="" disabled data-route-metric="elevacao_min_m">
    <input type="hidden" name="rota_metricas[elevacao_max_m]" value="" disabled data-route-metric="elevacao_max_m">
    <input type="hidden" name="rota_metricas[fonte_elevacao]" value="" disabled data-route-metric="fonte_elevacao">
    <div class="activity-route-heading">
        <?php if ($activityRouteCompact): ?>
            <div class="activity-route-copy">
                <strong><?php echo stridebr_e(stridebr_t('route.title')); ?></strong>
                <span data-route-distance><?php echo stridebr_e(stridebr_t($activityRouteHasPoints ? 'route.calculating_distance' : 'route.add_manual')); ?></span>
            </div>
            <button type="button" class="activity-secondary-button activity-inline-action activity-row-action" data-route-toggle><?php echo stridebr_e(stridebr_t($activityRouteHasPoints ? 'common.edit' : 'common.add')); ?></button>
        <?php else: ?>
            <div><span class="activity-section-label"><?php echo stridebr_e(stridebr_t('route.title')); ?></span><strong data-route-distance><?php echo stridebr_e(stridebr_t($activityRouteHasPoints ? 'route.calculating_distance' : 'route.no_route')); ?></strong></div>
            <button type="button" class="activity-secondary-button" data-route-toggle><?php echo stridebr_e(stridebr_t($activityRouteHasPoints ? 'route.edit' : 'route.add')); ?></button>
        <?php endif; ?>
    </div>
    <div class="activity-route-workspace" data-route-workspace hidden>
        <?php if ($activityRouteCompact): ?>
            <div class="activity-route-subview-header" data-route-subview-header>
                <button type="button" class="activity-route-subview-back" data-route-subview-back aria-label="<?php echo stridebr_e(stridebr_t('common.back')); ?>">← <span><?php echo stridebr_e(stridebr_t('common.back')); ?></span></button>
                <div><strong data-route-subview-title><?php echo stridebr_e(stridebr_t('route.title')); ?></strong><span data-route-subview-summary><?php echo stridebr_e(stridebr_t('route.add_manual')); ?></span><small data-route-subview-status hidden></small></div>
                <button type="button" class="activity-primary-action activity-route-subview-done" data-route-subview-done><?php echo stridebr_e(stridebr_t('route.done')); ?></button>
            </div>
        <?php endif; ?>
        <div class="activity-route-mode" role="group" aria-label="<?php echo stridebr_e(stridebr_t('route.trace')); ?>">
            <span><?php echo stridebr_e(stridebr_t('route.trace')); ?></span>
            <button type="button" data-route-mode="free" class="is-active" aria-pressed="true"><?php echo stridebr_e(stridebr_t('route.free')); ?></button>
            <button type="button" data-route-mode="circuit" aria-pressed="false"><?php echo stridebr_e(stridebr_t('route.circuit')); ?></button>
        </div>
        <div class="activity-route-toolbar">
            <button type="button" data-route-locate><?php echo stridebr_e(stridebr_t('route.my_location')); ?></button>
            <button type="button" data-route-undo><?php echo stridebr_e(stridebr_t('route.undo')); ?></button>
            <button type="button" data-route-clear><?php echo stridebr_e(stridebr_t('route.clear')); ?></button>
            <button type="button" data-route-close-free hidden><?php echo stridebr_e(stridebr_t('route.close_route')); ?></button>
            <button type="button" data-route-close-circuit hidden><?php echo stridebr_e(stridebr_t('route.close_circuit')); ?></button>
            <button type="button" class="activity-route-done" data-route-done<?php echo $activityRouteCompact ? ' hidden' : ''; ?>><?php echo stridebr_e(stridebr_t('route.done')); ?></button>
        </div>
        <div class="activity-route-map" data-route-map aria-label="<?php echo stridebr_e(stridebr_t('route.map_aria')); ?>"></div>
        <div class="activity-route-circuit-panel" data-route-circuit-panel role="group" aria-label="<?php echo stridebr_e(stridebr_t('route.circuit_summary')); ?>" hidden>
            <div><span><?php echo stridebr_e(stridebr_t('route.circuit_summary')); ?></span><strong data-route-lap-summary></strong><button type="button" class="activity-route-base-edit" data-route-edit-base aria-pressed="false"><?php echo stridebr_e(stridebr_t('route.edit_base')); ?></button></div>
            <div class="activity-route-laps-control">
                <span><?php echo stridebr_e(stridebr_t('route.laps')); ?></span>
                <button type="button" data-route-laps-dec aria-label="<?php echo stridebr_e(stridebr_t('route.decrease_laps')); ?>">−</button>
                <output data-route-laps aria-live="polite">1</output>
                <button type="button" data-route-laps-inc aria-label="<?php echo stridebr_e(stridebr_t('route.increase_laps')); ?>">+</button>
            </div>
            <div><span><?php echo stridebr_e(stridebr_t('route.total')); ?></span><strong data-route-total></strong></div>
        </div>
        <div class="activity-route-status" aria-live="polite"><span data-route-points><?php echo stridebr_e(stridebr_t('route.tap_points')); ?></span><span data-route-elevation hidden><?php echo stridebr_e(stridebr_t('route.estimated_elevation_after')); ?></span></div>
        <small class="activity-route-attribution"><?php echo stridebr_e(stridebr_t('route.elevation_attribution')); ?></small>
    </div>
</section>
