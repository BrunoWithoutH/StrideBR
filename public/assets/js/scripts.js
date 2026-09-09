const stridebrCommonT = (key, values = {}, fallback = key) => window.StrideBRI18n?.t?.(key, values, fallback) ?? fallback;

(() => {
    const requestKey = () => {
        const bytes = new Uint8Array(16)
        crypto.getRandomValues(bytes)
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
    }
    const ensureIdempotency = options => {
        if (String(options?.method || 'GET').toUpperCase() !== 'POST') return options
        const body = options?.body
        if (body instanceof FormData || body instanceof URLSearchParams) {
            if (!body.has('_idempotency_key')) body.set('_idempotency_key', requestKey())
        }
        return options
    }
    const request = async (resource, options = {}, timeoutMs = 12000) => {
        ensureIdempotency(options)
        const controller = new AbortController()
        const parentSignal = options.signal
        let timedOut = false
        const abortFromParent = () => controller.abort()
        if (parentSignal?.aborted) throw new DOMException(stridebrCommonT('common.operation_cancelled', {}, 'Operation cancelled.'), 'AbortError')
        parentSignal?.addEventListener('abort', abortFromParent, {once: true})
        const timer = window.setTimeout(() => {
            timedOut = true
            controller.abort()
        }, timeoutMs)
        try {
            return await fetch(resource, {...options, signal: controller.signal})
        } catch (error) {
            if (timedOut) {
                const timeoutError = new Error(stridebrCommonT('common.response_timeout', {}, 'The response took too long. Try again.'))
                timeoutError.name = 'TimeoutError'
                throw timeoutError
            }
            if (error?.name === 'AbortError') throw error
            if (!navigator.onLine) {
                const offlineError = new Error(stridebrCommonT('common.offline_retry', {}, 'You are offline. Try again when the connection returns.'))
                offlineError.name = 'OfflineError'
                throw offlineError
            }
            if (error instanceof TypeError) {
                const networkError = new Error(stridebrCommonT('common.network_error', {}, 'Could not reach StrideBR.'))
                networkError.name = 'NetworkError'
                throw networkError
            }
            throw error
        } finally {
            window.clearTimeout(timer)
            parentSignal?.removeEventListener('abort', abortFromParent)
        }
    }
    window.StrideBRNet = {fetch: request, requestKey, ensureIdempotency}
})();

(() => {
    const sameOriginReferrer = () => {
        const raw = String(document.referrer || '').trim()
        if (!raw) return null
        try {
            const url = new URL(raw, window.location.href)
            if (url.origin !== window.location.origin) return null
            if (url.href === window.location.href) return null
            return url
        } catch (_) {
            return null
        }
    }

    const safeBack = (element, event = null) => {
        if (!(element instanceof HTMLElement)) return false
        if (event && element instanceof HTMLAnchorElement && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0)) return false
        const fallback = element instanceof HTMLAnchorElement ? element.href : String(element.dataset.safeBackFallback || '').trim()
        const referrer = sameOriginReferrer()
        if (referrer && window.history.length > 1) {
            event?.preventDefault()
            window.history.back()
            return true
        }
        if (!(element instanceof HTMLAnchorElement) && fallback) {
            event?.preventDefault()
            window.location.assign(fallback)
            return true
        }
        return false
    }

    const init = (scope = document) => {
        scope.querySelectorAll('[data-safe-back]').forEach(element => {
            if (!(element instanceof HTMLElement) || element.dataset.safeBackBound === '1') return
            element.dataset.safeBackBound = '1'
            element.addEventListener('click', event => safeBack(element, event))
        })
    }

    window.StrideBRSafeBack = {init, navigate: safeBack}
    document.addEventListener('DOMContentLoaded', () => init(document))
})();

(() => {
    const toastHost = () => {
        const existing = document.querySelector('[data-ui-toast-host]')
        if (existing) return existing
        const host = document.createElement('div')
        host.className = 'ui-toast-host'
        host.dataset.uiToastHost = ''
        host.setAttribute('aria-live', 'polite')
        host.setAttribute('aria-relevant', 'additions removals')
        document.body.appendChild(host)
        return host
    }

    const closeToast = toast => {
        if (!(toast instanceof HTMLElement) || toast.dataset.closing === '1') return
        toast.dataset.closing = '1'
        toast.classList.add('is-leaving')
        window.setTimeout(() => toast.remove(), 180)
    }

    const createToast = ({message, type = 'info', timeout = 4200, actionLabel = '', onAction = null, undo = false} = {}) => {
        const text = String(message || '').trim()
        if (!text) return {close: () => {}, element: null}
        const normalizedType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info'
        const toast = document.createElement('div')
        toast.className = `ui-toast${undo ? ' is-undo' : ''}`
        toast.dataset.type = normalizedType
        toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status')

        const mark = document.createElement('span')
        mark.className = 'ui-toast-mark'
        mark.setAttribute('aria-hidden', 'true')

        const copy = document.createElement('div')
        copy.className = 'ui-toast-copy'
        const messageNode = document.createElement('span')
        messageNode.className = 'ui-toast-message'
        messageNode.textContent = text
        copy.appendChild(messageNode)

        const actions = document.createElement('div')
        actions.className = 'ui-toast-actions'
        let actionButton = null
        if (actionLabel && typeof onAction === 'function') {
            actionButton = document.createElement('button')
            actionButton.type = 'button'
            actionButton.className = 'ui-toast-action'
            actionButton.textContent = actionLabel
            actions.appendChild(actionButton)
        }
        const close = document.createElement('button')
        close.type = 'button'
        close.className = 'ui-toast-close'
        close.setAttribute('aria-label', stridebrCommonT('common.close_notice', {}, 'Close notification'))
        close.textContent = '×'
        actions.appendChild(close)

        toast.append(mark, copy, actions)
        close.addEventListener('click', () => closeToast(toast))

        let timer = 0
        let remaining = Math.max(0, Number(timeout) || 0)
        let startedAt = 0
        const clearTimer = () => {
            if (timer) window.clearTimeout(timer)
            timer = 0
        }
        const startTimer = () => {
            if (remaining <= 0 || toast.dataset.closing === '1') return
            startedAt = performance.now()
            clearTimer()
            timer = window.setTimeout(() => closeToast(toast), remaining)
        }
        const pauseTimer = () => {
            if (!timer) return
            remaining = Math.max(0, remaining - (performance.now() - startedAt))
            clearTimer()
        }
        toast.addEventListener('mouseenter', pauseTimer)
        toast.addEventListener('mouseleave', startTimer)
        toast.addEventListener('focusin', pauseTimer)
        toast.addEventListener('focusout', event => {
            if (!toast.contains(event.relatedTarget)) startTimer()
        })

        if (actionButton) {
            actionButton.addEventListener('click', async () => {
                if (actionButton.disabled) return
                pauseTimer()
                actionButton.disabled = true
                const original = actionButton.textContent
                actionButton.textContent = undo ? stridebrCommonT('common.undoing', {}, 'Undoing…') : stridebrCommonT('common.please_wait', {}, 'Please wait…')
                try {
                    await onAction()
                    closeToast(toast)
                } catch (error) {
                    actionButton.disabled = false
                    actionButton.textContent = original
                    startTimer()
                    createToast({message: error?.message || stridebrCommonT('common.action_failed', {}, 'Could not complete the action.'), type: 'error', timeout: 6000})
                }
            })
        }

        toastHost().appendChild(toast)
        startTimer()
        return {close: () => { clearTimer(); closeToast(toast) }, element: toast}
    }

    const notify = (message, type = 'info', timeout = 4200) => {
        if (typeof timeout === 'object' && timeout !== null) {
            return createToast({message, type, ...timeout})
        }
        return createToast({message, type, timeout})
    }

    const confirmAction = (message, options = {}) => new Promise(resolve => {
        const supportsDialog = typeof HTMLDialogElement !== 'undefined'
        const overlay = document.createElement(supportsDialog ? 'dialog' : 'div')
        overlay.className = 'ui-confirm-overlay'
        overlay.innerHTML = `<button type="button" class="ui-confirm-backdrop" data-ui-confirm-cancel></button><div class="ui-confirm-dialog" role="document"><h2></h2><div class="ui-confirm-copy"></div><div class="ui-confirm-actions"><button type="button" class="ui-confirm-cancel" data-ui-confirm-cancel></button><button type="button" class="ui-confirm-ok"></button></div></div>`
        overlay.querySelector('.ui-confirm-backdrop')?.setAttribute('aria-label', stridebrCommonT('common.cancel', {}, 'Cancel'))
        overlay.querySelector('.ui-confirm-cancel').textContent = stridebrCommonT('common.cancel', {}, 'Cancel')
        overlay.querySelector('.ui-confirm-ok').textContent = stridebrCommonT('common.confirm', {}, 'Confirm')
        overlay.querySelector('h2').textContent = options.title || stridebrCommonT('common.confirm_action', {}, 'Confirm action')
        const copy = overlay.querySelector('.ui-confirm-copy')
        const blocks = Array.isArray(options.messageBlocks) && options.messageBlocks.length ? options.messageBlocks : [String(message || stridebrCommonT('common.confirm_prompt', {}, 'Confirm this action?'))]
        blocks.forEach(block => { const paragraph = document.createElement('p'); paragraph.textContent = String(block || ''); copy.appendChild(paragraph) })
        const ok = overlay.querySelector('.ui-confirm-ok')
        ok.textContent = options.confirmLabel || stridebrCommonT('common.confirm', {}, 'Confirm')
        ok.classList.toggle('is-danger', Boolean(options.danger))
        let settled = false
        const finish = value => {
            if (settled) return
            settled = true
            if (supportsDialog && overlay.open) overlay.close()
            overlay.remove()
            resolve(value)
        }
        overlay.querySelectorAll('[data-ui-confirm-cancel]').forEach(button => button.addEventListener('click', () => finish(false)))
        ok.addEventListener('click', () => finish(true))
        overlay.addEventListener('cancel', event => { event.preventDefault(); finish(false) })
        overlay.addEventListener('keydown', event => { if (event.key === 'Escape' && !supportsDialog) finish(false) })
        document.body.appendChild(overlay)
        if (supportsDialog && typeof overlay.showModal === 'function') overlay.showModal()
        ok.focus()
    })

    const undo = (message, action, timeout = 8000) => {
        document.querySelectorAll('.ui-toast.is-undo').forEach(closeToast)
        return createToast({message, type: 'info', timeout, actionLabel: stridebrCommonT('common.undo', {}, 'Undo'), onAction: action, undo: true})
    }

    window.StrideBRUI = {notify, toast: notify, confirm: confirmAction, undo}
})()


document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const menu = document.querySelector('[data-nav-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    const headerMenus = document.querySelectorAll('[data-header-menu]');
    const hoverMenus = document.querySelectorAll('[data-header-menu="hover-toggle"]');
    const toggleMenus = document.querySelectorAll('[data-header-menu="toggle"]');
    const hoverCloseTimers = new WeakMap();
    const clearHoverClose = details => {
        const timer = hoverCloseTimers.get(details);
        if (timer) window.clearTimeout(timer);
        hoverCloseTimers.delete(details);
    };
    const setDetailsOpen = (details, open) => {
        if (!(details instanceof HTMLDetailsElement)) return;
        if (open) details.setAttribute('open', '');
        else details.removeAttribute('open');
        details.querySelector(':scope > summary')?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    const closeDetailsMenu = details => {
        if (!(details instanceof HTMLDetailsElement)) return;
        clearHoverClose(details);
        setDetailsOpen(details, false);
        delete details.dataset.pinnedOpen;
    };
    const closeOtherMenus = current => {
        headerMenus.forEach(details => {
            if (details !== current) closeDetailsMenu(details);
        });
    };
    const bindToggleMenu = details => {
        if (!(details instanceof HTMLDetailsElement)) return;
        const summary = details.querySelector(':scope > summary');
        summary?.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        summary?.addEventListener('click', event => {
            event.preventDefault();
            const shouldOpen = !details.open;
            closeOtherMenus(details);
            setDetailsOpen(details, shouldOpen);
        });
        details.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !details.open) return;
            closeDetailsMenu(details);
            summary?.focus();
        });
    };
    const bindHoverToggleMenu = details => {
        if (!(details instanceof HTMLDetailsElement)) return;
        const summary = details.querySelector(':scope > summary');
        summary?.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        summary?.addEventListener('click', event => {
            event.preventDefault();
            clearHoverClose(details);
            if (details.dataset.pinnedOpen === '1') {
                closeDetailsMenu(details);
                return;
            }
            closeOtherMenus(details);
            details.dataset.pinnedOpen = '1';
            setDetailsOpen(details, true);
        });
        details.addEventListener('mouseenter', () => {
            clearHoverClose(details);
            closeOtherMenus(details);
            setDetailsOpen(details, true);
        });
        details.addEventListener('mouseleave', () => {
            if (details.dataset.pinnedOpen === '1') return;
            clearHoverClose(details);
            hoverCloseTimers.set(details, window.setTimeout(() => {
                if (details.dataset.pinnedOpen !== '1') setDetailsOpen(details, false);
                hoverCloseTimers.delete(details);
            }, 140));
        });
        details.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !details.open) return;
            closeDetailsMenu(details);
            summary?.focus();
        });
    };
    hoverMenus.forEach(bindHoverToggleMenu);
    toggleMenus.forEach(bindToggleMenu);

    const userMenu = document.querySelector('.user-menu');
    const globalCreateMenu = document.querySelector('.global-create-menu');

    document.addEventListener('click', event => {
        headerMenus.forEach(details => {
            if (details.open && !details.contains(event.target)) closeDetailsMenu(details);
        });
    });

    const moreToggle = document.querySelector('[data-mobile-more-toggle]');
    const moreSheet = document.querySelector('[data-mobile-more-sheet]');
    const closeMore = () => {
        if (!moreSheet) return;
        moreSheet.hidden = true;
        moreToggle?.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('mobile-sheet-open');
    };
    moreToggle?.addEventListener('click', () => {
        if (!moreSheet) return;
        const next = moreSheet.hidden;
        moreSheet.hidden = !next;
        moreToggle.setAttribute('aria-expanded', next ? 'true' : 'false');
        document.documentElement.classList.toggle('mobile-sheet-open', next);
    });
    document.querySelectorAll('[data-mobile-more-close]').forEach(button => button.addEventListener('click', closeMore));
    moreSheet?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMore));
    moreSheet?.querySelector('[data-quick-tools-open]')?.addEventListener('click', closeMore);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeMore();
            globalCreateMenu?.removeAttribute('open');
        }
    });

    document.addEventListener('submit', async event => {
        const form = event.target.closest?.('form[data-confirm]');
        if (!form) return;
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        const message = form.dataset.confirm || stridebrCommonT('common.confirm_prompt', {}, 'Confirm this action?');
        const confirmed = await window.StrideBRUI.confirm(message, {danger: /apagar|excluir|encerrar|remover/i.test(message)});
        if (!confirmed) return;
        form.dataset.confirmed = '1';
        form.requestSubmit(event.submitter || undefined);
    });

    const loggedInUi = Boolean(document.querySelector('.mobile-bottom-nav'));
    document.querySelectorAll('.alert').forEach(alert => {
        const isError = alert.classList.contains('alert-danger') || alert.classList.contains('alert-error') || alert.classList.contains('error');
        const type = alert.classList.contains('alert-success') ? 'success' : alert.classList.contains('alert-warning') ? 'warning' : 'info';
        if (loggedInUi && !isError) {
            window.StrideBRUI?.notify(alert.textContent || '', type);
            alert.remove();
            return;
        }
        if (isError) return;
        window.setTimeout(() => {
            alert.classList.add('is-leaving');
            window.setTimeout(() => alert.remove(), 220);
        }, 5000);
    });

    document.querySelectorAll('[data-auto-submit]').forEach(field => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });

    document.querySelectorAll('[data-phone-mask]').forEach(input => {
        const format = () => {
            const raw = String(input.value || '').trim();
            if (raw.startsWith('+')) return;
            const digits = raw.replace(/\D/g, '').slice(0, 11);
            if (digits.length <= 2) { input.value = digits; return; }
            const area = digits.slice(0, 2);
            const rest = digits.slice(2);
            if (rest.length <= 4) { input.value = `(${area}) ${rest}`; return; }
            const split = rest.length > 8 ? 5 : 4;
            input.value = `(${area}) ${rest.slice(0, split)}-${rest.slice(split)}`;
        };
        input.addEventListener('input', format);
        input.addEventListener('blur', format);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-avatar-input]');
    const preview = document.querySelector('[data-avatar-preview]');
    const status = document.querySelector('[data-avatar-status]');
    if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLImageElement)) return;

    const say = message => {
        if (status instanceof HTMLElement) status.textContent = message;
    };

    const previewFile = file => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result !== 'string') {
                reject(new Error(stridebrCommonT('common.preview_unavailable', {}, 'preview unavailable')));
                return;
            }
            preview.onload = () => resolve();
            preview.onerror = () => reject(new Error(stridebrCommonT('common.preview_format_unavailable', {}, 'preview unavailable in this browser')));
            preview.src = reader.result;
        };
        reader.onerror = () => reject(new Error(stridebrCommonT('common.image_read_error', {}, 'could not read image')));
        reader.readAsDataURL(file);
    });

    const optimizeFile = async file => {
        if (!('DataTransfer' in window) || !HTMLCanvasElement.prototype.toBlob || !('createImageBitmap' in window)) return null;
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const targetSize = 512;
        const crop = Math.min(bitmap.width, bitmap.height);
        const sourceX = Math.max(0, Math.floor((bitmap.width - crop) / 2));
        const sourceY = Math.max(0, Math.floor((bitmap.height - crop) / 2));
        const canvas = document.createElement('canvas');
        canvas.width = targetSize;
        canvas.height = targetSize;
        const context = canvas.getContext('2d', { alpha: true });
        if (!context) throw new Error(stridebrCommonT('common.canvas_unavailable', {}, 'canvas unavailable'));
        context.drawImage(bitmap, sourceX, sourceY, crop, crop, 0, 0, targetSize, targetSize);
        bitmap.close?.();
        const blob = await new Promise((resolve, reject) => {
            canvas.toBlob(value => value ? resolve(value) : reject(new Error('falha ao converter')), 'image/webp', 0.82);
        });
        return new File([blob], `${file.name.replace(/\.[^.]+$/, '') || 'avatar'}.webp`, { type: 'image/webp', lastModified: Date.now() });
    };

    const putInInput = file => {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    };

    input.addEventListener('change', async () => {
        const original = input.files && input.files[0];
        if (!original) {
            say(stridebrCommonT('common.image_requirements', {}, 'JPG, PNG or WebP · up to 4 MB.'));
            return;
        }
        if (!original.type.startsWith('image/')) {
            say(stridebrCommonT('common.invalid_image', {}, 'Choose a valid image.'));
            return;
        }
        say(stridebrCommonT('common.image_selected', {file: original.name}, `Selected: ${original.name}`));
        try {
            await previewFile(original);
        } catch (_) {
            say(stridebrCommonT('common.image_preview_error', {}, 'Could not generate image preview.'));
        }
        try {
            const optimized = await optimizeFile(original);
            if (!optimized) return;
            putInInput(optimized);
            await previewFile(optimized);
            say(stridebrCommonT('common.image_ready', {size: Math.max(1, Math.round(optimized.size / 1024))}, `Ready to upload · ${Math.max(1, Math.round(optimized.size / 1024))} KB`));
        } catch (_) {
            // Mantém o arquivo original; o backend fará a validação final.
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const localeTag = window.StrideBRI18n?.locale === 'en' ? 'en-US' : 'pt-BR'
    const catalog = document.querySelector('[data-settings-sports-catalog]');
    const search = document.querySelector('[data-settings-sport-search]');
    const count = document.querySelector('[data-sports-count]');
    const hint = document.querySelector('[data-sports-hint]');
    const familyGrid = catalog?.querySelector('[data-settings-sport-family-grid]');
    const panels = catalog ? [...catalog.querySelectorAll('[data-settings-sport-family-panel]')] : [];
    const empty = catalog?.querySelector('[data-settings-sport-empty]');
    if (!(catalog instanceof HTMLElement)) return;
    if (window.location.hash === '#esportes') catalog.closest('details')?.setAttribute('open', '');

    const cards = [...catalog.querySelectorAll('[data-settings-sport]')];

    const refreshCardState = card => {
        const practice = card.querySelector('[data-sport-practice]');
        const favorite = card.querySelector('[data-sport-favorite]');
        card.classList.toggle('is-practiced', practice instanceof HTMLInputElement && practice.checked);
        card.classList.toggle('is-favorite', favorite instanceof HTMLInputElement && favorite.checked);
    };

    const refreshCount = () => {
        if (!(count instanceof HTMLElement)) return;
        const practiced = cards.filter(card => card.querySelector('[data-sport-practice]')?.checked).length;
        const favorites = cards.filter(card => card.querySelector('[data-sport-favorite]')?.checked).length;
        count.textContent = stridebrCommonT('sport_picker.settings_count', {practiced, favorites}, `${practiced} practiced · ${favorites} favorites`);
    };

    const resetFamilies = () => {
        catalog.classList.remove('is-searching');
        if (familyGrid instanceof HTMLElement) familyGrid.hidden = false;
        panels.forEach(panel => panel.hidden = true);
        catalog.querySelectorAll('[data-settings-sport-more-list]').forEach(list => list.hidden = true);
        catalog.querySelectorAll('[data-settings-sport-more]').forEach(button => button.setAttribute('aria-expanded', 'false'));
        cards.forEach(card => card.hidden = false);
        if (empty instanceof HTMLElement) empty.hidden = true;
        if (hint instanceof HTMLElement) hint.textContent = stridebrCommonT('sport_picker.settings_hint', {}, 'Choose a category or search by sport name.');
    };

    catalog.querySelectorAll('[data-settings-sport-family-open]').forEach(button => button.addEventListener('click', () => {
        const key = button.dataset.settingsSportFamilyOpen || '';
        if (familyGrid instanceof HTMLElement) familyGrid.hidden = true;
        panels.forEach(panel => panel.hidden = panel.dataset.settingsSportFamilyPanel !== key);
        panels.find(panel => !panel.hidden)?.querySelector('button')?.focus();
        if (hint instanceof HTMLElement) hint.textContent = stridebrCommonT('sport_picker.settings_more_hint', {}, 'The most common sports appear first. Open “More sports” to see the rest.');
    }));

    catalog.querySelectorAll('[data-settings-sport-family-back]').forEach(button => button.addEventListener('click', resetFamilies));
    catalog.querySelectorAll('[data-settings-sport-more]').forEach(button => button.addEventListener('click', () => {
        const list = button.nextElementSibling;
        if (!(list instanceof HTMLElement)) return;
        const expanding = list.hidden;
        list.hidden = !expanding;
        button.setAttribute('aria-expanded', expanding ? 'true' : 'false');
    }));

    const filterCards = () => {
        const query = search instanceof HTMLInputElement ? search.value.trim().toLocaleLowerCase(localeTag) : '';
        if (query === '') {
            resetFamilies();
            return;
        }
        catalog.classList.add('is-searching');
        if (familyGrid instanceof HTMLElement) familyGrid.hidden = true;
        let visible = 0;
        panels.forEach(panel => {
            let panelVisible = 0;
            panel.querySelectorAll('[data-settings-sport-more-list]').forEach(list => list.hidden = false);
            panel.querySelectorAll('[data-settings-sport]').forEach(card => {
                const match = String(card.dataset.searchText || '').includes(query);
                card.hidden = !match;
                if (match) {
                    visible += 1;
                    panelVisible += 1;
                }
            });
            panel.hidden = panelVisible === 0;
        });
        if (empty instanceof HTMLElement) empty.hidden = visible !== 0;
        if (hint instanceof HTMLElement) hint.textContent = visible ? stridebrCommonT('sport_picker.search_results_all_categories', {}, 'Results across all categories.') : stridebrCommonT('sport_picker.no_results', {}, 'No sports found.');
    };

    cards.forEach(card => {
        card.querySelectorAll('input[type="checkbox"]').forEach(input => input.addEventListener('change', () => {
            refreshCardState(card);
            refreshCount();
        }));
        refreshCardState(card);
    });
    if (search instanceof HTMLInputElement) search.addEventListener('input', filterCards);
    refreshCount();
    resetFamilies();
});


document.addEventListener('DOMContentLoaded', () => {
    const localDateTime = (value, options) => window.StrideBRI18n?.date?.(value, options) || new Intl.DateTimeFormat(window.StrideBRI18n?.locale === 'en' ? 'en-US' : 'pt-BR', options).format(value)
    const prefix = 'stridebr:draft:';
    const ignoredNames = new Set(['csrf_token', 'feedback_form_token']);
    const serialize = (form) => {
        const values = {};
        form.querySelectorAll('[name]').forEach((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
            if (ignoredNames.has(field.name) || field instanceof HTMLInputElement && ['password', 'file', 'submit', 'button'].includes(field.type)) return;
            if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                if (!values[field.name]) values[field.name] = [];
                if (field.checked) values[field.name].push(field.value);
                return;
            }
            values[field.name] = field.value;
        });
        return values;
    };
    const apply = (form, values) => {
        form.dispatchEvent(new CustomEvent('stridebr:draft-before-restore', {detail: {values}}));
        Object.entries(values).forEach(([name, value]) => {
            const fields = Array.from(form.querySelectorAll('[name]')).filter((field) => field.name === name);
            fields.forEach((field) => {
                if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
                if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                    field.checked = Array.isArray(value) && value.includes(field.value);
                } else if (!Array.isArray(value)) {
                    field.value = String(value ?? '');
                }
                field.dispatchEvent(new Event('change', {bubbles: true}));
                field.dispatchEvent(new Event('input', {bubbles: true}));
            });
        });
        form.querySelectorAll('[data-duration-field]').forEach((field) => {
            const hidden = field.querySelector('[data-duration-value]');
            const match = String(hidden?.value || '').match(/^(\d+):([0-5]\d):([0-5]\d)(?:\.(\d{1,3}))?$/);
            if (!match) return;
            const hours = field.querySelector('[data-duration-hours]');
            const minutes = field.querySelector('[data-duration-minutes]');
            const seconds = field.querySelector('[data-duration-seconds]');
            const milliseconds = field.querySelector('[data-duration-milliseconds]');
            if (hours) hours.value = String(Number(match[1]));
            if (minutes) minutes.value = match[2];
            if (seconds) seconds.value = match[3];
            if (milliseconds) milliseconds.value = match[4] ? match[4].padEnd(3, '0') : '';
        });
        const clock = form.querySelector('[data-clock-value]');
        const clockMatch = String(clock?.value || '').match(/^([0-2]\d):([0-5]\d)$/);
        if (clockMatch) {
            const hours = form.querySelector('[data-clock-hours]');
            const minutes = form.querySelector('[data-clock-minutes]');
            if (hours) hours.value = clockMatch[1];
            if (minutes) minutes.value = clockMatch[2];
        }
        form.dispatchEvent(new CustomEvent('stridebr:draft-restored', {detail: {values}}));
    };
    document.querySelectorAll('form[data-draft-key]').forEach((form) => {
        const key = prefix + String(form.dataset.draftKey || '');
        if (key === prefix) return;
        const isActivityDraft = String(form.dataset.draftKey || '') === 'activity-new';
        let changed = false;
        let timer = 0;
        let recoveryNotice = null;
        const status = document.createElement('small');
        status.className = 'form-draft-status';
        status.textContent = stridebrCommonT('draft.saved_device_only', {}, 'Draft saved only on this device.');
        status.hidden = true;
        if (!isActivityDraft) form.appendChild(status);
        const save = () => {
            if (!changed) return;
            try {
                localStorage.setItem(key, JSON.stringify({savedAt: Date.now(), values: serialize(form)}));
                if (!isActivityDraft) {
                    status.hidden = false;
                    status.textContent = stridebrCommonT('draft.saved_device_at', {time: localDateTime(new Date(), {hour:'2-digit', minute:'2-digit'})}, 'Draft saved on this device · {time}');
                }
            } catch (_) {
                status.hidden = true;
            }
        };
        const scheduleSave = () => {
            changed = true;
            window.clearTimeout(timer);
            timer = window.setTimeout(save, 450);
        };
        form.addEventListener('input', scheduleSave);
        form.addEventListener('change', scheduleSave);
        form.addEventListener('submit', () => {
            if (isActivityDraft) return;
            window.clearTimeout(timer);
            try { localStorage.removeItem(key); } catch (_) {}
        });
        form.addEventListener('stridebr:draft-clear', () => {
            window.clearTimeout(timer);
            changed = false;
            try { localStorage.removeItem(key); } catch (_) {}
            recoveryNotice?.remove();
            recoveryNotice = null;
        });
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) {}
        if (!draft || !draft.values || Number(draft.savedAt || 0) < Date.now() - 7 * 86400000) {
            if (draft) try { localStorage.removeItem(key); } catch (_) {}
            return;
        }
        const recovery = document.createElement('div');
        recoveryNotice = recovery;
        recovery.className = isActivityDraft ? 'draft-recovery draft-recovery-activity' : 'draft-recovery';
        recovery.setAttribute('role', 'region');
        recovery.setAttribute('aria-label', stridebrCommonT('common.draft_recovery', {}, 'Draft recovery'));
        const when = localDateTime(new Date(Number(draft.savedAt)), {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'});
        recovery.innerHTML = `<div><strong>${stridebrCommonT('draft.found', {}, 'Draft found')}</strong><span>${stridebrCommonT('draft.saved_at', {when}, 'Saved on this device at {when}.')}</span></div><div><button type="button" data-draft-restore>${isActivityDraft ? stridebrCommonT('common.continue', {}, 'Continue') : stridebrCommonT('draft.restore', {}, 'Restore')}</button><button type="button" data-draft-discard>${stridebrCommonT('draft.discard', {}, 'Discard')}</button></div>`;
        if (isActivityDraft) {
            const shell = form.closest('[data-activity-form]');
            if (shell) shell.before(recovery);
            else form.prepend(recovery);
        } else form.prepend(recovery);
        recovery.querySelector('[data-draft-restore]')?.addEventListener('click', () => {
            if (isActivityDraft) document.querySelector('[data-toggle-activity-form]')?.click();
            apply(form, draft.values);
            changed = true;
            recovery.remove();
            if (!isActivityDraft) {
                status.hidden = false;
                status.textContent = stridebrCommonT('draft.restored_continue', {}, 'Draft restored. Continue where you left off.');
            }
        });
        recovery.querySelector('[data-draft-discard]')?.addEventListener('click', () => {
            try { localStorage.removeItem(key); } catch (_) {}
            recovery.remove();
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const networkBanner = document.querySelector('[data-network-status]');
    const refreshNetworkState = () => {
        if (!(networkBanner instanceof HTMLElement)) return;
        networkBanner.hidden = navigator.onLine;
        document.documentElement.classList.toggle('is-offline', !navigator.onLine);
    };
    window.addEventListener('online', refreshNetworkState);
    window.addEventListener('offline', refreshNetworkState);
    refreshNetworkState();

    document.addEventListener('submit', event => {
        const form = event.target
        if (!(form instanceof HTMLFormElement) || String(form.method || 'get').toLowerCase() !== 'post') return
        if (form.querySelector('input[name="_idempotency_key"]')) return
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = '_idempotency_key'
        input.value = window.StrideBRNet?.requestKey?.() || String(Date.now())
        form.appendChild(input)
    }, true)

    const unlockForm = form => {
        if (!(form instanceof HTMLFormElement)) return;
        form.dataset.submitLocked = '0';
        form.classList.remove('is-submitting');
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-submit-lock-disabled="1"]').forEach(button => {
            button.disabled = false;
            button.classList.remove('is-submitting');
            button.removeAttribute('aria-busy');
            delete button.dataset.submitLockDisabled;
        });
    };

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-submit-lock')) return;
        if (form.dataset.submitLocked === '1') {
            event.preventDefault();
            return;
        }
        window.setTimeout(() => {
            if (event.defaultPrevented) return;
            form.dataset.submitLocked = '1';
            form.classList.add('is-submitting');
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(button => {
                if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement) || button.disabled) return;
                button.disabled = true;
                button.dataset.submitLockDisabled = '1';
                button.classList.add('is-submitting');
                button.setAttribute('aria-busy', 'true');
            });
            window.setTimeout(() => unlockForm(form), 15000);
        }, 0);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submit-locked="1"]').forEach(unlockForm);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-profile-link]').forEach(button => {
        button.addEventListener('click', async () => {
            const url = window.location.href.split('#')[0];
            const original = button.textContent;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const input = document.createElement('input');
                    input.value = url;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.append(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                }
                button.textContent = stridebrCommonT('common.link_copied', {}, 'Link copied');
            } catch (_) {
                button.textContent = stridebrCommonT('common.copy_failed', {}, 'Could not copy');
            }
            window.setTimeout(() => { button.textContent = original; }, 1800);
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const preview = document.querySelector('[data-banner-preview]');
    const input = document.querySelector('[data-banner-input]');
    const color = document.querySelector('[data-banner-color]');
    const status = document.querySelector('[data-banner-status]');
    if (!(preview instanceof HTMLElement)) return;
    if (color instanceof HTMLInputElement) {
        color.addEventListener('input', () => preview.style.setProperty('--settings-banner-color', color.value));
    }
    if (input instanceof HTMLInputElement) {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            if (status instanceof HTMLElement) status.textContent = stridebrCommonT('common.image_selected', {file: file.name}, `Selected: ${file.name}`);
            const url = URL.createObjectURL(file);
            preview.style.setProperty('--settings-banner-image', `url("${url}")`);
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-profile-highlight-slot]').forEach(slot => {
        const select = slot.querySelector('[data-profile-highlight-type]');
        const custom = slot.querySelector('[data-profile-highlight-custom]');
        if (!(select instanceof HTMLSelectElement) || !(custom instanceof HTMLElement)) return;
        const sync = () => { custom.hidden = select.value !== 'custom'; };
        select.addEventListener('change', sync);
        sync();
    });
});

window.StrideBRSportPickerInit = (root = document) => {
    const scope = root instanceof Element || root instanceof Document ? root : document;
    const pickers = [...scope.querySelectorAll('[data-generic-sport-picker]')];
    pickers.forEach(picker => {
        if (picker.dataset.sportPickerReady === '1') return;
        picker.dataset.sportPickerReady = '1';
        const native = picker.querySelector('[data-generic-sport-native]');
        const trigger = picker.querySelector('[data-generic-sport-trigger]');
        const triggerLabel = picker.querySelector('[data-generic-sport-trigger-label]');
        const triggerIcon = picker.querySelector('[data-generic-sport-trigger-icon]');
        const popover = picker.querySelector('[data-generic-sport-popover]');
        const search = picker.querySelector('[data-generic-sport-search]');
        const browser = picker.querySelector('[data-generic-sport-browser]');
        const familyGrid = picker.querySelector('[data-generic-sport-family-grid]');
        const noResults = picker.querySelector('[data-generic-sport-no-results]');
        if (!(native instanceof HTMLSelectElement) || !(trigger instanceof HTMLButtonElement) || !(popover instanceof HTMLElement)) return;

        const getPanels = () => [...picker.querySelectorAll('[data-generic-sport-family-panel]')];
        const getOptions = () => [...picker.querySelectorAll('[data-generic-sport-option]')];

        const resetBrowser = () => {
            browser?.classList.remove('is-searching');
            if (familyGrid instanceof HTMLElement) familyGrid.hidden = false;
            getPanels().forEach(panel => panel.hidden = true);
            picker.querySelectorAll('[data-generic-sport-more-list]').forEach(list => list.hidden = true);
            picker.querySelectorAll('[data-generic-sport-more]').forEach(button => button.setAttribute('aria-expanded', 'false'));
            getOptions().forEach(option => option.hidden = false);
            if (noResults instanceof HTMLElement) noResults.hidden = true;
            popover.scrollTop = 0;
        };

        const syncTrigger = () => {
            const selectedId = native.value;
            const options = getOptions();
            const matched = options.find(option => option.dataset.sportId === selectedId);
            options.forEach(option => option.setAttribute('aria-selected', option.dataset.sportId === selectedId ? 'true' : 'false'));
            if (matched) {
                if (triggerLabel instanceof HTMLElement) triggerLabel.textContent = matched.dataset.sportName || native.selectedOptions[0]?.textContent?.trim() || stridebrCommonT('common.sport', {}, 'Sport');
                const icon = matched.querySelector('.sport-option-icon');
                if (triggerIcon instanceof HTMLElement && icon instanceof HTMLElement) triggerIcon.innerHTML = icon.innerHTML;
            } else {
                if (triggerLabel instanceof HTMLElement) triggerLabel.textContent = native.selectedOptions[0]?.textContent?.trim() || stridebrCommonT('sport_picker.choose', {}, 'Choose a sport');
                if (triggerIcon instanceof HTMLElement) triggerIcon.innerHTML = '<span aria-hidden="true">◎</span>';
            }
        };

        const clearPopoverPlacement = () => {
            ['position', 'left', 'top', 'right', 'bottom', 'width', 'maxHeight'].forEach(property => popover.style.removeProperty(property));
        };

        const placePopover = () => {
            if (popover.hidden) return;
            if (window.matchMedia('(max-width: 620px)').matches) {
                clearPopoverPlacement();
                return;
            }
            const rect = trigger.getBoundingClientRect();
            const padding = 12;
            const gap = 6;
            const maxWidth = Math.max(240, Math.min(520, window.innerWidth - padding * 2));
            const width = Math.min(maxWidth, Math.max(rect.width, 360));
            const left = Math.min(Math.max(padding, rect.left), Math.max(padding, window.innerWidth - width - padding));
            const below = Math.max(0, window.innerHeight - rect.bottom - gap - padding);
            const above = Math.max(0, rect.top - gap - padding);
            const openAbove = below < 260 && above > below;
            const viewportAvailable = Math.max(120, window.innerHeight - padding * 2);
            const available = Math.max(120, Math.min(560, viewportAvailable, openAbove ? above : below));
            popover.style.position = 'fixed';
            popover.style.left = `${Math.round(left)}px`;
            popover.style.right = 'auto';
            popover.style.bottom = 'auto';
            popover.style.width = `${Math.round(width)}px`;
            popover.style.maxHeight = `${Math.round(available)}px`;
            const measured = Math.min(popover.scrollHeight, available);
            const desiredTop = openAbove ? rect.top - gap - measured : rect.bottom + gap;
            const maxTop = Math.max(padding, window.innerHeight - padding - measured);
            const top = Math.min(maxTop, Math.max(padding, desiredTop));
            popover.style.top = `${Math.round(top)}px`;
        };

        const close = () => {
            popover.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            clearPopoverPlacement();
            if (search instanceof HTMLInputElement) search.value = '';
            resetBrowser();
        };

        const open = () => {
            document.querySelectorAll('[data-generic-sport-picker] [data-generic-sport-popover]:not([hidden])').forEach(other => {
                if (other !== popover) {
                    other.hidden = true;
                    other.closest('[data-generic-sport-picker]')?.querySelector('[data-generic-sport-trigger]')?.setAttribute('aria-expanded', 'false');
                }
            });
            resetBrowser();
            popover.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            placePopover();
            window.setTimeout(() => search instanceof HTMLInputElement && search.focus(), 20);
        };

        trigger.addEventListener('click', () => popover.hidden ? open() : close());
        picker.addEventListener('click', event => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const familyButton = target.closest('[data-generic-sport-family-open]');
            if (familyButton && picker.contains(familyButton)) {
                const key = familyButton.dataset.genericSportFamilyOpen || '';
                if (familyGrid instanceof HTMLElement) familyGrid.hidden = true;
                getPanels().forEach(panel => panel.hidden = panel.dataset.genericSportFamilyPanel !== key);
                getPanels().find(panel => !panel.hidden)?.querySelector('button')?.focus({preventScroll:true});
                popover.scrollTop = 0;
                placePopover();
                return;
            }

            const backButton = target.closest('[data-generic-sport-family-back]');
            if (backButton && picker.contains(backButton)) {
                resetBrowser();
                placePopover();
                return;
            }

            const moreButton = target.closest('[data-generic-sport-more]');
            if (moreButton && picker.contains(moreButton)) {
                const list = moreButton.nextElementSibling;
                if (!(list instanceof HTMLElement)) return;
                const expanding = list.hidden;
                list.hidden = !expanding;
                moreButton.setAttribute('aria-expanded', expanding ? 'true' : 'false');
                placePopover();
                return;
            }

            const option = target.closest('[data-generic-sport-option]');
            if (option && picker.contains(option)) {
                native.value = option.dataset.sportId || '';
                native.dispatchEvent(new Event('change', {bubbles: true}));
                syncTrigger();
                close();
                return;
            }

            const emptyButton = target.closest('[data-generic-sport-empty]');
            if (emptyButton && picker.contains(emptyButton)) {
                native.value = '';
                native.dispatchEvent(new Event('change', {bubbles: true}));
                syncTrigger();
                close();
            }
        });
        search?.addEventListener('input', () => {
            const query = String(search.value || '').trim().toLocaleLowerCase(window.StrideBRI18n?.locale === 'en' ? 'en-US' : 'pt-BR');
            if (query === '') {
                resetBrowser();
                return;
            }
            browser?.classList.add('is-searching');
            if (familyGrid instanceof HTMLElement) familyGrid.hidden = true;
            let visible = 0;
            getPanels().forEach(panel => {
                const panelOptions = [...panel.querySelectorAll('[data-generic-sport-option]')];
                let panelVisible = 0;
                panel.querySelectorAll('[data-generic-sport-more-list]').forEach(list => list.hidden = false);
                panelOptions.forEach(option => {
                    const match = String(option.dataset.searchText || '').includes(query);
                    option.hidden = !match;
                    if (match) {
                        visible += 1;
                        panelVisible += 1;
                    }
                });
                panel.hidden = panelVisible === 0;
            });
            if (noResults instanceof HTMLElement) noResults.hidden = visible !== 0;
        });
        native.addEventListener('change', syncTrigger);
        window.addEventListener('resize', placePopover);
        window.addEventListener('scroll', placePopover, true);
        document.addEventListener('pointerdown', event => {
            if (!popover.hidden && !picker.contains(event.target)) close();
        });
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !popover.hidden) {
                event.preventDefault();
                close();
                trigger.focus();
            }
        });
        syncTrigger();
    });
};

document.addEventListener('DOMContentLoaded', () => window.StrideBRSportPickerInit(document));

// Existing dialogs share a page lock; nested dialogs must not unlock their parent.
(() => {
    let locked = false;
    let scrollY = 0;
    const sync = () => {
        const visible = [...document.querySelectorAll('[aria-modal="true"], dialog[open]')]
            .some(dialog => dialog.getClientRects().length > 0 && getComputedStyle(dialog).visibility !== 'hidden' && !dialog.closest('details:not([open])'));
        if (visible === locked) return;
        locked = visible;
        if (locked) {
            scrollY = window.scrollY;
            document.documentElement.style.setProperty('--ui-modal-scroll-top', `-${scrollY}px`);
            document.documentElement.classList.add('ui-modal-scroll-locked');
        } else {
            document.documentElement.classList.remove('ui-modal-scroll-locked');
            document.documentElement.style.removeProperty('--ui-modal-scroll-top');
            window.scrollTo({top: scrollY, behavior: 'instant'});
        }
    };
    document.addEventListener('DOMContentLoaded', () => {
        new MutationObserver(sync).observe(document.body, {subtree:true, childList:true, attributes:true, attributeFilter:['hidden','open','class','aria-modal']});
        sync();
    });
})();
