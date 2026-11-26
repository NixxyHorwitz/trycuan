// Membuat app/icons.php dari lucide-static (lisensi ISC), supaya ikon tidak perlu JavaScript.
import { readFileSync, writeFileSync } from 'node:fs';

const names = {
  trend: 'trending-up', home: 'house', grid: 'layout-dashboard', user: 'user', users: 'users', shield: 'shield-check', logout: 'log-out',
  link: 'link-2', edit: 'pencil', wallet: 'wallet', arrow: 'arrow-right', back: 'arrow-left', alert: 'circle-alert', campaign: 'megaphone',
  refresh: 'refresh-cw', check: 'check', bell: 'bell', moon: 'moon', sun: 'sun', menu: 'menu', close: 'x', search: 'search', eye: 'eye',
  eyeoff: 'eye-off', click: 'mouse-pointer-click', coins: 'coins', clock: 'clock', download: 'download', copy: 'copy', external: 'external-link',
  plus: 'plus', chevron: 'chevron-right', down: 'chevron-down', pause: 'pause', play: 'play', file: 'file-text', activity: 'activity',
  history: 'history', settings: 'settings', threads: 'at-sign', send: 'send', bank: 'landmark', upload: 'upload', up: 'arrow-up-right',
  in: 'arrow-down-left', target: 'target', calendar: 'calendar', key: 'key-round', archive: 'archive', filter: 'list-filter', lock: 'lock', star: 'trophy', spark: 'sparkles', mail: 'mail', book: 'book-open', layers: 'layers', list: 'list', trash: 'trash-2', chart: 'chart-column', heading: 'heading', bold: 'bold', numbers: 'list-ordered', quote: 'quote', 'chevron-up': 'chevron-up',
};
const lines = Object.entries(names).map(([key, file]) => {
  const svg = readFileSync(new URL(`../node_modules/lucide-static/icons/${file}.svg`, import.meta.url), 'utf8');
  const inner = svg.replace(/<!--[\s\S]*?-->/g, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace('</svg>', '').replace(/\s+/g, ' ').trim();
  return `    '${key}' => '${inner}',`;
});
const { dictionary } = await import('@zxcvbn-ts/language-common');
const common = dictionary['passwords-common'].filter((p) => p.length >= 5);
writeFileSync(new URL('../app/common-passwords.txt', import.meta.url), `${common.join('\n')}\n`);
writeFileSync(new URL('../app/icons.php', import.meta.url), `<?php\n// Dibuat otomatis oleh resources/icons.mjs. Ikon Lucide, lisensi ISC.\nreturn [\n${lines.join('\n')}\n];\n`);
