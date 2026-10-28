import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { ref } from 'vue';
import FmSearchField from './FmSearchField.vue';

describe('FmSearchField', () => {
    it('is a search box named by its label, that writes what is typed', async () => {
        const text = ref('bolletta');
        render({
            components: { FmSearchField },
            setup: () => ({ text }),
            template:
                '<FmSearchField v-model="text" label="Search a document" />',
        });

        const box = screen.getByRole('searchbox', {
            name: 'Search a document',
        });
        expect(box).toHaveValue('bolletta');
        expect(box).toHaveAttribute('placeholder', 'Search a document');

        await userEvent.clear(box);
        await userEvent.type(box, 'gas');

        expect(text.value).toBe('gas');
    });
});
