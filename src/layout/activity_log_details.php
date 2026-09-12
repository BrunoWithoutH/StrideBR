<?php
$detailEffort = (string) ($activityEditorEffort ?? '');
$detailEquipment = is_array($activityEditorEquipment ?? null) ? $activityEditorEquipment : [];
$detailEquipmentSelected = array_values(array_map('strval', is_array($activityEditorSelectedEquipment ?? null) ? $activityEditorSelectedEquipment : []));
$detailEquipmentSelectedMap = array_fill_keys($detailEquipmentSelected, true);
$detailEquipmentLoaded = !isset($activityEditorEquipmentLoaded) || !empty($activityEditorEquipmentLoaded);
$detailObservations = (string) ($activityEditorObservations ?? '');
$detailVisibility = (string) ($activityEditorVisibility ?? 'privado');
$detailCompetitions = is_array($activityEditorCompetitions ?? null) ? $activityEditorCompetitions : [];
$detailCompetition = trim((string) ($activityEditorCompetition ?? ''));
?>
<div class="activity-log-details">
    <section class="activity-effort-section" data-effort-selector>
        <div class="activity-detail-heading"><div><strong><?php echo stridebr_e(stridebr_t('activity.perceived_effort')); ?></strong><span><?php echo stridebr_e(stridebr_t('activity.effort_optional')); ?></span></div><button type="button" class="activity-inline-action" data-clear-effort<?php echo $detailEffort === '' ? ' hidden' : ''; ?>><?php echo stridebr_e(stridebr_t('schedule.not_inform')); ?></button></div>
        <input type="hidden" name="esforco_percebido" value="<?php echo stridebr_e($detailEffort); ?>" data-effort-value>
        <div class="effort-range-row"><input type="range" min="1" max="10" step="1" value="<?php echo stridebr_e($detailEffort !== '' ? $detailEffort : '5'); ?>" data-effort-range aria-label="<?php echo stridebr_e(stridebr_t('activity.effort_aria')); ?>"><output data-effort-output><?php echo $detailEffort !== '' ? stridebr_e($detailEffort) : '—'; ?></output></div>
        <div class="effort-scale"><span><?php echo stridebr_e(stridebr_t('activity.easy')); ?></span><span><?php echo stridebr_e(stridebr_t('schedule.moderate')); ?></span><span><?php echo stridebr_e(stridebr_t('activity.maximum')); ?></span></div>
    </section>

    <section class="activity-enrichment-section">
        <div class="activity-enrichment-heading"><div><strong><?php echo stridebr_e(stridebr_t('activity.details')); ?></strong></div><div class="activity-enrichment-actions"><button type="button" class="optional-field-chip" data-toggle-log-detail="competition" aria-expanded="<?php echo $detailCompetition !== '' ? 'true' : 'false'; ?>"><?php echo stridebr_e(stridebr_t('competitions.competition')); ?></button><button type="button" class="optional-field-chip" data-toggle-log-detail="equipment" aria-expanded="<?php echo $detailEquipmentSelected ? 'true' : 'false'; ?>"><?php echo stridebr_e(stridebr_t('activity.equipment_add')); ?></button></div></div>
        <div class="activity-contextual-detail" data-log-detail="competition"<?php echo $detailCompetition !== '' ? '' : ' hidden'; ?>>
            <div class="activity-detail-heading"><div><strong><?php echo stridebr_e(stridebr_t('competitions.competition')); ?></strong><span><?php echo stridebr_e(stridebr_t('competitions.activity_help')); ?></span></div><div class="activity-detail-heading-actions"><a href="/user/competicoes.php?new=1"><?php echo stridebr_e(stridebr_t('competitions.register')); ?></a><button type="button" class="activity-inline-action" data-close-log-detail="competition"><?php echo stridebr_e(stridebr_t('common.close')); ?></button></div></div>
            <select name="idcompeticao" aria-label="<?php echo stridebr_e(stridebr_t('competitions.competition')); ?>">
                <option value=""><?php echo stridebr_e(stridebr_t('competitions.none')); ?></option>
                <?php foreach ($detailCompetitions as $competition): $competitionId=(string)($competition['idcompeticao']??''); ?>
                    <option value="<?php echo stridebr_e($competitionId); ?>" data-competition-start="<?php echo stridebr_e((string)($competition['data_inicio']??'')); ?>" data-competition-end="<?php echo stridebr_e((string)($competition['data_fim']??'')); ?>"<?php echo $detailCompetition === $competitionId ? ' selected' : ''; ?>><?php echo stridebr_e((string)($competition['nome']??'')); ?> · <?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)($competition['data_inicio']??'today')))); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="activity-field-help"><?php echo stridebr_e(stridebr_t('competitions.link_not_automatic')); ?></small>
            <small class="activity-field-help" data-competition-date-warning hidden><?php echo stridebr_e(stridebr_t('competitions.date_outside')); ?></small>
        </div>

        <div class="activity-contextual-detail" data-log-detail="equipment"<?php echo $detailEquipmentSelected ? '' : ' hidden'; ?>>
            <div class="activity-detail-heading"><div><strong><?php echo stridebr_e(stridebr_t('activity.equipment')); ?></strong><span><?php echo stridebr_e(stridebr_t('activity.equipment_help')); ?></span></div><div class="activity-detail-heading-actions"><a href="/user/equipamentos.php"><?php echo stridebr_e(stridebr_t('activity.manage')); ?></a><button type="button" class="activity-inline-action" data-close-log-detail="equipment"><?php echo stridebr_e(stridebr_t('common.close')); ?></button></div></div>
            <div data-activity-equipment-host data-selected-equipment="<?php echo stridebr_e(implode(',', $detailEquipmentSelected)); ?>">
                <?php if ($detailEquipmentLoaded && $detailEquipment): ?><div class="equipment-picker">
                    <?php foreach ($detailEquipment as $equipamento): ?>
                        <?php $equipmentId = (string) ($equipamento['idequipamento'] ?? ''); $isSelected = isset($detailEquipmentSelectedMap[$equipmentId]); $active = !array_key_exists('ativo', $equipamento) || stridebr_db_bool($equipamento['ativo']); if (!$isSelected && !$active) continue; ?>
                        <label class="equipment-chip<?php echo !$active ? ' is-archived' : ''; ?>"><input type="checkbox" name="equipamentos[]" value="<?php echo stridebr_e($equipmentId); ?>"<?php echo $isSelected ? ' checked' : ''; ?>><span><?php echo stridebr_e((string) ($equipamento['nome'] ?? '')); ?><?php echo !$active ? ' · ' . stridebr_e(stridebr_t('common.archived')) : ''; ?></span></label>
                    <?php endforeach; ?>
                </div><?php elseif ($detailEquipmentLoaded): ?><a href="/user/equipamentos.php" class="activity-empty-action">+ <?php echo stridebr_e(stridebr_t('activity.add_first_equipment')); ?></a><?php else: ?><span class="activity-inline-muted"><?php echo stridebr_e(stridebr_t('activity.loads_on_open')); ?></span><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="input-field activity-observations-field"><label for="observacoes"><?php echo stridebr_e(stridebr_t('activity.notes')); ?> <span class="field-hint"><?php echo stridebr_e(stridebr_t('common.optional')); ?></span></label><textarea id="observacoes" name="observacoes" rows="1" placeholder="<?php echo stridebr_e(stridebr_t('activity.notes_placeholder')); ?>"><?php echo stridebr_e($detailObservations); ?></textarea></div>

    <div class="activity-visibility-row"><label for="visibilidade"><span><?php echo stridebr_e(stridebr_t('activity.activity_visibility')); ?></span><small><?php echo stridebr_e(stridebr_t('activity.activity_visibility_help')); ?></small></label><select id="visibilidade" name="visibilidade" aria-label="<?php echo stridebr_e(stridebr_t('activity.activity_visibility')); ?>"><option value="privado"<?php echo $detailVisibility === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.private')); ?></option><option value="amigos"<?php echo $detailVisibility === 'amigos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $detailVisibility === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></div>
</div>
