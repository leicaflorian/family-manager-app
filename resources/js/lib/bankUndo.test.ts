import { describe, expect, it } from 'vite-plus/test';
import { undoWords } from './bankUndo';

describe('undoWords', () => {
    it('says a payment the bank recorded is removed', () => {
        expect(undoWords('expense', 'delete')).toEqual({
            question: 'Take back this payment?',
            text: 'The expense is removed and the due date is to be paid again.',
        });
        expect(undoWords('income', 'delete').text).toBe(
            'The income is removed and the due date is to be paid again.',
        );
    });

    it('says a link to what was already there only comes off', () => {
        expect(undoWords('expense', 'detach')).toEqual({
            question: 'Take off this link?',
            text: 'The expense stays as it is. The movement goes back to be sorted.',
        });
        expect(undoWords('income', 'detach').text).toBe(
            'The income stays as it is. The movement goes back to be sorted.',
        );
    });
});
