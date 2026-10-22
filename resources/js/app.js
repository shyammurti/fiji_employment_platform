import './bootstrap';
import Alpine from 'alpinejs';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// ---- Chat formatting: Markdown from the assistant, sanitised before display ----
marked.setOptions({ gfm: true, breaks: true });
window.renderMarkdown = (text) => DOMPurify.sanitize(marked.parse(String(text ?? '')));

// ---- Dark mode: remembers the viewer's choice, otherwise follows the system ----
const media = window.matchMedia('(prefers-color-scheme: dark)');
function storedTheme() {
    try { return localStorage.getItem('theme'); } catch { return null; }
}
function applyTheme(mode) {
    const dark = mode === 'dark' || (mode !== 'light' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#0f1720' : '#ffffff');
    return dark;
}
document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: applyTheme(storedTheme()),
        toggle() {
            const next = this.dark ? 'light' : 'dark';
            try { localStorage.setItem('theme', next); } catch { /* private mode: still switch for this page */ }
            this.dark = applyTheme(next);
        },
    });
});
media.addEventListener('change', () => {
    if (!storedTheme() && window.Alpine) Alpine.store('theme').dark = applyTheme(null);
});

// ---- Date pickers: never offer a time that has already passed (Fiji time) ----
function fijiNowLocal() {
    const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Pacific/Fiji', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date());
    return parts.replace(' ', 'T');
}
function applyMinDates(root = document) {
    root.querySelectorAll('input[type="datetime-local"][data-future]').forEach((input) => { input.min = fijiNowLocal(); });
    root.querySelectorAll('input[type="date"][data-future]').forEach((input) => { input.min = fijiNowLocal().slice(0, 10); });
}
document.addEventListener('DOMContentLoaded', () => applyMinDates());
document.addEventListener('focusin', (event) => {
    if (event.target.matches?.('input[data-future]')) applyMinDates(event.target.parentElement);
});

window.Alpine = Alpine;
Alpine.start();

// Give ordinary forms immediate feedback without interfering with Alpine forms (NFR-4).
document.addEventListener('submit', (event) => {
    const form = event.target;
    // GET searches and <dialog> buttons (e.g. the confirm dialog) are not saves.
    if (!(form instanceof HTMLFormElement) || ['get', 'dialog'].includes(form.method.toLowerCase())) return;
    setTimeout(() => {
        if (event.defaultPrevented) return;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => button.disabled = true);
        if (!form.querySelector('.busy-indicator')) {
            const status = document.createElement('p');
            status.className = 'busy-indicator';
            status.setAttribute('role', 'status');
            status.textContent = form.enctype === 'multipart/form-data' ? 'Uploading and saving…' : 'Saving…';
            form.append(status);
        }
    }, 0);
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[aria-busy]').forEach(form => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button').forEach(button => button.disabled = false);
        form.querySelector('.busy-indicator')?.remove();
    });
});

// ---- Styled confirmation dialog ---------------------------------------------
// Replaces the browser's grey confirm() box. Forms opt in with
// data-confirm="Question?" plus optional data-confirm-title, data-confirm-button
// and data-confirm-tone="danger". Scripts can use: await window.confirmDialog(...)
let confirmEl;
function buildConfirmDialog() {
    confirmEl = document.createElement('dialog');
    confirmEl.className = 'confirm-dialog';
    confirmEl.setAttribute('aria-labelledby', 'confirm-dialog-title');
    confirmEl.setAttribute('aria-describedby', 'confirm-dialog-message');
    confirmEl.innerHTML = `
        <form method="dialog" class="p-6">
            <h2 id="confirm-dialog-title" class="text-lg font-semibold text-fiji-900"></h2>
            <p id="confirm-dialog-message" class="mt-2 text-sm leading-6 text-slate-600"></p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button value="cancel" class="btn-secondary">Cancel</button>
                <button value="confirm" data-confirm-ok class="btn-primary"></button>
            </div>
        </form>`;
    document.body.appendChild(confirmEl);
}
window.confirmDialog = (message, { title = 'Are you sure?', button = 'Confirm', tone = 'default' } = {}) => {
    if (!confirmEl) buildConfirmDialog();
    confirmEl.querySelector('#confirm-dialog-title').textContent = title;
    confirmEl.querySelector('#confirm-dialog-message').textContent = message;
    const ok = confirmEl.querySelector('[data-confirm-ok]');
    ok.textContent = button;
    ok.className = tone === 'danger' ? 'btn-danger' : 'btn-primary';
    return new Promise((resolve) => {
        confirmEl.returnValue = 'cancel';
        confirmEl.addEventListener('close', () => resolve(confirmEl.returnValue === 'confirm'), { once: true });
        confirmEl.showModal();
        confirmEl.querySelector('button[value="cancel"]').focus(); // safe default for destructive actions
    });
};
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
    event.preventDefault();
    const submitter = event.submitter;
    const confirmed = await window.confirmDialog(form.dataset.confirm, {
        title: form.dataset.confirmTitle, button: form.dataset.confirmButton, tone: form.dataset.confirmTone,
    });
    if (confirmed) {
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
    }
}, true);

// ---- After a failed submit: go straight to the first field that needs fixing ----
function fieldFor(name) {
    const candidates = [...document.querySelectorAll(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"], #${CSS.escape(name)}`)]
        .filter((el) => el.type !== 'hidden' && el.closest('[data-error-summary]') === null);
    const visible = candidates.find((el) => el.offsetParent !== null);
    if (visible) return visible;
    // Hidden inputs (e.g. the skill picker) point at their section instead.
    const hidden = document.querySelector(`input[type="hidden"][name="${CSS.escape(name)}"]`);
    return hidden?.closest('section, fieldset, .card') ?? document.getElementById(`${name}-error`);
}
function goToField(name) {
    const field = fieldFor(name);
    if (!field) return false;
    // Instant jump: smooth scrolling stalls in background tabs and delays the fix.
    field.scrollIntoView({ block: 'center' });
    if (field.matches('input, select, textarea, button')) {
        field.setAttribute('aria-invalid', 'true');
        field.focus({ preventScroll: true });
    }
    field.classList.remove('field-flash'); void field.offsetWidth; field.classList.add('field-flash');
    return true;
}
document.addEventListener('DOMContentLoaded', () => {
    const summary = document.querySelector('[data-error-summary]');
    if (!summary) return;
    const names = (summary.dataset.errorFields || '').split(',').filter(Boolean);
    names.forEach((name) => fieldFor(name)?.matches?.('input, select, textarea') && fieldFor(name).setAttribute('aria-invalid', 'true'));
    // Focus the first broken field in page order; fall back to the summary itself.
    const first = names.map((name) => ({ name, el: fieldFor(name) })).filter((f) => f.el)
        .sort((a, b) => (a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0];
    if (!(first && goToField(first.name))) { summary.setAttribute('tabindex', '-1'); summary.focus(); }
    summary.addEventListener('click', (event) => {
        const button = event.target.closest('[data-error-for]');
        if (button) goToField(button.dataset.errorFor);
    });
});

// ---- Help page: links like /help#faq-how-do-i-apply open that answer ----
function openFaqFromHash() {
    const target = location.hash && document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (!target) return;
    const details = target.matches('details') ? target : target.querySelector('details');
    if (details) details.open = true;
    target.scrollIntoView({ block: 'start' });
}
document.addEventListener('DOMContentLoaded', openFaqFromHash);
window.addEventListener('hashchange', openFaqFromHash);
