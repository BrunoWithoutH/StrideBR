const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict')
class Element {
    constructor(tag = 'div') { this.tagName = tag; this.dataset = {}; this.children = []; this.handlers = {}; this.style = {}; this.offsetWidth = 400; this.classes = new Set(); this.classList = {add: x => this.classes.add(x), remove: x => this.classes.delete(x), contains: x => this.classes.has(x)} }
    set className(v) { this.classes = new Set(v.split(' ')) } get className() { return [...this.classes].join(' ') }
    setAttribute() {} append(...items) { items.forEach(x => this.appendChild(x)) } appendChild(x) { if (x.parent) x.parent.children.splice(x.parent.children.indexOf(x), 1); this.children.push(x); x.parent = this; x.parentElement = this; return x } prepend(x) { this.children.unshift(x); x.parent = this }
    remove() { this.parent.children.splice(this.parent.children.indexOf(this), 1) }
    addEventListener(type, callback) { (this.handlers[type] ||= []).push(callback) }
    async fire(type, fields = {}) { const event = {type, target: this, button: 0, pointerId: 1, clientX: 0, clientY: 0, preventDefault() {}, stopImmediatePropagation() {}, ...fields}; for (const cb of this.handlers[type] || []) await cb(event) }
    contains(x) { return this === x || this.children.some(c => c.contains(x)) } closest() { return this.tagName === 'button' ? this : null }
    setPointerCapture() { this.captured = true } hasPointerCapture() { return this.captured } releasePointerCapture() { this.captured = false }
}
const body = new Element(), timers = new Map(); let serial = 0, now = 0
const document = {body, createElement: tag => new Element(tag), querySelector: selector => selector === '[data-ui-toast-host]' ? body.children.find(x => 'uiToastHost' in x.dataset) : null, querySelectorAll: () => document.querySelector('[data-ui-toast-host]')?.children.filter(x => x.classList.contains('is-undo')) || []}
const window = {setTimeout(cb, delay) { timers.set(++serial, {cb, delay}); return serial }, clearTimeout(id) { timers.delete(id) }}
const source = fs.readFileSync('public/assets/js/scripts.js', 'utf8'), start = source.indexOf('(() => {\n    const toastCleanup'), end = source.indexOf('\n\n\ndocument.addEventListener', start)
vm.runInNewContext(source.slice(start, end), {document, window, HTMLElement: Element, performance: {now: () => now}, stridebrCommonT: (_, __, fallback) => fallback})
const ui = window.StrideBRUI, host = () => document.querySelector('[data-ui-toast-host]'), timeoutCount = () => [...timers.values()].filter(t => t.delay > 180).length
;(async () => {
    let action = 0
    const toast = ui.notify('message', 'info', {timeout: 4200, actionLabel: 'Act', onAction: async () => action++}).element
    assert.equal(timeoutCount(), 1)
    now = 100; await toast.fire('pointerdown'); assert.equal(timeoutCount(), 0, 'gesture pauses timeout')
    await toast.fire('mouseleave'); assert.equal(timeoutCount(), 0, 'hover exit cannot restart timer during drag')
    await toast.fire('pointermove', {clientX: 20, clientY: 20}); assert.match(toast.style.transform, /20px, 20px/)
    await toast.fire('pointerup'); assert.equal(toast.style.transform, ''); assert.notEqual(toast.dataset.closing, '1'); assert.equal(timeoutCount(), 1, 'short gesture returns and resumes')
    await toast.fire('pointerdown'); await toast.fire('pointermove', {clientX: 50, clientY: 80}); await toast.fire('pointerup'); assert.equal(toast.dataset.closing, '1', 'diagonal gesture dismisses'); assert.equal(timeoutCount(), 0)
    const active = ui.notify('action', 'info', {actionLabel: 'Act', onAction: async () => action++}).element
    await active.fire('pointerdown', {target: active.children[2].children[0]}); assert(!active.classList.contains('is-dragging'), 'action buttons do not start drag')
    await active.children[2].children[0].fire('click'); assert.equal(action, 1); assert.equal(active.dataset.closing, '1')
    const undo = ui.undo('undo', async () => action++).element; await undo.children[2].children[0].fire('click'); assert.equal(action, 2, 'undo callback preserved')
    const focus = ui.notify('focus').element; await focus.fire('focusin'); await focus.fire('mouseenter'); await focus.fire('mouseleave'); assert.equal(timeoutCount(), 0); await focus.fire('focusout'); assert.equal(timeoutCount(), 1)
    for (let i = 0; i < 8; i++) ui.notify('stack ' + i)
    assert.equal(host().children.filter(x => x.dataset.closing !== '1').length, 4, 'stack capped')
    assert.equal(host().children[0].children[1].children[0].textContent, 'stack 7', 'newest first')
    console.log('Web UX Fixes V4 toast runtime passed (gesture, pause, actions, undo, stack).')
})().catch(error => { console.error(error); process.exitCode = 1 })
