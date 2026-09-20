<?php
$coachNav = [
    'overview' => 'trainer.workspace.overview',
    'athletes' => 'trainer.workspace.athletes',
    'calendar' => 'trainer.workspace.calendar',
    'library' => 'trainer.workspace.library',
];
$currentCoachNav = $coachView === 'athlete' ? 'athletes' : $coachView;
?>
<nav class="coach-workspace-nav" aria-label="<?php echo stridebr_e(stridebr_t('trainer.workspace.navigation')); ?>">
    <?php foreach ($coachNav as $key => $label): ?>
        <a href="/user/treinador.php?context=coach&amp;view=<?php echo stridebr_e($key); ?>"<?php echo $currentCoachNav === $key ? ' aria-current="page" class="is-active"' : ''; ?>><?php echo stridebr_e(stridebr_t($label)); ?></a>
    <?php endforeach; ?>
</nav>
