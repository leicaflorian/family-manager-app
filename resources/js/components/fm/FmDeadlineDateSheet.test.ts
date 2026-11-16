import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { agendaEntries, agendaEntry } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import FmDeadlineDateSheet from './FmDeadlineDateSheet.vue';

/** The expired health card of Giulia, 30/09/2026, which the person may change. */
const healthCard = agendaEntries[0];

afterEach(() => {
    vi.restoreAllMocks();
});

/** The renewal of a policy, which the person may replace with a new one. */
const policy = agendaEntry({
    kind: 'deadline',
    date: '2026-11-21',
    title: 'Renew the policy',
    name: 'RC auto Panda',
    detail: 'Policy renewal',
    update_url: '/agenda/deadlines/insurance_policies.expiry_date/3',
    replace_url: '/cards/create?card=insurance_policy&replaces=3',
});

describe('FmDeadlineDateSheet', () => {
    it('corrects the date starting from the one the card has', async () => {
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });

        expect(
            await screen.findByRole('dialog', { name: 'Update the date' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Tessera sanitaria di Giulia'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Document expiry · 30/09/2026'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Correct date')).toHaveValue('30/09/2026');
    });

    it('proposes the same day a year later for a renewal', async () => {
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: true,
            open: true,
        });

        expect(
            await screen.findByRole('dialog', { name: 'Renewed' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('New expiry date')).toHaveValue(
            '30/09/2027',
        );
        expect(
            screen.getByText(
                'The reminder is marked done, and a new one waits for the new date.',
            ),
        ).toBeInTheDocument();
    });

    it('sends the date and whether it is a renewal to the address of the entry', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/agenda/deadlines/documents.expiry_date/17',
            { date: '2027-09-30', renewed: true },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('a correction is not a renewal', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put.mock.calls[0][1]).toEqual({
            date: '2026-09-30',
            renewed: false,
        });
    });

    it('shows the message of the server under the date', async () => {
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onError?.({
                date: 'The date is not valid.',
            });
            (options as VisitOptions).onFinish?.(
                {} as Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            );
        });
        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(screen.getByText('The date is not valid.')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('closes after saving', async () => {
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            (options as VisitOptions).onSuccess?.(
                {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
            );
        });
        const view = renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('asks before closing with the × when the date changed', async () => {
        const view = renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');
        const field = screen.getByLabelText('Correct date');
        await userEvent.clear(field);
        await userEvent.type(field, '15/10/2026');
        await settle();

        await userEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(view.emitted()['update:open']).toBeUndefined();
    });

    it('offers the choice of the company only for a policy', async () => {
        const view = renderPage(FmDeadlineDateSheet, {
            entry: policy,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');

        expect(
            screen.getByRole('button', { name: 'Same company' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: 'I changed company' }),
        ).toHaveAttribute('aria-pressed', 'false');
        view.unmount();

        renderPage(FmDeadlineDateSheet, {
            entry: healthCard,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');
        expect(
            screen.queryByRole('button', { name: 'Same company' }),
        ).not.toBeInTheDocument();
    });

    it('is not offered when only the date is corrected', async () => {
        renderPage(FmDeadlineDateSheet, {
            entry: policy,
            renewed: false,
            open: true,
        });
        await screen.findByRole('dialog');

        expect(
            screen.queryByRole('button', { name: 'Same company' }),
        ).not.toBeInTheDocument();
    });

    it('goes to the new policy form when the company changed', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: policy,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(
            screen.getByRole('button', { name: 'I changed company' }),
        );
        await settle();

        expect(
            screen.queryByLabelText('New expiry date'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'The new policy opens already filled in with the car and the policyholder. The old one becomes «Cancelled» when you save the new one.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Continue' }));
        await settle();

        expect(visit).toHaveBeenCalledWith(policy.replace_url);
        expect(put).not.toHaveBeenCalled();
    });

    it('still renews with the same company', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(FmDeadlineDateSheet, {
            entry: policy,
            renewed: true,
            open: true,
        });
        await screen.findByRole('dialog');

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            policy.update_url,
            { date: '2027-11-21', renewed: true },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
