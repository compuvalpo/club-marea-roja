import { defineConfig } from 'vite';

// En desarrollo el frontend (5173) y la API (8080) comparten origen gracias al proxy,
// lo que permite usar la cookie HttpOnly SameSite=Strict del refresh token.
export default defineConfig({
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: process.env.API_URL ?? 'http://127.0.0.1:8080',
        changeOrigin: false,
      },
    },
  },
  preview: {
    proxy: {
      '/api': process.env.API_URL ?? 'http://127.0.0.1:8080',
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
    chunkSizeWarningLimit: 700,
  },
  test: {
    environment: 'jsdom',
    include: ['tests/**/*.test.js'],
  },
});
