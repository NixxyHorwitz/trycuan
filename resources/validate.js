// Validasi formulir: pesan sendiri, bukan balon bawaan browser. Tanpa JavaScript, validasi bawaan dan server tetap berjalan.
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const number = new Intl.NumberFormat('id-ID');
const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Email ---------- */

const domains = ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'hotmail.com', 'icloud.com', 'proton.me', 'ymail.com', 'live.com'];
const distance = (a, b) => {
  let row = Array.from({ length: b.length + 1 }, (_, i) => i);
  for (let i = 1; i <= a.length; i += 1) {
    const next = [i];
    for (let j = 1; j <= b.length; j += 1) {
      next[j] = Math.min(row[j] + 1, next[j - 1] + 1, row[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    }
    row = next;
  }
  return row[b.length];
};
const emailFix = (value) => {
  const [name, domain] = value.trim().toLowerCase().split('@');
  if (!name || !domain || domains.includes(domain)) return null;
  const near = domains.map((d) => [distance(domain, d), d]).sort((a, b) => a[0] - b[0])[0];
  return near[0] <= 2 && domain.length >= 5 ? `${name}@${near[1]}` : null;
};
const emailError = (value) => {
  const text = value.trim();
  if (!text.includes('@')) return 'Email harus memuat tanda @.';
  const [name, domain, more] = text.split('@');
  if (!name) return 'Isi bagian sebelum tanda @.';
  if (more !== undefined) return 'Tanda @ cukup satu.';
  if (!domain || !domain.includes('.')) return 'Lengkapi domainnya, misalnya gmail.com.';
  if (/\s/.test(text)) return 'Email tidak boleh memuat spasi.';
  return /^[^\s@]+@[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/i.test(text) && !text.includes('..') ? '' : 'Format email belum benar.';
};

/* ---------- Password ---------- */

const rows = ['01234567890', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
const runs = rows.flatMap((r) => [r, [...r].reverse().join('')]).flatMap((r) => Array.from({ length: r.length - 4 }, (_, i) => r.slice(i, i + 5)));
const personal = (form) => (form.dataset.personal ? JSON.parse(form.dataset.personal) : [])
  .concat($$('[name="name"], [name="email"]', form).map((el) => el.value))
  .flatMap((v) => v.toLowerCase().split('@')[0].split(/[^\p{L}\p{N}]+/u)).filter((v) => v.length >= 4);
// Urutan dan isi sama dengan App\Core\PasswordPolicy.
const checks = (value, min, words) => {
  const text = value.toLowerCase();
  const kinds = [/\p{Ll}/u, /\p{Lu}/u, /\d/, /[^\p{L}\d]/u].filter((r) => r.test(value)).length;
  return {
    length: [...value].length >= min,
    mix: kinds >= 3 || [...value].length >= 16,
    pattern: value !== '' && !/(.)\1{3}/u.test(text) && !runs.some((r) => text.includes(r)),
    personal: value !== '' && !words.some((w) => text.includes(w)),
  };
};
const messages = {
  length: (min) => `Password minimal ${min} karakter.`,
  mix: () => 'Gabungkan minimal tiga: huruf kecil, huruf besar, angka, simbol. Atau pakai 16 karakter.',
  pattern: () => 'Hindari pola seperti 12345, abcde, qwerty, atau aaaa.',
  personal: () => 'Password jangan memuat nama atau email kamu.',
};

let meter;
const estimator = () => {
  meter ??= import('./strength.js').then((m) => m.strength).catch(() => null);
  return meter;
};
const levels = ['Sangat lemah', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];
let turn = 0;

const paintStrength = async (input) => {
  const box = input.closest('.field').querySelector('[data-strength]');
  if (!box) return;
  const words = personal(input.form);
  const state = checks(input.value, Number(input.dataset.min), words);
  $$('[data-rule]', box).forEach((li) => li.classList.toggle('met', state[li.dataset.rule]));
  const passed = Object.values(state).filter(Boolean).length;
  const mine = ++turn;
  let score = input.value ? Math.min(passed - 1, 3) : -1;
  const check = input.value ? await estimator() : null;
  if (mine !== turn) return;
  const result = check ? check(input.value, words) : null;
  if (result) score = passed === 4 ? Math.max(1, result.score) : Math.min(result.score, 1);
  if (!input.value) score = -1;
  box.dataset.score = String(score);
  const common = Boolean(result?.common);
  const changed = common !== Boolean(input.dataset.common);
  input.dataset.common = common ? '1' : '';
  $('[data-level]', box).textContent = score < 0 ? 'Belum diisi' : (common ? 'Terlalu umum' : levels[score]);
  if (changed && input.dataset.touched) show(input);
};

const generate = (input) => {
  const sets = ['abcdefghijkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#$%*-_+?'];
  const pick = (chars) => { // Tanpa bias modulo.
    const limit = 256 - (256 % chars.length);
    let byte;
    do { [byte] = crypto.getRandomValues(new Uint8Array(1)); } while (byte >= limit);
    return chars[byte % chars.length];
  };
  let value;
  do {
    value = Array.from({ length: 16 }, () => pick(sets.join(''))).join('');
  } while (!sets.every((s) => [...s].some((c) => value.includes(c))) || !Object.values(checks(value, 16, personal(input.form))).every(Boolean));
  const again = input.form.elements.namedItem(input.dataset.again || '');
  [input, again].filter(Boolean).forEach((el) => {
    el.value = value;
    el.type = 'text';
    el.closest('.field').querySelector('.reveal')?.setAttribute('aria-pressed', 'true');
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });
  show(input);
  if (again) show(again);
};

/* ---------- Aturan satu kolom ---------- */

const labelOf = (input) => ($(`label[for="${CSS.escape(input.id)}"]`)?.firstChild?.textContent || input.getAttribute('aria-label') || 'Kolom ini').trim();

export const raw = (input) => ('money' in input.dataset ? input.value.replace(/\D/g, '') : input.value);

const errorOf = (input) => {
  const value = raw(input);
  const v = input.validity;
  if (input.type === 'checkbox') return input.required && !input.checked ? (input.dataset.required || 'Centang untuk melanjutkan.') : '';
  if (input.type === 'file') {
    const file = input.files[0];
    if (!file) return input.required ? 'Pilih berkas dulu.' : '';
    if (input.accept && !input.accept.split(',').some((t) => t.trim() === file.type)) return 'Jenis berkas tidak didukung.';
    return input.dataset.max && file.size > Number(input.dataset.max) ? `Ukuran maksimal ${Math.round(Number(input.dataset.max) / 1048576)} MB.` : '';
  }
  if (input.required && value.trim() === '') return input.dataset.required || (input.tagName === 'SELECT' ? `Pilih ${labelOf(input).toLowerCase()}.` : `${labelOf(input)} wajib diisi.`);
  if (value === '') return '';
  if ('strong' in input.dataset) {
    const state = checks(value, Number(input.dataset.min), personal(input.form));
    const bad = Object.keys(state).find((k) => !state[k]);
    if (bad) return messages[bad](input.dataset.min);
    if (new TextEncoder().encode(value).length > 72) return 'Password maksimal 72 byte.';
    if (input.dataset.common) return 'Password ini terlalu umum dan mudah ditebak.';
  }
  if (input.dataset.match) {
    const first = input.form.elements.namedItem(input.dataset.match);
    if (first && first.value !== value) return 'Password belum sama.';
  }
  if (input.type === 'email') return emailError(value);
  if (input.type === 'url') {
    try { if (!/^https?:$/.test(new URL(value).protocol)) return 'URL harus diawali https://.'; } catch { return 'URL belum lengkap, misalnya https://contoh.com/.'; }
  }
  if (input.minLength > 0 && [...value.trim()].length < input.minLength) return `${labelOf(input)} minimal ${input.minLength} karakter.`;
  if ('money' in input.dataset || input.type === 'number') {
    const n = Number(value);
    if (!Number.isFinite(n) || v.badInput) return 'Isi dengan angka.';
    const min = input.min || input.dataset.minimum;
    const max = input.max || input.dataset.maximum;
    const unit = 'money' in input.dataset && !('plain' in input.dataset) ? 'Rp' : '';
    if (min && n < Number(min)) return `Minimal ${unit}${number.format(min)}.`;
    if (max && n > Number(max)) return `Maksimal ${unit}${number.format(max)}.`;
  }
  if (input.dataset.pattern && !new RegExp(input.dataset.pattern, 'u').test(value)) return input.dataset.patternText || 'Format belum benar.';
  return '';
};

const fields = (form) => [...form.elements].filter((el) => /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && el.name && el.type !== 'hidden' && !el.disabled && !el.readOnly && !el.closest('[hidden]'));

/** Menampilkan atau menghapus pesan; mengembalikan true bila benar. */
export const show = (input) => {
  const text = errorOf(input);
  const field = input.closest('.field, .check-field') || input.parentElement;
  let box = $(':scope > .error', field);
  if (!box && text) {
    box = document.createElement('p');
    box.className = 'error';
    box.id = `${input.id || input.name}-error`;
    box.setAttribute('role', 'alert');
    field.append(box);
  }
  if (box) box.textContent = text;
  if (box && !text) box.remove();
  const hint = $(':scope > .hint', field);
  if (hint) hint.hidden = Boolean(text);
  if (text) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', `${input.id || input.name}-error`); } else { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); }
  field.classList.toggle('valid', !text && raw(input).trim() !== '' && input.type !== 'checkbox' && input.type !== 'file' && !('novalid' in input.dataset));
  if (input.type === 'email') suggest(input, field, text);
  return !text;
};

const suggest = (input, field, text) => {
  $(':scope > .suggest', field)?.remove();
  const fix = text ? null : emailFix(input.value);
  if (!fix) return;
  const p = document.createElement('p');
  p.className = 'suggest';
  p.append('Maksudnya ');
  const b = document.createElement('button');
  b.type = 'button';
  b.textContent = fix;
  b.addEventListener('click', () => { input.value = fix; show(input); input.focus(); });
  p.append(b, '?');
  field.append(p);
};

/** Memeriksa seluruh formulir sebelum dikirim. */
export const validate = (form, button) => {
  if (button?.formNoValidate) return true;
  const bad = fields(form).filter((el) => { el.dataset.touched = '1'; return !show(el); });
  if (!bad.length) return true;
  const target = bad[0].tomselect ? bad[0].tomselect.control_input : bad[0];
  target.focus({ preventScroll: true });
  (bad[0].closest('.field, .check-field') || bad[0]).scrollIntoView({ block: 'center', behavior: calm ? 'auto' : 'smooth' });
  if (!calm) bad.forEach((el) => (el.closest('.field, .check-field') || el).animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(5px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }], { duration: 280, easing: 'ease-out' }));
  return false;
};

export const prepare = () => {
  // Balon bawaan dimatikan hanya setelah pengganti ini siap.
  $$('form').forEach((f) => { f.noValidate = true; });
  $$('[data-money]').forEach((el) => { if (el.value) el.value = number.format(Number(el.value.replace(/\D/g, ''))); });
  $$('[data-strong]').forEach((el) => paintStrength(el));
};

// Pemeriksaan saat meninggalkan kolom, lalu langsung saat memperbaiki.
document.addEventListener('focusout', (event) => {
  const el = event.target;
  if (!el.form || !el.name || el.type === 'hidden' || !/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) return;
  if (el.type === 'search' || el.readOnly || (raw(el) === '' && !el.dataset.touched)) return;
  el.dataset.touched = '1';
  show(el);
});
document.addEventListener('input', (event) => {
  const el = event.target;
  if (!el.form || !el.name) return;
  if ('money' in el.dataset) {
    const digits = el.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 13);
    const after = el.value.slice(el.selectionStart).replace(/\D/g, '').length;
    el.value = digits ? number.format(Number(digits)) : '';
    let at = el.value.length;
    for (let seen = 0; at > 0 && seen < after; at -= 1) if (/\d/.test(el.value[at - 1])) seen += 1;
    el.setSelectionRange(at, at);
  }
  if ('strong' in el.dataset) paintStrength(el);
  if (/^(name|email)$/.test(el.name)) $$('[data-strong]', el.form).forEach(paintStrength);
  if (el.dataset.touched || el.hasAttribute('aria-invalid')) { el.dataset.touched = '1'; show(el); }
  if (el.dataset.again) {
    const again = el.form.elements.namedItem(el.dataset.again);
    if (again?.dataset.touched) show(again);
  }
});
document.addEventListener('change', (event) => {
  const el = event.target;
  if (!el.form || !el.name || !/^(checkbox|file|select-one)$/.test(el.type) || 'submit' in el.dataset || 'pick' in el.dataset) return;
  el.dataset.touched = '1';
  show(el);
});
const caps = (event) => {
  if (!(event.target instanceof HTMLInputElement) || !event.getModifierState || !('password' in event.target.dataset)) return;
  const tip = event.target.closest('.field')?.querySelector('[data-caps]');
  if (tip) tip.hidden = !event.getModifierState('CapsLock');
};
document.addEventListener('keydown', caps);
document.addEventListener('keyup', caps);
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-generate]');
  if (button) generate(document.getElementById(button.dataset.generate));
});
// Angka dikirim tanpa titik pemisah.
document.addEventListener('formdata', (event) => {
  $$('[data-money]', event.target).forEach((el) => { if (el.name && event.formData.has(el.name)) event.formData.set(el.name, raw(el)); });
});
