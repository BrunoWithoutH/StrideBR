const stridebrInitActivityExchange = () => {
    const page = document.querySelector('[data-exchange-page]')
    if (!page || page.dataset.exchangeBound === '1') return
    page.dataset.exchangeBound = '1'
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const tn = (oneKey, otherKey, count, values = {}) => window.StrideBRI18n?.tn?.(oneKey, otherKey, count, values) ?? t(Number(count) === 1 ? oneKey : otherKey, {...values, count})
    const input = page.querySelector('[data-import-files]')
    const dropzone = page.querySelector('[data-import-dropzone]')
    const list = page.querySelector('[data-import-list]')
    const bulk = page.querySelector('[data-import-bulk]')
    const bulkCount = page.querySelector('[data-import-selected-count]')
    const selectAllButton = page.querySelector('[data-import-select-all]')
    const closeSelectedButton = page.querySelector('[data-import-close-selected]')
    const importSelectedButton = page.querySelector('[data-import-selected]')
    const csrf = page.dataset.csrfToken || ''
    let modalities = []
    let routeTargets = null
    let routeTargetsPromise = null
    const uploadQueue = []
    let activeUploads = 0
    try { modalities = JSON.parse(document.getElementById('activity-import-modalities')?.textContent || '[]') } catch (_) {}

    const hasNumber = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))
    const formatDuration = seconds => {
        if (!hasNumber(seconds)) return '—'
        return window.StrideBRI18n?.duration?.(Number(seconds), true) || String(seconds)
    }
    const formatDistance = meters => hasNumber(meters) ? `${window.StrideBRI18n?.number?.(Number(meters) / 1000, 2, true) ?? (Number(meters) / 1000).toFixed(2)} km` : '—'
    const formatDate = iso => {
        if (!iso) return t('exchange.date_not_found', {}, 'Date not found')
        const date = new Date(iso)
        if (Number.isNaN(date.getTime())) return t('exchange.date_not_found', {}, 'Date not found')
        return window.StrideBRI18n?.date?.(date, {dateStyle: 'medium', timeStyle: 'short'}) || date.toLocaleString()
    }
    const inputDate = iso => {
        const date = new Date(iso || '')
        if (Number.isNaN(date.getTime())) return ''
        const y = date.getFullYear()
        const m = String(date.getMonth() + 1).padStart(2, '0')
        const d = String(date.getDate()).padStart(2, '0')
        return `${y}-${m}-${d}`
    }
    const inputTime = iso => {
        const date = new Date(iso || '')
        if (Number.isNaN(date.getTime())) return ''
        return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
    }
    const el = (tag, className, text) => {
        const node = document.createElement(tag)
        if (className) node.className = className
        if (text !== undefined) node.textContent = text
        return node
    }
    const requestJson = async (url, body) => {
        const response = await (window.StrideBRNet?.fetch || fetch)(url, {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}}, 20000)
        const result = await response.json().catch(() => null)
        if (!response.ok || !result?.ok) throw new Error(result?.error || t('common.action_failed', {}, 'Could not complete the action.'))
        return result
    }
    const historyCachePrefix = 'stridebr.activity.history.v3:'
    const pendingHistoryRefreshKey = 'stridebr.activity.import.pendingRefresh.v1'
    const invalidateActivityHistoryCache = () => {
        try {
            for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
                const key = sessionStorage.key(index)
                if (key?.startsWith(historyCachePrefix)) sessionStorage.removeItem(key)
            }
        } catch (_) {}
    }
    const notifyActivityImports = results => {
        const items = (Array.isArray(results) ? results : [results]).filter(result => result?.idregistro)
        if (!items.length) return
        invalidateActivityHistoryCache()
        try {
            const previous = JSON.parse(sessionStorage.getItem(pendingHistoryRefreshKey) || 'null')
            const ids = new Set(Array.isArray(previous?.ids) ? previous.ids.map(String) : [])
            items.forEach(result => ids.add(String(result.idregistro)))
            sessionStorage.setItem(pendingHistoryRefreshKey, JSON.stringify({ids: Array.from(ids), at: Date.now()}))
        } catch (_) {}
        window.dispatchEvent(new CustomEvent('stridebr:activity-imported', {
            detail: {
                count: items.length,
                ids: items.map(result => String(result.idregistro)),
                items: items.map(result => ({id: String(result.idregistro), url: String(result.url || '')}))
            }
        }))
        window.StrideBRUI?.notify(tn('exchange.imported_notice.one', 'exchange.imported_notice.other', items.length), 'success', 3200)
    }
    const ensureRouteTargets = async () => {
        if (Array.isArray(routeTargets)) return routeTargets
        if (routeTargetsPromise) return routeTargetsPromise
        routeTargetsPromise = (window.StrideBRNet?.fetch || fetch)('/api/atividade-rota-alvos.php', {credentials: 'same-origin', headers: {'Accept': 'application/json'}}, 10000)
            .then(async response => {
                const result = await response.json().catch(() => null)
                if (!response.ok || !result?.ok) throw new Error(result?.error || t('exchange.load_activities_error', {}, 'Could not load available activities.'))
                routeTargets = Array.isArray(result.items) ? result.items : []
                return routeTargets
            })
            .finally(() => { routeTargetsPromise = null })
        return routeTargetsPromise
    }
    const drawRoute = (canvas, coordinates) => {
        if (!canvas || !Array.isArray(coordinates) || coordinates.length < 2) return
        const context = canvas.getContext('2d')
        const width = canvas.width
        const height = canvas.height
        context.clearRect(0, 0, width, height)
        const xs = coordinates.map(point => Number(point[0]))
        const ys = coordinates.map(point => Number(point[1]))
        const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys)
        const rangeX = Math.max(maxX - minX, 0.000001), rangeY = Math.max(maxY - minY, 0.000001)
        const pad = 20
        const scale = Math.min((width - pad * 2) / rangeX, (height - pad * 2) / rangeY)
        const usedW = rangeX * scale, usedH = rangeY * scale
        const ox = (width - usedW) / 2, oy = (height - usedH) / 2
        const projected = coordinates.map(([lon, lat]) => [ox + (Number(lon) - minX) * scale, oy + (maxY - Number(lat)) * scale])
        context.beginPath()
        projected.forEach(([x, y], index) => index ? context.lineTo(x, y) : context.moveTo(x, y))
        context.lineCap = 'round'
        context.lineJoin = 'round'
        context.strokeStyle = '#40507c'
        context.lineWidth = 4
        context.stroke()
        ;[projected[0], projected.at(-1)].forEach(([x, y], index) => {
            context.beginPath()
            context.arc(x, y, 4.3, 0, Math.PI * 2)
            context.fillStyle = index ? '#40507c' : '#ffffff'
            context.fill()
            context.lineWidth = 2
            context.strokeStyle = '#40507c'
            context.stroke()
        })
    }
    const modalitySelect = preview => {
        const select = el('select', 'exchange-preview-select')
        modalities.forEach(item => {
            const option = document.createElement('option')
            option.value = item.slug
            option.textContent = item.name
            option.selected = item.slug === preview.modality?.slug
            select.append(option)
        })
        return select
    }
    const fillRouteTargetSelect = (select, items) => {
        select.replaceChildren()
        const placeholder = document.createElement('option')
        placeholder.value = ''
        placeholder.textContent = items.length ? t('exchange.choose_activity_no_route', {}, 'Choose an activity without a route') : t('exchange.no_activity_no_route', {}, 'No activity without a route available')
        placeholder.selected = true
        select.append(placeholder)
        items.forEach(item => {
            const option = document.createElement('option')
            option.value = item.id
            option.textContent = `${item.title} · ${item.modality} · ${formatDate(item.start)}`
            select.append(option)
        })
    }
    const routeTargetSelect = () => {
        const select = document.createElement('select')
        const placeholder = document.createElement('option')
        placeholder.value = ''
        placeholder.textContent = t('exchange.loading_activities', {}, 'Loading activities…')
        placeholder.selected = true
        select.append(placeholder)
        select.disabled = true
        return select
    }
    const removeRouteTarget = id => {
        if (!Array.isArray(routeTargets)) return
        routeTargets = routeTargets.filter(item => item.id !== id)
        page.querySelectorAll('.exchange-route-attach select').forEach(select => {
            select.querySelector(`option[value="${CSS.escape(id)}"]`)?.remove()
            const placeholder = select.querySelector('option[value=""]')
            if (placeholder && select.options.length === 1) placeholder.textContent = t('exchange.no_activity_no_route', {}, 'No activity without a route available')
            const button = select.closest('.exchange-route-attach')?.querySelector('.exchange-import-button')
            if (button && select.options.length === 1) button.disabled = true
        })
    }
    const discardPreview = async id => {
        if (!id) return
        const body = new FormData()
        body.append('csrf_token', csrf)
        body.append('idimportacao', id)
        try { await requestJson('/api/atividade-importacao-descartar.php', body) } catch (_) {}
    }
    const closeCard = async card => {
        const id = card.dataset.importId || ''
        const status = card.dataset.status || 'pending'
        if (id && status === 'pending') await discardPreview(id)
        card.remove()
        updateBulkToolbar()
    }
    const closeButton = card => {
        const button = el('button', 'exchange-card-close', '×')
        button.type = 'button'
        button.setAttribute('aria-label', t('common.close', {}, 'Close'))
        button.addEventListener('click', () => closeCard(card))
        return button
    }
    const renderError = (card, message, fileName = '') => {
        card.classList.remove('is-loading')
        card.classList.add('is-error')
        card.dataset.status = 'error'
        const title = fileName ? t('exchange.file_read_named', {file: fileName}, `Could not read ${fileName}`) : t('exchange.file_read_error', {}, 'Could not read the file')
        card.replaceChildren(closeButton(card), el('strong', '', title), el('span', '', message))
        updateBulkToolbar()
    }
    const metricCard = (label, value) => {
        const item = el('div')
        item.append(el('span', '', label), el('strong', '', value))
        return item
    }
    const makeTop = (card, preview, selectable) => {
        const top = el('div', 'exchange-preview-top')
        const identity = el('div', 'exchange-preview-identity')
        const fileLine = el('div', 'exchange-preview-fileline')
        if (selectable) {
            const selection = el('label', 'exchange-card-select')
            const checkbox = document.createElement('input')
            checkbox.type = 'checkbox'
            checkbox.setAttribute('aria-label', t('exchange.select_file', {file: preview.file_name}, `Select ${preview.file_name}`))
            checkbox.addEventListener('change', updateBulkToolbar)
            selection.append(checkbox)
            fileLine.append(selection)
        }
        fileLine.append(el('span', 'exchange-format-pill', preview.format), el('strong', '', preview.file_name))
                const typeLabel = preview.file_type === 'percurso' ? t('exchange.type_route', {}, 'Course') : preview.file_type === 'treino' ? t('exchange.type_workout', {}, 'Workout') : (preview.modality?.name || t('exchange.type_activity', {}, 'Activity'))
        identity.append(fileLine, el('span', '', `${typeLabel} · ${formatDate(preview.start)}`))
        const side = el('div', 'exchange-preview-side')
        side.append(closeButton(card))
        if (Array.isArray(preview.route_preview) && preview.route_preview.length > 1) {
            const canvas = document.createElement('canvas')
            canvas.className = 'exchange-route-preview'
            canvas.width = 240
            canvas.height = 110
            side.append(canvas)
            requestAnimationFrame(() => drawRoute(canvas, preview.route_preview))
        }
        top.append(identity, side)
        return top
    }
    const makeMetrics = preview => {
        const metrics = el('div', 'exchange-preview-metrics')
        metrics.append(
            metricCard(t('activity.summary.duration', {}, 'Duration'), formatDuration(preview.duration_s)),
            metricCard(t('activity.summary.distance', {}, 'Distance'), formatDistance(preview.distance_m)),
            metricCard(t('activity.summary.elevation', {}, 'Elevation'), hasNumber(preview.elevation_gain_m) ? `${Math.round(Number(preview.elevation_gain_m))} m` : '—'),
            metricCard(t('exchange.average_hr', {}, 'Avg. HR'), hasNumber(preview.avg_hr) && Number(preview.avg_hr) > 0 ? `${Math.round(Number(preview.avg_hr))} bpm` : '—')
        )
        return metrics
    }
    const makeMeta = preview => {
        const meta = el('div', 'exchange-preview-meta')
        const deviceText = preview.device?.name || [preview.device?.manufacturer, preview.device?.product_id ? t('exchange.device_product', {id: preview.device.product_id}, `product ${preview.device.product_id}`) : ''].filter(Boolean).join(' · ')
        if (deviceText) meta.append(el('span', '', deviceText))
        if (preview.stream_points) meta.append(el('span', '', tn('exchange.recorded_points.one', 'exchange.recorded_points.other', preview.stream_points)))
        if (!preview.original_saved) meta.append(el('span', '', t('exchange.original_large', {}, 'The original file is large; normalized data was preserved')))
        return meta
    }
    const renderRouteAttach = (card, preview, top, metrics, meta) => {
        const warning = el('div', 'exchange-preview-warning')
        warning.append(el('strong', '', t('exchange.route_heading', {}, 'Course/route')), el('span', '', t('exchange.route_file_help', {}, 'This file is not a recorded activity.')))
        const form = el('form', 'exchange-route-attach')
        const field = el('label')
        field.append(el('span', '', t('exchange.add_to_activity', {}, 'Add to an activity')))
        const select = routeTargetSelect()
        select.name = 'idregistro'
        field.append(select)
        const button = el('button', 'exchange-import-button', t('exchange.add_route', {}, 'Add route'))
        button.type = 'submit'
        button.disabled = true
        form.append(field, button)
        ensureRouteTargets().then(items => {
            if (!card.isConnected) return
            fillRouteTargetSelect(select, items)
            select.disabled = false
            button.disabled = items.length === 0
        }).catch(error => {
            if (!card.isConnected) return
            fillRouteTargetSelect(select, [])
            select.disabled = true
            button.disabled = true
            form.prepend(el('div', 'exchange-inline-error', error?.message || t('exchange.load_activities_error', {}, 'Could not load available activities.')))
        })
        form.addEventListener('submit', async event => {
            event.preventDefault()
            if (!select.value) return
            button.disabled = true
            button.textContent = t('exchange.adding', {}, 'Adding…')
            form.querySelector('.exchange-inline-error')?.remove()
            const body = new FormData()
            body.append('csrf_token', csrf)
            body.append('idimportacao', preview.id)
            body.append('idregistro', select.value)
            try {
                const targetId = select.value
                const result = await requestJson('/api/atividade-importacao-aplicar-rota.php', body)
                card.dataset.status = 'imported'
                removeRouteTarget(targetId)
                const success = el('div', 'exchange-import-success')
                success.append(el('strong', '', t('exchange.route_added', {}, 'Route added to activity')))
                const link = el('a', '', t('exchange.open_activity', {}, 'Open activity'))
                link.href = result.url || '/user/atividades.php'
                success.append(link)
                form.replaceWith(success)
            } catch (error) {
                button.disabled = false
                button.textContent = t('exchange.add_route', {}, 'Add route')
                form.prepend(el('div', 'exchange-inline-error', error?.message || t('exchange.route_add_error', {}, 'Could not add the route.')))
            }
        })
        card.replaceChildren(top, metrics, meta, warning, form)
    }
    const submitImport = async (card, {notify = true} = {}) => {
        const form = card.querySelector('.exchange-preview-form')
        const button = form?.querySelector('.exchange-import-button')
        if (!form || !button || card.dataset.status !== 'pending') return false
        if (!form.reportValidity()) return false
        button.disabled = true
        button.textContent = t('exchange.importing', {}, 'Importing…')
        form.querySelector('.exchange-inline-error')?.remove()
        const body = new FormData(form)
        body.append('csrf_token', csrf)
        body.append('idimportacao', card.dataset.importId || '')
        try {
            const result = await requestJson('/api/atividade-importacao-confirmar.php', body)
            card.dataset.status = 'imported'
            card.dataset.importable = '0'
            card.querySelector('.exchange-card-select')?.remove()
            const success = el('div', 'exchange-import-success')
            success.append(el('strong', '', t('exchange.activity_imported', {}, 'Activity imported')))
            const link = el('a', '', t('exchange.open_activity', {}, 'Open activity'))
            link.href = result.url || '/user/atividades.php'
            success.append(link)
            form.replaceWith(success)
            if (notify) notifyActivityImports(result)
            updateBulkToolbar()
            return result
        } catch (error) {
            button.disabled = false
            button.textContent = t('exchange.import', {}, 'Import')
            const message = el('div', 'exchange-inline-error', error?.message || t('exchange.import_error', {}, 'Could not import.'))
            form.prepend(message)
            updateBulkToolbar()
            return null
        }
    }
    const renderPreview = (card, preview) => {
        card.classList.remove('is-loading')
        card.dataset.importId = preview.id
        card.dataset.status = 'pending'
        const isActivity = preview.file_type === 'atividade'
        const top = makeTop(card, preview, isActivity)
        const metrics = makeMetrics(preview)
        const meta = makeMeta(preview)

        if (!isActivity) {
            if (preview.file_type === 'percurso' && Array.isArray(preview.route_preview) && preview.route_preview.length > 1) {
                renderRouteAttach(card, preview, top, metrics, meta)
            } else {
                const warning = el('div', 'exchange-preview-warning')
                const label = preview.file_type === 'treino' ? t('exchange.structured_workout', {}, 'structured workout') : t('exchange.unrecognized_file', {}, 'file not recognized as an activity')
                warning.append(el('strong', '', t('exchange.not_recorded_activity', {}, 'Not a recorded activity')), el('span', '', t('exchange.identified_as', {type: label}, `The file was identified as ${label}.`)))
                card.replaceChildren(top, metrics, meta, warning)
            }
            updateBulkToolbar()
            return
        }

        card.dataset.importable = '1'
        const form = el('form', 'exchange-preview-form')
        const titleField = el('label', 'exchange-field-title')
        titleField.append(el('span', '', t('common.title', {}, 'Title')))
        const titleInput = document.createElement('input')
        titleInput.name = 'titulo'
        titleInput.maxLength = 255
        titleInput.value = preview.title || preview.modality?.name || t('exchange.type_activity', {}, 'Activity')
        titleField.append(titleInput)

        const modalityField = el('label', 'exchange-field-modality')
        modalityField.append(el('span', '', t('common.sport', {}, 'Sport')))
        const select = modalitySelect(preview)
        select.name = 'modalidade_slug'
        modalityField.append(select)

        const dateField = el('label', 'exchange-field-date')
        dateField.append(el('span', '', t('common.date', {}, 'Date')))
        const dateInput = document.createElement('input')
        dateInput.type = 'date'
        dateInput.name = 'data_inicio'
        dateInput.value = inputDate(preview.start)
        dateInput.required = true
        dateField.append(dateInput)

        const timeField = el('label', 'exchange-field-time')
        timeField.append(el('span', '', t('exchange.time', {}, 'Time')))
        const timeInput = document.createElement('input')
        timeInput.type = 'time'
        timeInput.name = 'hora_inicio'
        timeInput.value = inputTime(preview.start)
        timeInput.required = true
        timeField.append(timeInput)

        const visibilityField = el('label', 'exchange-field-visibility')
        visibilityField.append(el('span', '', t('exchange.visibility', {}, 'Visibility')))
        const visibility = document.createElement('select')
        visibility.name = 'visibilidade'
        ;[['privado', t('exchange.private', {}, 'Private')], ['amigos', t('common.friends', {}, 'Friends')], ['publico', t('exchange.public', {}, 'Public')]].forEach(([value, label]) => {
            const option = document.createElement('option')
            option.value = value
            option.textContent = label
            visibility.append(option)
        })
        visibilityField.append(visibility)
        form.append(titleField, modalityField, dateField, timeField, visibilityField)

        if (preview.duplicate) {
            const duplicate = el('label', 'exchange-duplicate')
            const checkbox = document.createElement('input')
            checkbox.type = 'checkbox'
            checkbox.name = 'permitir_duplicata'
            checkbox.value = '1'
            const duplicateTitle = preview.duplicate.activity?.titulo || t('exchange.existing_activity', {}, 'existing activity')
            duplicate.append(checkbox, el('span', '', t('exchange.duplicate_warning', {title: duplicateTitle}, `A similar activity already exists: “${duplicateTitle}”.`)))
            form.append(duplicate)
        }

        const actions = el('div', 'exchange-preview-actions')
        const button = el('button', 'exchange-import-button', t('exchange.import', {}, 'Import'))
        button.type = 'submit'
        actions.append(button)
        form.append(actions)
        form.addEventListener('submit', event => {
            event.preventDefault()
            submitImport(card)
        })

        card.replaceChildren(top, metrics, meta, form)
        updateBulkToolbar()
    }
    const uploadFile = async file => {
        const card = el('article', 'exchange-import-preview is-loading')
        card.append(el('strong', '', file.name), el('span', '', t('exchange.analyzing_file', {}, 'Analyzing file…')))
        list.prepend(card)
        const extension = file.name.split('.').pop()?.toLowerCase()
        if (!['fit', 'tcx', 'gpx'].includes(extension || '')) return renderError(card, t('exchange.supported_formats', {}, 'Use FIT, TCX, or GPX.'), file.name)
        if (file.size > 25 * 1024 * 1024) return renderError(card, t('exchange.file_limit', {}, 'The limit is 25 MB per file.'), file.name)
        const body = new FormData()
        body.append('csrf_token', csrf)
        body.append('arquivo', file, file.name)
        try {
            const result = await requestJson('/api/atividade-importacao-preview.php', body)
            renderPreview(card, result.preview)
        } catch (error) {
            renderError(card, error?.message || t('exchange.analyze_error', {}, 'Could not analyze this file.'), file.name)
        }
    }
    const drainUploadQueue = () => {
        while (activeUploads < 3 && uploadQueue.length) {
            const file = uploadQueue.shift()
            activeUploads += 1
            uploadFile(file).finally(() => {
                activeUploads -= 1
                drainUploadQueue()
            })
        }
    }
    const enqueueUpload = file => {
        uploadQueue.push(file)
        drainUploadQueue()
    }
    const importableCards = () => Array.from(list.querySelectorAll('.exchange-import-preview[data-importable="1"][data-status="pending"]'))
    function updateBulkToolbar() {
        const cards = importableCards()
        const selected = cards.filter(card => card.querySelector('.exchange-card-select input')?.checked)
        if (bulk) bulk.hidden = cards.length === 0
        if (bulkCount) bulkCount.textContent = tn('exchange.selected.one', 'exchange.selected.other', selected.length)
        if (selectAllButton) selectAllButton.textContent = cards.length > 0 && selected.length === cards.length ? t('exchange.deselect_all', {}, 'Deselect all') : t('exchange.select_all', {}, 'Select all')
        if (importSelectedButton) importSelectedButton.disabled = selected.length === 0
        if (closeSelectedButton) closeSelectedButton.disabled = selected.length === 0
    }
    selectAllButton?.addEventListener('click', () => {
        const cards = importableCards()
        const allSelected = cards.length > 0 && cards.every(card => card.querySelector('.exchange-card-select input')?.checked)
        cards.forEach(card => {
            const checkbox = card.querySelector('.exchange-card-select input')
            if (checkbox) checkbox.checked = !allSelected
        })
        updateBulkToolbar()
    })
    closeSelectedButton?.addEventListener('click', async () => {
        const cards = importableCards().filter(card => card.querySelector('.exchange-card-select input')?.checked)
        closeSelectedButton.disabled = true
        for (const card of cards) await closeCard(card)
        updateBulkToolbar()
    })
    importSelectedButton?.addEventListener('click', async () => {
        const cards = importableCards().filter(card => card.querySelector('.exchange-card-select input')?.checked)
        if (!cards.length) return
        importSelectedButton.disabled = true
        const imported = []
        let finished = 0
        let cursor = 0
        const updateProgress = () => { importSelectedButton.textContent = t('exchange.importing_progress', {done: finished, total: cards.length}, `Importing ${finished}/${cards.length}…`) }
        updateProgress()
        const worker = async () => {
            while (cursor < cards.length) {
                const index = cursor++
                const result = await submitImport(cards[index], {notify: false})
                if (result?.idregistro) imported.push(result)
                finished += 1
                updateProgress()
            }
        }
        await Promise.all(Array.from({length: Math.min(2, cards.length)}, worker))
        if (imported.length) notifyActivityImports(imported)
        const failed = cards.length - imported.length
        if (failed > 0) window.StrideBRUI?.notify(tn('exchange.files_attention.one', 'exchange.files_attention.other', failed), 'warning', 5200)
        importSelectedButton.textContent = t('exchange.import_selected', {}, 'Import selected')
        updateBulkToolbar()
    })
    const handleFiles = files => {
        Array.from(files || []).slice(0, 10).forEach(enqueueUpload)
        if (input) input.value = ''
    }
    input?.addEventListener('change', () => handleFiles(input.files))
    ;['dragenter', 'dragover'].forEach(name => dropzone?.addEventListener(name, event => {
        event.preventDefault()
        dropzone.classList.add('is-dragging')
    }))
    ;['dragleave', 'drop'].forEach(name => dropzone?.addEventListener(name, event => {
        event.preventDefault()
        dropzone.classList.remove('is-dragging')
    }))
    dropzone?.addEventListener('drop', event => handleFiles(event.dataTransfer?.files))
    updateBulkToolbar()
}
window.StrideBRActivityExchangeInit = stridebrInitActivityExchange
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', stridebrInitActivityExchange)
else stridebrInitActivityExchange()
