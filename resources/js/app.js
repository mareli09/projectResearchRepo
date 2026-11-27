import './bootstrap';
import * as echarts from 'echarts';

// Alpine.js ships with Livewire 3; do not import it separately.

/* ---------------- Toast (CLAUDE.md §15 "toast") ---------------- */
export function toast(message) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3600);
}
window.toast = toast;

/*
 * Buttons on design screens whose action is not built yet carry data-preview="Sprint N".
 * They never submit; they explain what will happen and when it is built.
 */
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-preview]');
    if (!el) return;
    e.preventDefault();
    const what = el.dataset.previewWhat || el.textContent.trim();
    toast(`Design preview: "${what}" is built in ${el.dataset.preview}. Nothing was saved.`);
});

/* ---------------- Drawer (<dialog class="drawer">) ---------------- */
document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-drawer-open]');
    if (open) {
        document.getElementById(open.dataset.drawerOpen)?.showModal();
        return;
    }
    if (e.target.closest('[data-drawer-close]')) {
        e.target.closest('dialog')?.close();
    } else if (e.target.tagName === 'DIALOG' && e.target.classList.contains('drawer')) {
        e.target.close(); // click on the backdrop
    }
});

/* ---------------- LLM metadata suggestions on "Submit a study" (CLAUDE.md §10) ----------------
 * Suggestions are only suggestions: each item needs Accept, Edit or Remove, and nothing is added
 * without a click. The choices travel with the form (llm_log) and are saved to llm_suggestions.
 */
window.tuaSuggest = (opts) => ({
    loading: false,
    error: '',
    sug: null,
    kwText: opts.keywords || '',
    sdgSel: (opts.sdgs || []).map(String),
    type: opts.type || '',
    keywords() { return this.kwText.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean); },
    setKeywords(list) { this.kwText = [...new Set(list)].join(', '); },
    async suggest() {
        const title = document.getElementById('sb-title')?.value.trim() || '';
        const abstract = document.getElementById('sb-abs')?.value.trim() || '';
        if (!title || !abstract) { this.error = 'Write the title and abstract first.'; return; }
        this.loading = true; this.error = '';
        try {
            const res = await fetch(opts.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': opts.csrf },
                body: JSON.stringify({ title, abstract }),
            });
            if (!res.ok) throw new Error(res.status === 429 ? 'Too many requests. Try again in a minute.' : 'Suggestions are unavailable right now.');
            const d = await res.json();
            const has = this.keywords();
            this.sug = {
                source: d.source, message: d.message, provider: d.provider, latency_ms: d.latency_ms,
                kw: d.keywords.map((v) => ({ v, s: has.includes(v) ? 'accepted' : 'pending', e: '' })),
                sdg: d.sdgs.map((v) => ({ v, s: this.sdgSel.includes(String(v)) ? 'accepted' : 'pending', name: opts.sdgNames[v] || '' })),
                type: d.research_type ? { v: d.research_type, s: 'pending', label: opts.types[d.research_type] || d.research_type } : null,
            };
        } catch (e) { this.error = e.message; } finally { this.loading = false; }
    },
    act(kind, item, action) {
        if (kind === 'kw') {
            const list = this.keywords();
            if (action === 'accept') { if (list.length >= 5 && !list.includes(item.v)) { window.toast('Up to 5 keywords. Remove one first.'); return; } this.setKeywords([...list, item.v]); item.s = 'accepted'; }
            if (action === 'edit') { item.s = 'editing'; item.e = item.v; }
            if (action === 'save') { const v = item.e.trim().toLowerCase(); if (!v) return; if (list.length >= 5 && !list.includes(v)) { window.toast('Up to 5 keywords. Remove one first.'); return; } this.setKeywords([...list, v]); item.s = 'edited'; item.e = v; }
            if (action === 'reject') item.s = 'rejected';
            if (action === 'undo') { this.setKeywords(list.filter((k) => k !== item.v && k !== item.e)); item.s = 'pending'; item.e = ''; }
        }
        if (kind === 'sdg') {
            if (action === 'accept') { this.sdgSel = [...new Set([...this.sdgSel, String(item.v)])]; item.s = 'accepted'; }
            if (action === 'reject') item.s = 'rejected';
            if (action === 'undo') { this.sdgSel = this.sdgSel.filter((x) => x !== String(item.v)); item.s = 'pending'; }
        }
        if (kind === 'type') {
            if (action === 'accept') { this.type = item.v; item.s = 'accepted'; }
            if (action === 'reject') item.s = 'rejected';
            if (action === 'undo') item.s = 'pending';
        }
    },
    log() {
        if (!this.sug) return '[]';
        const out = [];
        this.sug.kw.forEach((k) => out.push({ kind: 'keyword', suggested: k.v, final: k.s === 'edited' ? k.e : null, action: k.s === 'editing' ? 'pending' : k.s }));
        this.sug.sdg.forEach((s) => out.push({ kind: 'sdg', suggested: String(s.v), final: null, action: s.s }));
        if (this.sug.type) out.push({ kind: 'research_type', suggested: this.sug.type.v, final: null, action: this.sug.type.s });
        return JSON.stringify(out);
    },
    meta() { return this.sug ? JSON.stringify({ source: this.sug.source, provider: this.sug.provider, latency_ms: this.sug.latency_ms }) : '{}'; },
});

/* ---------------- Landing page: "How submission works" step switcher ---------------- */
document.addEventListener('click', (e) => {
    const box = e.target.closest('[data-lp-steps]');
    if (!box) return;
    const tabs = [...box.querySelectorAll('[data-step]')];
    const cur = tabs.findIndex((t) => t.getAttribute('aria-selected') === 'true');
    let next = null;
    const tab = e.target.closest('[data-step]');
    if (tab) next = Number(tab.dataset.step);
    if (e.target.closest('[data-step-next]')) next = (cur + 1) % tabs.length;
    if (e.target.closest('[data-step-prev]')) next = (cur - 1 + tabs.length) % tabs.length;
    if (next === null) return;
    tabs.forEach((t, i) => t.setAttribute('aria-selected', String(i === next)));
    box.querySelectorAll('[data-step-panel]').forEach((p) => { p.hidden = Number(p.dataset.stepPanel) !== next; });
    const now = box.querySelector('[data-step-now]');
    if (now) now.textContent = String(next + 1).padStart(2, '0');
});

/* ---------------- Theme picker (CLAUDE.md §9) ---------------- */
const SWATCHES = [['#1b4a42', 'TUA Green'], ['#1e3a6b', 'Navy'], ['#7a1f2b', 'Maroon'], ['#0f5e66', 'Teal'], ['#4b3a8c', 'Violet'], ['#8a3b12', 'Rust'], ['#334155', 'Slate'], ['#9c2a6b', 'Magenta']];

function themePopHtml() {
    const T = window.tuaTheme;
    const cur = T.color().toLowerCase();
    const t = T.get();
    const allow = document.documentElement.dataset.allowUser === '1';
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    return `<div class="theme-pop" role="dialog" aria-label="Theme color" id="theme-pop">
      <div class="ph"><h3 class="font-semibold">Theme color</h3><button type="button" class="btn btn-sm" data-theme-close aria-label="Close">✕</button></div>
      ${allow ? `<div class="swatches">${SWATCHES.map(([c, n]) => `<button type="button" class="sw" aria-pressed="${cur === c}" data-theme-color="${c}"><i style="background:${c}"></i>${n}</button>`).join('')}</div>
      <label class="flex flex-col gap-1 text-[13px]" for="tp-custom">Custom color
        <span class="flex gap-2 items-center"><input type="color" id="tp-custom" value="${esc(cur)}" data-theme-custom style="width:44px;height:32px;border:1px solid var(--line);border-radius:6px;background:var(--surface);padding:2px"><span class="mono">${esc(cur)}</span></span></label>`
        : '<p class="text-[13px] text-muted">The URDC has set one theme color for everyone.</p>'}
      <div><div class="eyebrow mb-1.5">Appearance</div><div class="seg">${[['system', 'System'], ['light', 'Light'], ['dark', 'Dark']].map(([m, l]) => `<button type="button" aria-pressed="${(t.mode || 'system') === m}" data-theme-mode="${m}">${l}</button>`).join('')}</div></div>
      <div class="ph text-[13px]"><span class="text-muted">${document.documentElement.dataset.themeSave ? 'Saved to your account.' : 'Saved on this device.'}</span>${t.color || t.mode ? '<a href="#" data-theme-reset>Reset</a>' : ''}</div>
    </div>`;
}
function closeThemePop() { document.getElementById('theme-pop')?.remove(); }
function refreshThemePop() {
    if (!document.getElementById('theme-pop')) return;
    closeThemePop();
    document.body.insertAdjacentHTML('beforeend', themePopHtml());
}
document.addEventListener('click', (e) => {
    const T = window.tuaTheme;
    if (e.target.closest('[data-theme-toggle]')) {
        document.getElementById('theme-pop') ? closeThemePop() : document.body.insertAdjacentHTML('beforeend', themePopHtml());
        return;
    }
    const sw = e.target.closest('[data-theme-color]');
    if (sw) { T.set({ ...T.get(), color: sw.dataset.themeColor }); refreshThemePop(); redrawCharts(); return; }
    const md = e.target.closest('[data-theme-mode]');
    if (md) { T.set({ ...T.get(), mode: md.dataset.themeMode }); refreshThemePop(); redrawCharts(); return; }
    if (e.target.closest('[data-theme-reset]')) { e.preventDefault(); T.set({}); refreshThemePop(); redrawCharts(); return; }
    if (e.target.closest('[data-theme-close]') || (!e.target.closest('#theme-pop'))) closeThemePop();
});
document.addEventListener('input', (e) => {
    if (e.target.matches('[data-theme-custom]')) {
        window.tuaTheme.set({ ...window.tuaTheme.get(), color: e.target.value });
        redrawCharts();
    }
});
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { window.tuaTheme.apply(); redrawCharts(); });

/* ---------------- Charts (Apache ECharts, CLAUDE.md §7 M7) ----------------
 * <div class="chart" data-chart='{...ECharts option...}' data-chart-link="/search?year={name}">
 * Colors come from the CSS variables, so charts follow the theme and redraw when it changes.
 * Strings "var(--x)" inside the option are replaced with the current token value.
 */
export function chartPalette() {
    const css = getComputedStyle(document.documentElement);
    return ['--c1', '--c2', '--c3', '--c4', '--c5', '--c6', '--c7'].map((v) => css.getPropertyValue(v).trim());
}
window.chartPalette = chartPalette;

const CHARTS = new Map();

function resolveVars(value, css) {
    if (typeof value === 'string') {
        return value.replace(/var\((--[a-z0-9-]+)\)/g, (_, name) => css.getPropertyValue(name).trim());
    }
    if (Array.isArray(value)) return value.map((v) => resolveVars(v, css));
    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, resolveVars(v, css)]));
    }
    return value;
}

function drawChart(el) {
    const css = getComputedStyle(document.documentElement);
    const tk = (n) => css.getPropertyValue('--' + n).trim();
    const opt = resolveVars(JSON.parse(el.dataset.chart), css);
    const axis = (a) => a && [].concat(a).map((x) => ({
        axisLine: { lineStyle: { color: tk('line') } }, axisTick: { show: false },
        splitLine: { lineStyle: { color: tk('line'), type: 'dashed' } }, axisLabel: { color: tk('muted') }, ...x,
    }));
    if (opt.xAxis) opt.xAxis = axis(opt.xAxis);
    if (opt.yAxis) opt.yAxis = axis(opt.yAxis);
    if (opt.legend) opt.legend = { textStyle: { color: tk('muted') }, top: 0, type: 'scroll', icon: 'roundRect', itemWidth: 10, itemHeight: 10, ...opt.legend };
    const phone = matchMedia('(max-width: 820px)').matches;
    if (phone && opt.legend) opt.legend = { ...opt.legend, top: 'auto', bottom: 0 }; // legends move below the chart (§16)

    const base = {
        textStyle: { fontFamily: 'IBM Plex Sans, system-ui, sans-serif', color: tk('muted') },
        color: chartPalette(),
        grid: { left: 8, right: 16, top: opt.legend && !phone ? 40 : 28, bottom: opt.legend && phone ? 40 : 8, containLabel: true },
        tooltip: { backgroundColor: tk('surface'), borderColor: tk('line'), textStyle: { color: tk('ink'), fontSize: 12 } },
        animationDuration: 500,
    };
    CHARTS.get(el)?.dispose();
    const chart = echarts.init(el, null, { renderer: 'svg' });
    chart.setOption({ ...base, ...opt, tooltip: { ...base.tooltip, ...(opt.tooltip || {}) } });

    // Visual information-seeking mantra: clicking a segment opens the records behind it.
    if (el.dataset.chartLink) {
        chart.on('click', (p) => {
            const url = el.dataset.chartLink
                .replace('{name}', encodeURIComponent(p.name ?? ''))
                .replace('{series}', encodeURIComponent(p.seriesName ?? ''))
                .replace('{id}', encodeURIComponent(Array.isArray(p.data) ? (p.data[2] ?? '') : (p.data?.id ?? '')))
                .replace('{key}', encodeURIComponent(p.data?.key ?? p.name ?? ''));
            window.location.href = url;
        });
    }
    CHARTS.set(el, chart);
}

function redrawCharts() { CHARTS.forEach((_, el) => drawChart(el)); }

function initCharts() {
    document.querySelectorAll('[data-chart]').forEach(drawChart);
}
window.addEventListener('resize', () => CHARTS.forEach((c) => c.resize()));
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCharts);
else initCharts();

window.echarts = echarts;

// Installable web app (CLAUDE.md §16): the service worker caches the app shell only, never pages or files.
// Browsers allow it on HTTPS and on localhost.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}

// Uploads with progress and a retry on a weak connection (CLAUDE.md §16). Any form that sends files
// is posted with XHR so the page can show progress; the server's redirect (to the result, or back to
// the form with errors) is then followed as usual. Without JavaScript the form posts normally.
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.enctype !== 'multipart/form-data' || e.defaultPrevented) return;
    const hasFile = [...form.querySelectorAll('input[type=file]')].some((i) => !i.disabled && i.files && i.files.length);
    if (!hasFile) return;
    e.preventDefault();

    const data = new FormData(form);
    if (e.submitter && e.submitter.name) data.append(e.submitter.name, e.submitter.value);
    let box = form.querySelector('.upload-status');
    if (!box) {
        box = document.createElement('div');
        box.className = 'upload-status callout small mt-3';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        form.appendChild(box);
    }
    const buttons = [...form.querySelectorAll('button[type=submit]')];

    const send = () => {
        buttons.forEach((b) => { b.disabled = true; });
        box.innerHTML = '<div class="flex items-center justify-between gap-2"><span class="upload-label">Uploading… 0%</span></div>'
            + '<div class="meter mt-2" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i style="width:0%"></i></div>';
        const label = box.querySelector('.upload-label');
        const meter = box.querySelector('.meter');
        const xhr = new XMLHttpRequest();
        // Not form.action: a button named "action" (Save draft) would shadow it.
        xhr.open('POST', form.getAttribute('action') || window.location.href);
        xhr.setRequestHeader('Accept', 'text/html');
        xhr.setRequestHeader('X-Tua-Upload', '1');
        xhr.upload.addEventListener('progress', (p) => {
            if (!p.lengthComputable) return;
            const pct = Math.round((p.loaded / p.total) * 100);
            label.textContent = pct < 100 ? `Uploading… ${pct}%` : 'Checking the files…';
            meter.setAttribute('aria-valuenow', String(pct));
            meter.firstElementChild.style.width = pct + '%';
        });
        const fail = (msg) => {
            buttons.forEach((b) => { b.disabled = false; });
            box.className = 'upload-status callout callout-bad small mt-3';
            box.innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2"><span>${msg}</span><button type="button" class="btn btn-sm">Retry upload</button></div>`;
            box.querySelector('button').addEventListener('click', () => { box.className = 'upload-status callout small mt-3'; send(); });
        };
        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                // The server answers with the page to open next (the result, or the form with its errors).
                try { const next = JSON.parse(xhr.responseText).redirect; if (next) { window.location.href = next; return; } } catch (err) { /* not JSON */ }
                window.location.reload();
                return;
            }
            if (xhr.status === 413) { fail('The files are too large for the server. Attach smaller files.'); return; }
            if (xhr.status === 419) { fail('Your session expired. Reload the page and sign in again.'); return; }
            fail(`The upload did not finish (error ${xhr.status}). Your files are still selected.`);
        });
        xhr.addEventListener('error', () => fail('The connection dropped during the upload. Your files are still selected.'));
        xhr.addEventListener('timeout', () => fail('The upload took too long. Your files are still selected.'));
        xhr.timeout = 10 * 60 * 1000;
        xhr.send(data);
    };
    send();
});

// Behaviour that used to be inline handlers (onchange=, onsubmit=, onclick=), so the Content
// Security Policy can forbid inline scripts (CLAUDE.md §14 Security).
document.addEventListener('change', (e) => {
    const el = e.target;
    if (el instanceof HTMLElement && el.hasAttribute('data-autosubmit') && el.form) el.form.requestSubmit();
});
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) e.preventDefault();
}, true);
document.addEventListener('click', (e) => {
    const link = e.target instanceof Element ? e.target.closest('[data-open-dialog]') : null;
    if (!link) return;
    const dialog = document.getElementById(link.getAttribute('data-open-dialog'));
    if (dialog instanceof HTMLDialogElement) { e.preventDefault(); dialog.showModal(); }
});
