import type { Ref } from 'vue';
import { ref } from 'vue';

/** What `useCopy` gives to the pages. */
export type UseCopy = {
    /** The last text was copied: the page says so. */
    copied: Ref<boolean>;
    /** The browser refused to copy: the page asks the person to select the text and copy it by hand. */
    failed: Ref<boolean>;
    /** Copies a text to the clipboard; true when it worked. */
    copy: (text: string) => Promise<boolean>;
    /** Copies a text; when the browser refuses, selects the element showing it so the person can press ⌘C. True when it was copied. */
    copyOrSelect: (
        text: string,
        element: HTMLElement | null | undefined,
    ) => Promise<boolean>;
    /** Forgets both, as for a new text to copy. */
    reset: () => void;
};

/**
 * Copying to the clipboard, with *Copied* and the failure of a browser that
 * refuses (a page that is not secure, a permission denied): for an address
 * or a code the person pastes elsewhere.
 *
 * @returns whether the text was copied or the copy failed, the function that copies and the one that forgets
 */
export function useCopy(): UseCopy {
    const copied = ref(false);
    const failed = ref(false);

    const copy = async (text: string): Promise<boolean> => {
        try {
            // Without a clipboard (a page that is not secure) this throws too
            await navigator.clipboard.writeText(text);
            copied.value = true;
            failed.value = false;
        } catch {
            copied.value = false;
            failed.value = true;
        }

        return copied.value;
    };

    return {
        copied,
        failed,
        copy,
        copyOrSelect: async (text, element) => {
            const done = await copy(text);

            if (!done && element) {
                window.getSelection()?.selectAllChildren(element);
            }

            return done;
        },
        reset: () => {
            copied.value = false;
            failed.value = false;
        },
    };
}
