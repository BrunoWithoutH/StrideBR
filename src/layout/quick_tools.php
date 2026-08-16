<?php
$globalCsrf = stridebr_csrf_token();
?>
<div class="global-tools" data-global-tools data-csrf="<?php echo stridebr_e($globalCsrf); ?>">
    <div class="active-workout-pill" data-active-workout-pill hidden>
        <button type="button" class="active-workout-main" data-open-workout-session>
            <span class="active-workout-dot"></span>
            <span><strong data-active-workout-title>Treino em andamento</strong><small data-active-workout-summary>Toque para continuar</small></span>
            <time data-active-workout-time>00:00</time>
        </button>
    </div>

    <div class="pinned-tools" data-pinned-tools aria-label="Ferramentas fixadas"></div>
    <button class="quick-tools-launcher" type="button" data-quick-tools-open aria-label="Abrir ferramentas rápidas" title="Ferramentas rápidas">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 4v6l4 2"/></svg>
    </button>

    <div class="quick-tools-modal" data-quick-tools-modal hidden>
        <button type="button" class="quick-tools-backdrop" data-quick-tools-close aria-label="Fechar ferramentas"></button>
        <section class="quick-tools-panel" role="dialog" aria-modal="true" aria-labelledby="quick-tools-title">
            <div class="quick-tools-header">
                <div><span class="eyebrow">Sempre à mão</span><h2 id="quick-tools-title">Ferramentas rápidas</h2></div>
                <button type="button" class="quick-tools-close" data-quick-tools-close aria-label="Fechar">×</button>
            </div>
            <div class="quick-tools-tabs" role="tablist">
                <button type="button" class="is-active" data-quick-tool-tab="timer">Timer</button>
                <button type="button" data-quick-tool-tab="stopwatch">Cronômetro</button>
                <button type="button" data-quick-tool-tab="sets">Sets</button>
            </div>

            <section class="quick-tool-view is-active" data-quick-tool-view="timer">
                <div class="quick-tool-title-row"><div><h3>Timer</h3><p>Perfeito para descanso entre séries.</p></div><button type="button" class="pin-tool-button" data-pin-tool="timer" aria-label="Fixar Timer">☆</button></div>
                <div class="quick-timer-presets">
                    <button type="button" data-timer-preset="30">30s</button><button type="button" data-timer-preset="60">1min</button><button type="button" data-timer-preset="90">1:30</button><button type="button" data-timer-preset="120">2min</button>
                </div>
                <div class="quick-timer-inputs"><label>Min<input type="number" min="0" max="999" value="1" data-quick-timer-minutes></label><label>Seg<input type="number" min="0" max="59" value="0" data-quick-timer-seconds></label></div>
                <output class="quick-tool-output" data-quick-timer-output>01:00</output>
                <div class="quick-tool-actions"><button type="button" class="primary" data-quick-timer-start>Iniciar</button><button type="button" data-quick-timer-pause>Pausar</button><button type="button" data-quick-timer-reset>Resetar</button><button type="button" data-quick-timer-plus>+30s</button></div>
                <audio src="<?php echo stridebr_e(stridebr_asset('/assets/audio/alarm1.mp3')); ?>" preload="auto" data-quick-timer-alarm></audio>
            </section>

            <section class="quick-tool-view" data-quick-tool-view="stopwatch" hidden>
                <div class="quick-tool-title-row"><div><h3>Cronômetro</h3><p>Continua contando mesmo trocando de página.</p></div><button type="button" class="pin-tool-button" data-pin-tool="stopwatch" aria-label="Fixar cronômetro">☆</button></div>
                <output class="quick-tool-output" data-stopwatch-output>00:00.0</output>
                <div class="quick-tool-actions"><button type="button" class="primary" data-stopwatch-toggle>Iniciar</button><button type="button" data-stopwatch-reset>Resetar</button></div>
            </section>

            <section class="quick-tool-view" data-quick-tool-view="sets" hidden>
                <div class="quick-tool-title-row"><div><h3>Contador de sets</h3><p>Contagem rápida para não perder a série.</p></div><button type="button" class="pin-tool-button" data-pin-tool="sets" aria-label="Fixar contador de sets">☆</button></div>
                <output class="quick-tool-output" data-sets-output>0</output>
                <div class="quick-tool-actions"><button type="button" data-sets-minus>−</button><button type="button" class="primary" data-sets-plus>+ Set</button><button type="button" data-sets-reset>Resetar</button></div>
            </section>
        </section>
    </div>

    <div class="workout-session-modal" data-workout-session-modal hidden>
        <button type="button" class="workout-session-backdrop" data-close-workout-session aria-label="Fechar treino"></button>
        <section class="workout-session-panel" role="dialog" aria-modal="true" aria-labelledby="active-session-title">
            <header class="workout-session-header">
                <div><span class="eyebrow">Treino em andamento</span><h2 id="active-session-title" data-session-title>Treino</h2><p data-session-progress>Carregando...</p></div>
                <div class="workout-session-clock"><time data-session-time>00:00</time><button type="button" data-close-workout-session aria-label="Minimizar">—</button></div>
            </header>
            <div class="workout-session-exercises" data-session-exercises></div>
            <footer class="workout-session-actions">
                <button type="button" class="session-cancel" data-cancel-workout-session>Cancelar treino</button>
                <button type="button" class="session-finish" data-finish-workout-session>Finalizar treino</button>
            </footer>
            <section class="workout-finish-sheet" data-workout-finish-sheet hidden>
                <div class="workout-finish-heading"><div><span class="eyebrow">Fechar sessão</span><h3>Como foi o treino?</h3><p data-workout-finish-summary></p></div><button type="button" data-close-workout-finish aria-label="Voltar">×</button></div>
                <div class="workout-finish-field"><span>Intensidade</span><div class="workout-choice-row"><button type="button" data-finish-intensity="leve">Leve</button><button type="button" data-finish-intensity="moderado">Moderado</button><button type="button" data-finish-intensity="intenso">Intenso</button></div></div>
                <div class="workout-finish-field"><span>Sensação</span><div class="workout-choice-row workout-feeling-row"><button type="button" data-finish-feeling="1">1</button><button type="button" data-finish-feeling="2">2</button><button type="button" data-finish-feeling="3">3</button><button type="button" data-finish-feeling="4">4</button><button type="button" data-finish-feeling="5">5</button></div><small>1 = pesado · 5 = excelente</small></div>
                <label class="workout-finish-notes">Observações<textarea rows="3" maxlength="1000" data-finish-notes placeholder="Algo que vale lembrar no próximo treino?"></textarea></label>
                <div class="workout-finish-actions"><button type="button" data-close-workout-finish>Voltar</button><button type="button" class="session-finish" data-confirm-workout-finish>Salvar atividade</button></div>
            </section>
        </section>
    </div>
</div>
