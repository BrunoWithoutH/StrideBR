document.querySelectorAll('.showHidePw').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.getAttribute('aria-controls'))
        if (!field) return
        const show = field.type === 'password'
        field.type = show ? 'text' : 'password'
        button.textContent = show ? button.dataset.hideLabel : button.dataset.showLabel
        button.setAttribute('aria-pressed', String(show))
    })
})

document.querySelectorAll('[data-verification-code]').forEach(field => {
    field.addEventListener('input', () => {
        field.value = field.value.replace(/\D/g, '').slice(0, 6)
    })
})
