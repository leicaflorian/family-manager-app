import { h } from 'vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmAlsoDo from './FmAlsoDo.vue';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

describe('FmAlsoDo', () => {
    it('is titled What I do too, with its items inside', () => {
        render(FmAlsoDo, {
            slots: {
                default: () => [
                    h(FmAlsoDoItem, {
                        label: 'Create the expense',
                        modelValue: true,
                    }),
                ],
            },
        });

        expect(
            screen.getByRole('heading', { name: 'What I do too' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: 'Create the expense' }),
        ).toBeInTheDocument();
    });

    it('is a plain heading, not a titled card', () => {
        render(FmAlsoDo);

        expect(
            screen.getByRole('heading', { level: 3, name: 'What I do too' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('region')).not.toBeInTheDocument();
    });

    it('takes another title', () => {
        render(FmAlsoDo, { props: { title: 'And also' } });

        expect(
            screen.getByRole('heading', { level: 3, name: 'And also' }),
        ).toBeInTheDocument();
    });
});
