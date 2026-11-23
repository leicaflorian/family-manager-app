/**
 * The navigation items of the app, one list for the bottom bar of the phone and
 * for the sidebar (always open on the computer, opened from the menu on the
 * tablet): each places them in its own way (To sort and Received mail, for
 * example, sit under the sections in the sidebar and elsewhere on the phone).
 */
import type { LucideIcon } from '@lucide/vue';
import {
    BellPlus,
    CalendarRange,
    FileText,
    Inbox,
    LayoutDashboard,
    Mail,
    MessageSquareWarning,
    Receipt,
    ScanLine,
    Settings,
    Shapes,
    Upload,
    Users,
    Wallet,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { Features } from '@/lib/features';
import { featureOn } from '@/lib/features';
import { useAppName } from '@/composables/useAppName';
import {
    agenda,
    areas,
    budget,
    dashboard,
    family,
    logout,
    scan,
    settings,
    toSort,
    upload,
} from '@/routes';
import { create as newMovement } from '@/routes/budget/movements';
import { index as documents } from '@/routes/documents';
import { index as mail } from '@/routes/mail';
import { create as newReminder } from '@/routes/reminders';

/** The sections of the app, to know which one to mark. */
export type Section =
    | 'home'
    | 'agenda'
    | 'areas'
    | 'documents'
    | 'budget'
    | 'family'
    | 'settings'
    | 'toSort'
    | 'mail';

/** The groups of the sidebar: Home alone, what arrives, what is used every day and what is kept. */
export type SectionGroupKey = 'home' | 'incoming' | 'every-day' | 'archive';

/** A section of the app in the registry: where it stands, which feature it needs and which pages are its own. */
export type SectionEntry = {
    /** The section. */
    key: Section;
    /** The feature of the family that shows it; without one, it is always there. */
    feature?: App.Enums.Feature;
    /** The place in the bar of the phone (0, 1, 2), if it stays there. */
    bar?: number;
    /** It takes the place of a missing item of the bar. */
    barFallback?: boolean;
    /** The group of the sidebar. */
    group: SectionGroupKey;
    /** The Inertia pages of the section and its top tabs: they show no trail. */
    pages: string[];
};

/** Settings: always in the menus, with the account. */
const SETTINGS_ENTRY: SectionEntry = {
    key: 'settings',
    group: 'archive',
    pages: ['settings/Settings'],
};

/**
 * The sections of the app, in the order of the menus. Menus, trail and the section of a page come from here: a
 * section that depends on a feature of the family says it with `feature`.
 */
export const SECTIONS: readonly SectionEntry[] = [
    { key: 'home', bar: 0, group: 'home', pages: ['Home'] },
    { key: 'agenda', barFallback: true, group: 'every-day', pages: ['Agenda'] },
    { key: 'areas', bar: 2, group: 'archive', pages: ['Areas'] },
    { key: 'documents', group: 'archive', pages: ['documents/Documents'] },
    {
        key: 'budget',
        bar: 1,
        group: 'every-day',
        pages: [
            'budget/Movements',
            'budget/Planned',
            'budget/BankTransactions',
        ],
    },
    {
        key: 'family',
        group: 'archive',
        pages: ['family/People', 'family/Organizations'],
    },
    { key: 'toSort', group: 'incoming', pages: ['documents/ToSort'] },
    { key: 'mail', group: 'incoming', pages: ['mail/ReceivedMails'] },
    SETTINGS_ENTRY,
];

/** An item of the navigation. */
export type NavigationItem = {
    /** The section it leads to. */
    section: Section;
    /** The text of the item, already translated. */
    label: string;
    /** The address. */
    href: string;
    /** The Lucide icon. */
    icon: LucideIcon;
    /** A line below the label in More on the phone, if the item has one. */
    hint?: string;
    /** The group of the sidebar the item stands in. */
    group: SectionGroupKey;
    /** The place in the bar of the phone, if the item stays there. */
    bar?: number;
    /** The item takes the place of a missing one of the bar. */
    barFallback?: boolean;
};

/** What an item says about itself; its place comes from the registry. */
type ItemText = Omit<NavigationItem, 'group' | 'bar' | 'barFallback'>;

/** A way to add something, in the panel *Add* of the + on the phone. */
export type AddItem = {
    /** What it adds, already translated. */
    label: string;
    /** The line below: how, already translated. */
    hint: string;
    /** The address. */
    href: string;
    /** The Lucide icon. */
    icon: LucideIcon;
};

/** An item that opens a panel instead of a page. */
export type PanelItem = {
    /** The text of the item, already translated. */
    label: string;
    /** The line below the label in More on the phone. */
    hint: string;
    /** The Lucide icon. */
    icon: LucideIcon;
};

/** All the items, with the addresses of Upload and of Log out. */
export type NavigationItems = {
    /** Home, Agenda, Areas, Documents, Budget and Family, in this order. */
    sections: NavigationItem[];
    /** The settings, in the account menu or in More. */
    settings: NavigationItem;
    /** To sort, an item of the sidebar only: on the phone the number on Upload leads there. */
    toSort?: NavigationItem;
    /** Received mail, under To sort in the sidebar and after Documents in More on the phone. */
    mail?: NavigationItem;
    /** The address of Upload. */
    upload: string;
    /** The ways to add something, in the panel of the + on the phone, after To sort. */
    add: AddItem[];
    /** The address to log out (called with POST). */
    logout: string;
    /** *Report a problem or an idea*, in the account menu and in More, before Log out: it opens a panel. */
    feedback: PanelItem;
};

/**
 * What each section says about itself: label, address, icon and hint.
 *
 * @returns the texts by section, translated now (the translations are ready only when the app runs)
 */
function itemTexts(): Record<Section, ItemText> {
    return {
        home: {
            section: 'home',
            label: trans('Home'),
            href: dashboard().url,
            icon: LayoutDashboard,
        },
        agenda: {
            section: 'agenda',
            label: trans('Agenda'),
            href: agenda().url,
            icon: CalendarRange,
            hint: trans('What expires, month by month'),
        },
        areas: {
            section: 'areas',
            label: trans('Areas'),
            href: areas().url,
            icon: Shapes,
        },
        documents: {
            section: 'documents',
            label: trans('Documents'),
            href: documents().url,
            icon: FileText,
        },
        budget: {
            section: 'budget',
            label: trans('Budget'),
            href: budget().url,
            icon: Wallet,
        },
        family: {
            section: 'family',
            label: trans('Family'),
            href: family().url,
            icon: Users,
        },
        settings: {
            section: 'settings',
            label: trans('Settings'),
            href: settings().url,
            icon: Settings,
        },
        toSort: {
            section: 'toSort',
            label: trans('To sort'),
            href: toSort().url,
            icon: Inbox,
            hint: trans('Files uploaded to complete'),
        },
        mail: {
            section: 'mail',
            label: trans('Received mail'),
            href: mail().url,
            icon: Mail,
            hint: trans('The mail that arrived at the address for bills'),
        },
    };
}

/**
 * The navigation items with their addresses.
 *
 * @param features - the features of the family; null (no family, tests) shows every section
 * @param entries - the registry; the sections of the app unless a test passes its own
 * @returns the items of the bottom bar and of the sidebar, without the sections of a feature that is off
 */
export function navigationItems(
    features: Features | null = null,
    entries: readonly SectionEntry[] = SECTIONS,
): NavigationItems {
    const texts = itemTexts();
    const build = (entry: SectionEntry): NavigationItem => ({
        ...texts[entry.key],
        group: entry.group,
        ...(entry.bar === undefined ? {} : { bar: entry.bar }),
        ...(entry.barFallback ? { barFallback: true } : {}),
    });
    // A section whose feature is off has no item: a list of its own may also leave a section out
    const shown = (key: Section): NavigationItem | undefined => {
        const entry = entries.find((candidate) => candidate.key === key);

        return entry && featureOn(features, entry.feature)
            ? build(entry)
            : undefined;
    };
    const apart: Section[] = ['settings', 'toSort', 'mail'];
    const toSortItem = shown('toSort');
    const mailItem = shown('mail');

    return {
        sections: entries
            .filter(
                (entry) =>
                    !apart.includes(entry.key) &&
                    featureOn(features, entry.feature),
            )
            .map(build),
        settings: shown('settings') ?? build(SETTINGS_ENTRY),
        ...(toSortItem ? { toSort: toSortItem } : {}),
        ...(mailItem ? { mail: mailItem } : {}),
        upload: upload().url,
        add: [
            {
                label: trans('Upload a file'),
                hint: trans('PDF or photos from the phone'),
                href: upload().url,
                icon: Upload,
            },
            {
                label: trans('Scan a sheet'),
                hint: trans('Photograph a sheet with the camera'),
                // the page tries to open the camera at once: Android lets it, Safari on the iPhone waits for a tap
                href: scan({ query: { camera: 1 } }).url,
                icon: ScanLine,
            },
            {
                label: trans('New expense'),
                hint: trans('An expense already made, without a document'),
                href: newMovement().url,
                icon: Receipt,
            },
            {
                label: trans('New task'),
                hint: trans('Something to remember, with the date'),
                href: newReminder().url,
                icon: BellPlus,
            },
        ],
        logout: logout().url,
        feedback: {
            label: trans('Report a problem or an idea'),
            hint: trans('It reaches who makes :app', {
                app: useAppName().value,
            }),
            icon: MessageSquareWarning,
        },
    };
}

/**
 * The section of a page, from its address.
 *
 * @param path - the path of the page, for example `/documents/12`
 * @param items - the navigation items
 * @returns the section of the item that opens that path, or `home`
 */
export function sectionOf(path: string, items: NavigationItems): Section {
    // The cards belong to Areas: an area, a card, its form, New card
    if (path === '/cards' || path.startsWith('/cards/')) {
        return 'areas';
    }

    // The task form (`/reminders/create`, `/reminders/<id>/edit`) opens over the Agenda
    if (path === '/reminders' || path.startsWith('/reminders/')) {
        return 'agenda';
    }

    // Upload and Import are the pages of the files, which To sort collects
    if (['/upload', '/import'].includes(path)) {
        return 'toSort';
    }

    const found = allItems(items).find(
        (item) => path === item.href || path.startsWith(`${item.href}/`),
    );

    return found?.section ?? 'home';
}

/**
 * Every item of the navigation in one list, in the order of the registry: the sections, To sort, Received mail and
 * Settings. The menus, the trail and the sidebar groups read this list.
 *
 * @param items - the navigation items
 * @returns all the items
 */
export function allItems(items: NavigationItems): NavigationItem[] {
    const byKey = new Map(
        [
            ...items.sections,
            ...(items.toSort ? [items.toSort] : []),
            ...(items.mail ? [items.mail] : []),
            items.settings,
        ].map((item) => [item.section, item] as const),
    );

    return SECTIONS.flatMap((entry) => byKey.get(entry.key) ?? []);
}

/**
 * The items that stay in the bar of the phone, in order: each takes its place (`bar`); a missing one (its feature is
 * off) is replaced by the item marked `barFallback`.
 *
 * @param items - the navigation items
 * @returns the items of the bar, three of them
 */
export function barItems(items: NavigationItems): NavigationItem[] {
    const fallbacks = items.sections.filter((item) => item.barFallback);

    return [0, 1, 2].flatMap((slot): NavigationItem[] => {
        const item =
            items.sections.find((candidate) => candidate.bar === slot) ??
            fallbacks.shift();

        return item ? [item] : [];
    });
}
