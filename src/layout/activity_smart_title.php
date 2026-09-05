<?php
$titleValue = (string) ($activityEditorTitle ?? '');
$titlePreview = $titleValue !== '' ? $titleValue : stridebr_t('activity.automatic_title');
$titleLabelVisible = empty($activityEditorTitleEmbedded);
?>
<section class="activity-smart-title activity-smart-title-top" data-activity-title-card>
    <div class="activity-smart-title-copy">
        <?php if ($titleLabelVisible): ?><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span><?php endif; ?>
        <button type="button" class="activity-smart-title-trigger" data-edit-activity-title aria-expanded="false" aria-controls="activity-title-editor">
            <strong data-activity-title-preview><?php echo stridebr_e($titlePreview); ?></strong>
            <span class="visually-hidden"><?php echo stridebr_e(stridebr_t('activity.edit_title')); ?></span>
        </button>
        <div class="activity-summary" data-activity-summary hidden><span data-summary-text></span></div>
    </div>
    <div class="input-field activity-title-field" id="activity-title-editor" data-activity-title-editor hidden>
        <label for="titulo"><?php echo stridebr_e(stridebr_t('common.title')); ?></label>
        <input type="text" id="titulo" name="titulo" maxlength="255" value="<?php echo stridebr_e($titleValue); ?>" placeholder="Ex.: Corrida no parque">
    </div>
</section>
