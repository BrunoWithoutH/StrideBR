<?php
$routePrivacyHasRoute = !empty($activityRoutePrivacyHasRoute);
$routePrivacyStart = max(0, (int) ($activityRoutePrivacyStart ?? 0));
$routePrivacyEnd = max(0, (int) ($activityRoutePrivacyEnd ?? 0));
?>
<section class="activity-route-privacy-fields" data-route-privacy-fields<?php echo $routePrivacyHasRoute ? '' : ' hidden'; ?>>
    <div class="activity-route-privacy-summary">
        <div><strong><?php echo stridebr_e(stridebr_t('activity.privacy_route')); ?></strong><span><?php echo stridebr_e(stridebr_t('activity.route_full_saved')); ?></span></div>
        <button type="button" class="activity-inline-action" data-route-privacy-toggle aria-expanded="false"><?php echo stridebr_e(stridebr_t('common.configure')); ?></button>
    </div>
    <div class="activity-route-privacy-options" data-route-privacy-options hidden>
        <div class="activity-compact-two-columns">
            <label class="input-field"><span><?php echo stridebr_e(stridebr_t('activity.hide_start')); ?></span><input type="number" name="ocultar_inicio_m" min="0" max="10000" step="50" value="<?php echo $routePrivacyStart; ?>" inputmode="numeric"><small><?php echo stridebr_e(stridebr_t('activity.meters_zero_help')); ?></small></label>
            <label class="input-field"><span><?php echo stridebr_e(stridebr_t('activity.hide_end')); ?></span><input type="number" name="ocultar_fim_m" min="0" max="10000" step="50" value="<?php echo $routePrivacyEnd; ?>" inputmode="numeric"><small><?php echo stridebr_e(stridebr_t('activity.meters_zero_help')); ?></small></label>
        </div>
    </div>
</section>
