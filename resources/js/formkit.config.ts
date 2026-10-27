/**
 * FormKit configuration: its own messages in the language of the page and the
 * style of the fields of the canvas (*Components* page). It is the «FmField» of
 * the plan: not a component, but the classes every `<FormKit>` receives.
 */
import { en, it } from '@formkit/i18n';
import { generateClasses } from '@formkit/themes';
import { defaultConfig } from '@formkit/vue';

export default defaultConfig({
    locales: { en, it },
    // The server writes the language in <html lang>; the tests have no document language
    locale: globalThis.document?.documentElement.lang === 'it' ? 'it' : 'en',
    // No icons downloaded from the internet: the dropdown uses the arrow of the phone
    iconLoader: () => undefined,
    iconLoaderUrl: () => '',
    config: {
        classes: generateClasses({
            global: {
                outer: 'mb-3.5 formkit-disabled:opacity-50',
                label: 'block mb-1.5 text-[15px] font-semibold text-ink',
                inner: 'flex items-center h-13 rounded-[14px] border-[1.5px] border-edge bg-white px-3.5 focus-within:border-action focus-within:ring-2 focus-within:ring-action/20 formkit-errors:border-overdue',
                input: 'w-full min-w-0 bg-transparent text-base text-ink outline-none placeholder:text-ink-soft',
                help: 'mt-1 text-[13px] text-ink-soft',
                messages: 'mt-1 list-none p-0',
                message: 'text-sm font-semibold text-overdue',
            },
            // The classes of a type are added to the global ones, they do not replace
            // them: $reset removes them (bg-transparent of global covered bg-action).
            submit: {
                outer: 'mt-2 mb-0',
                inner: '$reset',
                input: '$reset h-14 w-full rounded-2xl bg-action text-[17px] font-bold text-white active:bg-action-dark disabled:bg-edge disabled:text-ink-soft',
            },
            textarea: {
                inner: '$reset flex rounded-[14px] border-[1.5px] border-edge bg-white px-3.5 py-3 focus-within:border-action focus-within:ring-2 focus-within:ring-action/20 formkit-errors:border-overdue',
                input: 'min-h-24',
            },
            'family:box': {
                inner: '$reset flex',
                wrapper:
                    'flex items-center gap-3 min-h-13 rounded-[14px] border-[1.5px] border-edge bg-white px-3.5 has-[:checked]:border-2 has-[:checked]:border-action has-[:checked]:bg-action-soft',
                input: '$reset size-[22px] shrink-0 accent-action',
                label: 'mb-0 text-base font-normal text-ink',
                options: 'flex flex-col gap-2 list-none p-0 m-0',
                legend: 'mb-1.5 text-[15px] font-semibold text-ink',
                fieldset: 'border-0 p-0 m-0',
            },
            // The checkboxes are plain, like «Stay signed in»; the choices (radio) stay boxes.
            checkbox: {
                wrapper: '$reset flex items-start gap-2.5 min-h-11 py-2.5',
                label: '$reset text-[15px] leading-snug text-ink',
            },
        }),
    },
});
