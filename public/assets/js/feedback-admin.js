(() => {
    const root = document.querySelector('[data-feedback-admin]')
    if (!root) return

    const csrf = root.dataset.csrf || ''
    const currentState = root.dataset.currentState || 'all'
    const rows = () => Array.from(root.querySelectorAll('[data-feedback-row]'))
    const selectAll = root.querySelector('[data-feedback-select-all]')
    const bulk = root.querySelector('[data-feedback-bulk]')
    const selectionCount = root.querySelector('[data-feedback-selection-count]')

    const selected = () => rows().filter(row => !row.hidden && row.querySelector('[data-feedback-select]')?.checked)

    const notify = (message, type = 'success') => {
        if (window.StrideBRUI?.notify) window.StrideBRUI.notify(message, type)
    }

    const syncSelection = () => {
        const visible = rows().filter(row => !row.hidden)
        const checked = visible.filter(row => row.querySelector('[data-feedback-select]')?.checked)
        if (selectAll) {
            selectAll.checked = visible.length > 0 && checked.length === visible.length
            selectAll.indeterminate = checked.length > 0 && checked.length < visible.length
        }
        if (selectionCount) selectionCount.textContent = String(checked.length)
        if (bulk) bulk.hidden = checked.length === 0
    }

    selectAll?.addEventListener('change', () => {
        rows().forEach(row => {
            if (row.hidden) return
            const checkbox = row.querySelector('[data-feedback-select]')
            if (checkbox) checkbox.checked = selectAll.checked
        })
        syncSelection()
    })

    root.addEventListener('change', event => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-feedback-select]')) syncSelection()
    })

    const semanticForAction = action => action === 'mark_unread' ? 'unread' : action === 'mark_resolved' ? 'resolved' : 'read'

    const adjustCount = (state, delta) => {
        const node = root.querySelector(`[data-state-count="${CSS.escape(state)}"]`)
        if (!node) return
        node.textContent = String(Math.max(0, Number(node.textContent || 0) + delta))
    }

    const labelForState = state => {
        const node = root.querySelector(`[data-state-tab="${CSS.escape(state)}"] span`)
        const text = node?.textContent?.trim() || ''
        if (state === 'unread') return text.replace(/s$/i, '') || text
        if (state === 'read') return text.replace(/s$/i, '') || text
        if (state === 'resolved') return text.replace(/s$/i, '') || text
        return text
    }

    const setRowState = (row, state) => {
        const old = row.dataset.feedbackState || 'read'
        row.dataset.feedbackState = state
        row.classList.remove('is-unread', 'is-read', 'is-resolved')
        row.classList.add(`is-${state}`)
        const label = row.querySelector('[data-feedback-state-label]')
        if (label) label.textContent = labelForState(state)
        if (old !== state) {
            adjustCount(old, -1)
            adjustCount(state, 1)
        }
        row.hidden = currentState !== 'all' && currentState !== state
    }

    const postState = async (ids, action, optimisticRows = []) => {
        if (!ids.length) return
        const targetState = semanticForAction(action)
        const snapshots = optimisticRows.map(row => ({
            row,
            state: row.dataset.feedbackState || 'read',
            hidden: row.hidden,
            className: row.className,
            label: row.querySelector('[data-feedback-state-label]')?.textContent || '',
        }))

        optimisticRows.forEach(row => setRowState(row, targetState))
        syncSelection()

        const body = new URLSearchParams()
        body.set('csrf_token', csrf)
        body.set('action', 'bulk_state')
        body.set('state_action', action)
        body.set('response', 'json')
        ids.forEach(id => body.append('ids[]', id))

        try {
            const response = await fetch('/admin/feedback.php', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                credentials: 'same-origin',
                body: body.toString(),
            })
            const data = await response.json().catch(() => ({}))
            if (!response.ok || !data.ok) throw new Error(data.error || 'Could not update feedback.')
            optimisticRows.forEach(row => {
                const checkbox = row.querySelector('[data-feedback-select]')
                if (checkbox) checkbox.checked = false
            })
            syncSelection()
            notify(data.message || 'Feedback updated.')
        } catch (error) {
            snapshots.forEach(snapshot => {
                const now = snapshot.row.dataset.feedbackState || 'read'
                if (now !== snapshot.state) {
                    adjustCount(now, -1)
                    adjustCount(snapshot.state, 1)
                }
                snapshot.row.dataset.feedbackState = snapshot.state
                snapshot.row.hidden = snapshot.hidden
                snapshot.row.className = snapshot.className
                const label = snapshot.row.querySelector('[data-feedback-state-label]')
                if (label) label.textContent = snapshot.label
            })
            syncSelection()
            notify(error?.message || 'Could not update feedback.', 'error')
        }
    }

    root.querySelectorAll('[data-feedback-bulk-action]').forEach(button => {
        button.addEventListener('click', () => {
            const items = selected()
            postState(items.map(row => row.dataset.feedbackId || '').filter(Boolean), button.dataset.feedbackBulkAction || '', items)
        })
    })

    const detail = root.querySelector('[data-feedback-detail]')
    const detailCard = detail?.querySelector('[data-feedback-open-id]')
    const detailId = detailCard?.dataset.feedbackOpenId || ''

    const updateDetailState = state => {
        if (!detailCard) return
        const previous = detailCard.dataset.feedbackOpenState || 'read'
        const row = root.querySelector(`[data-feedback-row][data-feedback-id="${CSS.escape(detailId)}"]`)
        if (row) setRowState(row, state)
        else if (previous !== state) {
            adjustCount(previous, -1)
            adjustCount(state, 1)
        }
        detailCard.dataset.feedbackOpenState = state
        const workflow = detailCard.querySelector('select[name="status"]')
        if (workflow) workflow.value = state === 'unread' ? 'novo' : state === 'resolved' ? 'resolvido' : 'lendo'
        const label = detailCard.querySelector('[data-feedback-detail-state]')
        if (label) label.textContent = labelForState(state)
    }

    const postSingle = async action => {
        if (!detailId) return
        const oldState = detailCard?.dataset.feedbackOpenState || 'read'
        const nextState = semanticForAction(action)
        updateDetailState(nextState)
        const body = new URLSearchParams({csrf_token: csrf, action: 'bulk_state', state_action: action, response: 'json'})
        body.append('ids[]', detailId)
        try {
            const response = await fetch('/admin/feedback.php', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                credentials: 'same-origin',
                body: body.toString(),
            })
            const data = await response.json().catch(() => ({}))
            if (!response.ok || !data.ok) throw new Error(data.error || 'Could not update feedback.')
            notify(data.message || 'Feedback updated.')
        } catch (error) {
            updateDetailState(oldState)
            notify(error?.message || 'Could not update feedback.', 'error')
        }
    }

    root.querySelectorAll('[data-feedback-single-action]').forEach(button => {
        button.addEventListener('click', () => postSingle(button.dataset.feedbackSingleAction || ''))
    })

    if (detailId && detailCard?.dataset.feedbackOpenState === 'unread') {
        const body = new URLSearchParams({csrf_token: csrf, action: 'mark_read_open', idfeedback: detailId, response: 'json'})
        fetch('/admin/feedback.php', {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            credentials: 'same-origin',
            body: body.toString(),
        }).then(async response => {
            const data = await response.json().catch(() => ({}))
            if (response.ok && data.ok) updateDetailState('read')
        }).catch(() => {})
    }

    syncSelection()
})()
