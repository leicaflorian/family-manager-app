/**
 * *Share* of a document: the menu of the phone (`navigator.share`) with the
 * file itself, where the browser has it.
 */

/** The file to share, as the pages receive it. */
export type ShareableFile = { name: string; mime_type: string; url: string };

/** What `useShare` gives to the pages. */
export type UseShare = {
    /** The browser has the share menu: otherwise the page offers *Download*. */
    canShare: boolean;
    /** Opens the menu with the file; closing the menu is not an error. */
    shareFile: (file: ShareableFile) => Promise<void>;
};

/**
 * The share menu of the phone, for the files of the family.
 *
 * @returns whether the browser can share, and the function that does it
 */
export function useShare(): UseShare {
    const canShare = typeof globalThis.navigator?.share === 'function';

    return {
        canShare,
        shareFile: async (file) => {
            // The file is private: it is read with the session, then handed to the menu.
            const response = await fetch(file.url, {
                credentials: 'same-origin',
            });
            const shared = new File([await response.blob()], file.name, {
                type: file.mime_type,
            });
            const data: ShareData = navigator.canShare?.({ files: [shared] })
                ? { title: file.name, files: [shared] }
                : { title: file.name };

            try {
                await navigator.share(data);
            } catch (error) {
                // AbortError: the person closed the menu.
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    throw error;
                }
            }
        },
    };
}
