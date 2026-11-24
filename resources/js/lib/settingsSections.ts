/**
 * The sections of the *Settings*, one list for the index of the phone (`Settings.vue`) and for the list beside every
 * page of the settings in the wide layout (`FmSettingsPage.vue`).
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Bell,
    CalendarDays,
    CalendarSync,
    FolderTree,
    House,
    Inbox,
    Landmark,
    LayoutGrid,
    LifeBuoy,
    Mail,
    Sparkles,
    Tags,
    ToggleRight,
    User,
} from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import type { Features } from '@/lib/features';
import { featureOn } from '@/lib/features';
import { edit as account } from '@/routes/account';
import { index as banks } from '@/routes/banks';
import { edit as calendar } from '@/routes/calendar';
import { index as categories } from '@/routes/categories';
import { index as expenseCategories } from '@/routes/expense-categories';
import { edit as mail } from '@/routes/mail-settings';
import { news } from '@/routes/settings';
import { edit as areas } from '@/routes/settings/areas';
import { edit as family } from '@/routes/settings/family';
import { edit as featuresPage } from '@/routes/settings/features';
import { edit as notices } from '@/routes/settings/notices';
import { edit as support } from '@/routes/settings/support';

/** A section of the settings, as the page names itself. */
export type SettingsKey =
    | 'account'
    | 'family'
    | 'features'
    | 'notices'
    | 'mail'
    | 'banks'
    | 'calendar'
    | 'categories'
    | 'expense-categories'
    | 'areas'
    | 'support'
    | 'news';

/** The groups of the index: mine, the family and the app. */
export type SettingsGroup = 'mine' | 'family' | 'app';

/** What the server sends to the index of the settings, for the subtitles of its rows. */
export type SettingsSubtitleProps = {
    /** My account. */
    account: App.Data.AccountData;
    /** How many main document categories there are. */
    mainCategories: number;
    /** How many main expense categories there are. */
    mainExpenseCategories: number;
    /** How many areas there are. */
    areaCount: number;
    /** How many areas the family hid. */
    hiddenAreas: number;
    /** How many banks the family linked. */
    bankCount: number;
    /** The features of the family, or null without a family. */
    features: Features | null;
    /** The version that runs. */
    release: string;
    /** The day the version came out (`yyyy-mm-dd`), or null. */
    releasedOn: string | null;
    /** Writes a date as the person reads it (`useFormat().date`). */
    date: (value: string) => string;
};

/** A section of the settings. */
export type SettingsSection = {
    /** The section. */
    key: SettingsKey;
    /** The name, already translated. */
    label: string;
    /** The icon of the list beside the pages (the wide boards). */
    icon: LucideIcon;
    /** The icon of the row on the phone, when the board draws another one. */
    phoneIcon?: LucideIcon;
    /** The address of the page. */
    href: string;
    /** The feature of the family that shows it; without one it is always there. */
    feature?: App.Enums.Feature;
    /** The group of the index. */
    group: SettingsGroup;
    /** The line under the name on the phone, from the props of the page; null for none. */
    subtitle?: (props: SettingsSubtitleProps) => string | null;
};

/**
 * The sections of the settings the family can use, in the order of the boards.
 *
 * @param features - the features of the family; null (no family, tests) shows every section
 * @returns the sections, translated now
 */
export function settingsSections(features: Features | null): SettingsSection[] {
    const all: SettingsSection[] = [
        {
            key: 'account',
            label: trans('My account'),
            icon: User,
            href: account().url,
            group: 'mine',
            subtitle: ({ account: me }) =>
                `${me.first_name} ${me.last_name} · ${me.role}`,
        },
        {
            key: 'calendar',
            label: trans('Calendar'),
            icon: CalendarSync,
            phoneIcon: CalendarDays,
            href: calendar().url,
            group: 'mine',
            subtitle: () => trans('Reminders and deadlines in Google Calendar'),
        },
        {
            key: 'family',
            label: trans('Family name'),
            icon: House,
            href: family().url,
            group: 'family',
            subtitle: (props) => props.account.family,
        },
        {
            key: 'features',
            label: trans('What you use'),
            icon: ToggleRight,
            href: featuresPage().url,
            group: 'family',
            subtitle: ({ features }) => {
                const budget = featureOn(features, 'budget');
                const mail = featureOn(features, 'mail');

                if (budget && mail) {
                    return trans('Budget and Mail for the bills on');
                }

                if (budget) {
                    return trans('Budget on');
                }

                return mail
                    ? trans('Mail for the bills on')
                    : trans('Budget and Mail for the bills off');
            },
        },
        {
            key: 'notices',
            label: trans('Notices'),
            icon: Bell,
            href: notices().url,
            group: 'family',
            subtitle: () => trans('How many days before, where and to whom'),
        },
        {
            key: 'mail',
            label: trans('Address for the bills'),
            icon: Mail,
            phoneIcon: Inbox,
            href: mail().url,
            group: 'family',
            subtitle: () => trans('The bills arrive by email by themselves'),
        },
        {
            key: 'banks',
            label: trans('Linked banks'),
            icon: Landmark,
            href: banks().url,
            feature: 'bank_feed',
            group: 'family',
            subtitle: ({ bankCount }) =>
                bankCount > 0
                    ? transChoice(
                          '{1} :count bank linked|[2,*] :count banks linked',
                          bankCount,
                          { count: String(bankCount) },
                      )
                    : trans('Receive the transactions by themselves'),
        },
        {
            key: 'categories',
            label: trans('Document categories'),
            icon: FolderTree,
            href: categories().url,
            group: 'family',
            subtitle: ({ mainCategories }) =>
                transChoice(
                    '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                    mainCategories,
                    { count: String(mainCategories) },
                ),
        },
        {
            key: 'expense-categories',
            label: trans('Expense categories'),
            icon: Tags,
            href: expenseCategories().url,
            feature: 'budget',
            group: 'family',
            subtitle: ({ mainExpenseCategories }) =>
                transChoice(
                    '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                    mainExpenseCategories,
                    { count: String(mainExpenseCategories) },
                ),
        },
        {
            key: 'areas',
            label: trans('Areas'),
            icon: LayoutGrid,
            href: areas().url,
            group: 'family',
            subtitle: ({ areaCount, hiddenAreas }) =>
                hiddenAreas === 0
                    ? trans(':count areas', { count: String(areaCount) })
                    : transChoice(
                          '{1} :total areas, 1 hidden|[2,*] :total areas, :count hidden',
                          hiddenAreas,
                          {
                              total: String(areaCount),
                              count: String(hiddenAreas),
                          },
                      ),
        },
        {
            key: 'support',
            label: trans('Support'),
            icon: LifeBuoy,
            href: support().url,
            group: 'family',
            subtitle: () =>
                trans('Let us look at the app when you ask for help'),
        },
        {
            key: 'news',
            label: trans("What's new"),
            icon: Sparkles,
            href: news().url,
            group: 'app',
            subtitle: ({ release, releasedOn, date }) => {
                if (releasedOn !== null) {
                    return `${trans('Version :version', { version: release })} · ${date(releasedOn)}`;
                }

                return release === 'dev'
                    ? trans('Development version')
                    : trans('Version :version', { version: release });
            },
        },
    ];

    return all.filter((section) => featureOn(features, section.feature));
}
