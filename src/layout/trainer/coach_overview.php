<section class="coach-workspace-view" aria-labelledby="coach-overview-title">
    <header class="coach-view-heading">
        <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('trainer.workspace.today')); ?></span><h2 id="coach-overview-title"><?php echo stridebr_e(stridebr_t('trainer.workspace.overview')); ?></h2></div>
        <a class="secondary-button" href="/user/treinador.php?context=coach&amp;view=athletes"><?php echo stridebr_e(stridebr_t('trainer.workspace.open_athletes')); ?></a>
    </header>
    <div class="coach-stat-grid" aria-label="<?php echo stridebr_e(stridebr_t('trainer.workspace.day_summary')); ?>">
        <?php foreach ([
            ['athletes','trainer.workspace.stat_athletes'],
            ['today','trainer.workspace.stat_today'],
            ['completed','trainer.workspace.stat_completed'],
            ['attention','trainer.workspace.stat_attention'],
        ] as [$key,$label]): ?>
            <article class="coach-stat"><strong><?php echo (int)($coachOverview['stats'][$key] ?? 0); ?></strong><span><?php echo stridebr_e(stridebr_t($label)); ?></span></article>
        <?php endforeach; ?>
    </div>
    <div class="coach-overview-grid">
        <section class="coach-frame coach-frame-attention">
            <header><h3><?php echo stridebr_e(stridebr_t('trainer.workspace.needs_attention')); ?></h3><span><?php echo count($coachOverview['attention'] ?? []); ?></span></header>
            <div class="coach-dense-list">
                <?php if (($coachOverview['attention'] ?? []) === []): ?><p class="coach-empty"><?php echo stridebr_e(stridebr_t('trainer.workspace.no_attention')); ?></p><?php endif; ?>
                <?php foreach (($coachOverview['attention'] ?? []) as $item): ?>
                    <article>
                        <div class="coach-list-main"><strong><?php echo stridebr_e((string)$item['athlete_name']); ?></strong><span><?php echo stridebr_e((string)$item['titulo']); ?></span><small><?php echo stridebr_e(stridebr_t('trainer.workspace.attention.' . (string)$item['attention_type'])); ?></small></div>
                        <a class="text-button" href="/user/treinador.php?context=coach&amp;view=athlete&amp;id=<?php echo rawurlencode((string)$item['idatleta']); ?>&amp;workout=<?php echo rawurlencode((string)$item['idagendamento']); ?>#coach-workout-review"><?php echo stridebr_e(stridebr_t('common.open')); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="coach-frame">
            <header><h3><?php echo stridebr_e(stridebr_t('trainer.workspace.today_workouts')); ?></h3></header>
            <div class="coach-dense-list">
                <?php if (($coachOverview['today'] ?? []) === []): ?><p class="coach-empty"><?php echo stridebr_e(stridebr_t('trainer.workspace.no_today')); ?></p><?php endif; ?>
                <?php foreach (($coachOverview['today'] ?? []) as $item): ?>
                    <article><div class="coach-list-main"><strong><?php echo stridebr_e((string)$item['athlete_name']); ?></strong><span><?php echo stridebr_e((string)$item['titulo']); ?></span><small><?php echo !empty($item['hora_inicio']) ? stridebr_e(substr((string)$item['hora_inicio'],0,5)) : stridebr_e(stridebr_t('trainer.workspace.no_time')); ?> · <?php echo stridebr_e(stridebr_t('planning.status.' . (string)$item['status'])); ?></small></div><a class="text-button" href="/user/treinador.php?context=coach&amp;view=athlete&amp;id=<?php echo rawurlencode((string)$item['idatleta']); ?>&amp;tab=calendar"><?php echo stridebr_e(stridebr_t('trainer.workspace.calendar')); ?></a></article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="coach-frame coach-frame-wide">
            <header><h3><?php echo stridebr_e(stridebr_t('trainer.workspace.recent_activity')); ?></h3></header>
            <div class="coach-dense-list coach-recent-grid">
                <?php if (($coachOverview['recent'] ?? []) === []): ?><p class="coach-empty"><?php echo stridebr_e(stridebr_t('trainer.workspace.no_recent')); ?></p><?php endif; ?>
                <?php foreach (($coachOverview['recent'] ?? []) as $item): ?>
                    <article><div class="coach-list-main"><strong><?php echo stridebr_e((string)$item['athlete_name']); ?></strong><span><?php echo stridebr_e(trim((string)$item['titulo']) !== '' ? (string)$item['titulo'] : (string)$item['modalidade_nome']); ?></span><small><?php echo stridebr_e(stridebr_format_datetime_short((string)$item['data_inicio'])); ?> · <?php echo stridebr_e(stridebr_sport_name((string)$item['modalidade_slug'], (string)$item['modalidade_nome'])); ?></small></div><a class="text-button" href="/user/treinador.php?context=coach&amp;view=athlete&amp;id=<?php echo rawurlencode((string)$item['idusuario']); ?>&amp;tab=activities&amp;view_activity=<?php echo rawurlencode((string)$item['idregistro']); ?>"><?php echo stridebr_e(stridebr_t('common.open')); ?></a></article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
