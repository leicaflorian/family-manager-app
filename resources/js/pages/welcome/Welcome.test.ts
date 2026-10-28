import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Welcome from './Welcome.vue';

const props = {
    people: [
        { id: 1, first_name: 'Marco', last_name: 'Bianchi', initials: 'MB' },
    ],
    roles: [
        { value: 'parent', label: 'Parent', help: null },
        { value: 'child', label: 'Child', help: null },
    ],
};

describe('Welcome', () => {
    it('shows who is already there at the first step and adds a person', async () => {
        renderPage(Welcome, props);

        expect(screen.getByText('Step 1 of 2')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Who you are' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco Bianchi')).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Add a person' }),
        );

        expect(screen.getByLabelText('First name')).toBeInTheDocument();
        expect(screen.getByLabelText('Last name')).toHaveValue('Bianchi');
        expect(
            screen.getByLabelText('In the family they are'),
        ).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(screen.queryByLabelText('First name')).not.toBeInTheDocument();
    });

    it('asks for the home at the second step, which can be left for later', async () => {
        renderPage(Welcome, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Next' }));

        expect(screen.getByText('Step 2 of 2')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'The home' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('What you call it')).toHaveValue('House');
        expect(screen.getByLabelText('Address')).toBeInTheDocument();
        expect(screen.getByLabelText('City')).toBeInTheDocument();
        expect(
            screen.getByRole('switch', { name: 'It is your property' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: "Done, let's go in" }),
        ).toHaveAttribute('type', 'submit');
        expect(
            screen.getByRole('button', { name: 'I will add it later' }),
        ).toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Back' }));

        expect(
            screen.getByRole('heading', { name: 'Who you are' }),
        ).toBeInTheDocument();
    });
});
