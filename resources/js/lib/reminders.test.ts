import { describe, expect, it } from 'vite-plus/test';
import type { NoticeRule, RulePerson } from './reminders';
import { nextNoticeText, ruleText, sentAtText } from './reminders';

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
