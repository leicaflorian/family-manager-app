import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmReadingNotice from './FmReadingNotice.vue';

describe('FmReadingNotice', () => {
    it('says what was found and asks to check it, when the reading is done', () => {
        render(FmReadingNotice, {
            props: { status: 'done', headline: 'I found a payslip' },
        });

        expect(screen.getByText('I found a payslip')).toBeInTheDocument();
        expect(
            screen.getByText('Check it, change what is wrong and save.'),
        ).toBeInTheDocument();
    });

    it('says why the file was not read, with Read again beside', () => {
        render(FmReadingNotice, {
            props: {
                status: 'failed',
                error: 'The reading did not work.',
                again: true,
            },
        });

        expect(
            screen.getByText('The reading did not work.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Read again' }),
        ).toBeInTheDocument();
    });

    it('says the file could not be read when a failure has no reason', () => {
        render(FmReadingNotice, {
            props: { status: 'failed', error: null, again: true },
        });

        expect(
            screen.getByText('The file could not be read.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Read again' }),
        ).toBeInTheDocument();
    });

    it('sends again when Read again is pressed, also for a reading that was off', async () => {
        const { emitted } = render(FmReadingNotice, {
            props: {
                status: 'off',
                error: 'Automatic reading is not on.',
                again: true,
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Read again' }),
        );

        expect(emitted('again')).toHaveLength(1);
    });

    it('offers Read again neither for a file skipped nor without again', () => {
        render(FmReadingNotice, {
            props: { status: 'skipped', error: 'Not a document.', again: true },
        });
        render(FmReadingNotice, {
            props: { status: 'failed', error: 'Broken.' },
        });

        expect(
            screen.queryByRole('button', { name: 'Read again' }),
        ).not.toBeInTheDocument();
    });

    it.each(['off', 'skipped'] as const)(
        'shows the reason when %s',
        (status) => {
            render(FmReadingNotice, { props: { status, error: 'Not now.' } });

            expect(screen.getByText('Not now.')).toBeInTheDocument();
        },
    );

    it.each([null, 'queued', 'reading'] as const)(
        'shows nothing while the state is %s',
        (status) => {
            render(FmReadingNotice, {
                props: { status, headline: 'x', error: 'y' },
            });

            expect(screen.queryByRole('status')).not.toBeInTheDocument();
        },
    );

    it('shows nothing when done without a sentence', () => {
        render(FmReadingNotice, { props: { status: 'done' } });

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
