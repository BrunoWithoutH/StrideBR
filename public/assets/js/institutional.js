(() => {
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-start-institutional]')
        if (!button || !window.StrideBRWorkout?.startInstitutional) return
        button.disabled = true
        button.setAttribute('aria-busy', 'true')
        try { await window.StrideBRWorkout.startInstitutional(button.dataset.startInstitutional || '') }
        finally { button.disabled = false; button.removeAttribute('aria-busy') }
    })
})()
