'use strict';

(() => {
  const go = document.getElementById('go');
  const box = document.getElementById('challenge');
  let sent = false;

  const signals = () => ({
    webdriver: navigator.webdriver === true,
    platform: navigator.platform || '',
    lang: navigator.language || '',
    tz: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    w: screen.width,
    h: screen.height,
    touch: navigator.maxTouchPoints || 0,
    cores: navigator.hardwareConcurrency || 0,
  });

  const send = async (challenge) => {
    if (sent) return;
    sent = true;
    const request = fetch(`/${go.dataset.code}/klik`, {
      method: 'POST',
      keepalive: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: go.dataset.token, challenge, signals: signals() }),
    }).catch(() => {});
    // Pengunjung tidak boleh tertahan oleh pencatatan.
    await Promise.race([request, new Promise((done) => setTimeout(done, 2500))]);
  };

  const leave = async (challenge) => {
    await send(challenge);
    location.replace(go.href);
  };

  go.addEventListener('click', (event) => {
    event.preventDefault();
    leave('');
  });

  if (!box.dataset.sitekey) {
    leave('');
    return;
  }
  // Tantangan gagal dimuat atau terlalu lama: tetap lanjut, klik ditahan server.
  const timer = setTimeout(() => leave(''), 8000);
  if (!window.turnstile) {
    clearTimeout(timer);
    leave('');
    return;
  }
  window.turnstile.render(box, {
    sitekey: box.dataset.sitekey,
    theme: 'dark',
    callback: (response) => { clearTimeout(timer); leave(response); },
    'error-callback': () => { clearTimeout(timer); leave(''); return true; },
    'before-interactive-callback': () => clearTimeout(timer),
  });
})();
