import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { personDetail, personRoles } from '@/test/fixtures';
import { choose, renderPage, settle } from '@/test/page';
import Welcome from './Welcome.vue';

/** Marco, who signed up: the person of whoever is looking. */
const me: App.Data.PersonDetailData = {
    ...personDetail,
    id: 1,
    first_name: 'Marco',
    initials: 'MB',
    role: 'parent',
    role_label: 'Adult / Parent',
    birth_date: null,
    age: null,
    tax_code: null,
    account_label: 'Administrator',
    is_me: true,
    can_delete: false,
};

const props = {
    me,
    roles: personRoles,
    features: { budget: false, mail: false, can_change: true },
};

/**
 * Taps a button of the page by its name.
 *
 * @param name - the text of the button
 */
async function tap(name: string): Promise<void> {
    await fireEvent.click(screen.getByRole('button', { name }));
}

/**
 * Goes from the first step to the fourth, leaving the home for later, with the mail switched on or off.
 *
 * @param mail - whether the mail is switched on at the third step
 */
async function welcomeToStepFour(mail: boolean): Promise<void> {
    await tap('Next');
    await tap("I'll add it later");
    if (mail) {
        await fireEvent.click(
            screen.getByRole('switch', { name: 'Mail for the bills' }),
        );
        await settle();
    }
    await tap('Next');
}

describe('Welcome', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('shows you at the first step with the two optional fields, and adds a person', async () => {
        renderPage(Welcome, props);

        expect(screen.getByText('Step 1 of 4')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Who you are' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco Bianchi')).toBeInTheDocument();
        expect(screen.getByText('You · Adult / Parent')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Date of birth (optional)'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Tax code (optional)'),
        ).toBeInTheDocument();

        await tap('Add a person');

        expect(screen.getByLabelText('First name')).toBeInTheDocument();
        expect(screen.getByLabelText('Last name')).toHaveValue('Bianchi');
        expect(
            screen.getByRole('button', { name: 'In the family they are' }),
        ).toHaveTextContent('Adult / Parent');
        expect(
            screen.getAllByLabelText('Date of birth (optional)'),
        ).toHaveLength(2);
        expect(screen.getAllByLabelText('Tax code (optional)')).toHaveLength(2);

        await tap('Remove');

        expect(screen.queryByLabelText('First name')).not.toBeInTheDocument();
    });

    it('asks for the home at the second step, and «later» leads to the third', async () => {
        renderPage(Welcome, props);

        await tap('Next');

        expect(screen.getByText('Step 2 of 4')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'The home' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('What you call it')).toHaveValue('House');
        expect(
            screen.getByRole('switch', { name: 'It is your property' }),
        ).toBeInTheDocument();

        await tap('Back');
        expect(
            screen.getByRole('heading', { name: 'Who you are' }),
        ).toBeInTheDocument();

        await tap('Next');
        await tap("I'll add it later");

        expect(screen.getByText('Step 3 of 4')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'What you use' }),
        ).toBeInTheDocument();
    });

    it('shows the two switches off at the third step', async () => {
        renderPage(Welcome, props);

        await tap('Next');
        await tap('Next');

        expect(screen.getByText('Always there')).toBeInTheDocument();
        expect(screen.getAllByText('Always on')).toHaveLength(3);
        expect(
            screen.getByRole('switch', { name: 'Budget' }),
        ).not.toBeChecked();
        expect(
            screen.getByRole('switch', { name: 'Mail for the bills' }),
        ).not.toBeChecked();
    });

    it('at the fourth step offers the scan from the phone with the mail off', async () => {
        renderPage(Welcome, props);

        await welcomeToStepFour(false);

        expect(screen.getByText('Step 4 of 4')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'How it works' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Scan from your phone')).toBeInTheDocument();
        expect(screen.getByText(/On every page you find the/).textContent).toBe(
            'On every page you find the i beside the title: it tells you what you can do there.',
        );
        expect(
            screen.queryByText('Mail for the bills'),
        ).not.toBeInTheDocument();
    });

    it('at the fourth step offers the mail for the bills with the mail on', async () => {
        renderPage(Welcome, props);

        await welcomeToStepFour(true);

        expect(screen.getByText('Mail for the bills')).toBeInTheDocument();
        expect(
            screen.queryByText('Scan from your phone'),
        ).not.toBeInTheDocument();
    });

    it('Done sends you, the people, the home and the switches', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Welcome, props);

        await fireEvent.update(
            screen.getByLabelText('Tax code (optional)'),
            'bncmrc80c12a944k',
        );
        await tap('Add a person');
        await fireEvent.update(screen.getByLabelText('First name'), 'Laura');
        await choose('In the family they are', 'Child');
        await settle();
        await tap('Next');
        await fireEvent.update(
            screen.getByLabelText('What you call it'),
            'Casa di via Roma',
        );
        await settle();
        await tap('Next');
        await fireEvent.click(screen.getByRole('switch', { name: 'Budget' }));
        await settle();
        await tap('Next');
        await tap("Done, let's go in");

        expect(post).toHaveBeenCalledWith(
            '/welcome',
            expect.objectContaining({
                me: { birth_date: '', tax_code: 'bncmrc80c12a944k' },
                people: [
                    expect.objectContaining({
                        first_name: 'Laura',
                        last_name: 'Bianchi',
                        role: 'child',
                    }),
                ],
                home: expect.objectContaining({ name: 'Casa di via Roma' }),
                features: { budget: true, mail: false },
            }),
            expect.anything(),
        );
    });

    it('leaves the home out when it is added later', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Welcome, props);

        await welcomeToStepFour(false);
        await tap("Done, let's go in");

        expect(post).toHaveBeenCalledWith(
            '/welcome',
            expect.objectContaining({ home: null }),
            expect.anything(),
        );
    });
});
