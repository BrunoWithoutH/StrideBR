document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-model-exercise-editor]');
    const list = editor?.querySelector('[data-model-exercise-list]');
    const template = editor?.querySelector('[data-model-exercise-template]');
    if (!editor || !list || !template) return;
    let nextIndex = list.querySelectorAll('[data-model-exercise-row]').length;

    const renumber = () => {
        [...list.querySelectorAll('[data-model-exercise-row]')].forEach((row, index) => {
            const number = row.querySelector('[data-model-number]');
            if (number) number.textContent = `Exercício ${index + 1}`;
        });
    };
    const wireRow = row => {
        const select = row.querySelector('[data-library-select]');
        const name = row.querySelector('[data-exercise-name]');
        select?.addEventListener('change', () => {
            const option = select.options[select.selectedIndex];
            if (select.value && option?.dataset.name && name) name.value = option.dataset.name;
        });
        row.querySelector('[data-remove-model-exercise]')?.addEventListener('click', () => {
            const next = row.nextElementSibling;
            row.remove();
            renumber();
            window.StrideBRUI?.undo?.('Exercício removido do treino salvo.', async () => {
                if (next?.isConnected) list.insertBefore(row, next);
                else list.appendChild(row);
                renumber();
            });
        });
    };
    list.querySelectorAll('[data-model-exercise-row]').forEach(wireRow);
    editor.querySelector('[data-add-model-exercise]')?.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)).trim();
        const row = wrapper.firstElementChild;
        if (!row) return;
        list.appendChild(row);
        wireRow(row);
        renumber();
        row.querySelector('[data-exercise-name]')?.focus();
    });
    if (!list.children.length) editor.querySelector('[data-add-model-exercise]')?.click();
    renumber();
});
