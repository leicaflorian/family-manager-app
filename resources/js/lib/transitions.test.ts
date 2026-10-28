import { describe, expect, it } from 'vite-plus/test';
import { pageVisitOptions } from './transitions';

describe('pageVisitOptions', () => {
    it('fades from one page to the next', () => {
        expect(pageVisitOptions({ method: 'get' })).toEqual({
            viewTransition: true,
        });
        expect(pageVisitOptions({})).toEqual({ viewTransition: true });
    });

    it('does not fade a visit that keeps the page, such as a filter or a form sent', () => {
        expect(
            pageVisitOptions({ method: 'get', preserveState: true }),
        ).toEqual({});
        expect(
            pageVisitOptions({ method: 'get', only: ['documents'] }),
        ).toEqual({});
        expect(pageVisitOptions({ method: 'post' })).toEqual({});
        expect(pageVisitOptions({ method: 'put' })).toEqual({});
    });

    it('fades a link too, which always says no transition', () => {
        expect(
            pageVisitOptions({
                method: 'get',
                only: [],
                viewTransition: false,
            }),
        ).toEqual({ viewTransition: true });
    });

    it('does not fade a page loaded ahead, which is not shown yet', () => {
        expect(
            pageVisitOptions({ prefetch: true, viewTransition: false }),
        ).toEqual({});
    });
});
