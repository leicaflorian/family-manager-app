import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmShowMore from './FmShowMore.vue';

describe('FmShowMore', () => {
    it('is a button with the given text, that says it was tapped', async () => {
        const result = render(FmShowMore, {
            props: { label: 'Show 5 more · 19 left' },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Show 5 more · 19 left' }),
        );

        expect(result.emitted('more')).toHaveLength(1);
    });

    it('cannot be tapped while the next rows are loading', () => {
        render(FmShowMore, {
            props: { label: 'Show 5 more · 19 left', loading: true },
        });

        expect(
            screen.getByRole('button', { name: 'Show 5 more · 19 left' }),
        ).toBeDisabled();
    });
});
