import { defineConfig } from 'cypress';
import { execSync } from 'node:child_process';

export default defineConfig({
  e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL ?? 'http://127.0.0.1:5173',
    specPattern: 'cypress/e2e/**/*.cy.js',
    supportFile: 'cypress/support/e2e.js',
    viewportWidth: 1366,
    viewportHeight: 900,
    video: false,
    defaultCommandTimeout: 8000,
    retries: { runMode: 1, openMode: 0 },
    setupNodeEvents(on) {
      on('task', {
        /** Recrea la BD SQLite con datos de demostración antes de cada spec. */
        resetDb() {
          execSync('php artisan migrate:fresh --seed --force', { cwd: '../backend', stdio: 'inherit' });
          return null;
        },
      });
    },
  },
});
