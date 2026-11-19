import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmLinkedRecords from './FmLinkedRecords.vue';

const groups: App.Data.LinkGroupData[] = [
    {
        type: 'health_event',
        label: 'Health',
        options: [{ value: '4', label: 'Visite di Giulia', help: null }],
    },
    {
        type: 'enrollment',
        label: 'School and activities',
        options: [
            { value: '2', label: 'Danza Giulia', help: null },
            { value: '3', label: 'Inglese Giulia', help: null },
        ],
    },
];

describe('FmLinkedRecords', () => {
    it('shows the records of the first type, and the others on request', async () => {
        render(FmLinkedRecords, { props: { groups } });

        expect(
            screen.getByRole('heading', { name: 'Links' }),
        ).toBeInTheDocument();
        const health = screen.getByRole('button', { name: 'Health 1' });
        expect(health).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByText('Visite di Giulia')).toBeInTheDocument();
        expect(screen.queryByText('Danza Giulia')).not.toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'School and activities 2' }),
        );

        expect(screen.getByText('Danza Giulia')).toBeInTheDocument();
        expect(screen.getByText('Inglese Giulia')).toBeInTheDocument();
        expect(health).toHaveAttribute('aria-pressed', 'false');
    });

    it('shows three records at a time, and starts again from three on another tab', async () => {
        const policies: App.Data.LinkGroupData = {
            type: 'insurance_policy',
            label: 'Policies',
            options: Array.from({ length: 8 }, (_, index) => ({
                value: String(index + 10),
                label: `Policy ${index + 1}`,
                help: null,
            })),
        };
        render(FmLinkedRecords, { props: { groups: [policies, ...groups] } });

        expect(screen.getAllByRole('link')).toHaveLength(3);
        await fireEvent.click(
            screen.getByRole('button', { name: 'Show 3 more · 5 left' }),
        );
        expect(screen.getAllByRole('link')).toHaveLength(6);
        await fireEvent.click(
            screen.getByRole('button', { name: 'Show 2 more · 2 left' }),
        );
        expect(screen.getAllByRole('link')).toHaveLength(8);
        expect(
            screen.queryByRole('button', { name: /Show .* more/ }),
        ).not.toBeInTheDocument();

        await fireEvent.click(screen.getByRole('button', { name: 'Health 1' }));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Policies 8' }),
        );

        expect(screen.getAllByRole('link')).toHaveLength(3);
    });

    it('has no button when the records of a tab fit', () => {
        const three: App.Data.LinkGroupData = {
            ...groups[1],
            options: [
                ...groups[1].options,
                { value: '5', label: 'Nuoto Giulia', help: null },
            ],
        };
        render(FmLinkedRecords, { props: { groups: [three] } });

        expect(screen.getAllByRole('link')).toHaveLength(3);
        expect(
            screen.queryByRole('button', { name: /Show .* more/ }),
        ).not.toBeInTheDocument();
    });

    it('says so when nothing names them', () => {
        render(FmLinkedRecords, { props: { groups: [] } });

        expect(screen.getByText('No records yet.')).toBeInTheDocument();
    });

    it('leads every record to its card', () => {
        render(FmLinkedRecords, {
            props: {
                groups: [
                    {
                        type: 'vehicle',
                        label: 'Vehicles',
                        options: [
                            { value: '1', label: 'Fiat Panda', help: null },
                        ],
                    },
                ],
            },
        });

        expect(
            screen.getByRole('link', { name: /Fiat Panda/ }),
        ).toHaveAttribute('href', '/cards/vehicle/1');
    });
});
