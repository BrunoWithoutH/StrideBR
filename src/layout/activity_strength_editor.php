<?php

declare(strict_types=1);

function atividadeForcaInputValor(mixed $value): string
{
    if ($value === null || $value === '') return '';
    if (is_float($value)) return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    return (string) $value;
}

function atividadeForcaRenderEditor(array $exercises = [], array $library = []): string
{
    $normalizedLibrary = [];
    foreach ($library as $exercise) {
        $id = trim((string) ($exercise['idexercicio'] ?? $exercise['id'] ?? ''));
        $name = trim((string) ($exercise['nome'] ?? $exercise['name'] ?? ''));
        if ($name === '') continue;
        $normalizedLibrary[] = ['id' => $id, 'name' => $name];
    }
    ob_start();
    ?>
    <section class="activity-strength-editor" data-strength-editor hidden>
        <div class="activity-detail-heading activity-strength-heading">
            <div>
                <strong><?php echo stridebr_e(stridebr_t('activity.strength.editor_title')); ?></strong>
                <span><?php echo stridebr_e(stridebr_t('activity.strength.editor_help')); ?></span>
            </div>
            <button type="button" class="activity-secondary-button activity-strength-add-exercise" data-strength-add-exercise><?php echo stridebr_e(stridebr_t('activity.strength.add_exercise')); ?></button>
        </div>
        <datalist id="activity-strength-exercise-options" data-strength-exercise-list>
            <?php foreach ($normalizedLibrary as $exercise): ?>
                <option value="<?php echo stridebr_e($exercise['name']); ?>" data-exercise-id="<?php echo stridebr_e($exercise['id']); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <div class="activity-strength-exercises" data-strength-exercises>
            <?php foreach ($exercises as $exerciseIndex => $exercise): ?>
                <?php
                $sets = is_array($exercise['series'] ?? null) ? $exercise['series'] : [];
                if ($sets === []) $sets = [['tipo' => 'trabalho', 'carga_kg' => null, 'repeticoes' => null, 'rir' => null, 'concluida' => true]];
                ?>
                <article class="activity-strength-exercise" data-strength-exercise>
                    <div class="activity-strength-exercise-head">
                        <label>
                            <span><?php echo stridebr_e(stridebr_t('activity.strength.exercise')); ?></span>
                            <input type="text" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][nome]" value="<?php echo stridebr_e((string) ($exercise['nome'] ?? '')); ?>" list="activity-strength-exercise-options" data-strength-exercise-name autocomplete="off" placeholder="<?php echo stridebr_e(stridebr_t('activity.strength.exercise_placeholder')); ?>">
                            <input type="hidden" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][idexercicio]" value="<?php echo stridebr_e((string) ($exercise['idexercicio'] ?? '')); ?>" data-strength-exercise-id>
                        </label>
                        <button type="button" class="activity-inline-action is-danger" data-strength-remove-exercise><?php echo stridebr_e(stridebr_t('common.remove')); ?></button>
                    </div>
                    <div class="activity-strength-reference" data-strength-reference hidden></div>
                    <div class="activity-strength-sets" data-strength-sets>
                        <div class="activity-strength-set activity-strength-set-head" aria-hidden="true"><span><?php echo stridebr_e(stridebr_t('activity.strength.set_short')); ?></span><span><?php echo stridebr_e(stridebr_t('activity.strength.type')); ?></span><span>kg</span><span><?php echo stridebr_e(stridebr_t('activity.strength.repetitions')); ?></span><span>RIR</span><span><?php echo stridebr_e(stridebr_t('activity.strength.done')); ?></span><span></span></div>
                        <?php foreach ($sets as $setIndex => $set): ?>
                            <div class="activity-strength-set" data-strength-set>
                                <strong data-strength-set-number><?php echo (int) $setIndex + 1; ?></strong>
                                <select name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][tipo]" aria-label="<?php echo stridebr_e(stridebr_t('activity.strength.set_type')); ?>">
                                    <?php foreach (['trabalho' => stridebr_t('activity.strength.normal'), 'aquecimento' => stridebr_t('activity.strength.warmup_short'), 'drop' => stridebr_t('activity.strength.drop'), 'falha' => stridebr_t('activity.strength.failure'), 'outro' => stridebr_t('activity.strength.other')] as $type => $label): ?>
                                        <option value="<?php echo $type; ?>"<?php echo (string) ($set['tipo'] ?? 'trabalho') === $type ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][carga_kg]" min="0" max="9999.999" step="0.25" inputmode="decimal" value="<?php echo stridebr_e(atividadeForcaInputValor($set['carga_kg'] ?? null)); ?>" placeholder="—" aria-label="<?php echo stridebr_e(stridebr_t('activity.strength.load_aria')); ?>">
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][repeticoes]" min="0" max="999" step="1" inputmode="numeric" value="<?php echo stridebr_e(atividadeForcaInputValor($set['repeticoes'] ?? null)); ?>" placeholder="—" aria-label="<?php echo stridebr_e(stridebr_t('activity.strength.repetitions')); ?>">
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][rir]" min="0" max="10" step="0.5" inputmode="decimal" value="<?php echo stridebr_e(atividadeForcaInputValor($set['rir'] ?? null)); ?>" placeholder="—" aria-label="<?php echo stridebr_e(stridebr_t('activity.strength.rir')); ?>">
                                <label class="activity-strength-done"><input type="hidden" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][concluida]" value="0"><input type="checkbox" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][concluida]" value="1"<?php echo !array_key_exists('concluida', $set) || !empty($set['concluida']) ? ' checked' : ''; ?>><span>✓</span></label>
                                <button type="button" class="activity-strength-remove-set" data-strength-remove-set aria-label="<?php echo stridebr_e(stridebr_t('activity.strength.remove_set')); ?>">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="activity-inline-action activity-strength-add-set" data-strength-add-set><?php echo stridebr_e(stridebr_t('activity.strength.add_set')); ?></button>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="activity-strength-empty" data-strength-empty<?php echo $exercises === [] ? '' : ' hidden'; ?>>
            <strong><?php echo stridebr_e(stridebr_t('activity.strength.start_first')); ?></strong>
            <span><?php echo stridebr_e(stridebr_t('activity.strength.empty_help')); ?></span>
            <button type="button" class="activity-secondary-button" data-strength-add-exercise><?php echo stridebr_e(stridebr_t('activity.strength.add_exercise_long')); ?></button>
        </div>
        <script type="application/json" data-strength-library-json><?php echo json_encode($normalizedLibrary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    </section>
    <?php
    return (string) ob_get_clean();
}
