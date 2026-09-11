<?php /* Shared connection cards; no credentials are rendered. */ ?>
<?php
$integrationProviderIds = array_keys($integrationRegistry);
usort($integrationProviderIds, static function (string $a, string $b) use ($integrationRegistry, $integrationConnections): int {
    $rank = static function (string $id) use ($integrationRegistry, $integrationConnections): int {
        $provider = $integrationRegistry[$id] ?? [];
        $connection = $integrationConnections[$id] ?? null;
        if (is_array($connection) && in_array((string) ($connection['status'] ?? ''), ['conectado', 'erro'], true)) return 0;
        if (($provider['kind'] ?? '') === 'cloud' && stridebr_integrations_configured($provider)) return 1;
        if (in_array((string) ($provider['kind'] ?? ''), ['mobile', 'bridge'], true)) return 2;
        return 3;
    };
    return ($rank($a) <=> $rank($b)) ?: strcmp($a, $b);
});
?>
<div class="settings-connections-grid">
<?php foreach ($integrationProviderIds as $providerId):
    $provider = $integrationRegistry[$providerId];
    $connection = $integrationConnections[$providerId] ?? null;
    $connected = is_array($connection) && in_array((string) ($connection['status'] ?? ''), ['conectado', 'erro'], true);
    $configured = stridebr_integrations_configured($provider);
    $kind = (string) ($provider['kind'] ?? 'cloud');
    $metadata = $connected ? stridebr_integrations_metadata($connection) : [];
    $syncState = is_array($metadata['sync'] ?? null) ? $metadata['sync'] : [];
    $reauthorize = !empty($syncState['reauthorize']);
    $syncing = !empty($syncState['started_at']) && (int) $syncState['started_at'] > time() - 1800;
    $hasError = $connected && ($connection['status'] ?? '') === 'erro';
    $automatic = in_array($providerId, stridebr_integrations_periodic_providers(), true);
    $enabled = stridebr_db_bool($connection['sincronizar_atividades'] ?? true);
    $returnTo = '/user/settings.php?view=connections#conexoes';
    $lastSync = $connected && !empty($connection['ultima_sincronizacao_em']) ? new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']) : null;
    $lastSync = $lastSync?->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $lastLabel = $lastSync && $lastSync->format('Y-m-d') === (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d') ? stridebr_t('integrations.today', ['time' => $lastSync->format('H:i')]) : ($lastSync ? stridebr_format_datetime_short($lastSync) : '');
    $statusKey = $connected ? ($reauthorize ? 'integrations.reauthorize' : ($syncing ? 'integrations.syncing' : ($hasError ? 'integrations.last_error' : 'common.connected'))) : ($configured ? 'integrations.not_connected' : 'settings.unavailable');
    $stravaView = null;
    $stravaState = '';
    $stravaTitle = '';
    $stravaHelp = '';
    $stravaProgress = '';
    if ($providerId === 'strava' && $connected && isset($pdo) && $pdo instanceof PDO) {
        $stravaView = stridebr_integrations_strava_backfill_view($pdo, (string) $connection['idusuario'], $connection);
        $backfill = is_array($stravaView['state'] ?? null) ? $stravaView['state'] : [];
        $backfillStatus = (string) ($backfill['status'] ?? 'pending');
        if (!$enabled) {
            $stravaState = 'disabled';
            $stravaTitle = stridebr_t('integrations.strava.disabled_title');
            $stravaHelp = stridebr_t('integrations.strava.disabled_help');
        } elseif ($reauthorize || ($backfill['error_code'] ?? '') === 'reauthorize') {
            $stravaState = 'reauthorize';
            $stravaTitle = stridebr_t('integrations.strava.reauth_title');
            $stravaHelp = stridebr_t('integrations.strava.reauth_help');
        } elseif ($syncing) {
            $stravaState = 'recent';
            $stravaTitle = stridebr_t('integrations.strava.recent_title');
            $stravaHelp = stridebr_t('integrations.strava.recent_help');
        } elseif ($backfillStatus === 'paused' && ($backfill['pause_reason'] ?? '') === 'rate_limit') {
            $stravaState = 'paused';
            $stravaTitle = stridebr_t('integrations.strava.paused_title');
            $stravaHelp = stridebr_t('integrations.strava.paused_help');
        } elseif ($backfillStatus === 'retry') {
            $stravaState = 'retry';
            $stravaTitle = stridebr_t('integrations.strava.retry_title');
            $stravaHelp = stridebr_t('integrations.strava.retry_help');
        } elseif ($backfillStatus === 'completed') {
            $stravaState = 'completed';
            if ((int) ($stravaView['total'] ?? 0) === 0) {
                $stravaTitle = stridebr_t('integrations.strava.none');
            } else {
                $stravaTitle = stridebr_t('integrations.strava.complete_title');
                $stravaHelp = stridebr_t('integrations.strava.complete_help');
            }
        } else {
            $stravaState = 'history';
            $stravaTitle = stridebr_t('integrations.strava.history_title');
            $stravaHelp = stridebr_t('integrations.strava.history_help');
        }
        $totalImported = (int) ($stravaView['total'] ?? 0);
        $oldestImported = trim((string) ($stravaView['oldest_at'] ?? ''));
        if ($totalImported > 0 && $oldestImported !== '') $stravaProgress = stridebr_t('integrations.strava.history_progress', ['count' => $totalImported, 'date' => stridebr_format_date_short($oldestImported)]);
        elseif ($totalImported > 0) $stravaProgress = stridebr_t('integrations.strava.history_count', ['count' => $totalImported]);
    }
?>
<article class="integration-card<?php echo $connected ? ' is-connected' : ''; ?>" data-integration-provider="<?php echo stridebr_e($providerId); ?>"<?php echo $stravaState !== '' ? ' data-strava-state="' . stridebr_e($stravaState) . '"' : ''; ?> data-syncing-label="<?php echo stridebr_e(stridebr_t('integrations.syncing')); ?>">
    <div class="integration-card-main">
        <span class="integration-provider-mark<?php echo $providerId === 'strava' ? ' is-wordmark' : ''; ?>" aria-hidden="true"><?php echo $providerId === 'strava' ? 'Strava' : stridebr_e((string) $provider['short']); ?></span>
        <div class="integration-card-copy">
            <div class="integration-card-title">
                <h3><?php echo stridebr_e($provider['label']); ?></h3>
                <?php if ($providerId !== 'strava' || !$connected): ?><span class="integration-status<?php echo $hasError ? ' has-error' : ($connected ? ' is-connected' : ''); ?>" aria-live="polite"><?php echo stridebr_e(stridebr_t($statusKey)); ?></span><?php endif; ?>
            </div>
            <?php if ($connected): ?>
                <?php if ($providerId === 'strava'): ?>
                    <?php if (trim((string) ($connection['usuario_externo_nome'] ?? '')) !== ''): ?><small class="integration-athlete"><?php echo stridebr_e($connection['usuario_externo_nome']); ?></small><?php endif; ?>
                    <div class="integration-strava-state" aria-live="polite"><strong><?php echo stridebr_e($stravaTitle); ?></strong><?php if ($stravaHelp !== ''): ?><p><?php echo stridebr_e($stravaHelp); ?></p><?php endif; ?><?php if ($stravaProgress !== ''): ?><small><?php echo stridebr_e($stravaProgress); ?></small><?php endif; ?></div>
                <?php else: ?>
                    <p class="integration-sync-mode"><?php echo stridebr_e(stridebr_t($automatic ? ($enabled ? 'integrations.automatic' : 'integrations.paused') : 'integrations.manual')); ?></p>
                <?php endif; ?>
                <small><?php echo stridebr_e($lastSync ? stridebr_t('settings.last_sync', ['date' => $lastLabel]) : stridebr_t('settings.awaiting_first_sync')); ?></small>
                <?php if ($hasError && $providerId !== 'strava'): ?><p class="integration-error-text"><?php echo stridebr_e(stridebr_t($reauthorize ? 'integrations.reauthorize_help' : 'integrations.try_later')); ?></p><?php endif; ?>
            <?php else: ?>
                <p><?php echo stridebr_e($providerId === 'strava' ? stridebr_t('integrations.strava.disconnected_help') : $provider['description']); ?></p>
                <?php if ($kind === 'future'): ?><small><?php echo stridebr_e(stridebr_t('settings.awaiting_availability')); ?></small>
                <?php elseif ($kind === 'mobile'): ?><small><?php echo stridebr_e(stridebr_t($providerId === 'health_connect' ? 'settings.android_activation' : 'settings.ios_activation')); ?></small>
                <?php elseif ($kind === 'bridge'): ?><small><?php echo stridebr_e(stridebr_t('settings.health_connect_help')); ?></small><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="integration-card-actions">
        <?php if ($connected): ?>
            <?php if (isset($integrationSyncReady[$providerId]) && !$reauthorize && $enabled): ?>
            <form method="POST" action="/function/integration-action.php" data-integration-sync>
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="sync"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                <button type="submit" class="integration-button"<?php echo $syncing ? ' disabled' : ''; ?>><?php echo stridebr_e(stridebr_t($syncing ? 'integrations.syncing' : 'settings.sync_now')); ?></button>
            </form>
            <?php endif; ?>
            <?php if ($reauthorize && $configured): ?><a class="integration-button" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;reauthorize=1&amp;return=<?php echo rawurlencode($returnTo); ?>"><?php echo stridebr_e(stridebr_t('settings.reauthorize')); ?></a><?php endif; ?>
            <details class="integration-menu">
                <summary class="integration-button integration-more" aria-label="<?php echo stridebr_e(stridebr_t('integrations.actions', ['provider' => $provider['label']])); ?>"><span aria-hidden="true">•••</span></summary>
                <div class="integration-menu-panel">
                    <?php if ($configured): ?><a class="integration-menu-action" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;reauthorize=1&amp;return=<?php echo rawurlencode($returnTo); ?>"><?php echo stridebr_e(stridebr_t('settings.reauthorize')); ?></a><?php endif; ?>
                    <details class="integration-preferences">
                        <summary class="integration-menu-action"><?php echo stridebr_e(stridebr_t('settings.preferences')); ?></summary>
                        <form method="POST" action="/function/integration-action.php" class="integration-preferences-form">
                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="preferences"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                            <label><input type="checkbox" name="sync_activities" value="1"<?php echo $enabled ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t($automatic ? 'integrations.automatic_preference' : 'settings.sync_activities')); ?></label>
                            <?php if (in_array('workouts_out', $provider['capabilities'] ?? [], true)): ?><label><input type="checkbox" name="sync_workouts" value="1"<?php echo stridebr_db_bool($connection['sincronizar_treinos'] ?? false) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('settings.send_workouts')); ?></label><?php endif; ?>
                            <?php if (!empty($provider['profile_link'])): ?><label><input type="checkbox" name="show_profile" value="1"<?php echo stridebr_db_bool($connection['mostrar_perfil'] ?? false) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('settings.show_connection')); ?></label><label class="integration-profile-url"><?php echo stridebr_e(stridebr_t('settings.public_profile_link')); ?><input type="url" name="profile_url" maxlength="500" placeholder="https://..." value="<?php echo stridebr_e((string) ($connection['perfil_publico_url'] ?? '')); ?>"></label><?php endif; ?>
                            <button type="submit" class="integration-button"><?php echo stridebr_e(stridebr_t('settings.save_preferences')); ?></button>
                        </form>
                    </details>
                    <form method="POST" action="/function/integration-action.php" class="integration-disconnect">
                        <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="disconnect"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                        <button type="submit" class="integration-menu-action is-danger"><?php echo stridebr_e(stridebr_t('settings.disconnect')); ?></button>
                    </form>
                </div>
            </details>
        <?php elseif ($kind === 'cloud' && $configured): ?>
            <a class="integration-button<?php echo $providerId === 'strava' ? ' integration-strava-connect' : ''; ?>" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;return=<?php echo rawurlencode($returnTo); ?>"><?php echo stridebr_e($providerId === 'strava' ? stridebr_t('integrations.strava.connect') : stridebr_t('settings.connect')); ?></a>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/integrations.js')); ?>" defer></script>
