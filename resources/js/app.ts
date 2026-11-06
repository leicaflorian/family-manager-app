import { autoAnimatePlugin } from '@formkit/auto-animate/vue';
import { plugin as formkit } from '@formkit/vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { I18n, i18nVue } from 'laravel-vue-i18n';
import formkitConfig from '@/formkit.config';
import { initializeFlashToast } from '@/lib/flashToast';
import { i18nOptions } from '@/lib/i18n';
import { pageLayout } from '@/lib/pageLayout';
import { progressColor } from '@/lib/progress';
import { pageVisitOptions } from '@/lib/transitions';

const appName = import.meta.env.VITE_APP_NAME || 'Family Manager';

// The server picks the language and writes it in <html lang>. The pages translate
// the texts of their layout as soon as they are loaded, before the app exists:
// the translations must be ready first.
const lang = document.documentElement.lang || 'en';
I18n.getSharedInstance(i18nOptions(lang));

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: pageLayout,
    withApp: (app) => {
        app.use(formkit, formkitConfig)
            .use(autoAnimatePlugin)
            .use(i18nVue, i18nOptions(lang));

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: progressColor(),
    },
    defaults: {
        visitOptions: (_href, options) => pageVisitOptions(options),
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
