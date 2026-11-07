import { router } from '@inertiajs/vue3';
import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, ref } from 'vue';
import type { DocumentPayload } from '@/lib/documents';
import { ferriProposal } from '@/test/fixtures';
import type { ImportChoices } from './useImportChoices';
import { useImportChoices } from './useImportChoices';

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

const document: DocumentPayload = {
    title: 'Visita dal dentista',
    category_id: null,
    person_id: 2,
    organization_id: null,
    date: '2026-10-22',
    expiry_date: null,
    status: 'active',
    tags: [],
    notes: null,
    links: [],
};

/** Mounts a component with the choices of the Studio Ferri invoice, and gives them back. */
function mount(
    options: {
        proposal?: App.Data.Reading.ProposalData;
        document?: ReturnType<typeof ref<DocumentPayload>>;
        enabled?: ReturnType<typeof ref<boolean>>;
    } = {},
): { choices: ImportChoices; unmount: () => void } {
    let choices!: ImportChoices;
    const view = render(
        defineComponent({
            setup() {
                choices = useImportChoices(
                    7,
                    () => options.proposal ?? ferriProposal,
                    {
                        document: () => options.document?.value,
                        enabled: () => options.enabled?.value ?? true,
                    },
                );

                return () => null;
            },
        }),
    );

    return { choices, unmount: () => view.unmount() };
}

describe('useImportChoices', () => {
    it('starts from what the proposal suggests, with nothing chosen by hand', () => {
        const { choices } = mount();

        expect(choices.also.value).toEqual({
            movement: true,
            event: true,
            records: ['organization'],
            targets: { movement: 'auto', event: 'auto', records: {} },
            policy: 'auto',
        });
    });

    it('takes the boxes, the targets, to pay and the policy the person chose', () => {
        const { choices } = mount();

        choices.picks.movement = false;
        choices.picks.records.organization = false;
        choices.picks.targets.event = 41;
        choices.picks.targets.records.organization = 'new';
        choices.picks.due = true;
        choices.picks.policy = 'none';

        expect(choices.also.value).toEqual({
            movement: false,
            event: true,
            records: [],
            targets: {
                movement: 'auto',
                event: 41,
                records: { organization: 'new' },
            },
            due: true,
            policy: 'none',
        });
    });

    it('gives nothing to send without a proposal', () => {
        let choices!: ImportChoices;
        render(
            defineComponent({
                setup() {
                    choices = useImportChoices(7, () => undefined);

                    return () => null;
                },
            }),
        );

        expect(choices.also.value).toBeUndefined();
    });

    it('asks the proposal again once, 400 ms after the last change, reloading only the proposals', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const { choices } = mount({ document: ref(document) });

        choices.picks.movement = false;
        await vi.advanceTimersByTimeAsync(200);
        choices.picks.due = true;
        await vi.advanceTimersByTimeAsync(399);
        expect(reload).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(reload).toHaveBeenCalledOnce();
        expect(reload).toHaveBeenCalledWith({
            only: ['proposals'],
            preserveUrl: true,
            data: {
                check: {
                    file_id: 7,
                    document,
                    also: expect.objectContaining({
                        movement: false,
                        due: true,
                    }),
                },
            },
        });
    });

    it('asks again when the document changes', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const values = ref(document);
        mount({ document: values });

        values.value = { ...document, date: '2026-10-23' };
        await vi.advanceTimersByTimeAsync(400);

        expect(reload).toHaveBeenCalledOnce();
    });

    it('asks again at once with refresh', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const { choices } = mount();

        choices.refresh();
        await vi.advanceTimersByTimeAsync(0);

        expect(reload).toHaveBeenCalledOnce();
    });

    it('does not ask for changes made while it is not enabled', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const enabled = ref(false);
        const values = ref(document);
        mount({ document: values, enabled });

        values.value = { ...document, title: 'Dentista' };
        await vi.advanceTimersByTimeAsync(1000);

        expect(reload).not.toHaveBeenCalled();
    });

    it('does not ask after the page went away', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const { choices, unmount } = mount();

        choices.picks.movement = false;
        await vi.advanceTimersByTimeAsync(100);
        unmount();
        await vi.advanceTimersByTimeAsync(1000);

        expect(reload).not.toHaveBeenCalled();
    });
});
