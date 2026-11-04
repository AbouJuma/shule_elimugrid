import './bootstrap';
import * as BootstrapJs from 'bootstrap/dist/js/bootstrap.bundle.min.js';
import TomSelect from 'tom-select';

// Vite's ES-module bundling never puts this on `window` the way a plain
// <script> tag would — several inline Blade scripts (e.g. the Admit
// Student modal reopening itself after a validation error) call
// `new bootstrap.Modal(...)` expecting that global, so without this they
// silently throw "bootstrap is not defined" and never run.
window.bootstrap = BootstrapJs;

// Every <select> in the portal gets an embedded search box — applied
// globally rather than opt-in per view, per explicit request that ALL
// dropdowns should be searchable, no exceptions. Skips anything already
// TomSelect-managed (avoids double-init if this ever runs twice) and
// multi-selects (none exist in this app yet, but TomSelect needs
// different config for those, so guard against it rather than mis-init).
function initSearchableSelects(scope = document) {
    scope.querySelectorAll('select').forEach((el) => {
        if (el.tomselect || el.multiple) return;

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            maxOptions: null,
        });
    });
}

document.addEventListener('DOMContentLoaded', () => initSearchableSelects());

// Re-init for selects inside a modal the first time it's shown — Bootstrap
// modals exist in the DOM from page load (just hidden), so the initial
// pass above already covers them in practice, but this guards against any
// future modal content that IS injected lazily.
document.addEventListener('shown.bs.modal', (event) => initSearchableSelects(event.target));

// Exposed for views that inject new <select> elements at runtime (e.g. the
// repeatable-row "add several at once" forms), so a freshly-added row's
// dropdown gets the same embedded search box as everything else.
window.initSearchableSelects = initSearchableSelects;

// Every password <input> gets a show/hide eye toggle — applied globally
// rather than opt-in per view, same rationale as the searchable-select
// pass above. Wraps the input in a position-relative container (skipping
// one already wrapped) and overlays a borderless eye button that flips
// the input's type and icon on click.
function addPasswordToggles(scope = document) {
    scope.querySelectorAll('input[type="password"]').forEach((input) => {
        if (input.closest('.password-field-wrapper')) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'password-field-wrapper position-relative';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.classList.add('pe-5');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-link position-absolute top-0 end-0 text-secondary p-0 border-0';
        toggle.style.cssText = 'height:100%;width:2.5rem;display:flex;align-items:center;justify-content:center;';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.innerHTML = '<i class="bi bi-eye"></i>';

        toggle.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });

        wrapper.appendChild(toggle);
    });
}

document.addEventListener('DOMContentLoaded', () => addPasswordToggles());
document.addEventListener('shown.bs.modal', (event) => addPasswordToggles(event.target));
window.addPasswordToggles = addPasswordToggles;

// Admit Student modal: picking an existing guardian from the dropdown hides
// the "create a new guardian" fields right below it (and the other way
// round) — there's no reason to fill in/see a name, phone etc. for a
// guardian who already exists. TomSelect (see initSearchableSelects above)
// syncs its value back to the underlying native <select> and dispatches a
// plain `change` event on it, so this delegated listener catches both a
// manual native change and a TomSelect-driven one with no extra wiring.
document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-existing-guardian-select]');
    if (!select) return;

    const fields = select.closest('.row')?.querySelector('[data-new-guardian-fields]');
    if (!fields) return;

    fields.classList.toggle('show', select.value === '');
});

// Rows-per-page control (see school-admin/partials/table-pagination.blade.php):
// changing it updates `per_page` in the URL (and resets that paginator's
// own page number back to 1) while preserving every other query param
// (search, status, date filters, ...) already on the page.
document.addEventListener('change', (event) => {
    const select = event.target.closest('.page-size-select');
    if (!select) return;

    const url = new URL(window.location.href);
    url.searchParams.set('per_page', select.value);

    const pageParam = select.dataset.pageParam || 'page';
    url.searchParams.delete(pageParam);

    window.location.href = url.toString();
});
