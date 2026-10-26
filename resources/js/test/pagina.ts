/**
 * Aiuto per i test delle pagine: le monta come fa l'app, con FormKit. Il `<Head>`
 * di Inertia lo toglie `setup.ts` per tutti i test.
 */
import { plugin as formkit } from '@formkit/vue';
import { render } from '@testing-library/vue';
import type { RenderResult } from '@testing-library/vue';
import type { Component } from 'vue';
import formkitConfig from '@/formkit.config';

/**
 * Monta una pagina per un test.
 *
 * @param pagina - il componente della pagina
 * @param props - le props che le darebbe il server
 * @returns il risultato di Testing Library
 */
export function renderPagina(
    pagina: Component,
    props: Record<string, unknown> = {},
): RenderResult {
    return render(pagina, {
        props,
        global: {
            plugins: [[formkit, formkitConfig]],
        },
    });
}
