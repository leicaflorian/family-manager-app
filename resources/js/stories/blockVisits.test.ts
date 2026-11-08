import { router, useForm } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { blockVisits } from './blockVisits';

describe('blockVisits', () => {
    let log: string[];
    let stop: () => void;
    let open: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        log = [];
        stop = blockVisits((entry) => log.push(entry));
        // Inertia 3 sends its visits with XMLHttpRequest: nothing may open one
        open = vi.spyOn(XMLHttpRequest.prototype, 'open');
    });

    afterEach(() => {
        stop();
        open.mockRestore();
        window.history.replaceState(null, '', '/');
    });

    it('lets no visit through by default', () => {
        router.get('/allowed/page?id=1');

        expect(log).toEqual(['GET /allowed/page?id=1']);
        expect(open).not.toHaveBeenCalled();
    });

    it('stops a router visit and logs it', () => {
        router.post('/budget/x');

        expect(log).toEqual(['POST /budget/x']);
        expect(open).not.toHaveBeenCalled();
    });

    it('stops a reload of the page', () => {
        window.history.replaceState(null, '', '/budget/2026-10');

        router.reload();

        expect(log).toEqual(['GET /budget/2026-10']);
        expect(open).not.toHaveBeenCalled();
    });

    it('stops the visit of a form', () => {
        useForm({}).post('/budget/x');

        expect(log).toEqual(['POST /budget/x']);
        expect(open).not.toHaveBeenCalled();
    });

    it('lets through the visits to a path it is told to allow', () => {
        stop();
        stop = blockVisits((entry) => log.push(entry), ['/allowed/page']);
        let prevented: boolean | null = null;
        const listener = (event: Event): void => {
            prevented = event.defaultPrevented;
            // Seen: the test stops it itself, so no request leaves
            event.preventDefault();
        };
        document.addEventListener('inertia:before', listener);

        router.get('/allowed/page?id=1');
        document.removeEventListener('inertia:before', listener);

        expect(prevented).toBe(false);
        expect(log).toEqual([]);
    });

    it('stops a path that only begins like an allowed one', () => {
        stop();
        stop = blockVisits((entry) => log.push(entry), ['/allowed/page']);

        router.get('/allowed/pageX');

        expect(log).toEqual(['GET /allowed/pageX']);
        expect(open).not.toHaveBeenCalled();
    });

    it('stops listening once stopped', () => {
        stop();
        let prevented: boolean | null = null;
        const listener = (event: Event): void => {
            prevented = event.defaultPrevented;
            event.preventDefault();
        };
        document.addEventListener('inertia:before', listener);

        router.post('/budget/x');
        document.removeEventListener('inertia:before', listener);

        expect(prevented).toBe(false);
        expect(log).toEqual([]);
    });
});
