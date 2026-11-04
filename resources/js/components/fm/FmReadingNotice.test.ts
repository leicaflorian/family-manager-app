import { render, screen } from '@testing-library/vue';
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

    it('says why the file was not read, with the action beside', () => {
        render(FmReadingNotice, {
            props: { status: 'failed', error: 'The reading did not work.' },
            slots: { action: '<button>Read again</button>' },
        });

        expect(
            screen.getByText('The reading did not work.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Read again' }),
        ).toBeInTheDocument();
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
