(() => {
    const configNode = document.getElementById('stridebr-ads-config')
    if (!configNode) return

    let config = {}
    try {
        config = JSON.parse(configNode.textContent || '{}')
    } catch {
        return
    }
    if (!config.enabled || !/^ca-pub-\d+$/.test(String(config.client || ''))) return

    const wrappers = Array.from(document.querySelectorAll('.site-ad-placement[data-ad-placement]:not([data-ad-preview])'))
    if (!wrappers.length) return

    const collapse = wrapper => {
        wrapper.hidden = true
        wrapper.setAttribute('data-ad-collapsed', '1')
    }

    const watchStatus = (wrapper, ad) => {
        const inspect = () => {
            if (ad.getAttribute('data-ad-status') === 'unfilled') collapse(wrapper)
        }
        inspect()
        const observer = new MutationObserver(inspect)
        observer.observe(ad, { attributes: true, attributeFilter: ['data-ad-status'] })
    }

    const loadProvider = () => {
        if (window.__stridebrAdsProviderPromise) return window.__stridebrAdsProviderPromise
        window.__stridebrAdsProviderPromise = new Promise(resolve => {
            const existing = document.querySelector('script[data-stridebr-ads-provider]')
            if (existing) {
                if (window.adsbygoogle) resolve(true)
                else {
                    existing.addEventListener('load', () => resolve(true), { once: true })
                    existing.addEventListener('error', () => resolve(false), { once: true })
                }
                return
            }
            const script = document.createElement('script')
            script.async = true
            script.crossOrigin = 'anonymous'
            script.dataset.stridebrAdsProvider = '1'
            script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(config.client)}`
            script.addEventListener('load', () => resolve(true), { once: true })
            script.addEventListener('error', () => resolve(false), { once: true })
            document.head.appendChild(script)
        })
        return window.__stridebrAdsProviderPromise
    }

    const initialize = async () => {
        wrappers.forEach(wrapper => {
            const ad = wrapper.querySelector('.adsbygoogle')
            if (ad) watchStatus(wrapper, ad)
        })

        const loaded = await loadProvider()
        if (!loaded) {
            wrappers.forEach(collapse)
            return
        }

        wrappers.forEach(wrapper => {
            if (wrapper.hidden) return
            const ad = wrapper.querySelector('.adsbygoogle')
            if (!ad || ad.dataset.stridebrAdInitialized === '1') return
            ad.dataset.stridebrAdInitialized = '1'
            try {
                window.adsbygoogle = window.adsbygoogle || []
                window.adsbygoogle.push({})
            } catch {
                collapse(wrapper)
            }
        })
    }

    initialize()
})()
