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

    it('says so when nothing names them', () => {
        render(FmLinkedRecords, { props: { groups: [] } });

        expect(screen.getByText('No records yet.')).toBeInTheDocument();
    });
});
