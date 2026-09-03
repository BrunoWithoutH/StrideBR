(() => {
    const button = document.querySelector('[data-copy-donation-pix]')
    const value = document.querySelector('[data-donation-pix]')
    if (!button || !value) return

    button.addEventListener('click', async () => {
        const key = (value.textContent || '').trim()
        if (!key) return
        try {
            await navigator.clipboard.writeText(key)
            const previous = button.textContent
            button.textContent = 'Copiado'
            window.setTimeout(() => { button.textContent = previous }, 1600)
        } catch {
            window.getSelection()?.selectAllChildren(value)
        }
    })
})()
