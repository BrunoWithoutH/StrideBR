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
    const savePeriodPreference = period => {
        if (!['all', '4w', '12w', '6m', '1y'].includes(period)) return
        const form = pageRoot()?.querySelector('[data-progress-preference-url]')
        const token = String(form?.dataset?.progressCsrfToken || '').trim()
        const endpoint = String(form?.dataset?.progressPreferenceUrl || '').trim()
        if (!token || !endpoint) return
        const body = new FormData()
        body.set('csrf_token', token)
        body.set('period', period)
        const request = window.StrideBRNet?.fetch || fetch
        Promise.resolve(request(endpoint, {
            method: 'POST',
            body,
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin'
        }, 8000)).catch(() => {})
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
            activateBenchmarkDialog()
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
    const closeNavMenus = except => {
        document.querySelectorAll('[data-progress-nav-more][open]').forEach(menu => {
            if (menu !== except) menu.removeAttribute('open')
        })
    }
    const syncBenchmarkOfficialContext = dialog => {
        if (!dialog) return
        const official = dialog.querySelector('[data-progress-reported-official]')
        const context = dialog.querySelector('[data-progress-benchmark-context]')
        const officialContext = dialog.querySelector('[data-progress-official-context]')
        const competitionField = dialog.querySelector('[data-progress-benchmark-competition-field]')
        const competition = dialog.querySelector('[data-progress-benchmark-competition]')
        if (!context) return
        if (official && officialContext && official.checked) {
            context.value = 'competicao'
            context.disabled = true
            officialContext.disabled = false
            if (competition) competition.required = !competition.disabled
        } else {
            context.disabled = false
            if (officialContext) officialContext.disabled = true
            if (competition) competition.required = false
        }
        if (competitionField) competitionField.hidden = context.value !== 'competicao'
    }
    const activateBenchmarkDialog = () => {
        const dialog = pageRoot()?.querySelector('[data-progress-benchmark-dialog]')
        if (!dialog) return
        syncBenchmarkOfficialContext(dialog)
        if (!dialog.dataset.progressDialogBound) {
            dialog.dataset.progressDialogBound = '1'
            dialog.addEventListener('close', () => {
                const returnUrl = String(dialog.dataset.returnUrl || '').trim()
                if (!returnUrl) return
                const current = `${window.location.pathname}${window.location.search}${window.location.hash}`
                const target = new URL(returnUrl, window.location.href)
                const targetValue = `${target.pathname}${target.search}${target.hash}`
                if (current !== targetValue) history.replaceState({progress: true}, '', targetValue)
            })
            dialog.addEventListener('click', event => {
                if (event.target === dialog) dialog.close()
            })
            dialog.querySelector('[data-progress-benchmark-context]')?.addEventListener('change', () => syncBenchmarkOfficialContext(dialog))
            dialog.querySelector('[data-progress-reported-official]')?.addEventListener('change', () => syncBenchmarkOfficialContext(dialog))
        }
        if (typeof dialog.showModal !== 'function') return
        if (dialog.hasAttribute('open')) dialog.removeAttribute('open')
        if (!dialog.open) dialog.showModal()
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
        const navMenu = event.target.closest?.('[data-progress-nav-more]')
        closeNavMenus(navMenu)
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
    window.addEventListener('resize', () => {
        hideTooltip()
        closeNavMenus()
    })
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return
        const openMenu = document.querySelector('[data-progress-nav-more][open]')
        if (!openMenu) return
        openMenu.removeAttribute('open')
        openMenu.querySelector('summary')?.focus()
    })

    document.addEventListener('change', event => {
        const official = event.target.closest?.('[data-progress-reported-official]')
        if (official) syncBenchmarkOfficialContext(official.closest('[data-progress-benchmark-dialog]'))
        const control = event.target.closest?.('[data-progress-page] [data-progress-auto-submit]')
        if (!control?.form) return
        if (control.matches('[data-progress-period-select]')) savePeriodPreference(String(control.value || ''))
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
    document.addEventListener('input', event => {
        const input = event.target.closest?.('[data-progress-exercise-search]')
        if (!input) return
        const value = String(input.value || '').trim().toLocaleLowerCase()
        document.querySelectorAll('[data-progress-exercise-list] [data-progress-exercise]').forEach(item => {
            item.hidden = value !== '' && !String(item.dataset.progressExercise || '').includes(value)
        })
    })
    window.addEventListener('popstate', () => {
        if (isProgressUrl(window.location.href)) navigate(window.location.href, false)
    })
    activateBenchmarkDialog()
})()
