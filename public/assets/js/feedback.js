(() => {
    const form = document.querySelector('[data-feedback-form]');
    if (!form) return;
    const submit = form.querySelector('[data-feedback-submit]');
    const context = form.querySelector('[data-feedback-context]');
    const build = document.querySelector('[data-stridebr-build]');
    const fillContext = () => {
        if (!context) return;
        const lines = [
            `Página: ${window.location.pathname}${window.location.search}`,
            `Viewport: ${window.innerWidth}x${window.innerHeight}`,
            `Tela: ${window.screen?.width || '?'}x${window.screen?.height || '?'}`,
            `Fuso: ${Intl.DateTimeFormat().resolvedOptions().timeZone || 'desconhecido'}`,
            `Idioma: ${navigator.language || 'desconhecido'}`,
            `StrideBR: ${build?.dataset.stridebrVersion || 'desconhecida'} · build ${build?.dataset.stridebrBuild || 'desconhecido'}`
        ];
        context.value = lines.join('\n');
    };
    fillContext();
    form.addEventListener('submit', () => {
        fillContext();
        if (!submit || submit.disabled) return;
        submit.disabled = true;
        submit.textContent = 'Enviando…';
    });
})();
