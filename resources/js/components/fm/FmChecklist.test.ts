import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmChecklist from './FmChecklist.vue';

const steps = [
    {
        key: 'utilities',
        title: 'The home bills',
        subtitle:
            'Electricity, gas, water and internet are already in the areas',
        href: '/areas',
        done: true,
    },
    {
        key: 'first_bill',
        title: 'Upload the first bill',
        subtitle: 'Take a photo or choose the PDF',
        href: '/upload',
        done: false,
    },
    {
        key: 'invite',
        title: 'Invite whoever lives with you',
        href: '/family/invite',
        done: false,
        skippable: true,
    },
];

describe('FmChecklist', () => {
    it('lists the steps, each a link', () => {
        render(FmChecklist, { props: { steps } });

        expect(
            screen.getByRole('heading', { name: 'To begin' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link')).toHaveLength(3);
        expect(
            screen.getByRole('link', { name: /Upload the first bill/ }),
        ).toHaveAttribute('href', '/upload');
    });

    it('marks a step done with a tick and the text struck through', () => {
        render(FmChecklist, { props: { steps } });

        expect(
            screen.getByRole('link', { name: /Done.*The home bills/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Upload the first bill/ }),
        ).not.toHaveAccessibleName(/Done/);
        expect(screen.getByTitle('The home bills')).toHaveClass('line-through');
        expect(screen.getAllByText('Done')).toHaveLength(1);
    });

    it('counts the steps done, with the bar', () => {
        render(FmChecklist, { props: { steps } });

        expect(screen.getByText('1 of 3')).toBeInTheDocument();
        const bar = screen.getByRole('progressbar', { name: 'To begin' });
        expect(bar).toHaveAttribute('aria-valuenow', '1');
        expect(bar).toHaveAttribute('aria-valuemax', '3');
    });

    it('writes the intro under the title', () => {
        render(FmChecklist, {
            props: { steps, intro: 'Three things to get started.' },
        });

        expect(
            screen.getByText('Three things to get started.'),
        ).toBeInTheDocument();
    });

    it('sends hide from Hide', async () => {
        const view = render(FmChecklist, { props: { steps } });

        await userEvent.click(screen.getByRole('button', { name: 'Hide' }));

        expect(view.emitted('hide')).toHaveLength(1);
    });

    it('offers Not needed only on a step that can be taken away and is not done', async () => {
        const view = render(FmChecklist, {
            props: {
                steps: [
                    ...steps,
                    {
                        key: 'people',
                        title: 'Add whoever lives with you',
                        href: '/family/people',
                        done: true,
                        skippable: true,
                    },
                ],
            },
        });

        const buttons = screen.getAllByRole('button', {
            name: /Remove the step/,
        });
        expect(buttons).toHaveLength(1);
        expect(buttons[0]).toHaveAccessibleName(
            'Remove the step Invite whoever lives with you',
        );
        expect(buttons[0]).toHaveTextContent('Not needed');

        await userEvent.click(buttons[0]);

        expect(view.emitted('skip')).toEqual([['invite']]);
        expect(
            screen.getByRole('link', {
                name: /Invite whoever lives with you/,
            }),
        ).toHaveAttribute('href', '/family/invite');
    });

    it('puts the footer at the end of the card', () => {
        render(FmChecklist, {
            props: { steps },
            slots: { footer: 'Need more?' },
        });

        expect(screen.getByText('Need more?')).toBeInTheDocument();
    });
});
