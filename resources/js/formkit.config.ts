/**
 * FormKit configuration: its own messages in the language of the page and the
 * style of the fields of the canvas (*Components* page). It is the «FmField» of
 * the plan: not a component, but the classes every `<FormKit>` receives. It
 * also adds the fields of FormKit Pro (toggle, autocomplete, datepicker, …).
 */
import { en, it } from '@formkit/i18n';
import { createProPlugin, inputs } from '@formkit/pro';
import type { FormKitExtendableSchemaRoot, FormKitNode } from '@formkit/core';
import { generateClasses } from '@formkit/themes';
import { defaultConfig } from '@formkit/vue';
import { chooseDateOnClick, markToday } from '@/lib/formkitDatepicker';
import { DROPDOWNS, openOnTheSideWithRoom } from '@/lib/formkitDropdownSide';
import { closeOnEmptyChoice } from '@/lib/formkitEmptyChoice';
import { toggleAsSwitch } from '@/lib/formkitSwitch';
import { tabChoosesSuggestion } from '@/lib/formkitTagTab';

// The key is read by the browser anyway: it stays out of the public repository,
// in the .env, so that each environment can have its own
const pro = createProPlugin(import.meta.env.VITE_FORMKIT_PRO_KEY, inputs);

/**
 * A Lucide icon as the SVG text that FormKit puts in its fields.
 *
 * @param paths - the `<path>` elements of the icon, as in Lucide
 * @returns the whole SVG, in the colour of the text
 */
function lucide(paths: string): string {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
}

/**
 * Keeps the list of a dropdown in the page when it is closed, hidden by its
 * classes: FormKit Pro takes it away at once, and it could not fade out.
 *
 * @param node - a field that FormKit is creating
 */
function keepDropdownList(node: FormKitNode): void {
    const definition = node.props.definition;
    const schema = definition?.schema;

    if (
        definition === undefined ||
        typeof schema !== 'function' ||
        !DROPDOWNS.has(String(node.props.type))
    ) {
        return;
    }

    // The field builds its schema from the sections the page gives it (the Vue
    // prop sectionsSchema): the list is added to them here. Closed, it is hidden
    // from screen readers too.
    const keepList: FormKitExtendableSchemaRoot = (sections) =>
        schema({
            ...sections,
            listbox: {
                if: 'true',
                attrs: {
                    'aria-hidden': '$expanded !== true || undefined',
                    'aria-labelledby':
                        '$expanded && $id + "_label" || undefined',
                },
            },
        });
    node.props.definition = { ...definition, schema: keepList };
}

export default defaultConfig({
    plugins: [
        pro,
        keepDropdownList,
        toggleAsSwitch,
        closeOnEmptyChoice,
        chooseDateOnClick,
        markToday,
        tabChoosesSuggestion,
        openOnTheSideWithRoom,
    ],
    locales: { en, it },
    // The server writes the language in <html lang>; the tests have no document language
    locale: globalThis.document?.documentElement.lang === 'it' ? 'it' : 'en',
    // No icons downloaded from the internet: the ones the dropdown needs are here
    iconLoader: () => undefined,
    iconLoaderUrl: () => '',
    icons: {
        select: lucide('<path d="m6 9 6 6 6-6"/>'),
        check: lucide('<path d="M20 6 9 17l-5-5"/>'),
        close: lucide('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'),
        loader: lucide('<path d="M21 12a9 9 0 1 1-6.219-8.56"/>'),
        date: lucide(
            '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        ),
        left: lucide('<path d="m15 18-6-6 6-6"/>'),
        right: lucide('<path d="m9 18 6-6-6-6"/>'),
        checkboxDecorator: lucide('<path d="M20 6 9 17l-5-5"/>'),
    },
    config: {
        // The datepickers show the dates as 15/10/2026 and give them to the form as
        // 2026-10-15, as the server reads them. Only the datepicker (and the
        // colorpicker, not used) reads these two.
        format: 'DD/MM/YYYY',
        valueFormat: 'YYYY-MM-DD',
        // Italian weeks start on Monday
        weekStart: 1,
        // The amounts are in euros and read as 84,20 €. Only currency and unit read these.
        currency: 'EUR',
        displayLocale: 'it-IT',
        classes: generateClasses({
            global: {
                outer: 'mb-3.5 formkit-disabled:opacity-50',
                label: 'block mb-1.5 text-[15px] font-semibold text-ink',
                inner: 'flex items-center h-11.5 rounded-[10px] border-[1.5px] border-edge bg-white px-3.5 focus-within:border-action focus-within:ring-2 focus-within:ring-action/20 formkit-errors:border-overdue transition-[border-color,box-shadow] duration-150 ease-out motion-reduce:transition-none',
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
                input: '$reset h-11.5 w-full rounded-[10px] bg-action text-base font-normal text-white active:bg-action-dark disabled:bg-edge disabled:text-ink-soft',
            },
            textarea: {
                inner: '$reset flex rounded-[10px] border-[1.5px] border-edge bg-white px-3.5 py-3 focus-within:border-action focus-within:ring-2 focus-within:ring-action/20 formkit-errors:border-overdue transition-[border-color,box-shadow] duration-150 ease-out motion-reduce:transition-none',
                input: 'min-h-24',
            },
            'family:box': {
                inner: '$reset flex',
                wrapper:
                    'flex items-center gap-3 min-h-11.5 rounded-[10px] border-[1.5px] border-edge bg-white px-3.5 has-[:checked]:border-2 has-[:checked]:border-action has-[:checked]:bg-action-soft',
                input: '$reset size-[22px] shrink-0 accent-action',
                label: 'mb-0 text-base font-normal text-ink',
                options: 'flex flex-col gap-2 list-none p-0 m-0',
                legend: 'mb-1.5 text-[15px] font-semibold text-ink',
                fieldset: 'border-0 p-0 m-0',
            },
            // The toggle: the switch and its label on one row, without the box of the
            // fields. The checkbox stays (hidden) for the keyboard and the screen
            // readers: the track follows it with peer-checked, the thumb with *.
            toggle: {
                wrapper: '$reset flex items-center gap-3 min-h-11',
                inner: '$reset relative flex shrink-0 cursor-pointer',
                input: '$reset peer sr-only',
                track: '$reset relative h-[26px] w-11 rounded-full bg-edge transition-colors duration-150 peer-checked:bg-action peer-focus-visible:ring-2 peer-focus-visible:ring-action/20 *:transition-transform *:duration-150 peer-checked:*:translate-x-[18px] motion-reduce:transition-none motion-reduce:*:transition-none',
                thumb: '$reset absolute top-[3px] left-[3px] size-5 rounded-full bg-white shadow-[0_1px_2px_rgba(24,33,47,0.25)]',
                label: '$reset cursor-pointer text-base leading-snug text-ink',
            },
            // A choice among few options: the segments on a grey track, the chosen one
            // white with a shadow. As high as the fields, track included.
            togglebuttons: {
                options:
                    '$reset m-0 flex list-none gap-1 rounded-[10px] bg-segment p-1',
                option: '$reset min-w-0 flex-1',
                input: '$reset h-[38px] w-full cursor-pointer rounded-[7px] px-2 text-[15px] text-ink-soft transition-[background-color,color,box-shadow] duration-150 ease-out aria-pressed:bg-white aria-pressed:text-ink aria-pressed:shadow-[0_1px_3px_rgba(24,33,47,0.12)] focus-visible:outline-2 focus-visible:outline-action motion-reduce:transition-none',
            },
            // The checkboxes are plain, like «Stay signed in»; the choices (radio) stay boxes.
            // The box is drawn here, with the border and the corners of the fields;
            // the tick is the decorator, over the box.
            checkbox: {
                wrapper: '$reset flex items-center gap-3 min-h-11',
                inner: '$reset relative flex shrink-0',
                input: '$reset peer size-[22px] shrink-0 cursor-pointer appearance-none rounded-md border-[1.5px] border-edge bg-white checked:border-action checked:bg-action focus-visible:ring-2 focus-visible:ring-action/20 focus-visible:outline-none',
                decorator:
                    '$reset pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100',
                decoratorIcon: '$reset flex size-4',
                label: '$reset text-base leading-snug text-ink',
            },
            // The dropdown, autocomplete and taglist of FormKit Pro: the list opens
            // over the page with a short fade, and the arrow turns.
            'family:dropdown': {
                inner: 'relative',
                selector:
                    '$reset flex h-full min-w-0 grow cursor-pointer items-center justify-between gap-2 bg-transparent text-left text-base text-ink outline-none',
                placeholder: 'text-ink-soft',
                selectIcon:
                    'flex size-[18px] shrink-0 text-ink-soft motion-safe:transition-transform motion-safe:duration-150 in-data-expanded:rotate-180',
                // FormKit sets the z-index of the closed list to -1: it would fade
                // out under the page
                // The holder lets the clicks through and, closed, is 0 high (once the
                // list has faded out): the closed list stays in the page, and would
                // cover the fields below and make the page longer
                // Without room under the field the list opens over it (data-side,
                // from openOnTheSideWithRoom)
                dropdownWrapper:
                    'pointer-events-none absolute top-full right-0 left-0 z-50! mt-1.5 max-h-0! data-[side=top]:top-auto! data-[side=top]:bottom-full! data-[side=top]:mt-0 data-[side=top]:mb-1.5 overflow-hidden! transition-[max-height] delay-150 duration-0 in-data-expanded:max-h-[400px]! in-data-expanded:overflow-visible! in-data-expanded:delay-0 motion-reduce:delay-0',
                listbox:
                    'invisible m-0 max-h-72 -translate-y-1 list-none overflow-auto rounded-[10px] border border-edge bg-white p-1.5 opacity-0 shadow-menu outline-none motion-safe:transition-[opacity,translate,visibility] motion-safe:duration-150 motion-safe:ease-out in-data-expanded:pointer-events-auto in-data-expanded:visible in-data-expanded:translate-y-0 in-data-expanded:opacity-100',
                listitem:
                    'relative flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-3 text-[15px] text-ink data-[is-active=true]:bg-canvas aria-selected:text-action-dark',
                selectedIcon:
                    'order-last ml-auto flex size-4 shrink-0 text-action',
                option: 'min-w-0 grow truncate',
                emptyMessage: 'px-3 py-2.5 text-[15px] text-ink-soft',
            },
            // The taglist: the tags are grey chips in the box, which grows with them;
            // there is no list to open, so no arrow. The box keeps the classes of
            // the fields: only its height and padding change (! wins over them).
            // The datepicker: the calendar opens over the page under the field, as
            // the list of a dropdown; the chosen day is a blue circle, the days of
            // the months around are faint. The button with the calendar opens it.
            datepicker: {
                inner: 'relative',
                // FormKit Pro writes the format (DD/MM/YYYY) in the field as text, to
                // type the date over it: empty and without the focus it does not show
                input: 'in-data-empty:not-focus:text-transparent',
                openButton:
                    '$reset flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-ink-soft hover:text-ink',
                calendarIcon: 'flex size-5',
                // FormKit adds the panel only when it opens: it fades in from its
                // starting style, as the other menus; it goes away at once
                panelWrapper:
                    'absolute top-full left-0 z-50 mt-1.5 w-[320px] max-w-[calc(100vw-32px)] rounded-[14px] border border-edge bg-white p-3 shadow-menu outline-none motion-safe:transition-[opacity,translate] motion-safe:duration-150 motion-safe:ease-out starting:-translate-y-1 starting:opacity-0',
                panelHeader: 'mb-2 flex items-center justify-between gap-2',
                prev: '$reset flex size-9 cursor-pointer items-center justify-center rounded-lg text-ink hover:bg-canvas motion-safe:transition-colors motion-safe:duration-150',
                next: '$reset flex size-9 cursor-pointer items-center justify-center rounded-lg text-ink hover:bg-canvas motion-safe:transition-colors motion-safe:duration-150',
                prevLabel: 'sr-only',
                nextLabel: 'sr-only',
                prevIcon: 'flex size-5',
                nextIcon: 'flex size-5',
                daysHeader: 'flex items-center gap-1',
                monthButton:
                    '$reset h-9 cursor-pointer rounded-lg px-2 text-[15px] font-bold text-ink capitalize hover:bg-canvas motion-safe:transition-colors motion-safe:duration-150',
                yearButton:
                    '$reset h-9 cursor-pointer rounded-lg px-2 text-[15px] font-bold text-ink hover:bg-canvas motion-safe:transition-colors motion-safe:duration-150',
                calendar: 'w-full border-collapse',
                weekDays: 'grid grid-cols-7',
                weekDay:
                    'flex h-8 items-center justify-center text-xs font-semibold text-ink-soft capitalize',
                calendarWeeks: 'flex flex-col gap-0.5',
                week: 'grid grid-cols-7',
                dayCell: 'flex cursor-pointer justify-center outline-none',
                day: 'flex size-10 items-center justify-center rounded-full motion-safe:transition-colors motion-safe:duration-150 text-[15px] text-ink hover:bg-canvas in-data-today:font-bold in-data-today:text-action in-data-today:ring-1 in-data-today:ring-action in-aria-selected:bg-action! in-aria-selected:font-bold in-aria-selected:text-white! in-data-is-extra:text-ink-soft/50 in-focus-visible:ring-2 in-focus-visible:ring-action/30',
                months: 'grid grid-cols-3 gap-1',
                month: 'flex h-11 cursor-pointer items-center justify-center rounded-lg text-[15px] text-ink capitalize hover:bg-canvas aria-selected:bg-action! aria-selected:text-white motion-safe:transition-colors motion-safe:duration-150',
                years: 'grid grid-cols-3 gap-1',
                year: 'flex h-11 cursor-pointer items-center justify-center rounded-lg text-[15px] text-ink hover:bg-canvas aria-selected:bg-action! aria-selected:text-white motion-safe:transition-colors motion-safe:duration-150',
            },
            taglist: {
                inner: 'h-auto! min-h-11.5 px-2.5! py-[5px]',
                tags: 'flex min-w-0 grow flex-wrap items-center gap-1.5',
                tagWrapper: 'flex',
                tag: '$reset flex h-8 items-center gap-1 rounded-full bg-divider pr-1 pl-3 text-sm font-semibold text-ink',
                removeSelection:
                    '$reset flex size-6 cursor-pointer items-center justify-center rounded-full text-ink-soft hover:text-ink *:flex *:size-3.5',
                input: '$reset h-8 min-w-20 grow basis-20 bg-transparent text-[15px] text-ink outline-none placeholder:text-ink-soft',
                listboxButton: 'hidden',
            },
        }),
    },
});
