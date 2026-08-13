document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-activity-form]')
    const openButton = document.querySelector('[data-toggle-activity-form]')
    const closeButton = document.querySelector('[data-close-activity-form]')
    const modalitySelect = document.getElementById('modalidade')
    const modelSelect = document.getElementById('modelo')

    if (shell && openButton) {
        openButton.addEventListener('click', () => shell.classList.add('is-open'))
    }
    if (shell && closeButton) {
        closeButton.addEventListener('click', () => shell.classList.remove('is-open'))
    }

    const setPanelState = (panel, active) => {
        panel.hidden = !active
        panel.querySelectorAll('input, select, textarea, button').forEach((element) => {
            if (element.matches('[data-add-unit], [data-remove-unit]')) return
            element.disabled = !active
        })
    }

    const updateModelPanels = () => {
        if (!modelSelect) return
        document.querySelectorAll('[data-model-panel]').forEach((panel) => {
            setPanelState(panel, panel.dataset.modelPanel === modelSelect.value)
        })
    }

    const updateModelsForModality = () => {
        if (!modalitySelect || !modelSelect) return
        const modality = modalitySelect.value
        const options = Array.from(modelSelect.options)
        let first = null

        options.forEach((option) => {
            const matches = option.dataset.modalidade === modality
            option.hidden = !matches
            option.disabled = !matches
            if (matches && first === null) first = option
        })

        if (!modelSelect.selectedOptions[0] || modelSelect.selectedOptions[0].disabled) {
            if (first) first.selected = true
        }
        updateModelPanels()
    }

    modalitySelect?.addEventListener('change', updateModelsForModality)
    modelSelect?.addEventListener('change', updateModelPanels)
    updateModelsForModality()
    updateModelPanels()

    const nextUnitIndex = (container) => {
        const indexes = Array.from(container.querySelectorAll('[data-unit-index]'))
            .map((unit) => Number(unit.dataset.unitIndex))
            .filter(Number.isFinite)
        return indexes.length ? Math.max(...indexes) + 1 : 0
    }

    const refreshRemoveButtons = (container) => {
        const units = container.querySelectorAll('[data-unit-index]')
        units.forEach((unit) => {
            const remove = unit.querySelector('[data-remove-unit]')
            if (remove) remove.hidden = units.length <= 1
        })
    }

    document.querySelectorAll('[data-add-unit]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.addUnit
            const template = document.querySelector(`template[data-unit-template="${CSS.escape(key)}"]`)
            const container = document.querySelector(`[data-units][data-model="${CSS.escape(key)}"]`)
            if (!template || !container) return

            const index = nextUnitIndex(container)
            const number = container.querySelectorAll('[data-unit-index]').length + 1
            const html = template.innerHTML
                .replaceAll('__INDEX__', String(index))
                .replaceAll('__NUMBER__', String(number))
            container.insertAdjacentHTML('beforeend', html)
            refreshRemoveButtons(container)
        })
    })

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-unit]')
        if (!button) return
        const unit = button.closest('[data-unit-index]')
        const container = button.closest('[data-units]')
        if (!unit || !container || container.querySelectorAll('[data-unit-index]').length <= 1) return
        unit.remove()
        refreshRemoveButtons(container)
    })

    document.querySelectorAll('[data-units]').forEach(refreshRemoveButtons)
})
