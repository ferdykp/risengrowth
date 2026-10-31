// Preserve the clicked submit button's value while preventing accidental repeat submits.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || event.defaultPrevented) return;
    const button = event.submitter;
    if (!(button instanceof HTMLButtonElement)) return;
    if (button.name) {
        const value = document.createElement('input');
        value.type = 'hidden'; value.name = button.name; value.value = button.value;
        form.append(value);
    }
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Memproses…';
});

// Native dialog retains Escape handling, focus trapping, and focus restoration.
document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-dialog-close]');
    if (close) close.closest('dialog')?.close();
});

// Suggestions prefill the existing form; saving remains an explicit user action.
document.addEventListener('click', (event) => {
    const suggestion = event.target.closest('[data-keyword-suggestion]');
    if (!suggestion) return;
    const dialog = document.getElementById('create-keyword');
    const form = dialog?.querySelector('form');
    if (!form) return;
    form.elements.phrase.value = suggestion.dataset.keywordSuggestion;
    form.elements.category.value = suggestion.dataset.keywordCategory;
    if (!dialog.open) dialog.showModal();
    form.elements.phrase.focus();
    form.elements.phrase.scrollIntoView({block: 'nearest'});
    form.elements.phrase.dispatchEvent(new Event('input', {bubbles: true}));
});
