document.querySelectorAll('.showHidePw').forEach(button => {
    button.addEventListener('click', () => {
        const field = button.closest('.input-field')?.querySelector('.password')
        if (!field) return
        const show = field.type === 'password'
        field.type = show ? 'text' : 'password'
        button.textContent = show ? 'Ocultar' : 'Mostrar'
        button.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha')
    })
})
