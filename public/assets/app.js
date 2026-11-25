'use strict';

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-reveal]');
  if (!button) return;
  const input = document.getElementById(button.dataset.reveal);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'Tutup' : 'Lihat';
  button.setAttribute('aria-pressed', String(show));
});

document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    const source = document.getElementById(button.dataset.copy);
    let copied = false;
    try {
      await navigator.clipboard.writeText(source.textContent);
      copied = true;
    } catch {
      // Clipboard API tidak tersedia di HTTP biasa.
      getSelection().selectAllChildren(source);
      copied = document.execCommand('copy');
    }
    button.textContent = copied ? 'Tersalin' : 'Salin manual';
    setTimeout(() => { button.textContent = 'Salin'; }, 2000);
  });
});

// Cegah kirim ganda; aktifkan lagi bila halaman kembali dari cache browser.
document.querySelectorAll('form[data-once]').forEach((form) => {
  const button = form.querySelector('[type="submit"]');
  const label = button.textContent;
  form.addEventListener('submit', () => {
    button.disabled = true;
    if (form.dataset.once) button.textContent = form.dataset.once;
  });
  window.addEventListener('pageshow', () => {
    button.disabled = false;
    button.textContent = label;
  });
});

const views = document.getElementById('views');
if (views) {
  const format = new Intl.NumberFormat('id-ID');
  const update = () => {
    const value = Math.min(10000000, Math.max(0, Math.floor(Number(views.value) || 0)));
    document.getElementById('fee').textContent = 'Rp' + format.format(Math.floor(value / 1000) * 10000);
    document.getElementById('meter').value = value % 1000;
    document.getElementById('next').textContent = format.format(1000 - (value % 1000)) + ' views lagi untuk +Rp10.000';
  };
  views.addEventListener('input', update);
  update();
}
