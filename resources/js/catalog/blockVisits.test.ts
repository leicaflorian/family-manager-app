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

    it('stops a router visit outside the catalogue and logs it', () => {
        router.post('/budget/x');

        expect(log).toEqual(['POST /budget/x']);
        expect(open).not.toHaveBeenCalled();
    });

    it('stops a reload of a page outside the catalogue', () => {
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

    it('lets the visits of the catalogue through', () => {
        let prevented: boolean | null = null;
        const listener = (event: Event): void => {
            prevented = event.defaultPrevented;
            // Seen: the test stops it itself, so no request leaves
            event.preventDefault();
        };
        document.addEventListener('inertia:before', listener);

        router.get('/dev/components?component=FmRow');
        document.removeEventListener('inertia:before', listener);

        expect(prevented).toBe(false);
        expect(log).toEqual([]);
    });

    it('stops a path that only begins like the catalogue', () => {
        router.get('/dev/componentsX');

        expect(log).toEqual(['GET /dev/componentsX']);
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
