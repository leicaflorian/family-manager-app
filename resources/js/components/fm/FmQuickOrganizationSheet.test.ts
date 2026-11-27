import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { answerLeave, leaveQuestion } from '@/lib/unsavedChanges';
import { organizationTypes } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import FmQuickOrganizationSheet from './FmQuickOrganizationSheet.vue';

afterEach(() => {
    vi.restoreAllMocks();
    answerLeave(false);
});

/**
 * Opens the panel and waits for it.
 *
 * @returns what `renderPage` gives
 */
async function openSheet(): Promise<ReturnType<typeof renderPage>> {
    const view = renderPage(FmQuickOrganizationSheet, {
        types: organizationTypes,
        open: true,
    });
    await screen.findByRole('dialog', { name: 'New organization' });

    return view;
}

/**
 * Fakes the visit of the panel: calls the callbacks of Inertia it is given.
 *
 * @param answer - what the server answers, the flash or the errors
 * @returns the spy on `router.post`
 */
function answerWith(
    answer:
        | { flash: Record<string, unknown> }
        | { errors: Record<string, string> },
): ReturnType<typeof vi.spyOn> {
    return vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) => {
            const visit = options as VisitOptions;

            if ('flash' in answer) {
                visit.onFlash?.(answer.flash);
                visit.onSuccess?.(
                    {} as Parameters<NonNullable<VisitOptions['onSuccess']>>[0],
                );
            } else {
                visit.onError?.(answer.errors);
            }

            visit.onFinish?.(
                {} as Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            );
        });
}

describe('FmQuickOrganizationSheet', () => {
    it('asks the name and the types, and says the rest comes later', async () => {
        await openSheet();

        expect(screen.getByLabelText('Name')).toBeInTheDocument();
        expect(screen.getByText('What kind it is')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Utility provider' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Phone, email, website and logo you add later, from the organization page.',
            ),
        ).toBeInTheDocument();
    });

    it('sends name and types in the organization bag, keeping the page as it is', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        await openSheet();

        await userEvent.type(
            screen.getByLabelText('Name'),
            'Autofficina Rossi',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Utility provider' }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/family/organizations/quick',
            { name: 'Autofficina Rossi', types: ['utility_provider'] },
            expect.objectContaining({
                errorBag: 'organization',
                preserveState: true,
                preserveScroll: true,
                preserveUrl: true,
            }),
        );
    });

    it('gives the new organization and closes when the server saves it', async () => {
        answerWith({
            flash: { organization: { id: 12, name: 'Autofficina Rossi' } },
        });
        const view = await openSheet();

        await userEvent.type(
            screen.getByLabelText('Name'),
            'Autofficina Rossi',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(view.emitted('created')).toEqual([
            [{ id: 12, name: 'Autofficina Rossi' }],
        ]);
        expect(view.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('shows the errors of the server in the panel and stays open', async () => {
        answerWith({
            errors: {
                name: 'The name field is required.',
                'types.0': 'The selected type is invalid.',
            },
        });
        const view = await openSheet();

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(
            screen.getByText('The name field is required.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The selected type is invalid.'),
        ).toBeInTheDocument();
        expect(view.emitted('created')).toBeUndefined();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('asks before closing with a name written, and closes without asking with Cancel', async () => {
        const view = await openSheet();

        await userEvent.type(screen.getByLabelText('Name'), 'Rossi');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Close' }));
        await settle();

        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        await settle();
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(view.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('starts empty again when it opens again', async () => {
        const view = await openSheet();
        await fireEvent.update(screen.getByLabelText('Name'), 'Rossi');
        await settle();
        expect(screen.getByLabelText('Name')).toHaveValue('Rossi');

        await view.rerender({ types: organizationTypes, open: false });
        await view.rerender({ types: organizationTypes, open: true });
        await settle();

        expect(await screen.findByLabelText('Name')).toHaveValue('');
    });
});
