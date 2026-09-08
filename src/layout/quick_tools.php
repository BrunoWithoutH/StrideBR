<?php
$globalCsrf = stridebr_csrf_token();
?>
<div class="global-tools" data-global-tools data-csrf="<?php echo stridebr_e($globalCsrf); ?>">
    <div class="active-workout-pill" data-active-workout-pill hidden>
        <button type="button" class="active-workout-main" data-open-workout-session>
            <span class="active-workout-dot"></span>
            <span><strong data-active-workout-title><?php echo stridebr_e(stridebr_t('workout_session.active')); ?></strong><small data-active-workout-summary><?php echo stridebr_e(stridebr_t('workout_session.tap_continue')); ?></small></span>
            <time data-active-workout-time>00:00</time>
        </button>
    </div>

    <div class="floating-utility-dock" aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.shortcuts')); ?>">
        <div class="pinned-tools" data-pinned-tools aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.pinned')); ?>"></div>
        <button class="quick-tools-launcher" type="button" data-quick-tools-open aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.open')); ?>" title="<?php echo stridebr_e(stridebr_t('common.quick_tools')); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 4v6l4 2"/></svg>
        </button>
    </div>

    <div class="quick-tools-modal" data-quick-tools-modal hidden>
        <button type="button" class="quick-tools-backdrop" data-quick-tools-close aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.close')); ?>"></button>
        <section class="quick-tools-panel" role="dialog" aria-modal="true" aria-labelledby="quick-tools-title">
            <div class="quick-tools-header">
                <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('quick_tools.always_ready')); ?></span><h2 id="quick-tools-title"><?php echo stridebr_e(stridebr_t('common.quick_tools')); ?></h2></div>
                <button type="button" class="quick-tools-close" data-quick-tools-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button>
            </div>
            <div class="quick-tools-tabs" role="tablist">
                <button type="button" class="is-active" data-quick-tool-tab="timer"><?php echo stridebr_e(stridebr_t('quick_tools.timer')); ?></button>
                <button type="button" data-quick-tool-tab="stopwatch"><?php echo stridebr_e(stridebr_t('quick_tools.stopwatch')); ?></button>
                <button type="button" data-quick-tool-tab="sets"><?php echo stridebr_e(stridebr_t('quick_tools.sets')); ?></button>
            </div>

            <section class="quick-tool-view is-active" data-quick-tool-view="timer">
                <div class="quick-tool-title-row"><div><h3><?php echo stridebr_e(stridebr_t('quick_tools.timer')); ?></h3><p><?php echo stridebr_e(stridebr_t('quick_tools.timer_help')); ?></p></div><button type="button" class="pin-tool-button" data-pin-tool="timer" aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.pin_timer')); ?>">☆</button></div>
                <div class="quick-timer-presets">
                    <button type="button" data-timer-preset="30">30s</button><button type="button" data-timer-preset="60">1min</button><button type="button" data-timer-preset="90">1:30</button><button type="button" data-timer-preset="120">2min</button>
                </div>
                <div class="quick-timer-inputs"><label><?php echo stridebr_e(stridebr_t('quick_tools.minute_abbr')); ?><input type="number" min="0" max="999" value="1" data-quick-timer-minutes></label><label><?php echo stridebr_e(stridebr_t('quick_tools.second_abbr')); ?><input type="number" min="0" max="59" value="0" data-quick-timer-seconds></label></div>
                <output class="quick-tool-output" data-quick-timer-output>01:00</output>
                <div class="quick-tool-actions"><button type="button" class="primary" data-quick-timer-start><?php echo stridebr_e(stridebr_t('agenda.start')); ?></button><button type="button" data-quick-timer-pause><?php echo stridebr_e(stridebr_t('quick_tools.pause')); ?></button><button type="button" data-quick-timer-reset><?php echo stridebr_e(stridebr_t('quick_tools.reset')); ?></button><button type="button" data-quick-timer-plus>+30s</button></div>
                <audio src="<?php echo stridebr_e(stridebr_asset('/assets/audio/alarm1.mp3')); ?>" preload="none" data-quick-timer-alarm></audio>
            </section>

            <section class="quick-tool-view" data-quick-tool-view="stopwatch" hidden>
                <div class="quick-tool-title-row"><div><h3><?php echo stridebr_e(stridebr_t('quick_tools.stopwatch')); ?></h3><p><?php echo stridebr_e(stridebr_t('quick_tools.stopwatch_help')); ?></p></div><button type="button" class="pin-tool-button" data-pin-tool="stopwatch" aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.pin_stopwatch')); ?>">☆</button></div>
                <output class="quick-tool-output" data-stopwatch-output>00:00.0</output>
                <div class="quick-tool-actions"><button type="button" class="primary" data-stopwatch-toggle><?php echo stridebr_e(stridebr_t('agenda.start')); ?></button><button type="button" data-stopwatch-reset><?php echo stridebr_e(stridebr_t('quick_tools.reset')); ?></button></div>
            </section>

            <section class="quick-tool-view" data-quick-tool-view="sets" hidden>
                <div class="quick-tool-title-row"><div><h3><?php echo stridebr_e(stridebr_t('quick_tools.set_counter')); ?></h3><p><?php echo stridebr_e(stridebr_t('quick_tools.sets_help')); ?></p></div><button type="button" class="pin-tool-button" data-pin-tool="sets" aria-label="<?php echo stridebr_e(stridebr_t('quick_tools.pin_sets')); ?>">☆</button></div>
                <output class="quick-tool-output" data-sets-output>0</output>
                <div class="quick-tool-actions"><button type="button" data-sets-minus>−</button><button type="button" class="primary" data-sets-plus><?php echo stridebr_e(stridebr_t('quick_tools.add_set')); ?></button><button type="button" data-sets-reset><?php echo stridebr_e(stridebr_t('quick_tools.reset')); ?></button></div>
            </section>
        </section>
    </div>

    <div class="workout-session-modal" data-workout-session-modal hidden>
        <button type="button" class="workout-session-backdrop" data-close-workout-session aria-label="<?php echo stridebr_e(stridebr_t('workout_session.minimize')); ?>"></button>
        <section class="workout-session-panel" role="dialog" aria-modal="true" aria-labelledby="active-session-title">
            <header class="workout-session-header">
                <div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('workout_session.active')); ?></span><h2 id="active-session-title" data-session-title><?php echo stridebr_e(stridebr_t('nav.workout')); ?></h2><p data-session-progress><?php echo stridebr_e(stridebr_t('workout_session.loading')); ?></p></div>
                <div class="workout-session-clock"><time data-session-time>00:00</time><button type="button" data-close-workout-session aria-label="<?php echo stridebr_e(stridebr_t('workout_session.minimize')); ?>">—</button></div>
            </header>
            <div class="workout-session-exercises" data-session-exercises></div>
            <footer class="workout-session-actions">
                <button type="button" class="session-cancel" data-cancel-workout-session><?php echo stridebr_e(stridebr_t('workout_session.cancel')); ?></button>
                <button type="button" class="session-mark-all" data-mark-all-workout><?php echo stridebr_e(stridebr_t('workout_session.mark_all')); ?></button>
                <button type="button" class="session-finish" data-finish-workout-session><?php echo stridebr_e(stridebr_t('workout_session.finish')); ?></button>
            </footer>
            <section class="workout-finish-sheet" data-workout-finish-sheet hidden>
                <div class="workout-finish-heading"><div><span class="eyebrow"><?php echo stridebr_e(stridebr_t('workout_session.finish_session')); ?></span><h3><?php echo stridebr_e(stridebr_t('workout_session.how_was')); ?></h3><p data-workout-finish-summary></p></div><button type="button" data-close-workout-finish aria-label="<?php echo stridebr_e(stridebr_t('common.back')); ?>">×</button></div>
                <div class="workout-finish-time">
                    <div><strong><?php echo stridebr_e(stridebr_t('workout_session.real_time')); ?></strong><small><?php echo stridebr_e(stridebr_t('workout_session.real_time_help')); ?></small></div>
                    <div class="workout-finish-time-grid">
                        <label><?php echo stridebr_e(stridebr_t('workout_session.start')); ?><input type="datetime-local" data-finish-start></label>
                        <label><?php echo stridebr_e(stridebr_t('workout_session.end')); ?><input type="datetime-local" data-finish-end></label>
                    </div>
                    <span data-finish-duration-preview></span>
                </div>
                <div class="workout-finish-field"><span><?php echo stridebr_e(stridebr_t('schedule.intensity')); ?></span><div class="workout-choice-row"><button type="button" data-finish-intensity="leve"><?php echo stridebr_e(stridebr_t('schedule.light')); ?></button><button type="button" data-finish-intensity="moderado"><?php echo stridebr_e(stridebr_t('schedule.moderate')); ?></button><button type="button" data-finish-intensity="intenso"><?php echo stridebr_e(stridebr_t('schedule.intense')); ?></button></div></div>
                <div class="workout-finish-field"><span><?php echo stridebr_e(stridebr_t('workout_session.feeling_label')); ?></span><div class="workout-choice-row workout-feeling-row"><button type="button" data-finish-feeling="1">1</button><button type="button" data-finish-feeling="2">2</button><button type="button" data-finish-feeling="3">3</button><button type="button" data-finish-feeling="4">4</button><button type="button" data-finish-feeling="5">5</button></div><small><?php echo stridebr_e(stridebr_t('workout_session.feeling_scale')); ?></small></div>
                <label class="workout-finish-notes"><?php echo stridebr_e(stridebr_t('activity.notes')); ?><textarea rows="3" maxlength="1000" data-finish-notes placeholder="<?php echo stridebr_e(stridebr_t('workout_session.notes_placeholder')); ?>"></textarea></label>
                <div class="workout-finish-actions"><button type="button" data-close-workout-finish><?php echo stridebr_e(stridebr_t('common.back')); ?></button><button type="button" class="session-finish" data-confirm-workout-finish><?php echo stridebr_e(stridebr_t('activity.save')); ?></button></div>
            </section>
        </section>
    </div>

    <div class="workout-complete-modal" data-workout-complete-modal hidden>
        <button type="button" class="workout-session-backdrop" data-workout-complete-close aria-label="<?php echo stridebr_e(stridebr_t('workout_session.complete_summary')); ?>"></button>
        <section class="workout-complete-card" role="dialog" aria-modal="true" aria-labelledby="workout-complete-title">
            <span class="workout-complete-mark" aria-hidden="true">✓</span>
            <span class="eyebrow"><?php echo stridebr_e(stridebr_t('workout_session.registered')); ?></span>
            <h2 id="workout-complete-title" data-workout-complete-title><?php echo stridebr_e(stridebr_t('workout_session.completed')); ?></h2>
            <p class="workout-complete-lead" data-workout-complete-lead><?php echo stridebr_e(stridebr_t('workout_session.completed_lead')); ?></p>
            <div class="workout-complete-stats">
                <div><strong data-workout-complete-duration>00:00</strong><span><?php echo stridebr_e(stridebr_t('common.duration')); ?></span></div>
                <div><strong data-workout-complete-exercises>0/0</strong><span><?php echo stridebr_e(stridebr_t('common.exercises')); ?></span></div>
                <div><strong data-workout-complete-sets>0/0</strong><span><?php echo stridebr_e(stridebr_t('workout_session.sets_label')); ?></span></div>
            </div>
            <p class="workout-complete-feedback" data-workout-complete-feedback hidden></p>
            <div class="workout-complete-actions">
                <button type="button" class="secondary-button" data-workout-complete-close><?php echo stridebr_e(stridebr_t('common.continue')); ?></button>
                <a class="primary-button" href="/user/atividades.php" data-workout-complete-activity><?php echo stridebr_e(stridebr_t('home.view_activity')); ?></a>
            </div>
        </section>
    </div>
</div>
