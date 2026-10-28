import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmCategoryOption from './FmCategoryOption.vue';

/**
 * The text the person sees in the list: the screen readers read the path instead.
 *
 * @param text - the name of the option
 * @returns its element
 */
function seen(text: string): HTMLElement {
    return screen.getByText(text, { selector: '[aria-hidden="true"]' });
}

describe('FmCategoryOption', () => {
    it('shows a main category with its name, not indented', () => {
        render(FmCategoryOption, {
            props: {
                option: { label: 'Work', name: 'Work', depth: 0 },
            },
        });

        expect(seen('Work')).toHaveStyle({ paddingInlineStart: '0rem' });
    });

    it('shows a subcategory with its name alone, indented by its depth', () => {
        render(FmCategoryOption, {
            props: {
                option: {
                    label: 'Work › Payslips › 2026',
                    name: '2026',
                    depth: 2,
                },
            },
        });

        expect(seen('2026')).toHaveStyle({ paddingInlineStart: '2rem' });
    });

    it('reads the path of a subcategory to screen readers', () => {
        render(FmCategoryOption, {
            props: {
                option: {
                    label: 'Work › Payslips',
                    name: 'Payslips',
                    depth: 1,
                },
            },
        });

        expect(screen.getByText('Work › Payslips')).toHaveClass('sr-only');
    });

    it('shows a choice that is not a category, as «All», with its label', () => {
        render(FmCategoryOption, {
            props: { option: { label: 'All' } },
        });

        expect(seen('All')).toHaveStyle({ paddingInlineStart: '0rem' });
    });
});
