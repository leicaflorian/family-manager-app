/**
 * The actions of a page, shown by `FmActions`: at most three as buttons, as
 * many as fit, and the others in the ⋯ menu.
 */
import type { LucideIcon } from '@lucide/vue';
import type { ButtonVariant } from '@/components/fm/FmButton.vue';

/** The most buttons shown beside the ⋯, however wide the page. */
export const MAX_VISIBLE_ACTIONS = 3;

/** An action of a page: a button while it fits, an entry of the ⋯ menu after. */
export interface Action {
    /** The text of the button and of the entry. */
    label: string;
    /** The icon before the text. */
    icon: LucideIcon;
    /** The look of the button; `danger` makes the entry red too. */
    variant?: ButtonVariant;
    /** The address: if there is one, the action is a link. */
    href?: string;
    /** With `href`: a plain link that downloads the file. */
    download?: boolean;
    /** With `href`: a plain link that opens the address in a new tab. */
    external?: boolean;
    /** What happens when the action is chosen, without `href`. */
    onSelect?: () => void;
    /** Delete, after asking in the panel from below. */
    confirm?: {
        /** The address the deletion is sent to. */
        url: string;
        /** The title of the panel. */
        question: string;
        /** What happens, under the title. */
        text: string;
        /** The text of the button that deletes. */
        confirmLabel: string;
    };
    /** Always in the menu, never a button. */
    menuOnly?: boolean;
}

/**
 * How many buttons fit in the room: at most three, taken from the start, with
 * the ⋯ after them when something is left for the menu.
 *
 * @param sizes - the widths in pixels: `widths` of the buttons in order,
 *   `menuWidth` of the ⋯, `gap` between them and the `available` room;
 *   `menuOnly` when some actions are always in the menu
 * @returns the number of buttons to show, from 0 to three
 */
export function visibleActionCount(sizes: {
    widths: number[];
    menuWidth: number;
    gap: number;
    available: number;
    menuOnly: boolean;
}): number {
    const { widths, menuWidth, gap, available, menuOnly } = sizes;

    for (
        let count = Math.min(MAX_VISIBLE_ACTIONS, widths.length);
        count > 0;
        count--
    ) {
        const buttons = widths
            .slice(0, count)
            .reduce((total, width) => total + width + gap, -gap);
        const withMenu = menuOnly || count < widths.length;

        if (buttons + (withMenu ? gap + menuWidth : 0) <= available) {
            return count;
        }
    }

    return 0;
}
