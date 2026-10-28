import { describe, expect, it } from 'vite-plus/test';
import { withMethod } from './forms';

describe('withMethod', () => {
    it('leaves the data of a POST as they are', () => {
        expect(withMethod({ name: 'Coop' }, 'post')).toEqual({ name: 'Coop' });
    });

    it('sends a PUT as a POST with _method, so a file can go with it', () => {
        expect(withMethod({ name: 'Coop' }, 'put')).toEqual({
            name: 'Coop',
            _method: 'put',
        });
    });
});
