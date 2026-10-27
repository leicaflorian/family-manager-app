import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFaces from './FmFaces.vue';

describe('FmFaces', () => {
    it('shows the initials of each person, with the names for those who cannot see', () => {
        render(FmFaces, {
            props: {
                people: [
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
            screen.getByRole('list', { name: 'Marco and Laura' }),
        ).toBeInTheDocument();
    });
});
