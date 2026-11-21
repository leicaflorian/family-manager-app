import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { BellNotices } from '@/lib/notices';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        /** The project key of FormKit Pro (`fk-…`). */
        readonly VITE_FORMKIT_PRO_KEY: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** The page is open on a phone linked with a code, which sees only the scan. */
            linked_phone?: boolean;
            family: App.Data.FamilyData | null;
            /** The areas with their active cards, for the sidebar; empty for a guest, missing on a phone linked with a code. */
            areas?: App.Data.AreaCountData[];
            /** The notices of the menu of the bell: sent only when a page asks for them (`only: ['bell_notices']`). */
            bell_notices?: BellNotices;
            /** How many notices the account has not seen yet; 0 for a guest. */
            unread_notices?: number;
            /** The files waiting to be sorted, without the copies; 0 for a guest. */
            to_sort_count?: number;
            /** The link to the bank: what is still to sort; null while it is off for the family, missing on a phone linked with a code. */
            bank_feed?: { unsorted: number } | null;
            /** Whether the family has an electricity, gas or water utility, so the Consumption page exists; missing on a phone linked with a code. */
            has_consumption?: boolean;
            locale: string;
            /** The name of the account the owner looks as during a visit (*Enter as…*); null otherwise. */
            impersonating?: string | null;
            /** The theme of the account; `system` for a guest. */
            theme: App.Enums.Theme;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

/** The version of the app (the content of the `VERSION` file), written by Vite (`define`). */
declare global {
    const __APP_BUILD__: string;
}

export {};
