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

    return {
        copied,
        failed,
        copy: async (text) => {
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
        },
        reset: () => {
            copied.value = false;
            failed.value = false;
        },
    };
}
