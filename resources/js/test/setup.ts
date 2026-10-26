/**
 * Preparazione comune dei test di Vitest: i matcher di jest-dom
 * (`toBeInTheDocument`, `toHaveClass`, …) e la pulizia del DOM dopo ogni test.
 */
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/vue';
import { afterEach, vi } from 'vite-plus/test';

// Il <Head> di Inertia scrive il titolo della pagina con il gestore che crea
// createInertiaApp: nei test l'app non c'è, e il titolo non serve.
vi.mock('@inertiajs/vue3', async (originale) => ({
    ...(await originale<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
}));

afterEach(() => {
    cleanup();
});
