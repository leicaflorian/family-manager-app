import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmMailText from './FmMailText.vue';

/** The frame of the text, found by its name. */
function frame(): HTMLIFrameElement {
    return screen.getByTitle('Text of the mail') as HTMLIFrameElement;
}

describe('FmMailText', () => {
    it('shows the text in a sandboxed frame, never in the page', () => {
        render(FmMailText, { props: { src: '/mail/4/text' } });

        expect(frame()).toHaveAttribute('src', '/mail/4/text');
        expect(frame()).toHaveAttribute(
            'sandbox',
            'allow-popups allow-popups-to-escape-sandbox',
        );
        expect(
            screen.queryByRole('button', { name: 'Plain text' }),
        ).not.toBeInTheDocument();
    });

    it('switches between the plain text and the original when both are kept', async () => {
        render(FmMailText, { props: { src: '/mail/4/text', both: true } });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Plain text' }),
        );
        expect(frame()).toHaveAttribute('src', '/mail/4/text?plain=1');

        await fireEvent.click(screen.getByRole('button', { name: 'Original' }));
        expect(frame()).toHaveAttribute('src', '/mail/4/text');
    });

    it('says when the original was too large to keep, with its size', () => {
        render(FmMailText, {
            props: { src: '/mail/4/text', tooLarge: 1_468_006 },
        });

        expect(
            screen.getByText(
                'The original is too large (1,4 MB) and was not kept',
            ),
        ).toBeInTheDocument();
    });

    it('says when no text was kept, without a frame', () => {
        render(FmMailText, { props: { src: null } });

        expect(
            screen.getByText('The text of this mail was not kept.'),
        ).toBeInTheDocument();
        expect(screen.queryByTitle('Text of the mail')).not.toBeInTheDocument();
    });
});
