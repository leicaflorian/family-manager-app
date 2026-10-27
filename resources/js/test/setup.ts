/**
 * Common setup of the Vitest tests: the jest-dom matchers
 * (`toBeInTheDocument`, `toHaveClass`, …), the translations and the cleanup of
 * the DOM after each test.
 */
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/vue';
import { config } from '@vue/test-utils';
import { i18nVue } from 'laravel-vue-i18n';
import { afterEach, vi } from 'vite-plus/test';
import { i18nOptions } from '@/lib/i18n';

// The Inertia <Head> writes the page title with the manager that
// createInertiaApp creates: in the tests there is no app, and no title is needed.
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
}));

// The tests read the texts in English, the language they are written in.
config.global.plugins.push([i18nVue, i18nOptions('en')]);

afterEach(() => {
    cleanup();
});
