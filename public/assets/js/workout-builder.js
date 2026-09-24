(() => {
    const roots = [...document.querySelectorAll('[data-workout-builder]')]
    if (!roots.length) return
    const t = (key, params = {}, fallback = key) => window.StrideBRI18n?.t?.(key, params) || fallback
    const q = (root, selector) => root.querySelector(selector)
    const qa = (root, selector) => [...root.querySelectorAll(selector)]
    const text = value => String(value ?? '').trim()
    const numeric = value => {
        const parsed = Number(text(value).replace(',', '.'))
        return Number.isFinite(parsed) ? parsed : null
    }
    const randomKey = () => `grp_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`
    const replaceRowIndex = (name, index) => String(name || '').replace(/rows\[[^\]]+\]/, `rows[${index}]`)
    const replaceStageIndex = (name, index) => String(name || '').replace(/\[stages\]\[[^\]]+\]/, `[stages][${index}]`)
    const replaceClusterIndex = (name, index) => String(name || '').replace(/\[clusters\]\[[^\]]+\]/, `[clusters][${index}]`)

    roots.forEach(root => {
        const form = root.closest('form') || root
        const list = q(root, '[data-wb-list]')
        const cardTemplate = q(root, '[data-wb-card-template]')
        const picker = q(root, '[data-wb-picker]')
        const pickerSearch = picker ? q(picker, '[data-wb-picker-search]') : null
        const pickerResults = picker ? q(picker, '[data-wb-picker-results]') : null
        const live = q(root, '[data-wb-live]')
        const dirtyLabels = qa(root, '[data-wb-dirty]')
        const empty = q(root, '[data-wb-empty]')
        const selectionBar = q(root, '[data-wb-selection]')
        const selectedCount = selectionBar ? q(selectionBar, '[data-wb-selected-count]') : null
        let nextIndex = qa(root, '[data-wb-card]').length
        let pickerController = null
        let pickerTimer = null
        let dragged = null
        let dirty = false

        const announce = message => {
            if (!live) return
            live.textContent = ''
            window.requestAnimationFrame(() => { live.textContent = message })
        }
        const setDirty = value => {
            dirty = value
            root.dataset.dirty = value ? '1' : '0'
            dirtyLabels.forEach(label => { label.hidden = !value })
        }
        const topUnits = () => [...list.children].filter(node => node.matches?.('[data-wb-card], [data-wb-group]'))
        const flattenCards = () => {
            const cards = []
            topUnits().forEach(unit => {
                if (unit.matches('[data-wb-card]')) cards.push(unit)
                else cards.push(...qa(unit, ':scope > [data-wb-group-members] > [data-wb-card]'))
            })
            return cards
        }
        const cardName = card => text(q(card, '[data-exercise-name]')?.value || q(card, '[data-wb-name-output]')?.textContent || t('home.exercise', {}, 'Exercício'))
        const cardContainer = card => card.closest('[data-wb-group-members]') || list
        const cardUnit = card => card.closest('[data-wb-group]') || card

        const reindexCluster = card => {
            qa(card, '[data-wb-cluster-piece]').forEach((piece, index) => {
                qa(piece, '[name]').forEach(input => { input.name = replaceClusterIndex(input.name, index) })
            })
        }
        const reindexDrop = card => {
            qa(card, '[data-wb-drop-stage]').forEach((stage, index) => {
                q(stage, 'strong')?.replaceChildren(document.createTextNode(t('workout_builder.drop_stage', {number: index + 1}, `Etapa ${index + 1}`)))
                qa(stage, '[name]').forEach(input => { input.name = replaceStageIndex(input.name, index) })
            })
        }
        const reindex = () => {
            flattenCards().forEach((card, index) => {
                qa(card, '[name]').forEach(input => { input.name = replaceRowIndex(input.name, index) })
                const order = q(card, '[data-wb-order]')
                if (order) order.textContent = String(index + 1)
                reindexCluster(card)
                reindexDrop(card)
            })
            if (empty) empty.hidden = flattenCards().length > 0
            updateSelection()
        }
        const groupMembers = group => qa(group, ':scope > [data-wb-group-members] > [data-wb-card]')
        const syncGroup = group => {
            if (!group) return
            const key = text(q(group, '[data-wb-group-key]')?.value || group.dataset.groupKey)
            const type = text(q(group, '[data-wb-group-type]')?.value || 'superset')
            const fields = {}
            qa(group, '[data-wb-group-setting]').forEach(input => { fields[input.dataset.wbGroupSetting] = input.value })
            groupMembers(group).forEach(card => {
                const values = {grupo_chave: key, grupo_tipo: type, ...fields}
                Object.entries(values).forEach(([name, value]) => {
                    const input = q(card, `[data-wb-group-field="${name}"]`)
                    if (input) input.value = value
                })
                const selector = q(card, '[data-wb-select-group]')
                if (selector) selector.disabled = true
            })
            const title = q(group, '[data-wb-group-title]')
            if (title) title.textContent = t(`workout_builder.group.${type}`, {}, type === 'circuit' ? 'Circuito' : 'Superset')
            const rounds = numeric(fields.grupo_voltas) || 1
            group.setAttribute('aria-label', `${title?.textContent || ''}, ${groupMembers(group).length} exercícios, ${rounds} voltas`)
        }
        const clearGroupFields = card => {
            qa(card, '[data-wb-group-field]').forEach(input => { input.value = '' })
            const selector = q(card, '[data-wb-select-group]')
            if (selector) selector.disabled = false
        }
        const dissolveGroup = group => {
            const members = groupMembers(group)
            members.forEach(clearGroupFields)
            group.replaceWith(...members)
            reindex()
            setDirty(true)
        }
        const normalizeGroups = () => {
            qa(root, '[data-wb-group]').forEach(group => {
                const members = groupMembers(group)
                if (members.length < 2) dissolveGroup(group)
                else syncGroup(group)
            })
        }

        const repTarget = (scope, prefix = '') => {
            const mode = text(q(scope, `${prefix}[data-wb-rep-mode]`)?.value || q(scope, '[data-wb-rep-mode]')?.value || 'fixed')
            const panel = selector => q(scope, selector)
            const findValue = suffix => numeric(panel(`input[name$="${suffix}"]`)?.value)
            if (mode === 'range') return {mode, min: findValue('[min]'), max: findValue('[max]')}
            if (mode === 'amrap' || mode === 'failure') return {mode}
            return {mode: 'fixed', value: findValue('[value]')}
        }
        const standardConfig = card => {
            const panel = q(card, '[data-wb-method-panel="standard"]')
            const load = numeric(q(panel, 'input[name*="[load][value]"]')?.value)
            const durationValue = numeric(q(panel, 'input[name*="[duration][value]"]')?.value)
            const durationUnit = text(q(panel, 'select[name*="[duration][unit]"]')?.value || 's')
            const distanceValue = numeric(q(panel, '[data-wb-distance-value]')?.value)
            const distanceUnit = text(q(panel, '[data-wb-distance-unit]')?.value || 'm')
            const config = {
                method: 'standard',
                sets: numeric(q(panel, 'input[name*="[sets]"]')?.value),
                reps: repTarget(panel),
                rest_after_s: numeric(q(panel, 'input[name*="[rest_after_s]"]')?.value),
            }
            if (load !== null) config.load = {value: load, unit: 'kg'}
            if (durationValue !== null) config.duration_s = Math.round(durationValue * (durationUnit === 'h' ? 3600 : durationUnit === 'min' ? 60 : 1))
            if (distanceValue !== null) {
                config.distance_m = distanceUnit === 'km' ? distanceValue * 1000 : distanceValue
                config.distance_display_unit = distanceUnit
            }
            return config
        }
        const clusterConfig = card => {
            const panel = q(card, '[data-wb-method-panel="cluster"]')
            const load = numeric(q(panel, 'input[name*="[load][value]"]')?.value)
            const config = {
                method: 'cluster',
                blocks: numeric(q(panel, 'input[name*="[blocks]"]')?.value),
                clusters: qa(panel, '[data-wb-cluster-piece] input[type="number"]').map(input => ({reps: numeric(input.value)})),
                intra_cluster_rest_s: numeric(q(panel, 'input[name*="[intra_cluster_rest_s]"]')?.value),
                between_blocks_rest_s: numeric(q(panel, 'input[name*="[between_blocks_rest_s]"]')?.value),
            }
            if (load !== null) config.load = {value: load, unit: 'kg'}
            return config
        }
        const dropConfig = card => {
            const panel = q(card, '[data-wb-method-panel="drop_set"]')
            return {
                method: 'drop_set',
                rounds: numeric(q(panel, 'input[name*="[rounds]"]')?.value),
                stages: qa(panel, '[data-wb-drop-stage]').map(stage => {
                    const load = numeric(q(stage, 'input[name*="[load][value]"]')?.value)
                    const result = {
                        reps: repTarget(stage),
                        rest_after_s: numeric(q(stage, 'input[name*="[rest_after_s]"]')?.value),
                    }
                    if (load !== null) result.load = {value: load, unit: 'kg'}
                    return result
                }),
                round_rest_s: numeric(q(panel, 'input[name*="[round_rest_s]"]')?.value),
            }
        }
        const updateSummary = card => {
            const step = text(q(card, '[data-wb-step-type]')?.value || card.dataset.stepType || 'exercise')
            const method = text(q(card, '[data-wb-method]')?.value || 'standard')
            const methodOut = q(card, '[data-wb-method-output]')
            const summaryOut = q(card, '[data-wb-summary]')
            const nameOut = q(card, '[data-wb-name-output]')
            if (nameOut) nameOut.textContent = cardName(card)
            if (step !== 'exercise') {
                if (methodOut) methodOut.textContent = t(`workout_builder.step.${step}`, {}, step)
                const duration = text(q(card, 'input[name$="[duracao]"]')?.value)
                const distance = text(q(card, 'input[name$="[distancia]"]')?.value)
                const intensity = text(q(card, 'input[name$="[intensidade]"]')?.value)
                const repeat = text(q(card, 'input[name$="[repeticoes_bloco]"]')?.value)
                if (summaryOut) summaryOut.textContent = [repeat ? `${repeat}×` : '', duration, distance, intensity].filter(Boolean).join(' · ')
                return
            }
            if (methodOut) methodOut.textContent = t(`workout_builder.method.${method}`, {}, method)
            const config = method === 'cluster' ? clusterConfig(card) : method === 'drop_set' ? dropConfig(card) : standardConfig(card)
            if (summaryOut) summaryOut.textContent = window.StrideBRWorkoutPrescription?.structuredSummary?.(method, config) || ''
        }
        const updateRepPanels = scope => {
            qa(scope, '[data-wb-rep-mode]').forEach(select => {
                const label = select.closest('[data-wb-rep-target], .wb-rep-target') || select.parentElement
                if (!label) return
                const mode = select.value
                qa(label, '[data-wb-rep-panel]').forEach(panel => { panel.hidden = panel.dataset.wbRepPanel !== mode })
            })
        }
        const updateMethod = card => {
            const method = text(q(card, '[data-wb-method]')?.value || 'standard')
            card.dataset.method = method
            qa(card, '[data-wb-method-panel]').forEach(panel => {
                const active = panel.dataset.wbMethodPanel === method
                panel.hidden = !active
                qa(panel, 'input,select,textarea,button').forEach(control => { control.disabled = !active })
            })
            updateRepPanels(card)
            updateSummary(card)
        }
        const updateTracking = card => {
            const mode = text(q(card, '[data-wb-tracking-input]')?.value || card.dataset.trackingMode || 'load_reps')
            card.dataset.trackingMode = mode
            const label = q(card, '[data-wb-tracking-label]')
            const labels = {
                load_reps: `${t('common.load', {}, 'Carga')} + ${t('common.repetitions', {}, 'Repetições')}`,
                reps: t('common.repetitions', {}, 'Repetições'),
                duration: t('common.duration', {}, 'Duração'),
                distance: t('common.distance', {}, 'Distância'),
                duration_distance: `${t('common.duration', {}, 'Duração')} + ${t('common.distance', {}, 'Distância')}`,
            }
            if (label) label.textContent = labels[mode] || labels.load_reps
        }
        const updateStep = card => {
            const select = q(card, '[data-wb-step-type]')
            const step = text(select?.value || 'exercise')
            card.dataset.stepType = step
            const exercise = q(card, '[data-wb-exercise-prescription]')
            const structured = q(card, '[data-wb-structured-step]')
            if (exercise) exercise.hidden = step !== 'exercise'
            if (structured) structured.hidden = step === 'exercise'
            const name = q(card, '[data-exercise-name]')
            if (name) name.required = step === 'exercise'
            updateSummary(card)
        }
        const updateDistanceStep = card => {
            const select = q(card, '[data-wb-distance-unit]')
            const input = q(card, '[data-wb-distance-value]')
            if (!select || !input) return
            input.step = select.value === 'm' ? '0.01' : '0.001'
        }

        const wireCard = card => {
            if (card.dataset.wbBound) return
            card.dataset.wbBound = '1'
            const toggle = q(card, '[data-wb-toggle]')
            toggle?.addEventListener('click', () => {
                const editor = q(card, '[data-wb-editor]')
                const open = editor?.hidden !== false
                qa(root, '[data-wb-card]').forEach(other => {
                    if (other === card) return
                    const otherEditor = q(other, '[data-wb-editor]')
                    const otherToggle = q(other, '[data-wb-toggle]')
                    if (otherEditor) otherEditor.hidden = true
                    otherToggle?.setAttribute('aria-expanded', 'false')
                })
                if (editor) editor.hidden = !open
                toggle.setAttribute('aria-expanded', String(open))
            })
            q(card, '[data-wb-method]')?.addEventListener('change', () => { updateMethod(card); setDirty(true) })
            q(card, '[data-wb-step-type]')?.addEventListener('change', () => { updateStep(card); setDirty(true) })
            q(card, '[data-wb-distance-unit]')?.addEventListener('change', event => {
                const select = event.currentTarget
                const input = q(card, '[data-wb-distance-value]')
                if (input) {
                    const oldUnit = select.dataset.previousUnit || (select.value === 'm' ? 'km' : 'm')
                    const value = numeric(input.value)
                    if (value !== null && oldUnit !== select.value) input.value = String(select.value === 'm' ? value * 1000 : value / 1000)
                    select.dataset.previousUnit = select.value
                    updateDistanceStep(card)
                }
                updateSummary(card)
                setDirty(true)
            })
            const distanceUnit = q(card, '[data-wb-distance-unit]')
            if (distanceUnit) distanceUnit.dataset.previousUnit = distanceUnit.value
            card.addEventListener('change', event => {
                if (event.target.matches('[data-wb-rep-mode]')) updateRepPanels(event.target.closest('[data-wb-drop-stage], [data-wb-method-panel]') || card)
                updateSummary(card)
                setDirty(true)
            })
            card.addEventListener('input', () => { updateSummary(card); setDirty(true) })
            card.addEventListener('stridebr:exercise-selected', event => {
                const item = event.detail?.item || {}
                const tracking = text(item.tipo_registro || item.tracking_mode || 'load_reps')
                const trackingInput = q(card, '[data-wb-tracking-input]')
                if (trackingInput) trackingInput.value = tracking
                card.dataset.trackingMode = tracking
                updateTracking(card)
                updateSummary(card)
                setDirty(true)
            })
            q(card, '[data-wb-remove]')?.addEventListener('click', () => removeCard(card))
            q(card, '[data-wb-ungroup]')?.addEventListener('click', () => ungroupCard(card))
            qa(card, '[data-wb-move]').forEach(button => button.addEventListener('click', () => {
                moveCard(card, button.dataset.wbMove)
                button.closest('.wb-card-menu')?.removeAttribute('open')
                q(card, '[data-wb-toggle]')?.focus()
            }))
            q(card, '[data-wb-add-cluster-piece]')?.addEventListener('click', () => addClusterPiece(card))
            card.addEventListener('click', event => {
                if (event.target.closest('[data-wb-remove-cluster-piece]')) removeClusterPiece(card, event.target.closest('[data-wb-cluster-piece]'))
                if (event.target.closest('[data-wb-add-drop-stage]')) addDropStage(card)
                if (event.target.closest('[data-wb-remove-drop-stage]')) removeDropStage(card, event.target.closest('[data-wb-drop-stage]'))
            })
            const handle = q(card, '[data-wb-drag]')
            if (handle) {
                handle.addEventListener('pointerdown', () => { card.draggable = true })
                handle.addEventListener('pointerup', () => { card.draggable = false })
                handle.addEventListener('keydown', event => {
                    if (event.altKey && event.key === 'ArrowUp') { event.preventDefault(); moveCard(card, 'up'); handle.focus() }
                    if (event.altKey && event.key === 'ArrowDown') { event.preventDefault(); moveCard(card, 'down'); handle.focus() }
                })
            }
            card.addEventListener('dragstart', event => {
                if (!card.draggable) { event.preventDefault(); return }
                dragged = card
                card.classList.add('is-dragging')
                event.dataTransfer.effectAllowed = 'move'
            })
            card.addEventListener('dragend', () => {
                card.classList.remove('is-dragging')
                card.draggable = false
                dragged = null
                qa(root, '.is-drop-target').forEach(node => node.classList.remove('is-drop-target'))
                normalizeGroups()
                reindex()
            })
            updateTracking(card)
            updateStep(card)
            updateMethod(card)
            updateDistanceStep(card)
        }

        const removeCard = card => {
            const parent = card.parentElement
            const next = card.nextElementSibling
            const group = card.closest('[data-wb-group]')
            const groupParent = group?.parentElement || null
            const groupNext = group?.nextElementSibling || null
            const groupCards = group ? groupMembers(group) : []
            card.remove()
            normalizeGroups()
            reindex()
            setDirty(true)
            const undo = () => {
                if (group && !group.isConnected && groupParent) {
                    const members = q(group, '[data-wb-group-members]')
                    groupCards.forEach(member => members?.appendChild(member))
                    if (groupNext?.isConnected && groupNext.parentElement === groupParent) groupParent.insertBefore(group, groupNext)
                    else groupParent.appendChild(group)
                    syncGroup(group)
                } else if (next?.isConnected && next.parentElement === parent) parent.insertBefore(card, next)
                else parent.appendChild(card)
                wireCard(card)
                if (group?.isConnected) syncGroup(group)
                reindex()
                setDirty(true)
                q(card, '[data-wb-toggle]')?.focus()
            }
            if (window.StrideBRUI?.undo) window.StrideBRUI.undo(t('workout_builder.removed', {}, 'Exercício removido.'), async () => undo())
            else announce(t('workout_builder.removed', {}, 'Exercício removido.'))
        }
        const moveCard = (card, direction) => {
            const container = cardContainer(card)
            const siblings = [...container.children].filter(node => node.matches?.('[data-wb-card]'))
            const index = siblings.indexOf(card)
            const targetIndex = direction === 'up' ? index - 1 : index + 1
            if (targetIndex < 0 || targetIndex >= siblings.length) return
            const target = siblings[targetIndex]
            if (direction === 'up') container.insertBefore(card, target)
            else container.insertBefore(target, card)
            reindex()
            setDirty(true)
            const position = flattenCards().indexOf(card) + 1
            announce(t('workout_builder.reorder_announcement', {name: cardName(card), position}, `${cardName(card)} movido para a posição ${position}.`))
        }
        const ungroupCard = card => {
            const group = card.closest('[data-wb-group]')
            if (!group) return
            clearGroupFields(card)
            group.parentElement.insertBefore(card, group.nextSibling)
            normalizeGroups()
            reindex()
            setDirty(true)
            q(card, '[data-wb-toggle]')?.focus()
        }
        const addClusterPiece = card => {
            const container = q(card, '[data-wb-cluster-pieces]')
            const last = container?.lastElementChild
            if (!container || !last || container.children.length >= 12) return
            const clone = last.cloneNode(true)
            const input = q(clone, 'input')
            if (input) input.value = input.value || '2'
            container.appendChild(clone)
            reindexCluster(card)
            setDirty(true)
            input?.focus()
            updateSummary(card)
        }
        const removeClusterPiece = (card, piece) => {
            const container = piece?.parentElement
            if (!container || container.children.length <= 2) return
            piece.remove()
            reindexCluster(card)
            setDirty(true)
            updateSummary(card)
        }
        const addDropStage = card => {
            const container = q(card, '[data-wb-drop-stages]')
            const last = container?.lastElementChild
            if (!container || !last || container.children.length >= 10) return
            const clone = last.cloneNode(true)
            qa(clone, 'input').forEach(input => {
                if (input.type === 'hidden') return
                input.value = input.name.includes('[rest_after_s]') ? '0' : ''
            })
            qa(clone, 'select').forEach(select => { if (select.matches('[data-wb-rep-mode]')) select.value = 'fixed' })
            container.appendChild(clone)
            reindexDrop(card)
            updateRepPanels(clone)
            setDirty(true)
            q(clone, 'input:not([type="hidden"])')?.focus()
            updateSummary(card)
        }
        const removeDropStage = (card, stage) => {
            const container = stage?.parentElement
            if (!container || container.children.length <= 2) return
            stage.remove()
            reindexDrop(card)
            setDirty(true)
            updateSummary(card)
        }

        const cardFromTemplate = () => {
            if (!cardTemplate) return null
            const wrapper = document.createElement('div')
            wrapper.innerHTML = cardTemplate.innerHTML.replaceAll('__INDEX__', String(nextIndex++)).trim()
            const card = wrapper.firstElementChild
            if (!card) return null
            list.appendChild(card)
            wireCard(card)
            reindex()
            setDirty(true)
            return card
        }
        const addExercise = item => {
            const card = cardFromTemplate()
            if (!card) return
            const name = q(card, '[data-exercise-name]')
            const id = q(card, '[data-exercise-id]')
            const tracking = q(card, '[data-wb-tracking-input]')
            if (name) name.value = text(item.nome)
            if (id) id.value = text(item.idexercicio)
            if (tracking) tracking.value = text(item.tipo_registro || item.tracking_mode || 'load_reps')
            card.dataset.trackingMode = tracking?.value || 'load_reps'
            updateTracking(card)
            updateSummary(card)
            const editor = q(card, '[data-wb-editor]')
            const toggle = q(card, '[data-wb-toggle]')
            if (editor) editor.hidden = false
            toggle?.setAttribute('aria-expanded', 'true')
            name?.dispatchEvent(new CustomEvent('stridebr:exercise-selected', {bubbles: true, detail: {item, source: 'picker'}}))
            name?.focus()
        }
        const addStep = step => {
            const card = cardFromTemplate()
            if (!card) return
            const select = q(card, '[data-wb-step-type]')
            const name = q(card, '[data-exercise-name]')
            if (select) select.value = step
            if (name) name.value = t(`workout_builder.step.${step}`, {}, step)
            updateStep(card)
            const editor = q(card, '[data-wb-editor]')
            const toggle = q(card, '[data-wb-toggle]')
            if (editor) editor.hidden = false
            toggle?.setAttribute('aria-expanded', 'true')
            q(card, '[data-wb-structured-step] input')?.focus()
        }

        const cardLogicalRounds = card => {
            const method = String(q(card, '[data-wb-method]')?.value || card.dataset.method || 'standard')
            const selector = method === 'cluster' ? '[name*="[prescription][blocks]"]' : method === 'drop_set' ? '[name*="[prescription][rounds]"]' : '[name*="[prescription][sets]"]'
            const value = Number(q(card, selector)?.value || 1)
            return Number.isFinite(value) && value > 0 ? Math.round(value) : 1
        }
        const createGroupFromCards = (type, selected, settings = {}, key = randomKey(), markDirty = true) => {
            selected = selected.filter(Boolean)
            if (selected.length < 2 || selected.some(card => card.closest('[data-wb-group]'))) return null
            if (markDirty) {
                const rounds = [...new Set(selected.map(cardLogicalRounds))]
                if (rounds.length !== 1) {
                    announce(t('workout_builder.group_round_mismatch', {}, 'Os exercícios precisam ter o mesmo número de séries/blocos/rodadas para formar um grupo.'))
                    return null
                }
                if (settings.grupo_voltas === undefined && settings.rounds === undefined) settings = {...settings, grupo_voltas: rounds[0]}
            }
            const first = selected[0]
            const group = document.createElement('section')
            group.className = 'workout-builder-group'
            group.dataset.wbGroup = ''
            group.dataset.groupKey = key
            group.innerHTML = `<div class="wb-group-head"><button type="button" class="wb-drag-handle" data-wb-group-drag aria-label="${t('workout_builder.group', {}, 'Agrupar')}">≡</button><div><strong data-wb-group-title></strong><span data-wb-group-summary></span></div><div class="wb-group-controls"><label><span>${t('workout_builder.group_rounds', {}, 'Voltas')}</span><input type="number" min="1" max="99" value="1" data-wb-group-setting="grupo_voltas"></label><label><span>${t('workout_builder.group_rest_between', {}, 'Descanso entre exercícios')}</span><input type="number" min="0" max="86400" value="" data-wb-group-setting="grupo_descanso_entre_exercicios_s"></label><label><span>${t('workout_builder.group_rest_round', {}, 'Descanso após volta')}</span><input type="number" min="0" max="86400" value="" data-wb-group-setting="grupo_descanso_pos_volta_s"></label></div><div class="wb-group-actions"><button type="button" data-wb-group-move="up" aria-label="${t('workout_builder.move_up', {}, 'Mover para cima')}">↑</button><button type="button" data-wb-group-move="down" aria-label="${t('workout_builder.move_down', {}, 'Mover para baixo')}">↓</button><button type="button" data-wb-dissolve-group>${t('workout_builder.ungroup', {}, 'Remover do grupo')}</button></div><input type="hidden" value="${key}" data-wb-group-key><input type="hidden" value="${type}" data-wb-group-type></div><div class="wb-group-members" data-wb-group-members></div>`
            list.insertBefore(group, first)
            const members = q(group, '[data-wb-group-members]')
            selected.forEach(card => {
                const selector = q(card, '[data-wb-select-group]')
                if (selector) selector.checked = false
                members.appendChild(card)
            })
            const settingMap = {
                grupo_voltas: settings.grupo_voltas ?? settings.rounds ?? 1,
                grupo_descanso_entre_exercicios_s: settings.grupo_descanso_entre_exercicios_s ?? settings.rest_between_exercises_s ?? '',
                grupo_descanso_pos_volta_s: settings.grupo_descanso_pos_volta_s ?? settings.rest_after_round_s ?? '',
            }
            qa(group, '[data-wb-group-setting]').forEach(input => {
                if (Object.prototype.hasOwnProperty.call(settingMap, input.dataset.wbGroupSetting)) input.value = String(settingMap[input.dataset.wbGroupSetting] ?? '')
            })
            wireGroup(group)
            syncGroup(group)
            reindex()
            if (markDirty) setDirty(true)
            return group
        }
        const createGroup = type => {
            const units = topUnits()
            const selected = qa(root, '[data-wb-card] [data-wb-select-group]:checked').map(input => input.closest('[data-wb-card]')).filter(Boolean).sort((a, b) => units.indexOf(a) - units.indexOf(b))
            const group = createGroupFromCards(type, selected)
            if (group) q(group, '[data-wb-group-setting]')?.focus()
        }
        const moveGroup = (group, direction) => {
            const units = topUnits()
            const index = units.indexOf(group)
            const targetIndex = direction === 'up' ? index - 1 : index + 1
            if (targetIndex < 0 || targetIndex >= units.length) return
            const target = units[targetIndex]
            if (direction === 'up') list.insertBefore(group, target)
            else list.insertBefore(target, group)
            reindex()
            setDirty(true)
            q(group, `[data-wb-group-move="${direction}"]`)?.focus()
        }
        const wireGroup = group => {
            if (group.dataset.wbBound) return
            group.dataset.wbBound = '1'
            qa(group, '[data-wb-group-setting]').forEach(input => input.addEventListener('input', () => { syncGroup(group); setDirty(true) }))
            qa(group, '[data-wb-group-move]').forEach(button => button.addEventListener('click', () => moveGroup(group, button.dataset.wbGroupMove)))
            q(group, '[data-wb-dissolve-group]')?.addEventListener('click', () => dissolveGroup(group))
            const handle = q(group, '[data-wb-group-drag]')
            if (handle) {
                handle.addEventListener('pointerdown', () => { group.draggable = true })
                handle.addEventListener('pointerup', () => { group.draggable = false })
            }
            group.addEventListener('dragstart', event => {
                if (event.target !== group || !group.draggable) return
                dragged = group
                group.classList.add('is-dragging')
                event.dataTransfer.effectAllowed = 'move'
            })
            group.addEventListener('dragend', () => {
                group.classList.remove('is-dragging')
                group.draggable = false
                dragged = null
                reindex()
            })
            groupMembers(group).forEach(wireCard)
            syncGroup(group)
        }

        const dropTarget = (container, event) => {
            if (!dragged) return
            const isCard = dragged.matches('[data-wb-card]')
            const validContainer = isCard ? cardContainer(dragged) : list
            if (container !== validContainer) return
            event.preventDefault()
            const nodes = [...container.children].filter(node => isCard ? node.matches?.('[data-wb-card]') : node.matches?.('[data-wb-card], [data-wb-group]'))
            const after = nodes.find(node => {
                if (node === dragged) return false
                const box = node.getBoundingClientRect()
                return event.clientY < box.top + box.height / 2
            })
            if (after) container.insertBefore(dragged, after)
            else container.appendChild(dragged)
            setDirty(true)
            reindex()
        }
        list.addEventListener('dragover', event => dropTarget(list, event))
        qa(root, '[data-wb-group-members]').forEach(container => container.addEventListener('dragover', event => dropTarget(container, event)))
        root.addEventListener('dragover', event => {
            const container = event.target.closest('[data-wb-group-members]')
            if (container) dropTarget(container, event)
        })

        const updateSelection = () => {
            const selected = qa(root, '[data-wb-select-group]:checked').filter(input => !input.disabled)
            if (selectionBar) selectionBar.hidden = selected.length < 2
            if (selectedCount) selectedCount.textContent = t('workout_builder.selected_count', {count: selected.length}, `${selected.length} selecionados`)
        }
        root.addEventListener('change', event => { if (event.target.matches('[data-wb-select-group]')) updateSelection() })
        qa(root, '[data-wb-create-group]').forEach(button => button.addEventListener('click', () => createGroup(button.dataset.wbCreateGroup)))

        const openPicker = () => {
            if (!picker) return
            if (typeof picker.showModal === 'function') picker.showModal()
            else picker.hidden = false
            if (pickerSearch) {
                pickerSearch.value = ''
                pickerSearch.focus()
                searchPicker('')
            }
        }
        const closePicker = () => {
            if (!picker) return
            if (typeof picker.close === 'function') picker.close()
            else picker.hidden = true
        }
        const renderPicker = items => {
            if (!pickerResults) return
            pickerResults.replaceChildren()
            items.forEach(item => {
                const button = document.createElement('button')
                button.type = 'button'
                button.className = 'wb-picker-result'
                const title = document.createElement('strong')
                title.textContent = item.nome
                const meta = document.createElement('span')
                meta.textContent = [item.equipamento, item.tipo_registro].filter(Boolean).join(' · ')
                button.append(title, meta)
                button.addEventListener('click', () => addExercise(item))
                pickerResults.append(button)
            })
            if (!items.length) {
                const emptyResult = document.createElement('p')
                emptyResult.className = 'wb-picker-empty'
                emptyResult.textContent = t('workout_builder.empty_search', {}, 'Nenhum exercício encontrado.')
                pickerResults.append(emptyResult)
            }
        }
        const searchPicker = query => {
            clearTimeout(pickerTimer)
            pickerController?.abort()
            pickerTimer = setTimeout(async () => {
                pickerController = new AbortController()
                try {
                    const response = await fetch(`/api/exercicio-resolver.php?name=${encodeURIComponent(query)}`, {credentials: 'same-origin', signal: pickerController.signal})
                    const data = await response.json()
                    if (!response.ok || !data.ok) return
                    renderPicker(Array.isArray(data.options) ? data.options : [])
                } catch (error) {
                    if (error.name !== 'AbortError') renderPicker([])
                }
            }, query ? 140 : 0)
        }
        pickerSearch?.addEventListener('input', () => searchPicker(pickerSearch.value.trim()))
        pickerSearch?.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                const first = q(pickerResults, 'button')
                if (first) { event.preventDefault(); first.focus() }
            }
        })
        pickerResults?.addEventListener('keydown', event => {
            if (!['ArrowDown','ArrowUp'].includes(event.key)) return
            const buttons = qa(pickerResults, 'button')
            const index = buttons.indexOf(document.activeElement)
            if (index < 0) return
            event.preventDefault()
            buttons[(index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length]?.focus()
        })
        qa(root, '[data-wb-add-exercise]').forEach(button => button.addEventListener('click', openPicker))
        qa(root, '[data-wb-add-step]').forEach(button => button.addEventListener('click', () => addStep(button.dataset.wbAddStep)))
        q(root, '[data-wb-picker-close]')?.addEventListener('click', closePicker)
        picker?.addEventListener('click', event => { if (event.target === picker) closePicker() })

        const formRows = () => {
            normalizeGroups()
            reindex()
            const rows = []
            const setDeep = (target, path, value) => {
                let current = target
                path.forEach((key, index) => {
                    const last = index === path.length - 1
                    if (last) { current[key] = value; return }
                    const nextIsIndex = /^\d+$/.test(path[index + 1] || '')
                    if (current[key] === undefined || current[key] === null) current[key] = nextIsIndex ? [] : {}
                    current = current[key]
                })
            }
            for (const [name, value] of new FormData(form).entries()) {
                const match = /^rows\[(\d+)\](.*)$/.exec(name)
                if (!match) continue
                const index = Number(match[1])
                if (!rows[index]) rows[index] = {}
                const path = [...match[2].matchAll(/\[([^\]]+)\]/g)].map(item => item[1])
                if (path.length) setDeep(rows[index], path, value)
            }
            return rows.filter(Boolean)
        }
        const canonicalToFormPrescription = source => {
            if (!source || typeof source !== 'object') return {}
            const config = structuredClone(source)
            if (config.duration_s !== undefined && config.duration === undefined) {
                const seconds = Number(config.duration_s)
                if (Number.isFinite(seconds)) config.duration = {value: seconds % 60 === 0 ? seconds / 60 : seconds, unit: seconds % 60 === 0 ? 'min' : 's'}
            }
            if (config.distance_m !== undefined && config.distance === undefined) {
                const unit = config.distance_display_unit === 'km' ? 'km' : 'm'
                const meters = Number(config.distance_m)
                if (Number.isFinite(meters)) config.distance = {value: unit === 'km' ? meters / 1000 : meters, unit}
            }
            return config
        }
        const deepValue = (source, path) => path.reduce((value, key) => value !== undefined && value !== null ? value[key] : undefined, source)
        const rowForForm = raw => {
            const row = {...(raw || {})}
            if (!row.nome && row.nome_snapshot) row.nome = row.nome_snapshot
            if (!row.prescription && row.config_prescricao) {
                try { row.prescription = JSON.parse(row.config_prescricao) } catch (_) {}
            }
            if (row.prescription) row.prescription = canonicalToFormPrescription(row.prescription)
            return row
        }
        const fillCard = (card, raw) => {
            const row = rowForForm(raw)
            const method = text(row.prescription?.method || row.metodo_prescricao || row.prescription_method || 'standard')
            const step = text(row.tipo_passo || row.step_type || 'exercise')
            const tracking = text(row.tracking_mode || row.tipo_registro || 'load_reps')
            const methodSelect = q(card, '[data-wb-method]')
            const stepSelect = q(card, '[data-wb-step-type]')
            if (methodSelect) methodSelect.value = method
            if (stepSelect) stepSelect.value = step
            const trackingInput = q(card, '[data-wb-tracking-input]')
            if (trackingInput) trackingInput.value = tracking
            card.dataset.trackingMode = tracking
            card.dataset.method = method
            card.dataset.stepType = step
            updateStep(card)
            updateMethod(card)
            updateTracking(card)
            if (method === 'cluster') {
                const desired = Array.isArray(row.prescription?.clusters) ? row.prescription.clusters.length : 2
                let pieces = qa(card, '[data-wb-cluster-piece]')
                while (pieces.length < desired) { addClusterPiece(card); pieces = qa(card, '[data-wb-cluster-piece]') }
                while (pieces.length > desired && pieces.length > 2) { pieces.at(-1)?.remove(); pieces = qa(card, '[data-wb-cluster-piece]') }
                reindexCluster(card)
            }
            if (method === 'drop_set') {
                const desired = Array.isArray(row.prescription?.stages) ? row.prescription.stages.length : 2
                let stages = qa(card, '[data-wb-drop-stage]')
                while (stages.length < desired) { addDropStage(card); stages = qa(card, '[data-wb-drop-stage]') }
                while (stages.length > desired && stages.length > 2) { stages.at(-1)?.remove(); stages = qa(card, '[data-wb-drop-stage]') }
                reindexDrop(card)
            }
            qa(card, '[name]').forEach(input => {
                const path = [...input.name.matchAll(/\[([^\]]+)\]/g)].map(item => item[1]).slice(1)
                const value = deepValue(row, path)
                if (value === undefined || value === null || typeof value === 'object') return
                if (input.type === 'checkbox') input.checked = ['1','true',true].includes(value)
                else input.value = String(value)
            })
            updateStep(card)
            updateMethod(card)
            updateTracking(card)
            updateSummary(card)
            return card
        }
        const hydrateRows = rows => {
            topUnits().forEach(unit => unit.remove())
            nextIndex = 0
            const groupMembersByKey = new Map()
            ;(Array.isArray(rows) ? rows : []).forEach(raw => {
                const card = cardFromTemplate()
                if (!card) return
                fillCard(card, raw)
                const key = text(raw?.grupo_chave || raw?.group?.key || '')
                if (key) {
                    if (!groupMembersByKey.has(key)) groupMembersByKey.set(key, {cards: [], raw})
                    groupMembersByKey.get(key).cards.push(card)
                }
            })
            for (const [key, entry] of groupMembersByKey.entries()) {
                if (entry.cards.length < 2) continue
                const raw = entry.raw || {}
                createGroupFromCards(text(raw.grupo_tipo || raw.group?.type || 'superset'), entry.cards, {
                    grupo_voltas: raw.grupo_voltas ?? raw.group?.rounds,
                    grupo_descanso_entre_exercicios_s: raw.grupo_descanso_entre_exercicios_s ?? raw.group?.rest_between_exercises_s,
                    grupo_descanso_pos_volta_s: raw.grupo_descanso_pos_volta_s ?? raw.group?.rest_after_round_s,
                }, key, false)
            }
            normalizeGroups()
            reindex()
            setDirty(false)
        }
        root.StrideBRWorkoutBuilder = {serialize: formRows, hydrate: hydrateRows, setDirty}
        root.dispatchEvent(new CustomEvent('stridebr:workout-builder-ready', {bubbles: true, detail: {builder: root.StrideBRWorkoutBuilder}}))
        form.addEventListener('submit', () => {
            normalizeGroups()
            reindex()
            setDirty(false)
        })
        form.addEventListener('reset', () => setDirty(false))
        qa(root, '[data-wb-card]').forEach(wireCard)
        qa(root, '[data-wb-group]').forEach(wireGroup)
        reindex()
        setDirty(false)
    })
})()
