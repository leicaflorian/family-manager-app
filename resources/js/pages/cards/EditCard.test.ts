import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { healthCard, policyDetail, policyFields } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import EditCard from './EditCard.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a card', () => {
    const type: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };

    it('names a new card and a change', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(
            screen.getByRole('heading', { name: 'New insurance policy' }),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car' },
            card: policyDetail.card,
        });
        expect(
            screen.getByRole('heading', { name: 'Edit the insurance policy' }),
        ).toBeInTheDocument();
    });

    it('saves a change as a POST with _method put, so files can go with it, and a new card with POST', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car', vehicle_id: '1' },
            card: policyDetail.card,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy/1',
            expect.objectContaining({ title: 'RC auto Panda', _method: 'put' }),
            expect.anything(),
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'Nuova', type: 'car' },
            card: null,
        });
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy',
            expect.objectContaining({ title: 'Nuova' }),
            expect.anything(),
        );
    });

    it('cancels back to the card, or to the area for a new one', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: policyDetail.card,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/cards/insurance_policy/1',
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/areas/insurance',
        );
    });

    describe('documents of the card', () => {
        const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
            type: 'application/pdf',
        });

        /**
         * Chooses files in the zone of the documents.
         *
         * @param files - the files
         */
        async function choose(...files: File[]): Promise<void> {
            await fireEvent.change(screen.getByLabelText('Choose the files'), {
                target: { files },
            });
        }

        it('asks for them on a new card, and shows the linked ones when editing', () => {
            const first = renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: null,
            });
            expect(
                screen.getByText(
                    'Its documents? Drag them here, or choose them',
                ),
            ).toBeInTheDocument();
            expect(screen.getByText('No documents yet')).toBeInTheDocument();
            first.unmount();

            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: policyDetail.card,
                documents: [healthCard],
            });
            expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
            expect(
                screen.getByLabelText('Choose the files'),
            ).toBeInTheDocument();
        });

        it('sends the new files with a change too', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'RC auto Panda', type: 'car' },
                card: policyDetail.card,
                documents: [healthCard],
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy/1',
                expect.objectContaining({ _method: 'put', files: [libretto] }),
                expect.anything(),
            );
        });

        it('lists the chosen files, which can be removed', async () => {
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: {},
                card: null,
            });
            const bollo = new File(['%PDF-1.4'], 'Bollo.pdf', {
                type: 'application/pdf',
            });

            await choose(libretto);
            await choose(bollo);
            expect(screen.getByText('Libretto.pdf')).toBeInTheDocument();
            expect(screen.getByText('Bollo.pdf')).toBeInTheDocument();

            await userEvent.click(
                screen.getByRole('button', { name: 'Remove Libretto.pdf' }),
            );
            expect(screen.queryByText('Libretto.pdf')).not.toBeInTheDocument();
            expect(screen.getByText('Bollo.pdf')).toBeInTheDocument();
        });

        it('sends them with the card when it is saved', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post).toHaveBeenCalledWith(
                '/cards/insurance_policy',
                expect.objectContaining({ title: 'Nuova', files: [libretto] }),
                expect.anything(),
            );
        });

        it('sends no files when none is chosen', async () => {
            const post = vi.spyOn(router, 'post').mockImplementation(() => {});
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(post.mock.calls[0]?.[1]).not.toHaveProperty('files');
        });

        it('shows why the server refused a file', async () => {
            vi.spyOn(router, 'post').mockImplementation(
                (_url, _data, visit) => {
                    visit?.onError?.({
                        'files.0':
                            'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                    });
                },
            );
            renderPage(EditCard, {
                type,
                fields: policyFields,
                values: { title: 'Nuova', type: 'car' },
                card: null,
            });

            await choose(libretto);
            await userEvent.click(screen.getByRole('button', { name: 'Save' }));
            await settle();

            expect(
                screen.getByText(
                    'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                ),
            ).toBeInTheDocument();
        });
    });
});
