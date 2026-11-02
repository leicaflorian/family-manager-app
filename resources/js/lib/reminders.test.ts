import { describe, expect, it } from 'vite-plus/test';
import type { NoticeRule, RulePerson } from './reminders';
import {
    agendaTabs,
    offsetsHint,
    nextNoticeText,
    recipientsSentence,
    ruleText,
    sentAtText,
    toNoticeItem,
    toReminderItem,
} from './reminders';

const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

const rule: NoticeRule = {
    days: 45,
    timing: 'before',
    channels: 'both',
    recipients: ['all'],
};

describe('ruleText', () => {
    it('says days, where and to whom', () => {
        expect(ruleText(rule, people)).toBe(
            '45 days before · App and email · Everyone',
        );
    });

    it('says the day itself for 0 days, and after for a late one', () => {
        expect(ruleText({ ...rule, days: 0 }, people)).toBe(
            'On the day · App and email · Everyone',
        );
        expect(ruleText({ ...rule, days: 1, timing: 'after' }, people)).toBe(
            '1 day after · App and email · Everyone',
        );
    });

    it('names the people chosen and the channel', () => {
        expect(
            ruleText({ ...rule, channels: 'app', recipients: [1, 2] }, people),
        ).toBe('45 days before · App only · Marco, Laura');
        expect(ruleText({ ...rule, channels: 'email' }, people)).toBe(
            '45 days before · Email only · Everyone',
        );
    });

    it('skips a person who is no longer there, and falls back to everyone', () => {
        expect(ruleText({ ...rule, recipients: [1, 9] }, people)).toContain(
            '· Marco',
        );
        expect(ruleText({ ...rule, recipients: [9] }, people)).toContain(
            '· Everyone',
        );
    });
});

describe('nextNoticeText', () => {
    it('says the first notice when none has gone yet', () => {
        expect(nextNoticeText('2026-10-30', 0)).toBe('First notice on 30/10');
    });

    it('says the next notice when one has gone', () => {
        expect(nextNoticeText('2026-10-30', 1)).toBe('Notice on 30/10');
    });

    it('says there is no other notice', () => {
        expect(nextNoticeText(null, 2)).toBe('No other notice');
    });
});

describe('sentAtText', () => {
    const today = new Date('2026-10-26T10:00:00+01:00');

    it('puts the weekday on a notice of the last days', () => {
        expect(sentAtText('2026-10-24T06:00:00+02:00', today, 'en')).toBe(
            'Saturday 24/10 at 6:00',
        );
    });

    it('writes only the date for an older one', () => {
        expect(sentAtText('2026-10-07T06:00:00+02:00', today, 'en')).toBe(
            '07/10 at 6:00',
        );
    });
});

const row: App.Data.ReminderData = {
    id: 7,
    title: 'Pay the road tax',
    due_date: '2026-11-20',
    postponed_from: null,
    status: 'open' as App.Enums.ReminderStatus,
    is_automatic: false,
    subject: {
        type: 'vehicle',
        id: 3,
        label: 'Fiat Panda',
        href: '/cards/vehicle/3',
        area: 'vehicles',
    },
    person: { id: 1, name: 'Marco', initials: 'MB', photo: null },
    group_id: null,
    next_notice: '2026-11-13',
    notice_sent: false,
    missing_expense: true,
    href: '/reminders/7/edit',
    can_edit: true,
    can_delete: true,
};

describe('toReminderItem', () => {
    it('gives the row what it shows', () => {
        expect(toReminderItem(row)).toEqual({
            id: 7,
            title: 'Pay the road tax',
            due_date: '2026-11-20',
            done: false,
            manual: true,
            area: 'vehicles',
            subject: { label: 'Fiat Panda', href: '/cards/vehicle/3' },
            person: { name: 'Marco', initials: 'MB', photo: null },
            next_notice_on: '2026-11-13',
            notices_sent: 0,
            missing_planned_expense: true,
            href: '/reminders/7/edit',
        });
    });

    it('marks a done one, an automatic one and a notice already sent', () => {
        const item = toReminderItem({
            ...row,
            status: 'done' as App.Enums.ReminderStatus,
            is_automatic: true,
            notice_sent: true,
            subject: null,
            person: null,
        });

        expect(item).toMatchObject({
            done: true,
            manual: false,
            notices_sent: 1,
            subject: null,
            person: null,
            area: null,
        });
    });
});

describe('agendaTabs', () => {
    it('lists the two pages with the number of reminders and the open one marked', () => {
        expect(agendaTabs('reminders', 10)).toEqual([
            { label: 'Deadlines', href: '/agenda', current: false },
            {
                label: 'Reminders',
                href: '/reminders',
                count: 10,
                current: true,
            },
        ]);
        expect(agendaTabs('deadlines', 0)[0]?.current).toBe(true);
    });
});

describe('recipientsSentence', () => {
    it('names the people who get the notices', () => {
        expect(recipientsSentence(['Marco', 'Laura'])).toBe(
            'Notices arrive in the bell and by email to those with an account: Marco and Laura.',
        );
        expect(recipientsSentence(['Marco'])).toBe(
            'Notices arrive in the bell and by email to those with an account: Marco.',
        );
    });

    it('says it without names when nobody is left', () => {
        expect(recipientsSentence([])).toBe(
            'Notices arrive in the bell and by email to those with an account.',
        );
    });
});

describe('offsetsHint', () => {
    it('lists the notices from the earliest, until the reminder is done', () => {
        expect(offsetsHint([-1, -7, 0])).toBe(
            '7 days before, 1 day before, and on the day, until it is done.',
        );
        expect(offsetsHint([3])).toBe('3 days after, until it is done.');
    });

    it('says so when the group sends nothing', () => {
        expect(offsetsHint([])).toBe('This group sends no notices.');
    });
});

describe('toNoticeItem', () => {
    const notice: App.Data.ReminderNoticeData = {
        id: 4,
        title: 'Renew the policy · Panda car insurance',
        subject: 'Panda car insurance',
        area: 'insurance',
        due_text: 'Due in 26 days, on 21/11/2026',
        sent_at: '2026-10-24T06:00',
        read: false,
        state: 'expired',
        href: '/cards/insurance/2',
    };

    it('gives the row what it shows, and calls an expired reminder overdue', () => {
        expect(toNoticeItem(notice)).toEqual({
            id: 4,
            title: 'Renew the policy · Panda car insurance',
            due_text: 'Due in 26 days, on 21/11/2026',
            sent_at: '2026-10-24T06:00',
            read: false,
            area: 'insurance',
            state: 'overdue',
            href: '/cards/insurance/2',
        });
    });

    it('keeps done and no state as they are', () => {
        expect(toNoticeItem({ ...notice, state: 'done' }).state).toBe('done');
        expect(toNoticeItem({ ...notice, state: null }).state).toBeNull();
    });
});
