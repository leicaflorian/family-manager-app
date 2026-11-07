/**
 * Storybook preview: gives every story the surroundings the app gives its
 * components: the stylesheet, the translations (menu `locale`), FormKit Pro,
 * the shared props of an Inertia page (parameter `page`), visits that do not
 * leave (they are written in the *Actions* panel) and the light or dark theme.
 */
import { withThemeByClassName } from '@storybook/addon-themes';
import type { Decorator, Preview } from '@storybook/vue3-vite';
import { setup } from '@storybook/vue3-vite';
import { I18n, i18nVue } from 'laravel-vue-i18n';
import { action } from 'storybook/actions';
import { formPlugins } from '../resources/js/lib/formPlugins';
import { i18nOptions } from '../resources/js/lib/i18n';
import type { InertiaConfig } from '@inertiajs/core';
import { blockVisits } from '../resources/js/stories/blockVisits';
import { setStoryPage } from '../resources/js/stories/inertia';
import '../resources/css/app.css';

/** The language a story opens in. */
const defaultLocale = 'it';

// Once for the whole preview: the plugins of the app, like `app.ts` installs them
setup((app) => {
    for (const plugin of formPlugins) {
        if (Array.isArray(plugin)) {
            app.use(...plugin);
        } else {
            app.use(plugin);
        }
    }

    app.use(i18nVue, i18nOptions(defaultLocale));
});

/** Writes each visit Inertia would send in the *Actions* panel. */
const logVisit = action('visit');
let stopBlocking: (() => void) | null = null;

/**
 * Puts the language of the menu and the fake page of the story around it, and
 * stops the visits: a *Delete* or a form in a story must not reach a server.
 *
 * @param story - The story to show.
 * @param context - Its parameters (`page`) and the toolbar globals (`locale`).
 * @returns The story, ready to render.
 */
const withApp: Decorator = (story, context) => {
    const locale = String(context.globals.locale ?? defaultLocale);
    const page = (context.parameters.page ?? {}) as Partial<
        InertiaConfig['sharedPageProps']
    >;

    I18n.getSharedInstance().loadLanguage(locale);
    setStoryPage({ locale, ...page });

    stopBlocking?.();
    stopBlocking = blockVisits(logVisit);

    return story();
};

const preview: Preview = {
    globalTypes: {
        locale: {
            description: 'Language of the texts',
            toolbar: {
                title: 'Language',
                icon: 'globe',
                items: [
                    { value: 'en', title: 'English' },
                    { value: 'it', title: 'Italiano' },
                ],
                dynamicTitle: true,
            },
        },
    },
    initialGlobals: { locale: defaultLocale },
    decorators: [
        withThemeByClassName({
            themes: { light: '', dark: 'dark' },
            defaultTheme: 'light',
            parentSelector: 'html',
        }),
        withApp,
    ],
};

export default preview;
