/**
 * The navigation items of the app, the same for the bottom bar of the phone and
 * for the sidebar of the computer.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    CalendarRange,
    FileText,
    LayoutDashboard,
    Settings,
    Shapes,
    Users,
    Wallet,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import {
    agenda,
    areas,
    budget,
    dashboard,
    family,
    logout,
    settings,
    upload,
} from '@/routes';
import { index as documents } from '@/routes/documents';

/** The sections of the app, to know which one to mark. */
export type Section =
    | 'home'
    | 'agenda'
    | 'areas'
    | 'documents'
    | 'budget'
    | 'family'
    | 'settings';

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
};

/** All the items, with the addresses of Upload and of Log out. */
export type NavigationItems = {
    /** Home, Agenda, Areas, Documents, Budget and Family, in this order. */
    sections: NavigationItem[];
    /** The settings, in the account menu or in More. */
    settings: NavigationItem;
    /** The address of Upload. */
    upload: string;
    /** The address to log out (called with POST). */
    logout: string;
};

/**
 * The navigation items with their addresses.
 *
 * @returns the items of the bottom bar and of the sidebar
 */
export function navigationItems(): NavigationItems {
    return {
        sections: [
            {
                section: 'home',
                label: trans('Home'),
                href: dashboard().url,
                icon: LayoutDashboard,
            },
            {
                section: 'agenda',
                label: trans('Agenda'),
                href: agenda().url,
                icon: CalendarRange,
                hint: trans('What expires, month by month'),
            },
            {
                section: 'areas',
                label: trans('Areas'),
                href: areas().url,
                icon: Shapes,
            },
            {
                section: 'documents',
                label: trans('Documents'),
                href: documents().url,
                icon: FileText,
            },
            {
                section: 'budget',
                label: trans('Budget'),
                href: budget().url,
                icon: Wallet,
            },
            {
                section: 'family',
                label: trans('Family'),
                href: family().url,
                icon: Users,
            },
        ],
        settings: {
            section: 'settings',
            label: trans('Settings'),
            href: settings().url,
            icon: Settings,
        },
        upload: upload().url,
        logout: logout().url,
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

    // The reminders are the second tab of the Agenda
    if (path === '/reminders' || path.startsWith('/reminders/')) {
        return 'agenda';
    }

    const all = [...items.sections, items.settings];
    const found = all.find(
        (item) => path === item.href || path.startsWith(`${item.href}/`),
    );

    return found?.section ?? 'home';
}
