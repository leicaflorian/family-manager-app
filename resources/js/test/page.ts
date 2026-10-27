/**
 * Helper for the tests of the pages: mounts them as the app does, with FormKit.
 * The Inertia `<Head>` and the translations are set up by `setup.ts` for all
 * the tests.
 */
import { plugin as formkit } from '@formkit/vue';
import { render } from '@testing-library/vue';
import type { RenderResult } from '@testing-library/vue';
import type { Component } from 'vue';
import formkitConfig from '@/formkit.config';

/**
 * Mounts a page for a test.
 *
 * @param page - the component of the page
 * @param props - the props the server would give it
 * @returns the result of Testing Library
 */
export function renderPage(
    page: Component,
    props: Record<string, unknown> = {},
): RenderResult {
    return render(page, {
        props,
        global: {
            plugins: [[formkit, formkitConfig]],
        },
    });
}
