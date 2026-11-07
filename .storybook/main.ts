/**
 * Storybook configuration: Vue 3 on Vite+, with the stories next to their
 * components. The Vite plugins are the ones of `vite.config.ts`, except
 * `laravel-vite-plugin`, because Storybook serves its own HTML.
 */
import { fileURLToPath } from 'node:url';
import type { StorybookConfig } from '@storybook/vue3-vite';
import type { PluginOption } from 'vite';
import { mergeConfig } from 'vite';

/**
 * Tells whether a plugin option is the one of `laravel-vite-plugin`.
 *
 * @param plugin - A plugin, or a nested list of plugins, from the Vite config.
 * @returns True when it must not be loaded by Storybook.
 */
function isLaravelPlugin(plugin: PluginOption): boolean {
    if (Array.isArray(plugin)) {
        return plugin.some(isLaravelPlugin);
    }

    return (
        typeof plugin === 'object' &&
        plugin !== null &&
        'name' in plugin &&
        typeof plugin.name === 'string' &&
        plugin.name.startsWith('laravel')
    );
}

const config: StorybookConfig = {
    framework: {
        name: '@storybook/vue3-vite',
        options: {
            // Props are read from the types, as the check of the stories reads them
            docgen: 'vue-component-meta',
        },
    },
    stories: ['../resources/js/**/*.stories.ts'],
    addons: [
        '@storybook/addon-themes',
        '@storybook/addon-a11y',
        '@storybook/addon-docs',
        'storybook-addon-pseudo-states',
    ],
    async viteFinal(viteConfig) {
        return mergeConfig(
            {
                ...viteConfig,
                plugins: (viteConfig.plugins ?? []).filter(
                    (plugin) => !isLaravelPlugin(plugin),
                ),
            },
            {
                resolve: {
                    alias: [
                        {
                            find: '@',
                            replacement: fileURLToPath(
                                new URL('../resources/js', import.meta.url),
                            ),
                        },
                        // The stories get a fake `usePage`. Exact match: the shim reaches the real package as `@inertiajs/vue3-actual` (alias in vite.config.ts), which this one leaves alone
                        {
                            find: /^@inertiajs\/vue3$/,
                            replacement: fileURLToPath(
                                new URL(
                                    '../resources/js/stories/inertia.ts',
                                    import.meta.url,
                                ),
                            ),
                        },
                    ],
                },
            },
        );
    },
};

export default config;
