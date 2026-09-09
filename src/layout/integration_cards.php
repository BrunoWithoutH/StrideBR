<?php /* Shared profile connection cards; no credentials are rendered. */ ?>
<div class="settings-connections-grid">
<?php foreach ($integrationRegistry as $providerId => $provider):
    $connection = $integrationConnections[$providerId] ?? null;
    $connected = is_array($connection) && in_array((string) ($connection['status'] ?? ''), ['conectado', 'erro'], true);
    $configured = stridebr_integrations_configured($provider);
    $kind = (string) ($provider['kind'] ?? 'cloud');
    $syncState = $connected ? (stridebr_integrations_metadata($connection)['sync'] ?? []) : [];
    $reauthorize = !empty($syncState['reauthorize']);
    $syncing = !empty($syncState['started_at']) && (int) $syncState['started_at'] > time() - 1800;
    $hasError = $connected && ($connection['status'] ?? '') === 'erro';
    $automatic = in_array($providerId, stridebr_integrations_periodic_providers(), true);
    $enabled = stridebr_db_bool($connection['sincronizar_atividades'] ?? true);
    $statusKey = $connected ? ($reauthorize ? 'integrations.reauthorize' : ($syncing ? 'integrations.syncing' : ($hasError ? 'integrations.last_error' : 'common.connected'))) : ($configured ? 'integrations.not_connected' : 'settings.unavailable');
    $returnTo = '/user/edit-profile.php#conexoes';
    $lastSync = $connected && !empty($connection['ultima_sincronizacao_em']) ? new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']) : null;
    $lastSync = $lastSync?->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $lastLabel = $lastSync && $lastSync->format('Y-m-d') === (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d') ? stridebr_t('integrations.today', ['time' => $lastSync->format('H:i')]) : ($lastSync ? stridebr_format_datetime_short($lastSync) : '');
?>
<article class="integration-card<?php echo $connected ? ' is-connected' : ''; ?>" data-integration-provider="<?php echo stridebr_e($providerId); ?>" data-syncing-label="<?php echo stridebr_e(stridebr_t('integrations.syncing')); ?>">
    <div class="integration-card-main">
        <span class="integration-provider-mark" aria-hidden="true"><?php echo stridebr_e(strtoupper(substr((string) $provider['short'], 0, 1))); ?></span>
        <div class="integration-card-copy">
            <div class="integration-card-title">
                <h3><?php echo stridebr_e($provider['label']); ?></h3>
                <span class="integration-status<?php echo $hasError ? ' has-error' : ($connected ? ' is-connected' : ''); ?>" aria-live="polite"><?php echo stridebr_e(stridebr_t($statusKey)); ?></span>
            </div>
            <?php if ($connected): ?>
                <?php if ($providerId === 'strava' && trim((string) ($connection['usuario_externo_nome'] ?? '')) !== ''): ?><small class="integration-athlete"><?php echo stridebr_e($connection['usuario_externo_nome']); ?></small><?php endif; ?>
                <p class="integration-sync-mode"><?php echo stridebr_e(stridebr_t($automatic ? ($enabled ? 'integrations.automatic' : 'integrations.paused') : 'integrations.manual')); ?></p>
                <small><?php echo stridebr_e($lastSync ? stridebr_t('settings.last_sync', ['date' => $lastLabel]) : stridebr_t('settings.awaiting_first_sync')); ?></small>
                <?php if ($hasError): ?><p class="integration-error-text"><?php echo stridebr_e(stridebr_t($reauthorize ? 'integrations.reauthorize_help' : 'integrations.try_later')); ?></p><?php endif; ?>
            <?php else: ?>
                <p><?php echo stridebr_e($provider['description']); ?></p>
                <?php if ($kind === 'future'): ?><small><?php echo stridebr_e(stridebr_t('settings.awaiting_availability')); ?></small>
                <?php elseif ($kind === 'mobile'): ?><small><?php echo stridebr_e(stridebr_t($providerId === 'health_connect' ? 'settings.android_activation' : 'settings.ios_activation')); ?></small>
                <?php elseif ($kind === 'bridge'): ?><small><?php echo stridebr_e(stridebr_t('settings.health_connect_help')); ?></small><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="integration-card-actions">
        <?php if ($connected): ?>
            <?php if (isset($integrationSyncReady[$providerId]) && !$reauthorize): ?>
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
            <a class="integration-button" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;return=<?php echo rawurlencode($returnTo); ?>"><?php echo stridebr_e(stridebr_t('settings.connect')); ?></a>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/integrations.js')); ?>" defer></script>
