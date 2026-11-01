import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import type { ReminderGroupItem, RulePerson } from '@/lib/reminders';
import FmReminderGroup from './FmReminderGroup.vue';

const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
];

const group: ReminderGroupItem = {
    name: 'Vehicles',
    is_default: false,
    kinds: ['Road tax', 'Inspection'],
    rules: [
        { days: 45, timing: 'before', channels: 'both', recipients: ['all'] },
        { days: 15, timing: 'before', channels: 'app', recipients: [1] },
    ],
};

describe('FmReminderGroup', () => {
    it('shows the name, the kinds in words and a line for each notice', () => {
        render(FmReminderGroup, { props: { group, people, canEdit: true } });

        expect(
            screen.getByRole('heading', { name: 'Vehicles' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Road tax, inspection')).toBeInTheDocument();
        expect(
            screen.getByText('45 days before · App and email · Everyone'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('15 days before · App only · Marco'),
        ).toBeInTheDocument();
    });

    it('marks the default group and says what it is for', () => {
        render(FmReminderGroup, {
            props: {
                group: {
                    ...group,
                    name: 'Default',
                    is_default: true,
                    kinds: [],
                },
                people,
                canEdit: true,
            },
        });

        expect(
            screen.getByText('Default', { selector: 'span' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Reminders written by hand and everything else'),
        ).toBeInTheDocument();
    });

    it('sends edit from the button', async () => {
        const view = render(FmReminderGroup, {
            props: { group, people, canEdit: true },
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Edit' }));

        expect(view.emitted()).toHaveProperty('edit');
    });

    it('has no button for who cannot edit', () => {
        render(FmReminderGroup, { props: { group, people, canEdit: false } });

        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
    });
});
