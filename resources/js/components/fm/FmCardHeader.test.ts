import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { pandaRow } from '@/test/fixtures';
import FmCardHeader from './FmCardHeader.vue';

describe('FmCardHeader', () => {
    it('shows the area and type, the name and the line of data', () => {
        render(FmCardHeader, {
            props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
            slots: { actions: '<button>Edit</button>' },
        });

        expect(screen.getByText('Vehicles · Vehicle')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Fiat Panda' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Plate GH 482 TK · Marco')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit' }),
        ).toBeInTheDocument();
    });
});
