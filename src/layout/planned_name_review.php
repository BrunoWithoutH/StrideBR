<?php
$plannedNameCount = count(array_filter($plannedReviewRows, static fn(array $row): bool => !empty($row['nome_revisao'])));
if ($plannedNameCount > 0): ?>
<div class="planned-name-review" role="status">
    <span><?php echo stridebr_e(stridebr_t('schedule.names.count', ['count'=>$plannedNameCount])); ?></span>
    <form method="POST" action="/user/revisar-exercicios-plano.php">
        <?php echo stridebr_csrf_field(); ?>
        <input type="hidden" name="kind" value="<?php echo stridebr_e($plannedReviewKind); ?>">
        <input type="hidden" name="id" value="<?php echo stridebr_e($plannedReviewId); ?>">
        <input type="hidden" name="return_to" value="<?php echo stridebr_e($plannedReviewReturn); ?>">
        <input type="hidden" name="action" value="safe">
        <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('schedule.names.safe')); ?></button>
    </form>
    <a class="secondary-button" href="/user/revisar-exercicios-plano.php?<?php echo stridebr_e(http_build_query(['kind'=>$plannedReviewKind,'id'=>$plannedReviewId,'return_to'=>$plannedReviewReturn])); ?>"><?php echo stridebr_e(stridebr_t('schedule.names.review')); ?></a>
</div>
<?php endif; ?>
