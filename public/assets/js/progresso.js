'use strict';
(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    let controller = null
    let navigationId = 0

    const pageRoot = () => document.querySelector('[data-progress-page]')
    const isProgressUrl = value => {
        try { return new URL(value, window.location.href).pathname === '/user/progresso.php' } catch { return false }
    }
    const shouldHandleLink = (link, event) => {
        if (!link || !isProgressUrl(link.href)) return false
        if (link.target && link.target !== '_self') return false
        if (event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0)) return false
        return true
    }
    const setLoading = active => {
        const region = pageRoot()?.querySelector('[data-progress-dynamic]')
        if (!region) return
        if (active) region.setAttribute('aria-busy', 'true')
        else region.removeAttribute('aria-busy')
    }
    const navigate = async (target, push = true) => {
        const url = new URL(target, window.location.href)
        controller?.abort()
        controller = new AbortController()
        const requestId = ++navigationId
        setLoading(true)
        const scrollY = window.scrollY
        try {
            const request = window.StrideBRNet?.fetch || fetch
            const response = await request(url.toString(), {
                headers: {'Accept': 'text/html', 'X-StrideBR-Partial': 'progress'},
                credentials: 'same-origin',
                signal: controller.signal
            }, 14000)
            if (response.redirected && !isProgressUrl(response.url)) { window.location.href = response.url; return }
            if (!response.ok) throw new Error(response.status >= 500 ? t('progress.load_period_error', {}, 'Could not load this period right now.') : t('progress.open_view_error', {}, 'Could not open this progress view.'))
            const html = await response.text()
            if (requestId !== navigationId) return
            const doc = new DOMParser().parseFromString(html, 'text/html')
            const next = doc.querySelector('[data-progress-page]')
            const current = pageRoot()
            if (!next || !current) throw new Error(t('progress.incomplete_response', {}, 'The Progress response was incomplete.'))
            const nextDynamic = next.querySelector('[data-progress-dynamic]')
            const currentDynamic = current.querySelector('[data-progress-dynamic]')
            if (nextDynamic && currentDynamic) {
                currentDynamic.replaceWith(nextDynamic)
                const currentNav = current.querySelector('.progress-sport-nav')
                const nextNav = next.querySelector('.progress-sport-nav')
                if (currentNav && nextNav) currentNav.replaceChildren(...nextNav.cloneNode(true).childNodes)
            }
            else current.replaceWith(next)
            if (doc.title) document.title = doc.title
            if (push) history.pushState({progress: true}, '', `${url.pathname}${url.search}${url.hash}`)
            requestAnimationFrame(() => window.scrollTo({top: Math.min(scrollY, document.documentElement.scrollHeight), behavior: 'auto'}))
        } catch (error) {
            if (error?.name === 'AbortError') return
            window.StrideBRUI?.notify?.(error?.message || t('progress.update_error', {}, 'Could not update Progress.'), 'error', 6000)
        } finally {
            if (requestId === navigationId) {
                controller = null
                setLoading(false)
            }
        }
    }

    const tooltip = () => document.querySelector('[data-progress-tooltip-popover]')
    const hideTooltip = () => {
        const tip = tooltip()
        if (tip) tip.hidden = true
    }
    const showTooltip = target => {
        const tip = tooltip()
        const text = String(target?.dataset?.progressTooltip || '').trim()
        if (!tip || !target || !text) return
        tip.textContent = text
        tip.hidden = false
        const rect = target.getBoundingClientRect()
        const tipRect = tip.getBoundingClientRect()
        const margin = 10
        let left = rect.left + rect.width / 2 - tipRect.width / 2
        left = Math.max(margin, Math.min(left, window.innerWidth - tipRect.width - margin))
        let top = rect.top - tipRect.height - 8
        if (top < margin) top = Math.min(window.innerHeight - tipRect.height - margin, rect.bottom + 8)
        tip.style.left = `${Math.round(left)}px`
        tip.style.top = `${Math.round(top)}px`
    }

    document.addEventListener('click', event => {
        const tooltipTarget = event.target.closest?.('[data-progress-tooltip]')
        if (tooltipTarget) {
            showTooltip(tooltipTarget)
            return
        }
        hideTooltip()
        const link = event.target.closest?.('[data-progress-page] a[href]')
        if (!shouldHandleLink(link, event)) return
        event.preventDefault()
        navigate(link.href, true)
    })
    document.addEventListener('mouseover', event => {
        const target = event.target.closest?.('[data-progress-tooltip]')
        if (target) showTooltip(target)
    })
    document.addEventListener('mouseout', event => {
        if (event.target.closest?.('[data-progress-tooltip]')) hideTooltip()
    })
    document.addEventListener('focusin', event => {
        const target = event.target.closest?.('[data-progress-tooltip]')
        if (target) showTooltip(target)
    })
    document.addEventListener('focusout', event => {
        if (event.target.closest?.('[data-progress-tooltip]')) hideTooltip()
    })
    window.addEventListener('scroll', hideTooltip, {passive: true})
    window.addEventListener('resize', hideTooltip)

    document.addEventListener('change', event => {
        const control = event.target.closest?.('[data-progress-page] [data-progress-auto-submit]')
        if (!control?.form) return
        const form = control.form
        const url = new URL(form.action || '/user/progresso.php', window.location.href)
        const params = new URLSearchParams(new FormData(form))
        url.search = params.toString()
        navigate(url, true)
    })
    document.addEventListener('submit', event => {
        const form = event.target.closest?.('[data-progress-page] .progress-filterbar')
        if (!form) return
        event.preventDefault()
        const url = new URL(form.action || '/user/progresso.php', window.location.href)
        url.search = new URLSearchParams(new FormData(form)).toString()
        navigate(url, true)
    })
    window.addEventListener('popstate', () => {
        if (isProgressUrl(window.location.href)) navigate(window.location.href, false)
    })
})()
