<?php
/** $planningSummary is authorized by the caller; user-created text is never translated. */
?>
<section class="planning-week" aria-label="<?php echo stridebr_e(stridebr_t(isset($planningWeekOffset) && $planningWeekOffset !== 0 ? 'planning.selected_week' : 'planning.week')); ?>">
    <div class="section-title-row"><h2><?php echo stridebr_e(stridebr_t(isset($planningWeekOffset) && $planningWeekOffset !== 0 ? 'planning.selected_week' : 'planning.week')); ?></h2><span><?php echo stridebr_e(stridebr_format_date_short($planningStart)); ?> – <?php echo stridebr_e(stridebr_format_date_short((new DateTimeImmutable($planningStart))->modify('+6 days'))); ?></span></div>
    <p><?php foreach (['planned', 'completed', 'missed'] as $metric): ?><span><strong><?php echo (int) $planningSummary[$metric]; ?></strong> <?php echo stridebr_e(stridebr_t('planning.' . $metric)); ?></span> · <?php endforeach; ?><span><strong><?php echo count($planningSummary['extra']); ?></strong> <?php echo stridebr_e(stridebr_t('planning.extra')); ?></span></p>
    <div class="trainer-mini-list">
    <?php foreach ($planningSummary['items'] as $planned): ?>
        <article><div><span><?php echo stridebr_e(stridebr_format_date_weekday($planned['data_planejada'] ?? $planned['data_treino'])); ?></span><strong><?php echo stridebr_e($planned['titulo']); ?></strong><small><?php echo stridebr_e(stridebr_t('planning.status.' . $planned['planning_state'])); ?><?php if ($planned['planning_state'] === 'shifted'): ?> · <?php echo stridebr_e(stridebr_format_date_short($planned['data_realizada'] ?? $planned['realizada'])); ?><?php endif; ?></small><?php if (!empty($planned['excecao']) && !empty($planned['data_original']) && $planned['data_original'] !== ($planned['data_planejada'] ?? $planned['data_treino'])): ?><small><?php echo stridebr_e(stridebr_t('planning.rescheduled_from', ['date' => stridebr_format_date_short($planned['data_original'])])); ?></small><?php endif; ?></div></article>
    <?php endforeach; ?>
    <?php if (!$planningSummary['items']): ?><p><?php echo stridebr_e(stridebr_t('planning.empty')); ?></p><?php endif; ?>
    </div>
    <?php if ($planningSummary['extra']): ?><details><summary><?php echo count($planningSummary['extra']); ?> <?php echo stridebr_e(stridebr_t('planning.extra')); ?></summary><ul><?php foreach ($planningSummary['extra'] as $extra): ?><li><?php echo stridebr_e(stridebr_format_date_short($extra['data_inicio'])); ?> · <?php echo stridebr_e($extra['titulo']); ?></li><?php endforeach; ?></ul></details><?php endif; ?>
</section>
