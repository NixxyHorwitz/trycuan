import { BarController, BarElement, CategoryScale, Chart, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip);

const short = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
const full = new Intl.NumberFormat('id-ID');

/** data-chart: {labels, sets: [{label, data, color: accent|soft}], type, prefix} */
export function draw(canvas, still = false) {
  const spec = JSON.parse(canvas.dataset.chart);
  const css = getComputedStyle(document.documentElement);
  const color = (name) => css.getPropertyValue(`--c-${name}`).trim();
  const prefix = spec.prefix || '';
  const bar = spec.type === 'bar';
  Chart.defaults.font.family = 'Poppins, sans-serif';
  Chart.defaults.font.size = 11;
  return new Chart(canvas, {
    type: bar ? 'bar' : 'line',
    data: {
      labels: spec.labels,
      datasets: spec.sets.map((s) => {
        const ink = color(s.color);
        return bar
          ? { label: s.label, data: s.data, backgroundColor: ink, borderRadius: 5, maxBarThickness: 22, stack: 'a' }
          : { label: s.label, data: s.data, borderColor: ink, backgroundColor: `color-mix(in srgb, ${ink} 14%, transparent)`, borderWidth: 2.5, tension: 0.35, pointRadius: 0, pointHitRadius: 24, pointHoverRadius: 5, pointBackgroundColor: ink, fill: spec.sets.length === 1 };
      }),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: still || matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 600 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: color('ink'), titleColor: color('bg'), bodyColor: color('bg'), padding: 10, cornerRadius: 10, boxPadding: 4,
          callbacks: { label: (c) => `${c.dataset.label}: ${prefix}${full.format(c.parsed.y)}` },
        },
      },
      scales: {
        x: { stacked: bar, grid: { display: false }, border: { display: false }, ticks: { color: color('soft'), maxRotation: 0, autoSkipPadding: 14 } },
        y: { stacked: bar, beginAtZero: true, suggestedMax: 4, border: { display: false }, grid: { color: color('line') }, ticks: { color: color('soft'), maxTicksLimit: 5, precision: 0, callback: (v) => prefix + short.format(v) } },
      },
    },
  });
}
