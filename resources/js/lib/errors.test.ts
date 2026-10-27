import { describe, expect, it } from 'vite-plus/test';
import { errors, listErrors } from './errors';

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

describe('listErrors', () => {
    it('collects the messages of a list field and of its items', () => {
        expect(
            listErrors(
                {
                    files: 'Choose at least one file.',
                    'files.0': 'Each file can be at most 25 MB.',
                    'files.1':
                        'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                    filesize: 'Not this one.',
                    title: 'Not this one either.',
                },
                'files',
            ),
        ).toEqual([
            'Choose at least one file.',
            'Each file can be at most 25 MB.',
            'Only PDF, JPG, PNG and HEIC files can be uploaded.',
        ]);
    });
});
