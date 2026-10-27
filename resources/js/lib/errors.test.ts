import { describe, expect, it } from 'vite-plus/test';
import { errors } from './errors';

describe('errors', () => {
    it('turns a message of the server into the list FormKit wants', () => {
        expect(errors('The email field is required.')).toEqual([
            'The email field is required.',
        ]);
    });

    it('gives an empty list without a message', () => {
        expect(errors(undefined)).toEqual([]);
    });
});
