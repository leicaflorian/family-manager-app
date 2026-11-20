import { http } from '@inertiajs/vue3';
import { effectScope, nextTick, ref } from 'vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { useBankMatchPreview } from './useBankMatchPreview';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const request =
    vi.fn<
        (
            config: Config,
        ) => Promise<{ status: number; data: unknown; headers: object }>
    >();

const ok = (count: number) => ({
    status: 200,
    headers: {},
    data: { count, rows: [] },
});

beforeEach(() => {
    vi.useFakeTimers();
    request.mockReset();
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.useRealTimers();
});

/** Runs the composable in a scope, with the sources it reads. */
function start(pattern = '') {
    const text = ref(pattern);
    const kind = ref<'expense' | 'income'>('expense');
    const account = ref<number | null>(null);
    const scope = effectScope();
    const preview = scope.run(() =>
        useBankMatchPreview({
            pattern: () => text.value,
            kind: () => kind.value,
            accountId: () => account.value,
        }),
    )!;

    return { text, kind, account, preview, stop: () => scope.stop() };
}

describe('useBankMatchPreview', () => {
    it('asks half a second after the last letter, not at every letter', async () => {
        request.mockResolvedValue(ok(3));
        const { text, preview } = start();

        text.value = 'SC';
        await nextTick();
        await vi.advanceTimersByTimeAsync(400);
        text.value = 'SCUOLA';
        await nextTick();
        await vi.advanceTimersByTimeAsync(499);

        expect(request).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);

        expect(request).toHaveBeenCalledTimes(1);
        expect(request.mock.calls[0]![0].url).toContain('pattern=SCUOLA');
        expect(preview.result.value?.count).toBe(3);
        expect(preview.error.value).toBeNull();
    });

    it('looks at the rule the field starts with', async () => {
        request.mockResolvedValue(ok(1));
        const { preview } = start('ENEL');

        await vi.advanceTimersByTimeAsync(500);

        expect(preview.result.value?.count).toBe(1);
    });

    it('asks again when the direction or the account changes, and says nothing for an empty rule', async () => {
        request.mockResolvedValue(ok(2));
        const { text, kind, account, preview } = start('ENEL');
        await vi.advanceTimersByTimeAsync(500);

        kind.value = 'income';
        await nextTick();
        await vi.advanceTimersByTimeAsync(500);
        account.value = 5;
        await nextTick();
        await vi.advanceTimersByTimeAsync(500);

        expect(request).toHaveBeenCalledTimes(3);
        expect(request.mock.calls[2]![0].url).toContain('account_id=5');

        text.value = '  ';
        await nextTick();
        await vi.advanceTimersByTimeAsync(500);

        expect(request).toHaveBeenCalledTimes(3);
        expect(preview.result.value).toBeNull();
    });

    it('keeps the message of a rule that does not work, and drops it with the next good one', async () => {
        request.mockRejectedValueOnce(
            Object.assign(new Error('422'), {
                response: {
                    status: 422,
                    data: {
                        errors: {
                            pattern: ['This rule does not work.'],
                        },
                    },
                },
            }),
        );
        const { text, preview } = start('.*');
        await vi.advanceTimersByTimeAsync(500);

        expect(preview.error.value).toBe('This rule does not work.');
        expect(preview.result.value).toBeNull();

        request.mockResolvedValue(ok(1));
        text.value = 'ENEL';
        await nextTick();
        await vi.advanceTimersByTimeAsync(500);

        expect(preview.error.value).toBeNull();
        expect(preview.result.value?.count).toBe(1);
    });

    it('drops an answer that is no longer for the last rule', async () => {
        let first: (value: unknown) => void = () => undefined;
        request.mockImplementationOnce(
            () =>
                new Promise(
                    (resolve) => (first = resolve as (value: unknown) => void),
                ),
        );
        request.mockResolvedValueOnce(ok(9));
        const { text, preview } = start('OLD');
        await vi.advanceTimersByTimeAsync(500);

        text.value = 'NEW';
        await nextTick();
        await vi.advanceTimersByTimeAsync(500);
        first(ok(1));
        await vi.advanceTimersByTimeAsync(0);

        expect(preview.result.value?.count).toBe(9);
    });

    it('does not ask after it was stopped', async () => {
        const { text, stop } = start();

        text.value = 'ENEL';
        await nextTick();
        stop();
        await vi.advanceTimersByTimeAsync(600);

        expect(request).not.toHaveBeenCalled();
    });
});
