/**
 * Preparazione comune dei test di Vitest: i matcher di jest-dom
 * (`toBeInTheDocument`, `toHaveClass`, …) e la pulizia del DOM dopo ogni test.
 */
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/vue';
import { afterEach } from 'vite-plus/test';

afterEach(() => {
    cleanup();
});
