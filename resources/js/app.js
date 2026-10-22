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
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get') return;
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
