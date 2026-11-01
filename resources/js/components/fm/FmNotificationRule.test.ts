import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import type { NoticeRule, RulePerson } from '@/lib/reminders';
import { choose, renderPage, settle } from '@/test/page';
import userEvent from '@testing-library/user-event';
import FmNotificationRule from './FmNotificationRule.vue';

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

/** The last rule the component handed to `v-model`. */
function lastRule(emitted: Record<string, unknown[][]>): NoticeRule {
    const calls = emitted['update:modelValue']!;

    return calls[calls.length - 1]![0] as NoticeRule;
}

describe('FmNotificationRule', () => {
    it('shows days, when, where and to whom', () => {
        renderPage(FmNotificationRule, { modelValue: rule, people });

        expect(screen.getByLabelText('Days')).toHaveValue(45);
        expect(screen.getByRole('button', { name: 'before' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(
            screen.getByRole('button', { name: 'Where it goes' }),
        ).toHaveTextContent('App and email');
        expect(screen.getAllByText('Everyone').length).toBeGreaterThan(0);
    });

    it('sends the new days', async () => {
        const view = renderPage(FmNotificationRule, {
            modelValue: rule,
            people,
        });

        const field = screen.getByLabelText('Days');
        await userEvent.clear(field);
        await userEvent.type(field, '30');
        await settle();

        expect(lastRule(view.emitted() as never)).toMatchObject({ days: 30 });
    });

    it('turns "on the day" into 0 days', async () => {
        const view = renderPage(FmNotificationRule, {
            modelValue: rule,
            people,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'on the day' }),
        );
        await settle();

        expect(lastRule(view.emitted() as never)).toMatchObject({ days: 0 });
    });

    it('sends the place the notice goes to', async () => {
        const view = renderPage(FmNotificationRule, {
            modelValue: rule,
            people,
        });

        await choose('Where it goes', 'Email only');

        expect(lastRule(view.emitted() as never)).toMatchObject({
            channels: 'email',
        });
    });

    it('sends remove from the button', async () => {
        const view = renderPage(FmNotificationRule, {
            modelValue: rule,
            people,
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove this notice' }),
        );

        expect(view.emitted()).toHaveProperty('remove');
    });
});
