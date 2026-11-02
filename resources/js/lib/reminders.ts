/**
 * The words and the logic of the reminders and notices: how a rule of a group
 * reads, when a notice went out, and how the list of the reminders is grouped,
 * searched, counted by area and postponed. The pages and the components do
 * these the same way, so they live here.
 */
import { format } from '@formkit/tempo';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import type { Tab } from '@/components/fm/FmTabs.vue';
import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { edit, index as remindersIndex, postpone } from '@/routes/reminders';
import { agenda } from '@/routes';
import { shortDate } from './budget';
import { addDays, addMonths, endOfWeek } from './days';
import { daysFromToday, toDate } from './format';
import { plainText } from './text';

/** Before or after the due date; the day itself is 0 days. */
export type NoticeTiming = 'before' | 'after';

/** Where a notice arrives: the app, the email, or both. */
export type NoticeChannels = 'app' | 'email' | 'both';

/** One notice of a group, in the shape the server saves it. */
export type NoticeRule = {
    /** The name saved with the row; the page keeps it and does not show it. */
    name?: string;
    /** How many days from the due date, 0 for the day itself. */
    days: number;
    /** Before or after the due date. */
    timing: NoticeTiming;
    /** Where the notice arrives. */
    channels: NoticeChannels;
    /** `'all'` alone, or the ids of the people chosen. */
    recipients: ('all' | number)[];
};

/** A person with an account, as a rule names them. */
export type RulePerson = {
    /** The id of the person. */
    id: number;
    /** The name. */
    name: string;
    /** The initials, for the circle. */
    initials: string;
    /** The address of the photo. */
    photo: string | null;
};

/**
 * Where a notice arrives, in words.
 *
 * @param channels - app, email or both
 * @returns for example «App and email»
 */
export function channelsText(channels: NoticeChannels): string {
    switch (channels) {
        case 'app':
            return trans('App only');
        case 'email':
            return trans('Email only');
        default:
            return trans('App and email');
    }
}

/**
 * When a notice goes out, in words.
 *
 * @param days - the days from the due date
 * @param timing - before or after
 * @returns for example «45 days before», «On the day»
 */
export function timingText(days: number, timing: NoticeTiming): string {
    if (days === 0) {
        return trans('On the day');
    }

    return timing === 'before'
        ? transChoice('{1} :count day before|[2,*] :count days before', days, {
              count: String(days),
          })
        : transChoice('{1} :count day after|[2,*] :count days after', days, {
              count: String(days),
          });
}

/**
 * Who gets a notice, in words. A person who is not among the people with an
 * account is skipped, and without anyone left it is everyone, as the server
 * does.
 *
 * @param recipients - `'all'` or the ids of the people
 * @param people - the people with an account
 * @returns «Everyone» or the names, separated by commas
 */
export function recipientsText(
    recipients: ('all' | number)[],
    people: RulePerson[],
): string {
    const names = people
        .filter((person) => recipients.includes(person.id))
        .map((person) => person.name);

    return recipients.includes('all') || names.length === 0
        ? trans('Everyone')
        : names.join(', ');
}

/**
 * One notice of a group on one line.
 *
 * @param rule - the notice
 * @param people - the people with an account, to name the recipients
 * @returns for example «45 days before · App and email · Everyone»
 */
export function ruleText(rule: NoticeRule, people: RulePerson[]): string {
    return [
        timingText(rule.days, rule.timing),
        channelsText(rule.channels),
        recipientsText(rule.recipients, people),
    ].join(' · ');
}

/**
 * When a notice went out: the weekday and the day if it was in the last week,
 * the day alone if older, then the time.
 *
 * @param sentAt - the moment, written by the server in the time of Rome
 * @param today - the moment taken as now, the clock if missing
 * @param locale - the language of the weekday, the one of the page if missing
 * @returns for example «Saturday 24/10 at 6:00» or «07/10 at 6:00»
 */
export function sentAtText(
    sentAt: string,
    today: Date = new Date(),
    locale: string = getActiveLanguage(),
): string {
    const day = shortDate(sentAt);
    const ago = -daysFromToday(sentAt, today);
    const weekday = format(toDate(sentAt), 'dddd', locale);
    const when =
        ago >= 0 && ago < 7
            ? `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${day}`
            : day;
    const time = sentAt.slice(11, 16).replace(/^0/, '');

    return trans(':when at :time', { when, time });
}

/** A reminder as the list shows it. */
export type ReminderItem = {
    /** The id of the reminder. */
    id: number;
    /** What to do: «Renew the health card». */
    title: string;
    /** The day it is due, `yyyy-mm-dd`; the new day when it was postponed. */
    due_date: string;
    /** The day of the deadline it was postponed from, `yyyy-mm-dd`, or null. */
    postponed_from: string | null;
    /** It is marked as done. */
    done: boolean;
    /** It was written by hand, not born from a date of a card or document. */
    manual: boolean;
    /** The area of the thing it refers to, `general` when it refers to nothing. */
    area: string;
    /** The thing it refers to, with the address of its page if it has one. */
    subject: { label: string; href: string | null } | null;
    /** Who takes care of it. */
    person: { name: string; initials: string; photo: string | null } | null;
    /** The day of the next notice, `yyyy-mm-dd`, or null if none is left. */
    next_notice_on: string | null;
    /** A payment of the card is not in the budget. */
    missing_planned_expense: boolean;
    /** Where the row leads: the card, or the form of a reminder written by hand. */
    href: string;
    /** The form of the reminder, when the person may change it. */
    edit_href: string | null;
    /** The card or document an automatic reminder comes from, when it has a page. */
    card_href: string | null;
    /** The person may delete it. */
    can_delete: boolean;
};

/** A block of the list of the reminders to do. */
export type ReminderGroup = {
    /** Late, this week (today to Sunday), or later. */
    key: 'late' | 'week' | 'later';
    /** The reminders of the block, in the order of the server. */
    items: ReminderItem[];
};

/** A quick choice of *Postpone*: its words and the day it moves the reminder to. */
export type PostponeChoice = {
    /** For example «Tomorrow». */
    label: string;
    /** The new day, `yyyy-mm-dd`. */
    to: string;
};

/** A notice of the bell as the list shows it. */
export type NoticeItem = {
    /** The id of the notice. */
    id: number;
    /** What is due, with the thing it refers to: «Renew the policy · Panda car insurance». */
    title: string;
    /** The line about the deadline as the notice said it that day. */
    due_text: string;
    /** When it went out, written by the server in the time of Rome. */
    sent_at: string;
    /** The person already saw it. */
    read: boolean;
    /** The area of the thing it refers to. */
    area: string | null;
    /** What the reminder is today: late and still open, or done. */
    state: 'overdue' | 'done' | null;
    /** Where the notice leads. */
    href: string;
};

/** A group of notices as *Settings → Notices* shows it. */
export type ReminderGroupItem = {
    /** The name of the group. */
    name: string;
    /** It is the group of everything that has no group of its own. */
    is_default: boolean;
    /** The kinds of deadline it covers, in words: «Road tax», «inspection». */
    kinds: string[];
    /** Its notices. */
    rules: NoticeRule[];
};

/**
 * A reminder as the server sends it, in the shape the row of the list reads.
 *
 * @param reminder - the reminder of `RemindersPageData`
 * @returns the item for `FmReminderRow`
 */
export function toReminderItem(reminder: App.Data.ReminderData): ReminderItem {
    const { subject, person } = reminder;

    return {
        id: reminder.id,
        title: reminder.title,
        due_date: reminder.due_date,
        postponed_from: reminder.postponed_from,
        done: reminder.status === 'done',
        manual: !reminder.is_automatic,
        area: subject?.area ?? 'general',
        subject:
            subject === null
                ? null
                : { label: subject.label, href: subject.href },
        person:
            person === null
                ? null
                : {
                      name: person.name,
                      initials: person.initials,
                      photo: person.photo,
                  },
        next_notice_on: reminder.next_notice,
        missing_planned_expense: reminder.missing_expense,
        href: reminder.href,
        edit_href: reminder.can_edit ? edit.url(reminder.id) : null,
        card_href: reminder.is_automatic ? (subject?.href ?? null) : null,
        can_delete: reminder.can_delete,
    };
}

/**
 * Splits the reminders to do as the list shows them: the late ones (their day
 * is passed), this week (today to Sunday) and later.
 *
 * @param items - the reminders to do, in the order of the server
 * @param today - today, `yyyy-mm-dd`
 * @returns the blocks, without the empty ones
 */
export function groupReminders(
    items: ReminderItem[],
    today: string,
): ReminderGroup[] {
    const sunday = endOfWeek(today);
    const keyOf = (item: ReminderItem): ReminderGroup['key'] =>
        item.due_date < today
            ? 'late'
            : item.due_date <= sunday
              ? 'week'
              : 'later';

    return (['late', 'week', 'later'] as const)
        .map((key) => ({
            key,
            items: items.filter((item) => keyOf(item) === key),
        }))
        .filter((group) => group.items.length > 0);
}

/**
 * Whether a reminder holds what is searched, in its title or in the name of
 * the thing it refers to, without capitals and accents.
 *
 * @param item - the reminder
 * @param search - the text typed
 * @returns true when it holds it, or nothing is typed
 */
export function matchesReminder(item: ReminderItem, search: string): boolean {
    const text = plainText(search.trim());

    return [item.title, item.subject?.label ?? ''].some((part) =>
        plainText(part).includes(text),
    );
}

/**
 * The quick choices of *Postpone*, counted from today.
 *
 * @param today - today, `yyyy-mm-dd`
 * @returns tomorrow, in a week and in a month
 */
export function postponeChoices(today: string): PostponeChoice[] {
    return [
        { label: trans('Tomorrow'), to: addDays(today, 1) },
        { label: trans('In a week'), to: addDays(today, 7) },
        { label: trans('In a month'), to: addMonths(today, 1) },
    ];
}

/**
 * Moves a reminder to another day; the page stays where it is.
 *
 * @param id - the reminder
 * @param to - the new day, `yyyy-mm-dd`, after today
 * @param options - what to do when the server answers
 */
export function postponeReminder(
    id: number,
    to: string,
    options: VisitOptions = {},
): void {
    router.post(postpone.url(id), { to }, { preserveScroll: true, ...options });
}

/**
 * How many reminders each area has. The areas turned off count too: their
 * number stays when their name is struck.
 *
 * @param items - the reminders
 * @param areas - the areas the server sends, in order
 * @returns the count of each area, zero included
 */
export function reminderAreaCounts(
    items: ReminderItem[],
    areas: App.Data.OptionData[],
): Record<string, number> {
    const counts: Record<string, number> = Object.fromEntries(
        areas.map((area) => [area.value, 0]),
    );

    for (const item of items) {
        counts[item.area] = (counts[item.area] ?? 0) + 1;
    }

    return counts;
}

/**
 * A notice as the server sends it, in the shape the row of the list reads.
 *
 * @param notice - the notice of `ReminderNoticeData`
 * @returns the item for `FmNoticeRow`
 */
export function toNoticeItem(notice: App.Data.ReminderNoticeData): NoticeItem {
    return {
        id: notice.id,
        title: notice.title,
        due_text: notice.due_text,
        sent_at: notice.sent_at,
        read: notice.read,
        area: notice.area,
        state:
            notice.state === 'expired'
                ? 'overdue'
                : notice.state === 'done'
                  ? 'done'
                  : null,
        href: notice.href,
    };
}

/**
 * The tabs of the Agenda, shared by *Deadlines* and *Reminders* so neither builds its own.
 *
 * @param current - the page that is open
 * @param open - how many reminders are still to do
 * @returns the two tabs, with the open one marked
 */
export function agendaTabs(
    current: 'deadlines' | 'reminders',
    open: number,
): Tab[] {
    return [
        {
            label: trans('Deadlines'),
            href: agenda().url,
            current: current === 'deadlines',
        },
        {
            label: trans('Reminders'),
            href: remindersIndex().url,
            count: open,
            current: current === 'reminders',
        },
    ];
}

/**
 * The sentence of the reminder form about who gets the notices.
 *
 * @param names - the first names of the accounts that get them
 * @returns for example «Notices arrive … an account: Marco and Laura.»
 */
export function recipientsSentence(names: string[]): string {
    if (names.length === 0) {
        return trans(
            'Notices arrive in the bell and by email to those with an account.',
        );
    }

    return trans(
        'Notices arrive in the bell and by email to those with an account: :names.',
        {
            names: new Intl.ListFormat(getActiveLanguage(), {
                type: 'conjunction',
            }).format(names),
        },
    );
}

/**
 * When a group notifies, in words, for the hint under *Notices* of the form.
 *
 * @param offsets - the days from the due date, negative before, 0 the day, positive after
 * @returns for example «7 days before and 1 day before, until it is done.»
 */
export function offsetsHint(offsets: number[]): string {
    if (offsets.length === 0) {
        return trans('This group sends no notices.');
    }

    const when = [...offsets]
        .sort((a, b) => a - b)
        .map((offset) =>
            timingText(
                Math.abs(offset),
                offset > 0 ? 'after' : 'before',
            ).toLocaleLowerCase(),
        );

    return trans(':when, until it is done.', {
        when: new Intl.ListFormat(getActiveLanguage(), {
            type: 'conjunction',
        }).format(when),
    });
}
