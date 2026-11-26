// Pembaruan views: satu utas per request supaya Threads tidak dibanjiri.
let busy = false;
let toast;
let after;
let lastStale = '';

const card = () => document.getElementById('account-card');
const status = (text) => { const el = card()?.querySelector('[data-status]'); if (el) el.textContent = text; };

const post = async (path) => {
  const response = await fetch(path, { method: 'POST', headers: { 'X-CSRF-Token': card().dataset.csrf, Accept: 'application/json' } });
  if (!(response.headers.get('Content-Type') || '').includes('json')) throw new Error('Sesi berakhir. Muat ulang halaman.');
  return { ok: response.ok, data: await response.json() };
};

const reload = async () => {
  const response = await fetch(card().dataset.page);
  const next = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('account-card');
  if (!next || !card()) return;
  card().replaceWith(document.adoptNode(next));
  after();
};

const run = async (load, silent) => {
  if (busy || !card()) return;
  busy = true;
  const toggle = (state) => card()?.querySelectorAll('[data-refresh-all], [data-refresh-post]').forEach((b) => {
    b.disabled = state;
    b.querySelector('.icon')?.classList.toggle('animate-spin', state);
  });
  toggle(true);
  let message = '';
  let good = false;
  try {
    const ids = await load();
    let failed = 0;
    let waiting = 0;
    for (const [index, id] of ids.entries()) {
      // Pengguna sudah pindah halaman.
      if (!card()) { busy = false; return; }
      status(`Memperbarui ${index + 1}/${ids.length}`);
      const { data } = await post(`/utas/${id}/segarkan`);
      if (data.result === 'failed' || data.result === 'missing') failed += 1;
      if (data.result === 'wait') waiting += 1;
    }
    await reload();
    if (failed) message = `${failed} utas gagal diperbarui`;
    else if (waiting && waiting === ids.length) message = 'Baru saja diperbarui. Coba lagi dalam satu menit.';
    else good = true;
  } catch (error) {
    message = error instanceof TypeError ? 'Koneksi terputus. Coba lagi.' : error.message;
  }
  busy = false;
  toggle(false);
  if (message) { status(message); if (!silent) toast.error({ message: message.replace(/[<>&]/g, '') }); }
  else if (good && !silent) toast.success('Views diperbarui.');
};

document.addEventListener('click', (event) => {
  const one = event.target.closest('[data-refresh-post]');
  if (one) run(async () => [one.dataset.refreshPost]);
  if (event.target.closest('[data-refresh-all]')) {
    run(async () => {
      status('Memeriksa link bio');
      const { ok, data } = await post('/segarkan');
      if (!ok) throw new Error(data.message);
      return data.ids;
    });
  }
});

export function startRefresh(notifier, done) {
  toast = notifier;
  after = done;
  const stale = card()?.dataset.stale;
  // Pratinjau dari cache Turbo tidak boleh memicu scraping dua kali.
  if (!stale || document.documentElement.hasAttribute('data-turbo-preview')) return;
  const key = `${stale}@${Math.floor(Date.now() / 60000)}`;
  if (key === lastStale) return;
  lastStale = key;
  run(async () => stale.split(','), true);
}
