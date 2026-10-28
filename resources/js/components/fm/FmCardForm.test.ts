import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { policyFields } from '@/test/fixtures';
import { choose, renderPage, settle } from '@/test/page';
import FmCardForm from './FmCardForm.vue';

const values = {
    title: 'RC auto Panda',
    type: 'car',
    vehicle_id: '1',
    home_id: null,
};

describe('FmCardForm', () => {
    it('asks the vehicle for a car policy and the home for a home policy', async () => {
        renderPage(FmCardForm, { fields: policyFields, values });

        expect(screen.getByLabelText('Vehicle')).toBeInTheDocument();
        expect(screen.queryByLabelText('Residence')).not.toBeInTheDocument();

        await choose('Type', 'Home');

        expect(screen.queryByLabelText('Vehicle')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Residence')).toBeInTheDocument();
    });

    it('sends a hidden field empty', async () => {
        const view = renderPage(FmCardForm, {
            fields: policyFields,
            values,
        });

        await choose('Type', 'Home');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')?.[0]).toEqual([
            expect.objectContaining({ type: 'home', vehicle_id: null }),
        ]);
    });

    it('does not save without a required field', async () => {
        const view = renderPage(FmCardForm, {
            fields: policyFields,
            values: { title: '', type: 'car' },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('submit')).toBeUndefined();
    });
});
