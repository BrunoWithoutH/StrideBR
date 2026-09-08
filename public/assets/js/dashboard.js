document.addEventListener('DOMContentLoaded', () => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const localDate = value => window.StrideBRI18n?.date?.(value, {year:'numeric', month:'short', day:'numeric'}) || String(value || '')
    const dialog = document.querySelector('[data-goal-dialog]');
    const openButtons = document.querySelectorAll('[data-goal-open]');
    const closeButtons = document.querySelectorAll('[data-goal-close]');
    const metric = document.querySelector('[data-goal-metric]');
    const period = document.querySelector('[data-goal-period]');
    const customDates = document.querySelector('[data-goal-custom-dates]');
    const unit = document.querySelector('[data-goal-unit]');
    const target = dialog?.querySelector('input[name="valor_alvo"]');
    const sport = dialog?.querySelector('select[name="idmodalidade"]');
    const exerciseField = dialog?.querySelector('[data-goal-exercise-field]');
    const exercise = dialog?.querySelector('[data-goal-exercise]');
    const endDate = dialog?.querySelector('input[name="data_fim"]');
    const summary = dialog?.querySelector('[data-goal-summary]');
    let goalOptionsLoaded = false;
    let goalOptionsPromise = null;

    const units = {
        distancia: ['km', '20'],
        duracao: ['min', '180'],
        atividades: [t('goals.unit.activities', {}, 'activities'), '4'],
        elevacao: ['m', '500'],
        dias_ativos: [t('goals.unit.days', {}, 'days'), '4'],
        carga_maxima: ['kg', '200']
    };

    const syncSummary = () => {
        if (!summary) return;
        const metricLabel = metric?.selectedOptions?.[0]?.textContent?.trim() || t('goals.metric_fallback', {}, 'metric');
        const sportLabel = sport?.selectedOptions?.[0]?.textContent?.replace(/^★\s*/, '').trim() || t('goals.all_sports', {}, 'All sports');
        const exerciseLabel = exercise?.selectedOptions?.[0]?.textContent?.trim() || '';
        const value = target?.value?.trim() || target?.placeholder || '0';
        const unitLabel = unit?.textContent?.trim() || '';
        let deadline = t('goals.no_deadline', {}, 'no deadline');
        if (period?.value === 'personalizado') deadline = endDate?.value ? t('goals.until_date', {date: localDate(`${endDate.value}T12:00:00`)}, `until ${localDate(`${endDate.value}T12:00:00`)}`) : t('goals.until_chosen_date', {}, 'until the selected date');
        if (period?.value === 'semanal') deadline = t('goals.every_week', {}, 'every week');
        if (period?.value === 'mensal') deadline = t('goals.every_month', {}, 'every month');
        if (period?.value === 'anual') deadline = t('goals.every_year', {}, 'every year');
        const subject = metric?.value === 'carga_maxima' && exerciseLabel ? exerciseLabel : sportLabel;
        summary.textContent = t('goals.summary_compact', {metric: metricLabel, value, unit: unitLabel, subject, deadline}, `${metricLabel}: ${value} ${unitLabel} · ${subject} · ${deadline}`);
    };

    const syncMetric = () => {
        if (!metric || !unit || !target) return;
        const [label, placeholder] = units[metric.value] || units.distancia;
        unit.textContent = label;
        target.placeholder = placeholder;
        target.inputMode = ['atividades', 'dias_ativos'].includes(metric.value) ? 'numeric' : 'decimal';
        const isLoad = metric.value === 'carga_maxima';
        if (exerciseField) exerciseField.hidden = !isLoad;
        if (exercise) exercise.required = isLoad;
        syncSummary();
    };

    const loadGoalOptions = () => {
        if (goalOptionsLoaded || !dialog) return Promise.resolve();
        if (goalOptionsPromise) return goalOptionsPromise;
        goalOptionsPromise = (window.StrideBRNet?.fetch || fetch)('/api/dashboard-goal-options.php', {credentials: 'same-origin', headers: {'Accept': 'application/json'}}, 10000)
            .then(async response => {
                const result = await response.json().catch(() => null);
                if (!response.ok || !result?.ok) throw new Error(result?.error || t('goals.load_options_error', {}, 'Could not load the options.'));
                if (sport && !sport.closest('[data-generic-sport-picker]')) {
                    const current = sport.value;
                    sport.replaceChildren();
                    const all = document.createElement('option');
                    all.value = '';
                    all.textContent = t('goals.all_sports', {}, 'All sports');
                    sport.append(all);
                    (result.modalities || []).forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = `${item.favorite ? '★ ' : ''}${item.name}`;
                        sport.append(option);
                    });
                    sport.value = current;
                }
                if (exercise) {
                    const current = exercise.value;
                    exercise.replaceChildren();
                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = t('goals.choose_exercise', {}, 'Choose an exercise');
                    exercise.append(placeholder);
                    (result.exercises || []).forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        option.dataset.modalidades = item.modalities || '';
                        exercise.append(option);
                    });
                    exercise.value = current;
                }
                goalOptionsLoaded = true;
                syncSummary();
            })
            .catch(error => {
                if (summary) summary.textContent = error?.message || t('goals.load_options_error', {}, 'Could not load goal options.');
            })
            .finally(() => { goalOptionsPromise = null; });
        return goalOptionsPromise;
    };

    const syncPeriod = () => {
        if (!period || !customDates) return;
        const custom = period.value === 'personalizado';
        customDates.hidden = !custom;
        customDates.querySelectorAll('input').forEach(input => {
            input.required = custom;
        });
        if (custom) {
            const start = customDates.querySelector('input[name="data_inicio"]');
            if (start && !start.value) start.value = new Date().toISOString().slice(0, 10);
        }
        syncSummary();
    };

    openButtons.forEach(button => button.addEventListener('click', () => {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        loadGoalOptions();
    }));

    closeButtons.forEach(button => button.addEventListener('click', () => dialog?.close()));
    dialog?.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    metric?.addEventListener('change', syncMetric);
    period?.addEventListener('change', syncPeriod);
    sport?.addEventListener('change', syncSummary);
    exercise?.addEventListener('change', syncSummary);
    target?.addEventListener('input', syncSummary);
    endDate?.addEventListener('change', syncSummary);
    syncMetric();
    syncPeriod();
    syncSummary();
});

document.addEventListener('DOMContentLoaded', () => {
    const t = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback
    const root = document.querySelector('[data-dashboard-root]');
    const modules = document.querySelector('[data-dashboard-modules]');
    const customizeDialog = document.querySelector('[data-dashboard-customize-dialog]');
    const customizeList = customizeDialog?.querySelector('[data-dashboard-customize-list]');
    const saveButton = customizeDialog?.querySelector('[data-dashboard-customize-save]');
    const resetButton = customizeDialog?.querySelector('[data-dashboard-customize-reset]');
    const csrf = root?.dataset.dashboardCsrf || '';

    const openDialog = () => {
        if (!customizeDialog) return;
        if (typeof customizeDialog.showModal === 'function') customizeDialog.showModal();
        else customizeDialog.setAttribute('open', '');
    };
    const closeDialog = () => {
        if (!customizeDialog) return;
        if (typeof customizeDialog.close === 'function') customizeDialog.close();
        else customizeDialog.removeAttribute('open');
    };
    document.querySelectorAll('[data-dashboard-customize-open]').forEach(button => button.addEventListener('click', openDialog));
    customizeDialog?.querySelectorAll('[data-dashboard-customize-close]').forEach(button => button.addEventListener('click', closeDialog));
    customizeDialog?.addEventListener('click', event => {
        if (event.target === customizeDialog) closeDialog();
    });

    const refreshMoveButtons = () => {
        if (!customizeList) return;
        const items = Array.from(customizeList.querySelectorAll('[data-dashboard-customize-item]'));
        items.forEach((item, index) => {
            const up = item.querySelector('[data-dashboard-move="up"]');
            const down = item.querySelector('[data-dashboard-move="down"]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === items.length - 1;
        });
    };

    customizeList?.addEventListener('click', event => {
        const button = event.target.closest('[data-dashboard-move]');
        if (!button) return;
        const item = button.closest('[data-dashboard-customize-item]');
        if (!item) return;
        if (button.dataset.dashboardMove === 'up' && item.previousElementSibling) {
            item.parentElement.insertBefore(item, item.previousElementSibling);
        }
        if (button.dataset.dashboardMove === 'down' && item.nextElementSibling) {
            item.parentElement.insertBefore(item.nextElementSibling, item);
        }
        refreshMoveButtons();
    });

    resetButton?.addEventListener('click', () => {
        if (!customizeList) return;
        const defaults = ['progress', 'goals', 'upcoming', 'recent'];
        defaults.forEach(id => {
            const item = customizeList.querySelector(`[data-dashboard-customize-item="${id}"]`);
            if (!item) return;
            item.querySelector('[data-dashboard-module-visible]').checked = true;
            customizeList.append(item);
        });
        refreshMoveButtons();
    });

    const applyPreferences = (order, hidden) => {
        if (!modules) return;
        order.forEach(id => {
            const module = modules.querySelector(`[data-dashboard-module="${id}"]`);
            if (!module) return;
            module.hidden = hidden.includes(id);
            modules.append(module);
        });
    };

    saveButton?.addEventListener('click', async () => {
        if (!customizeList || !csrf) return;
        const items = Array.from(customizeList.querySelectorAll('[data-dashboard-customize-item]'));
        const order = items.map(item => item.dataset.dashboardCustomizeItem).filter(Boolean);
        const hidden = items.filter(item => !item.querySelector('[data-dashboard-module-visible]')?.checked).map(item => item.dataset.dashboardCustomizeItem).filter(Boolean);
        const body = new FormData();
        body.set('csrf_token', csrf);
        body.set('order', JSON.stringify(order));
        body.set('hidden', JSON.stringify(hidden));
        saveButton.disabled = true;
        saveButton.setAttribute('aria-busy', 'true');
        try {
            const request = window.StrideBRNet?.fetch || fetch;
            const response = await request('/api/dashboard-preferences.php', {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}}, 10000);
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) throw new Error(result?.error || t('goals.save_dashboard_error', {}, 'Could not save the dashboard.'));
            applyPreferences(result.preferences?.order || order, result.preferences?.hidden || hidden);
            closeDialog();
            window.StrideBRUI?.notify?.(t('goals.dashboard_updated', {}, 'Dashboard updated.'), 'success', 2600);
        } catch (error) {
            window.StrideBRUI?.notify?.(error?.message || t('goals.save_dashboard_error', {}, 'Could not save the dashboard.'), 'error');
        } finally {
            saveButton.disabled = false;
            saveButton.removeAttribute('aria-busy');
        }
    });

    refreshMoveButtons();

    const startButton = document.querySelector('[data-dashboard-start-workout]');
    startButton?.addEventListener('click', async () => {
        if (!window.StrideBRWorkout) return;
        startButton.disabled = true;
        startButton.setAttribute('aria-busy', 'true');
        try {
            await window.StrideBRWorkout.start(startButton.dataset.dashboardStartWorkout || '', {
                data_ocorrencia_origem: startButton.dataset.occurrenceOrigin || '',
                data_ocorrencia_planejada: startButton.dataset.occurrencePlanned || '',
                hora_ocorrencia_planejada: startButton.dataset.occurrenceTime || ''
            });
        } catch (_) {
        } finally {
            startButton.disabled = false;
            startButton.removeAttribute('aria-busy');
        }
    });

    const scheduledButton = document.querySelector('[data-dashboard-start-scheduled]');
    scheduledButton?.addEventListener('click', async () => {
        if (!window.StrideBRWorkout) return;
        scheduledButton.disabled = true;
        scheduledButton.setAttribute('aria-busy', 'true');
        try {
            await window.StrideBRWorkout.startScheduled(scheduledButton.dataset.dashboardStartScheduled || '');
        } catch (_) {
        } finally {
            scheduledButton.disabled = false;
            scheduledButton.removeAttribute('aria-busy');
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const popover = document.querySelector('[data-dashboard-week-popover]')
    const triggers = Array.from(document.querySelectorAll('[data-week-popover-trigger]'))
    if (!(popover instanceof HTMLElement) || triggers.length === 0) return
    if (popover.parentElement !== document.body) document.body.append(popover)

    let activeTrigger = null
    let closeTimer = 0

    const isTouchContext = () => window.matchMedia('(hover: none), (pointer: coarse)').matches
    const clearCloseTimer = () => {
        if (!closeTimer) return
        window.clearTimeout(closeTimer)
        closeTimer = 0
    }
    const position = () => {
        if (!(activeTrigger instanceof HTMLElement) || popover.hidden) return
        const margin = 8
        const gap = 8
        const target = activeTrigger.getBoundingClientRect()
        const box = popover.getBoundingClientRect()
        const viewportWidth = document.documentElement.clientWidth
        const viewportHeight = window.visualViewport?.height || document.documentElement.clientHeight
        let left = target.left + (target.width / 2) - (box.width / 2)
        left = Math.max(margin, Math.min(left, viewportWidth - box.width - margin))
        let top = target.bottom + gap
        if (top + box.height > viewportHeight - margin) top = target.top - box.height - gap
        if (top < margin) top = margin
        popover.style.left = `${Math.round(left)}px`
        popover.style.top = `${Math.round(top)}px`
        popover.style.visibility = 'visible'
    }
    const close = () => {
        clearCloseTimer()
        if (activeTrigger instanceof HTMLElement) activeTrigger.setAttribute('aria-expanded', 'false')
        activeTrigger = null
        popover.hidden = true
        popover.replaceChildren()
        popover.style.removeProperty('left')
        popover.style.removeProperty('top')
        popover.style.removeProperty('visibility')
        popover.removeAttribute('aria-label')
    }
    const scheduleClose = source => {
        clearCloseTimer()
        closeTimer = window.setTimeout(() => {
            if (source instanceof HTMLElement && activeTrigger !== source) return
            if (popover.matches(':hover') || popover.contains(document.activeElement)) return
            close()
        }, 100)
    }
    const open = trigger => {
        const templateId = trigger.dataset.weekPopoverTemplate || ''
        const template = templateId ? document.getElementById(templateId) : null
        if (!(template instanceof HTMLTemplateElement)) return
        clearCloseTimer()
        if (activeTrigger instanceof HTMLElement && activeTrigger !== trigger) activeTrigger.setAttribute('aria-expanded', 'false')
        activeTrigger = trigger
        popover.replaceChildren(template.content.cloneNode(true))
        popover.setAttribute('aria-label', trigger.getAttribute('aria-label') || '')
        popover.style.visibility = 'hidden'
        popover.hidden = false
        trigger.setAttribute('aria-expanded', 'true')
        requestAnimationFrame(position)
    }

    triggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', () => {
            if (!isTouchContext()) open(trigger)
        })
        trigger.addEventListener('mouseleave', () => {
            if (!isTouchContext()) scheduleClose(trigger)
        })
        trigger.addEventListener('focus', () => open(trigger))
        trigger.addEventListener('blur', () => scheduleClose(trigger))
        trigger.addEventListener('click', event => {
            const isLink = trigger instanceof HTMLAnchorElement
            if (isLink && !isTouchContext()) return
            event.preventDefault()
            open(trigger)
        })
    })

    popover.addEventListener('mouseenter', clearCloseTimer)
    popover.addEventListener('mouseleave', () => scheduleClose(activeTrigger))
    popover.addEventListener('focusin', clearCloseTimer)
    popover.addEventListener('focusout', () => scheduleClose(activeTrigger))
    document.addEventListener('pointerdown', event => {
        if (popover.hidden) return
        if (popover.contains(event.target) || activeTrigger?.contains?.(event.target)) return
        close()
    })
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !popover.hidden) {
            const returnTarget = activeTrigger
            returnTarget?.focus?.({preventScroll: true})
            close()
        }
    })
    window.addEventListener('resize', position)
    window.addEventListener('scroll', position, {passive: true, capture: true})
})
