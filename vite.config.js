import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

// Hasil build ikut di-commit, jadi server tidak membutuhkan Node.
export default defineConfig({
  base: '/assets/build/',
  publicDir: false,
  plugins: [tailwindcss()],
  build: {
    outDir: 'public/assets/build',
    emptyOutDir: true,
    modulePreload: { polyfill: false },
    assetsInlineLimit: 0,
    rollupOptions: {
      input: { app: 'resources/app.js' },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunk-[name]-[hash].js',
        assetFileNames: (file) => (file.names?.[0]?.endsWith('.css') ? 'app.css' : 'font-[name][extname]'),
      },
    },
    cssCodeSplit: false,
  },
});
