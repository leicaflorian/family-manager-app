import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFacce from './FmFacce.vue';

describe('FmFacce', () => {
    it('mostra le iniziali di ognuno, con il nome per chi non vede', () => {
        render(FmFacce, {
            props: {
                persone: [
                    {
                        id: 1,
                        first_name: 'Marco',
                        last_name: 'Bianchi',
                        initials: 'MB',
                    },
                    {
                        id: 2,
                        first_name: 'Laura',
                        last_name: 'Bianchi',
                        initials: 'LB',
                    },
                ],
            },
        });

        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.getByText('LB')).toBeInTheDocument();
        expect(
            screen.getByRole('list', { name: 'Marco e Laura' }),
        ).toBeInTheDocument();
    });
});
