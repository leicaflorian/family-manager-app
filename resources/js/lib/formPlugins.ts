/**
 * The Vue plugins the forms of the app need: FormKit (with Pro and the
 * settings of the app) and the animation of the lists. Used by the tests of
 * the pages, which mount components outside the app.
 */
import { autoAnimatePlugin } from '@formkit/auto-animate/vue';
import { plugin as formkit } from '@formkit/vue';
import type { Plugin } from 'vue';
import formkitConfig from '@/formkit.config';

/** Plugins for `app.use(…)` or `global.plugins`, each alone or with its options. */
export const formPlugins: Array<Plugin | [Plugin, ...unknown[]]> = [
    [formkit, formkitConfig],
    autoAnimatePlugin,
];
