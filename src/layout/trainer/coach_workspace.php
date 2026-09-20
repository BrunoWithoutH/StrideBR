<section class="trainer-section coach-workspace" data-trainer-coach-section>
    <?php if (!$trainerMode): ?>
        <div class="coach-frame trainer-enable-card"><h2><?php echo stridebr_e(stridebr_t('trainer.coach_mode')); ?></h2><p><?php echo stridebr_e(stridebr_t('trainer.enable_help')); ?></p><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="activate_trainer"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('trainer.enable_mode')); ?></button></form></div>
    <?php else: ?>
        <?php require __DIR__ . '/coach_nav.php'; ?>
        <?php if ($coachView === 'overview') require __DIR__ . '/coach_overview.php'; ?>
        <?php if (in_array($coachView,['athletes','calendar'],true)) require __DIR__ . '/coach_athletes.php'; ?>
        <?php if ($coachView === 'library') require __DIR__ . '/coach_library.php'; ?>
        <?php if ($coachView === 'athlete' && $selectedAthlete !== []) require __DIR__ . '/coach_athlete_workspace.php'; ?>
        <?php if ($coachView === 'athlete' && $selectedAthlete !== [] && stridebr_db_bool($selectedLink['pode_prescrever']??false)) require __DIR__ . '/coach_prescription_modal.php'; ?>
    <?php endif; ?>
</section>
