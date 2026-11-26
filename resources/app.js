import './app.css';
import * as Turbo from '@hotwired/turbo';
import { Notyf } from 'notyf';
import { startRefresh } from './refresh.js';
import { prepare, validate } from './validate.js';

// Bilah progres bawaan Turbo menyuntik <style>, yang ditolak CSP. Dipakai milik sendiri.
Turbo.config.drive.progressBarDelay = 1e9;
const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
const number = new Intl.NumberFormat('id-ID');
const toast = new Notyf({
  duration: 4000,
  position: { x: 'center', y: 'top' },
  dismissible: true,
  types: [{ type: 'success', background: '#0d7a43' }, { type: 'error', background: '#c42b2b' }],
});
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const bar = document.createElement('div');
bar.className = 'bar-load';
const loading = (on) => { document.documentElement.append(bar); bar.classList.toggle('run', on); };
document.addEventListener('turbo:visit', () => loading(true));
document.addEventListener('turbo:submit-start', () => loading(true));
document.addEventListener('turbo:submit-end', () => loading(false));
document.addEventListener('turbo:load', () => loading(false));

/* ---------- Tiap halaman ---------- */

const gauges = () => $$('.gauge .fill').forEach((el) => {
  requestAnimationFrame(() => requestAnimationFrame(() => { el.style.strokeDashoffset = String(100 - Number(el.dataset.value)); }));
});

const calculator = () => {
  const views = $('#views');
  if (!views) return;
  const range = $('#views-range');
  const rate = Number(views.closest('[data-rate]').dataset.rate);
  const update = () => {
    const value = Math.min(10000000, Math.max(0, Math.floor(Number(views.value) || 0)));
    $('#fee').textContent = `Rp${number.format(Math.floor(value / 1000) * rate)}`;
    $('#meter').value = value % 1000;
    $('#next').textContent = `${number.format(1000 - (value % 1000))} views lagi untuk +Rp${number.format(rate)}`;
  };
  views.addEventListener('input', () => { range.value = views.value; update(); });
  range.addEventListener('input', () => { views.value = range.value; update(); });
  update();
};

let charts = [];
const drawCharts = async (still = false) => {
  charts.forEach((c) => c.destroy());
  charts = [];
  const nodes = $$('canvas[data-chart]');
  if (!nodes.length) return;
  const { draw } = await import('./charts.js');
  charts = nodes.filter((n) => n.isConnected).map((n) => draw(n, still));
};

const dates = async () => {
  const nodes = $$('input[type="datetime-local"]:not([readonly])');
  if (!nodes.length) return;
  const { default: flatpickr } = await import('flatpickr');
  const { Indonesian } = await import('flatpickr/dist/l10n/id.js');
  nodes.forEach((el) => {
    const value = el.value;
    el.type = 'text';
    flatpickr(el, { disableMobile: true, enableTime: true, time_24hr: true, dateFormat: 'Y-m-d\\TH:i', altInput: true, altFormat: 'j M Y, H:i', locale: Indonesian, defaultDate: value || null });
  });
};

const currentChip = () => $$('.chips [aria-current]').forEach((el) => { el.parentElement.scrollLeft = Math.max(0, el.offsetLeft - 24); });

// Pilihan bawaan diganti daftar yang bisa dicari.
const selects = async () => {
  const nodes = $$('select:not(.tomselected)');
  if (!nodes.length) return;
  const { default: TomSelect } = await import('tom-select/base');
  nodes.filter((el) => el.isConnected && !el.tomselect).forEach((el) => new TomSelect(el, {
    create: false,
    maxOptions: null,
    placeholder: el.options[0]?.value === '' ? el.options[0].text : undefined,
    hidePlaceholder: true,
    allowEmptyOption: !el.required,
    controlInput: el.options.length > 7 ? undefined : null,
    render: { no_results: () => '<div class="no-results">Tidak ada pilihan</div>' },
  }));
};

const files = () => $$('.file input').forEach((el) => {
  const label = el.parentElement.querySelector('[data-file]');
  const size = (n) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.ceil(n / 1024)} KB`);
  el.onchange = () => {
    const [file] = el.files;
    label.textContent = file ? `${file.name} (${size(file.size)})` : label.dataset.file;
    el.parentElement.classList.toggle('filled', Boolean(file));
  };
  ['dragenter', 'dragover'].forEach((n) => el.addEventListener(n, () => el.parentElement.classList.add('over')));
  ['dragleave', 'drop'].forEach((n) => el.addEventListener(n, () => el.parentElement.classList.remove('over')));
});

const init = () => {
  loading(false);
  prepare();
  selects();
  files();
  const flash = $('[data-flash]');
  if (flash) { toast.success({ message: flash.textContent.replace(/[<>&]/g, '') }); flash.remove(); }
  gauges();
  calculator();
  drawCharts();
  dates();
  currentChip();
  counters();
  editor();
  startRefresh(toast, () => { gauges(); drawCharts(true); });
};

// turbo:load tidak terjadi setelah formulir ditolak (422), jadi halaman disiapkan pada setiap turbo:render.
document.addEventListener('turbo:load', init, { once: true });
document.addEventListener('turbo:render', () => {
  if (!document.documentElement.hasAttribute('data-turbo-preview')) init();
});

document.addEventListener('turbo:before-cache', () => {
  charts.forEach((c) => c.destroy());
  charts = [];
  $$('dialog[open]').forEach((d) => d.close());
  $$('.menu[open]').forEach((m) => { m.open = false; });
  $$('select').forEach((el) => el.tomselect?.destroy());
  $$('.busy').forEach(idle);
  $$('.notyf__toast').forEach((t) => t.remove());
});

/* ---------- Interaksi ---------- */

const theme = (name) => {
  document.documentElement.dataset.theme = name;
  document.cookie = `trycuan_theme=${name};path=/;max-age=31536000;samesite=lax${location.protocol === 'https:' ? ';secure' : ''}`;
  drawCharts(true);
};

const copy = async (button) => {
  const source = document.getElementById(button.dataset.copy);
  let done = false;
  try {
    await navigator.clipboard.writeText(source.textContent);
    done = true;
  } catch {
    // Clipboard API tidak tersedia di HTTP biasa.
    getSelection().selectAllChildren(source);
    done = document.execCommand('copy');
  }
  if (done) toast.success('Tersalin.');
  else toast.error('Tidak bisa menyalin otomatis. Salin manual.');
};

document.addEventListener('click', (event) => {
  const el = event.target.closest('[data-reveal], [data-copy], [data-theme-toggle], [data-open], [data-close], [data-filter]');
  if (event.target instanceof HTMLDialogElement) event.target.close();
  if (!el) return;
  if (el.dataset.reveal) {
    const input = document.getElementById(el.dataset.reveal);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    el.setAttribute('aria-pressed', String(show));
    el.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
  } else if (el.dataset.copy) copy(el);
  else if ('themeToggle' in el.dataset) theme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
  else if (el.dataset.open) { document.getElementById(el.dataset.open).showModal(); }
  else if ('close' in el.dataset) el.closest('dialog').close();
  else if ('filter' in el.dataset) {
    const group = el.closest('[data-filter-group]');
    $$('[data-filter]', group).forEach((b) => b.setAttribute('aria-pressed', String(b === el)));
    smooth(() => applyFilter(group));
  }
});

// Perubahan kecil di halaman ikut dianimasikan bila browser mendukung.
const smooth = (change) => {
  if (calm || !document.startViewTransition) change();
  else document.startViewTransition(change);
};

const applyFilter = (group) => {
  const state = $('[data-filter][aria-pressed="true"]', group)?.dataset.filter || '';
  const text = ($('[data-filter-text]', group)?.value || '').toLowerCase().trim();
  let shown = 0;
  $$('[data-item]', group).forEach((item) => {
    const ok = (!state || item.dataset.item.split(' ').includes(state)) && (!text || item.textContent.toLowerCase().includes(text));
    item.hidden = !ok;
    if (ok) shown += 1;
  });
  const none = $('[data-none]', group);
  if (none) none.hidden = shown > 0;
};

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-filter-text]')) applyFilter(event.target.closest('[data-filter-group]'));
  if (event.target.matches('[data-pick], [data-pick-all]')) {
    const all = $('[data-pick-all]');
    const picks = $$('[data-pick]');
    if (event.target === all) picks.forEach((p) => { p.checked = all.checked; });
    const total = picks.filter((p) => p.checked).length;
    all.checked = total === picks.length;
    all.indeterminate = total > 0 && total < picks.length;
    const bulk = $('.bulk');
    bulk.classList.toggle('show', total > 0);
    $('[data-picked]', bulk).textContent = `${total} dipilih`;
  }
});

// Enter pada pilihan langsung menyaring.
document.addEventListener('change', (event) => { if (event.target.matches('[data-submit]')) event.target.form.requestSubmit(); });

/* Menu akun, avatar, dan editor dokumen */
document.addEventListener('click', (event) => {
  $$('.menu[open]').forEach((m) => { if (!m.contains(event.target) || event.target.closest('.menu-panel a')) m.open = false; });
  const shuffle = event.target.closest('[data-shuffle]');
  if (shuffle) {
    const box = shuffle.closest('[data-avatars]');
    const seed = [...crypto.getRandomValues(new Uint8Array(8))].map((n) => n.toString(16).padStart(2, '0')).join('');
    $('[name="avatar_seed"]', box).value = seed;
    $$('label', box).forEach((l) => { $('img', l).src = `${box.dataset.url}${$('input', l).value}/svg?seed=${seed}`; });
  }
  const mark = event.target.closest('[data-mark], [data-insert]');
  if (mark) insert(mark);
  // Daftar isi menutup sendiri di layar kecil.
  const toc = event.target.closest('.docs-nav nav a');
  if (toc && innerWidth < 1024) toc.closest('details').open = false;
});
document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  const open = $('.menu[open]');
  if (open) { open.open = false; $('summary', open).focus(); }
});
// CDN avatar gagal: huruf awal di bawahnya yang tampil.
document.addEventListener('error', (event) => { if (event.target.matches?.('.avatar img')) event.target.remove(); }, true);

const counters = () => $$('[data-count]').forEach((el) => {
  const out = document.createElement('span');
  out.className = 'counter';
  const paint = () => { out.textContent = `${[...el.value].length}/${el.maxLength}`; };
  el.closest('.field').querySelector('.counter')?.remove();
  $('label', el.closest('.field')).after(out);
  el.addEventListener('input', paint);
  paint();
});

let previewTimer;
const preview = async (box) => {
  const text = $('textarea', box);
  try {
    const response = await fetch(box.dataset.preview, { method: 'POST', headers: { 'X-CSRF-Token': $('input[name="_csrf"]', text.form).value }, body: new URLSearchParams({ body: text.value }) });
    // Server meng-escape semua isi sebelum membentuk HTML.
    if (response.ok) $('[data-preview-out]', box).innerHTML = await response.text() || '<p class="hint">Pratinjau muncul di sini.</p>';
  } catch { /* Pratinjau dicoba lagi saat mengetik. */ }
};
const editor = () => $$('[data-editor]').forEach((box) => {
  preview(box);
  $('textarea', box).addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(() => preview(box), 350); });
});
const insert = (button) => {
  const text = $('[data-editor] textarea', button.form);
  const { selectionStart: from, selectionEnd: to } = text;
  const picked = text.value.slice(from, to);
  const lead = from > 0 && text.value[from - 1] !== '\n' ? '\n' : '';
  const kind = button.dataset.mark;
  let out = button.dataset.insert;
  if (kind === '**') out = `**${picked || 'teks tebal'}**`;
  else if (kind === '[]') out = `[${picked || 'teks link'}](https://)`;
  else if (kind === '```') out = `${lead}\`\`\`Contoh utas\n${picked || 'Tulis teks di sini'}\n\`\`\`\n`;
  else if (kind) out = lead + (picked || 'teks').split('\n').map((l) => kind + l).join('\n');
  text.setRangeText(out, from, to, 'end');
  text.focus();
  text.dispatchEvent(new Event('input', { bubbles: true }));
};

/* Konfirmasi, lalu cegah kirim ganda. */
const ask = (text, label) => new Promise((resolve) => {
  const dialog = document.createElement('dialog');
  dialog.className = 'modal';
  dialog.innerHTML = '<h2></h2><p class="text-soft"></p><div class="actions"><button class="btn outline" value="no">Batal</button><button class="btn primary" value="yes"></button></div>';
  $('h2', dialog).textContent = 'Konfirmasi';
  $('p', dialog).textContent = text;
  $('.primary', dialog).textContent = label;
  const settle = (yes) => { dialog.close(); dialog.remove(); resolve(yes); };
  dialog.addEventListener('click', (e) => { if (e.target.value) settle(e.target.value === 'yes'); });
  // Esc atau ketuk di luar.
  dialog.addEventListener('close', () => settle(false), { once: true });
  document.body.append(dialog);
  dialog.showModal();
});

const busy = (button, text) => {
  if (!button) return;
  const label = $('span', button) || button;
  button.dataset.label = label.textContent;
  button.style.minWidth = `${button.offsetWidth}px`;
  label.textContent = text || label.textContent;
  button.classList.add('busy');
  button.setAttribute('aria-busy', 'true');
};
function idle(button) {
  if (!button.classList.contains('busy')) return;
  ($('span', button) || button).textContent = button.dataset.label;
  button.classList.remove('busy');
  button.removeAttribute('aria-busy');
  button.style.minWidth = '';
}

document.addEventListener('submit', async (event) => {
  const form = event.target;
  const button = event.submitter;
  if (!validate(form, button)) {
    event.preventDefault();
    event.stopImmediatePropagation();
    return;
  }
  const text = button?.dataset.confirm || form.dataset.confirm;
  if (text && form.dataset.agreed !== '1') {
    event.preventDefault();
    event.stopImmediatePropagation();
    if (await ask(text, button?.textContent.trim() || 'Lanjut')) {
      form.dataset.agreed = '1';
      form.requestSubmit(button);
      delete form.dataset.agreed;
    }
    return;
  }
  if (form.method === 'post') busy(button || $('[type="submit"]', form), form.dataset.once);
}, true);
document.addEventListener('turbo:submit-end', (event) => {
  const response = event.detail.fetchResponse?.response;
  if (!response?.redirected) $$('.busy', event.target).forEach(idle);
  if (!response) toast.error('Koneksi terputus. Coba lagi.');
  else if (response.status >= 400 && response.status < 500) {
    // Setelah halaman baru tergambar.
    document.addEventListener('turbo:render', () => {
      const bad = $('[aria-invalid], .notice.bad');
      bad?.scrollIntoView({ block: 'center' });
      if (bad?.matches('input')) bad.focus({ preventScroll: true });
      else $('.auth input[type="password"]')?.focus({ preventScroll: true });
    }, { once: true });
  }
});

// Formulir yang lama terbuka memakai token yang mungkin sudah berganti.
let shown = Date.now();
document.addEventListener('visibilitychange', async () => {
  if (document.hidden || Date.now() - shown < 900000 || !$('input[name="_csrf"]')) return;
  shown = Date.now();
  try {
    const page = new DOMParser().parseFromString(await (await fetch(location.href)).text(), 'text/html');
    const token = $('input[name="_csrf"]', page)?.value;
    if (token) $$('input[name="_csrf"]').forEach((el) => { el.value = token; });
  } catch { /* Dicoba lagi nanti. */ }
});

/* ---------- Pencarian cepat admin ---------- */

const pages = () => $$('.sidebar .side-nav a').map((a) => ({ group: 'Halaman', label: $('span', a).textContent, note: 'Halaman', url: a.getAttribute('href') }));
let timer;
let turn = 0;

const list = (rows) => {
  const box = $('#palette-list');
  box.replaceChildren(...rows.map((r, i) => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    const note = document.createElement('span');
    a.href = r.url;
    a.textContent = r.label;
    note.textContent = r.note === r.group ? r.group : `${r.group}: ${r.note}`;
    a.append(note);
    if (i === 0) a.setAttribute('aria-selected', 'true');
    li.append(a);
    return li;
  }));
  $('#palette-none').hidden = rows.length > 0;
};

const find = async (text) => {
  const mine = ++turn;
  const q = text.trim().toLowerCase();
  const local = pages().filter((p) => p.label.toLowerCase().includes(q));
  list(local);
  if (q.length < 2) return;
  try {
    const response = await fetch(`/admin/cari?q=${encodeURIComponent(text.trim())}`, { headers: { Accept: 'application/json' } });
    if (!(response.headers.get('Content-Type') || '').includes('json')) return;
    const rows = await response.json();
    if (mine === turn) list([...local, ...rows]);
  } catch { /* Daftar halaman tetap tampil. */ }
};

document.addEventListener('input', (event) => {
  if (event.target.id !== 'palette-input') return;
  clearTimeout(timer);
  timer = setTimeout(() => find(event.target.value), 160);
});
document.addEventListener('keydown', (event) => {
  const palette = document.getElementById('palette');
  if (!palette) return;
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    if (!palette.open) palette.showModal();
  }
  if (!palette.open || event.target.id !== 'palette-input') return;
  const links = $$('#palette-list a');
  const at = links.findIndex((a) => a.getAttribute('aria-selected') === 'true');
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    if (!links.length) return;
    const next = (at + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
    links.forEach((a, i) => a.toggleAttribute('aria-selected', false) || (i === next && a.setAttribute('aria-selected', 'true')));
    links[next].scrollIntoView({ block: 'nearest' });
  }
  if (event.key === 'Enter' && links[at]) { event.preventDefault(); palette.close(); Turbo.visit(links[at].getAttribute('href')); }
});
document.addEventListener('click', (event) => {
  if (event.target.closest('[data-open="palette"]')) find($('#palette-input').value);
  if (event.target.closest('#palette-list a')) document.getElementById('palette').close();
  if (event.target.closest('.drawer a')) event.target.closest('dialog').close();
});
document.addEventListener('focusin', (event) => { if (event.target.id === 'palette-input' && !$('#palette-list a')) find(''); });
