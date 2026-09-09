(() => {
    const copyText = async value => {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value)
            return
        }
        const input = document.createElement('textarea')
        input.value = value
        input.setAttribute('readonly', '')
        input.style.position = 'fixed'
        input.style.opacity = '0'
        document.body.appendChild(input)
        input.select()
        document.execCommand('copy')
        input.remove()
    }

    document.querySelectorAll('[data-copy-short-url]').forEach(button => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy-short-url') || ''
            if (!value) return
            const original = button.textContent
            try {
                await copyText(value)
                button.textContent = button.dataset.copiedLabel || 'Copiado'
            } catch (_) {
                button.textContent = button.dataset.copyErrorLabel || 'Não foi possível copiar'
            }
            window.setTimeout(() => { button.textContent = original }, 1600)
        })
    })

    const makeSvg = url => {
        if (!window.StrideBRQR) return ''
        const qr = new window.StrideBRQR.QRCode(-1, window.StrideBRQR.ErrorCorrectLevel.M)
        qr.addData(url)
        qr.make()
        const count = qr.getModuleCount()
        const margin = 4
        const size = count + margin * 2
        const cells = []
        for (let row = 0; row < count; row += 1) {
            for (let col = 0; col < count; col += 1) {
                if (qr.isDark(row, col)) cells.push(`M${col + margin} ${row + margin}h1v1h-1z`)
            }
        }
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" role="img" aria-label="QR Code"><rect width="${size}" height="${size}" fill="#fff"/><path d="${cells.join('')}" fill="#111827"/></svg>`
    }

    document.querySelectorAll('[data-marketing-qr]').forEach(container => {
        const url = container.getAttribute('data-marketing-qr') || ''
        if (!url) return
        try {
            const svg = makeSvg(url)
            if (!svg) return
            const preview = container.querySelector('[data-qr-preview]')
            if (preview) preview.innerHTML = svg
            const download = container.querySelector('[data-download-qr]')
            if (download) {
                download.addEventListener('click', () => {
                    const filename = (download.getAttribute('data-download-qr') || 'stridebr-campaign') + '.svg'
                    const blob = new Blob([svg], {type: 'image/svg+xml;charset=utf-8'})
                    const objectUrl = URL.createObjectURL(blob)
                    const anchor = document.createElement('a')
                    anchor.href = objectUrl
                    anchor.download = filename
                    document.body.appendChild(anchor)
                    anchor.click()
                    anchor.remove()
                    URL.revokeObjectURL(objectUrl)
                })
            }
        } catch (_) {
            container.setAttribute('data-qr-error', '1')
        }
    })
})()
