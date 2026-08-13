document.querySelectorAll('.showHidePw').forEach((button) => {
    const toggle = () => {
        const field = button.closest('.input-field')?.querySelector('.password')
        if (!field) return
        const show = field.type === 'password'
        field.type = show ? 'text' : 'password'
        button.classList.toggle('uil-eye', show)
        button.classList.toggle('uil-eye-slash', !show)
    }

    button.addEventListener('click', toggle)
    button.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            toggle()
        }
    })
})
