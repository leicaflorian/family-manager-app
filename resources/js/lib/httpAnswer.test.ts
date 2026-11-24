import { describe, expect, it } from 'vite-plus/test';
import { failedAnswer, jsonBody, serverMessage } from '@/lib/httpAnswer';

describe('jsonBody', () => {
    it('keeps a body the client has already read', () => {
        expect(jsonBody({ count: 2 })).toEqual({ count: 2 });
    });

    it('reads a body that came as text', () => {
        expect(jsonBody('{"message":"No"}')).toEqual({ message: 'No' });
    });

    it('gives null for text that is not a JSON object', () => {
        expect(jsonBody('<html>')).toBeNull();
        expect(jsonBody('42')).toBeNull();
        expect(jsonBody('null')).toBeNull();
    });

    it('gives null without a body', () => {
        expect(jsonBody(undefined)).toBeNull();
        expect(jsonBody(null)).toBeNull();
        expect(jsonBody(7)).toBeNull();
    });
});

describe('failedAnswer', () => {
    it('reads the status and the body of an error of the client', () => {
        expect(
            failedAnswer({
                response: { status: 422, data: '{"message":"Too big"}' },
            }),
        ).toEqual({ status: 422, body: { message: 'Too big' } });
    });

    it('gives 0 and no body when the server did not answer', () => {
        expect(failedAnswer(new Error('Network'))).toEqual({
            status: 0,
            body: null,
        });
        expect(failedAnswer(null)).toEqual({ status: 0, body: null });
    });
});

describe('serverMessage', () => {
    it('gives the message the server wrote', () => {
        expect(serverMessage({ message: 'Too big' })).toBe('Too big');
    });

    it('gives null for an empty or missing message', () => {
        expect(serverMessage({ message: '' })).toBeNull();
        expect(serverMessage({ message: 3 })).toBeNull();
        expect(serverMessage(null)).toBeNull();
    });
});
