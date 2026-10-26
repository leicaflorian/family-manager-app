import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmScelte from './FmScelte.vue';

const opzioni = [
    { value: 'da-pagare', label: 'Da pagare' },
    { value: 'pagata', label: 'Già pagata' },
];

describe('FmScelte', () => {
    it('segna la scelta fatta', () => {
        render(FmScelte, {
            props: {
                etichetta: 'La bolletta',
                opzioni,
                modelValue: 'da-pagare',
            },
        });

        expect(
            screen.getByRole('radiogroup', { name: 'La bolletta' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'Da pagare' })).toBeChecked();
        expect(
            screen.getByRole('radio', { name: 'Già pagata' }),
        ).not.toBeChecked();
    });

    it('al tocco cambia la scelta', async () => {
        const risultato = render(FmScelte, {
            props: {
                etichetta: 'La bolletta',
                opzioni,
                modelValue: 'da-pagare',
            },
        });

        await userEvent.click(screen.getByText('Già pagata'));

        expect(risultato.emitted('update:modelValue')).toEqual([['pagata']]);
    });
});
