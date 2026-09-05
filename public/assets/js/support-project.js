(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const button = document.querySelector('[data-copy-donation-pix]')
    const value = document.querySelector('[data-donation-pix]')
    if (!button || !value) return

    button.addEventListener('click', async () => {
        const key = (value.textContent || '').trim()
        if (!key) return
        try {
            await navigator.clipboard.writeText(key)
            const previous = button.textContent
            button.textContent = t('common.copied', {}, 'Copied')
            window.setTimeout(() => { button.textContent = previous }, 1600)
        } catch {
            window.getSelection()?.selectAllChildren(value)
        }
    })
})()
