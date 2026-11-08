import { describe, expect, it, vi } from 'vite-plus/test';
import { useVisitQueue } from './useVisitQueue';

describe('useVisitQueue', () => {
    it('starts the first visit at once', () => {
        const { enqueue } = useVisitQueue();
        const first = vi.fn();

        enqueue(first);

        expect(first).toHaveBeenCalledOnce();
    });

    it('starts a second visit only when the first one finishes', () => {
        const { enqueue } = useVisitQueue();
        let finishFirst: () => void = () => {};
        const second = vi.fn();

        enqueue((done) => {
            finishFirst = done;
        });
        enqueue(second);

        expect(second).not.toHaveBeenCalled();

        finishFirst();

        expect(second).toHaveBeenCalledOnce();
    });

    it('frees the line when the queue is empty', () => {
        const { enqueue } = useVisitQueue();
        const later = vi.fn();

        enqueue((done) => done());
        enqueue(later);

        expect(later).toHaveBeenCalledOnce();
    });

    it('starts the next visit even when the one before ended with an error or was cancelled', () => {
        const { enqueue } = useVisitQueue();
        const calls: string[] = [];

        // `done` is what the visits call in `onFinish`, which Inertia runs after an error or a cancel too.
        enqueue((done) => {
            calls.push('first');
            done();
        });
        enqueue((done) => {
            calls.push('second');
            done();
        });
        enqueue(() => calls.push('third'));

        expect(calls).toEqual(['first', 'second', 'third']);
    });
});
