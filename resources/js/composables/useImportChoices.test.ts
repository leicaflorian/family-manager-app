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
import { enrolmentProposal, ferriProposal } from '@/test/fixtures';
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

    it('sends Create the card only when the paper names no card and the person ticks it', () => {
        const { choices } = mount({ proposal: enrolmentProposal });
        expect(choices.also.value).not.toHaveProperty('create_card');

        choices.picks.createCard = true;
        expect(choices.also.value).toMatchObject({ create_card: true });

        const ferri = mount();
        ferri.choices.picks.createCard = true;
        expect(ferri.choices.also.value).not.toHaveProperty('create_card');
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
        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
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
            }),
        );
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

    it('sends only the corrections the person wrote, in the shape of the server', () => {
        const { choices } = mount();

        choices.setOverride('movement', 'due_date', '2026-11-30');
        choices.setOverride('event', 'type', 'visit');
        choices.setOverride('policy', 'type', 'car');

        expect(choices.overrideOf('movement', 'due_date')).toBe('2026-11-30');
        expect(choices.overrideOf('event', 'title')).toBeUndefined();
        expect(choices.also.value?.overrides).toEqual({
            movement: { due_date: '2026-11-30' },
            event: { type: 'visit' },
            records: { policy: { type: 'car' } },
        });
    });

    it('ticks, unticks and links each box', () => {
        const { choices } = mount();

        choices.setTicked('movement', false);
        choices.setTicked('organization', false);
        choices.setTarget('event', 41);
        choices.setTarget('organization', 'new');

        expect(choices.targetOf('event')).toBe(41);
        expect(choices.targetOf('organization')).toBe('new');
        expect(choices.targetOf('vehicle')).toBe('auto');
        expect(choices.also.value).toMatchObject({
            movement: false,
            records: [],
            targets: { event: 41, records: { organization: 'new' } },
        });
    });

    it('cancels the request still running when it asks again, so an older proposal never comes after a newer one', async () => {
        const tokens: { cancel: ReturnType<typeof vi.fn> }[] = [];
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            const token = { cancel: vi.fn() };
            tokens.push(token);
            options?.onCancelToken?.(token);
        });
        const { choices } = mount();

        choices.setTicked('movement', false);
        await vi.advanceTimersByTimeAsync(400);
        choices.setTicked('event', false);
        await vi.advanceTimersByTimeAsync(400);

        expect(tokens).toHaveLength(2);
        expect(tokens[0]!.cancel).toHaveBeenCalledOnce();
        expect(tokens[1]!.cancel).not.toHaveBeenCalled();
    });

    it('cancels the request running when the page goes away', async () => {
        const token = { cancel: vi.fn() };
        vi.spyOn(router, 'reload').mockImplementation((options) => {
            options?.onCancelToken?.(token);
        });
        const { choices, unmount } = mount();

        choices.setTicked('movement', false);
        await vi.advanceTimersByTimeAsync(400);
        unmount();

        expect(token.cancel).toHaveBeenCalledOnce();
    });

    it('asks nothing after cancel, until the next change', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        const { choices } = mount();

        choices.setTicked('movement', false);
        await vi.advanceTimersByTimeAsync(100);
        choices.cancel();
        await vi.advanceTimersByTimeAsync(1000);

        expect(reload).not.toHaveBeenCalled();
    });

    it('knows whether the person changed anything', () => {
        const { choices } = mount();
        expect(choices.changed.value).toBe(false);

        choices.setOverride('event', 'title', 'Controllo');

        expect(choices.changed.value).toBe(true);
    });
});
