import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    AGENT_URL,
    API,
    LIST_EVERY,
    STATUS_EVERY,
    failureOf,
    hasChoices,
    isOlder,
    scanners,
    status,
} from './agent';

afterEach(() => vi.unstubAllGlobals());

describe('agent', () => {
    it('has the fixed values', () => {
        expect(AGENT_URL).toBe('http://127.0.0.1:47115');
        expect([API, STATUS_EVERY, LIST_EVERY]).toEqual([2, 2000, 3]);
    });

    it('knows the choices only from api 2', () => {
        expect(hasChoices(null)).toBe(false);
        expect(hasChoices({ version: '1', os: 'mac', scanner: 'ready' })).toBe(
            false,
        );
        expect(
            hasChoices({ version: '1', os: 'mac', scanner: 'ready', api: 2 }),
        ).toBe(true);
    });

    it('compares versions', () => {
        expect(isOlder('1.0.0', '1.0.1')).toBe(true);
        expect(isOlder('1.10.0', '1.9.0')).toBe(false);
        expect(isOlder('1.0', '1.0.0')).toBe(false);
    });

    it('asks for the status with api=2 and the scanner', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(
                JSON.stringify({
                    version: '1',
                    os: 'mac',
                    scanner: 'ready',
                }),
            ),
        );
        vi.stubGlobal('fetch', fetchMock);
        await status('abc', 'flatbed');
        expect(fetchMock.mock.calls[0][0]).toBe(
            `${AGENT_URL}/status?api=2&scanner=abc&source=flatbed`,
        );
    });

    it('turns the answers into failures', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('refused')));
        await expect(scanners()).rejects.toMatchObject({
            reason: 'unreachable',
        });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(JSON.stringify({ error: 'origin_pending' }), {
                    status: 403,
                }),
            ),
        );
        await expect(scanners()).rejects.toMatchObject({ reason: 'pending' });
        expect(failureOf(new Error('x'))).toBe('failed');
    });
});
