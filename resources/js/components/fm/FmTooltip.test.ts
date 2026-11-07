import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmTooltip from './FmTooltip.vue';

/**
 * A button with a tooltip, as a page uses it.
 *
 * @param text - the text of the tooltip
 */
function withTooltip(text: string): void {
    render(FmTooltip, {
        props: { text },
        slots: { default: '<button type="button">Upload</button>' },
    });
}

describe('FmTooltip', () => {
    it('says what to do when the person reaches the element with Tab', async () => {
        withTooltip('Upload a document from the computer');

        expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
        await userEvent.tab();

        expect(await screen.findByRole('tooltip')).toHaveTextContent(
            'Upload a document from the computer',
        );
        expect(screen.getByRole('button', { name: 'Upload' })).toHaveFocus();
    });

    it('goes away with Escape', async () => {
        withTooltip('Upload a document from the computer');
        await userEvent.tab();
        await screen.findByRole('tooltip');

        await userEvent.keyboard('{Escape}');

        expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    });
});
