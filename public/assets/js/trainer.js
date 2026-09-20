(() => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t(key, values, fallback) ?? fallback
    let prescriptionTrigger = null
    let prescriptionModal = null

    const focusable = root => [...(root?.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') || [])].filter(node => !node.hidden && node.getClientRects().length)

    const setPrescriptionOpen = (open, date = '') => {
        if (!prescriptionModal) return
        prescriptionModal.hidden = !open
        document.documentElement.classList.toggle('trainer-prescription-open', open)
        if (open && date) {
            const dateInput = prescriptionModal.querySelector('[data-prescription-date]')
            if (dateInput) dateInput.value = date
        }
        if (open) requestAnimationFrame(() => prescriptionModal.querySelector('input[name="titulo"]')?.focus())
        else prescriptionTrigger?.focus()
    }

    const bindRemove = (row, list) => {
        const remove = row.querySelector('[data-remove-prescription-exercise]')
        if (!remove || remove.dataset.trainerBound === '1') return
        remove.dataset.trainerBound = '1'
        remove.addEventListener('click', () => {
            const rows = list?.querySelectorAll('[data-prescription-exercise]') || []
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach(input => { input.value = '' })
                return
            }
            const next = row.nextElementSibling
            row.remove()
            window.StrideBRUI?.undo?.(t('trainer.exercise_removed_undo', {}, 'Exercise removed.'), async () => {
                if (next?.isConnected) list.insertBefore(row, next)
                else list.appendChild(row)
            })
        })
    }

    const setupPrescription = () => {
        prescriptionModal = document.querySelector('[data-prescription-modal]')
        if (!prescriptionModal) return
        if (prescriptionModal.parentElement !== document.body) document.body.appendChild(prescriptionModal)
        prescriptionModal.querySelectorAll('[data-close-prescription]').forEach(button => {
            if (button.dataset.trainerBound === '1') return
            button.dataset.trainerBound = '1'
            button.addEventListener('click', () => setPrescriptionOpen(false))
        })
        document.querySelectorAll('[data-open-prescription]').forEach(button => {
            if (button.dataset.trainerBound === '1') return
            button.dataset.trainerBound = '1'
            button.addEventListener('click', () => {
                prescriptionTrigger = button
                setPrescriptionOpen(true)
            })
        })
        document.querySelectorAll('[data-coach-create-date]').forEach(button => {
            if (button.dataset.trainerBound === '1') return
            button.dataset.trainerBound = '1'
            button.addEventListener('click', () => {
                prescriptionTrigger = button
                setPrescriptionOpen(true, button.dataset.coachCreateDate || '')
            })
        })
        const list = prescriptionModal.querySelector('[data-prescription-exercises]')
        list?.querySelectorAll('[data-prescription-exercise]').forEach(row => bindRemove(row, list))
        const add = prescriptionModal.querySelector('[data-add-prescription-exercise]')
        if (add && add.dataset.trainerBound !== '1') {
            add.dataset.trainerBound = '1'
            add.addEventListener('click', () => {
                const source = list?.querySelector('[data-prescription-exercise]')
                if (!source || !list || list.querySelectorAll('[data-prescription-exercise]').length >= 100) return
                const row = source.cloneNode(true)
                row.querySelectorAll('input').forEach(input => { input.value = '' })
                row.querySelectorAll('[data-trainer-bound]').forEach(node => delete node.dataset.trainerBound)
                bindRemove(row, list)
                list.appendChild(row)
                row.querySelector('input')?.focus()
            })
        }
        if (prescriptionModal.dataset.autoOpen === '1') requestAnimationFrame(() => setPrescriptionOpen(true))
    }

    document.addEventListener('keydown', event => {
        if (!prescriptionModal || prescriptionModal.hidden) return
        if (event.key === 'Escape') {
            event.preventDefault()
            setPrescriptionOpen(false)
            return
        }
        if (event.key !== 'Tab') return
        const dialog = prescriptionModal.querySelector('[role="dialog"]')
        const items = focusable(dialog)
        if (!items.length) return
        const first = items[0]
        const last = items[items.length - 1]
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault()
            last.focus()
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault()
            first.focus()
        }
    })

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-start-scheduled-workout]')
        if (!button || button.dataset.startScheduledBusy === '1') return
        const id = button.dataset.startScheduledWorkout || ''
        if (!id || !window.StrideBRWorkout?.startScheduled) return
        button.dataset.startScheduledBusy = '1'
        button.disabled = true
        try { await window.StrideBRWorkout.startScheduled(id) } catch (_) {} finally {
            delete button.dataset.startScheduledBusy
            button.disabled = false
        }
    })

    setupPrescription()
})()
