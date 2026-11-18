import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import { emptyPlan, type SubscriptionPlan } from '@/lib/subscriptionPlan';
import { hasChanges } from '@/lib/unsavedChanges';
import FmSubscriptionPlanned from './FmSubscriptionPlanned.vue';

const categories = [{ value: '4', label: 'Abbonamenti', help: null }];

/**
 * Renders the section with a plan that follows what it emits.
 *
 * @param activation - the activation date of the card
 * @param start - the plan to start from
 * @returns the plan as last emitted, and the render result
 */
function renderSection(
    activation: string | null,
    start: SubscriptionPlan = emptyPlan(),
): { plan: () => SubscriptionPlan; view: ReturnType<typeof renderPage> } {
    let plan = start;
    const view = renderPage(FmSubscriptionPlanned, {
        categories,
        activation,
        modelValue: plan,
        'onUpdate:modelValue': (value: SubscriptionPlan) => {
            plan = value;
            // The section can emit while it mounts, before view exists
            queueMicrotask(() => void view.rerender({ modelValue: value }));
        },
    });

    return { plan: () => plan, view };
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-18T10:00:00+01:00'));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('FmSubscriptionPlanned', () => {
    it('asks for the cost, how often, the first payment, the category and whether it is paid', async () => {
        renderSection('2026-11-15');
        await settle();

        expect(
            screen.getByRole('group', { name: 'Planned expense' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Optional: with the cost the planned expense is created too.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Cost')).toHaveValue('');
        expect(screen.getByLabelText('Every')).toHaveValue(1);
        expect(screen.getByRole('button', { name: 'Months' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByLabelText('Category')).toBeInTheDocument();
        expect(
            screen.getByRole('switch', { name: 'I already paid it' }),
        ).not.toBeChecked();
    });

    it('starts the first payment from the activation, and follows it', async () => {
        const { plan, view } = renderSection('2026-11-15');
        await settle();
        expect(plan().start_date).toBe('2026-11-15');
        expect(screen.getByLabelText('First payment')).toHaveValue(
            '15/11/2026',
        );

        await view.rerender({ activation: '2026-12-01' });
        await settle();

        expect(plan().start_date).toBe('2026-12-01');
    });

    it('keeps a first payment the person chose when the activation changes', async () => {
        const { plan, view } = renderSection('2026-11-15', {
            ...emptyPlan(),
            start_date: '2027-01-10',
        });
        await settle();

        await view.rerender({ activation: '2026-12-01' });
        await settle();

        expect(plan().start_date).toBe('2027-01-10');
    });

    it('starts from today without an activation', async () => {
        const { plan } = renderSection(null);
        await settle();

        expect(plan().start_date).toBe('2026-11-18');
    });

    it('asks before leaving once the person writes something, not for the first payment it fills in', async () => {
        const { view } = renderSection('2026-11-15');
        await settle();
        await view.rerender({ activation: '2026-12-01' });
        await settle();

        expect(hasChanges()).toBe(false);

        await userEvent.click(screen.getByText('I already paid it'));
        await settle();

        expect(hasChanges()).toBe(true);
    });
});
