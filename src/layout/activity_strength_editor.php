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
                <strong>Exercícios e séries</strong>
                <span>Registre carga e repetições para acompanhar sua evolução.</span>
            </div>
            <button type="button" class="activity-secondary-button activity-strength-add-exercise" data-strength-add-exercise>+ Exercício</button>
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
                            <span>Exercício</span>
                            <input type="text" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][nome]" value="<?php echo stridebr_e((string) ($exercise['nome'] ?? '')); ?>" list="activity-strength-exercise-options" data-strength-exercise-name autocomplete="off" placeholder="Ex.: Leg press">
                            <input type="hidden" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][idexercicio]" value="<?php echo stridebr_e((string) ($exercise['idexercicio'] ?? '')); ?>" data-strength-exercise-id>
                        </label>
                        <button type="button" class="activity-inline-action is-danger" data-strength-remove-exercise>Remover</button>
                    </div>
                    <div class="activity-strength-reference" data-strength-reference hidden></div>
                    <div class="activity-strength-sets" data-strength-sets>
                        <div class="activity-strength-set activity-strength-set-head" aria-hidden="true"><span>Série</span><span>Tipo</span><span>kg</span><span>Reps</span><span>RIR</span><span>Feita</span><span></span></div>
                        <?php foreach ($sets as $setIndex => $set): ?>
                            <div class="activity-strength-set" data-strength-set>
                                <strong data-strength-set-number><?php echo (int) $setIndex + 1; ?></strong>
                                <select name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][tipo]" aria-label="Tipo da série">
                                    <?php foreach (['trabalho' => 'Normal', 'aquecimento' => 'Aquec.', 'drop' => 'Drop', 'falha' => 'Falha', 'outro' => 'Outro'] as $type => $label): ?>
                                        <option value="<?php echo $type; ?>"<?php echo (string) ($set['tipo'] ?? 'trabalho') === $type ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][carga_kg]" min="0" max="9999.999" step="0.25" inputmode="decimal" value="<?php echo stridebr_e(atividadeForcaInputValor($set['carga_kg'] ?? null)); ?>" placeholder="—" aria-label="Carga em kg">
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][repeticoes]" min="0" max="999" step="1" inputmode="numeric" value="<?php echo stridebr_e(atividadeForcaInputValor($set['repeticoes'] ?? null)); ?>" placeholder="—" aria-label="Repetições">
                                <input type="number" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][rir]" min="0" max="10" step="0.5" inputmode="decimal" value="<?php echo stridebr_e(atividadeForcaInputValor($set['rir'] ?? null)); ?>" placeholder="—" aria-label="Repetições em reserva">
                                <label class="activity-strength-done"><input type="hidden" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][concluida]" value="0"><input type="checkbox" name="strength_exercises[<?php echo (int) $exerciseIndex; ?>][series][<?php echo (int) $setIndex; ?>][concluida]" value="1"<?php echo !array_key_exists('concluida', $set) || !empty($set['concluida']) ? ' checked' : ''; ?>><span>✓</span></label>
                                <button type="button" class="activity-strength-remove-set" data-strength-remove-set aria-label="Remover série">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="activity-inline-action activity-strength-add-set" data-strength-add-set>+ Série</button>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="activity-strength-empty" data-strength-empty<?php echo $exercises === [] ? '' : ' hidden'; ?>>
            <strong>Comece pelo primeiro exercício.</strong>
            <span>Carga e repetições alimentam seu histórico e os gráficos de progresso.</span>
            <button type="button" class="activity-secondary-button" data-strength-add-exercise>+ Adicionar exercício</button>
        </div>
        <script type="application/json" data-strength-library-json><?php echo json_encode($normalizedLibrary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    </section>
    <?php
    return (string) ob_get_clean();
}
