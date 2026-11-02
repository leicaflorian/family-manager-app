import { router } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    answerLeave,
    askToLeave,
    guardVisits,
    hasChanges,
    leaveQuestion,
    sameValues,
    trackChanges,
} from './unsavedChanges';

describe('sameValues', () => {
    it('sees a changed text', () => {
        expect(sameValues({ title: 'Gas' }, { title: 'Luce' })).toBe(false);
        expect(sameValues({ title: 'Gas' }, { title: 'Gas' })).toBe(true);
    });

    it('takes empty values as the same: nothing written is nothing written', () => {
        expect(
            sameValues(
                { note: '', person: null, tags: [], date: undefined },
                { note: null, person: '', tags: undefined },
            ),
        ).toBe(true);
    });

    it('takes a number and the same number written as text as the same', () => {
        expect(sameValues({ amount: 84.2 }, { amount: '84.20' })).toBe(true);
        expect(sameValues({ amount: 84.2 }, { amount: '84.3' })).toBe(false);
    });

    it('looks inside lists and groups', () => {
        expect(
            sameValues(
                { rules: [{ days: 7 }, { days: 1 }] },
                { rules: [{ days: 7 }, { days: 2 }] },
            ),
        ).toBe(false);
        expect(sameValues({ types: ['a', 'b'] }, { types: ['b', 'a'] })).toBe(
            false,
        );
    });

    it('takes a file chosen again with the same name and size as the same', () => {
        const before = new File(['abc'], 'bolletta.pdf');

        expect(
            sameValues(
                { file: before },
                { file: new File(['xyz'], 'bolletta.pdf') },
            ),
        ).toBe(true);
        expect(sameValues({ file: null }, { file: before })).toBe(false);
    });
});

describe('the forms with changes', () => {
    it('has changes while one of the forms open has them', () => {
        let dirty = false;
        const stop = trackChanges(() => dirty);

        expect(hasChanges()).toBe(false);
        dirty = true;
        expect(hasChanges()).toBe(true);

        stop();
        expect(hasChanges()).toBe(false);
    });
});

describe('askToLeave', () => {
    afterEach(() => {
        if (leaveQuestion.open) {
            answerLeave(false);
        }
    });

    it('opens the question and says what the person answered', async () => {
        const stay = askToLeave();
        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);
        expect(await stay).toBe(false);
        expect(leaveQuestion.open).toBe(false);

        const leave = askToLeave();
        answerLeave(true);
        expect(await leave).toBe(true);
    });
});

describe('guardVisits', () => {
    let stopGuard: () => void;
    let stopTracking: () => void;
    let dirty: boolean;
    let passed: string[];

    /**
     * Notes the visits the guard let through, as `GET /agenda`, then stops
     * them itself: in the tests Inertia has no page to send them from.
     *
     * @param event - a visit about to start
     */
    function watchVisits(event: Event): void {
        const { visit } = (event as CustomEvent).detail as {
            visit: { method: string; url: URL };
        };

        if (!event.defaultPrevented) {
            passed.push(`${visit.method.toUpperCase()} ${visit.url.pathname}`);
        }

        event.preventDefault();
    }

    beforeEach(() => {
        window.history.replaceState(null, '', '/reminders/5/edit');
        dirty = true;
        passed = [];
        stopTracking = trackChanges(() => dirty);
        stopGuard = guardVisits();
        // After the guard: it sees whether the guard stopped the visit
        document.addEventListener('inertia:before', watchVisits);
    });

    afterEach(() => {
        if (leaveQuestion.open) {
            answerLeave(false);
        }
        document.removeEventListener('inertia:before', watchVisits);
        stopGuard();
        stopTracking();
        window.history.replaceState(null, '', '/');
    });

    /** The visits that went through. */
    function sent(): string[] {
        return passed;
    }

    it('stops a visit to another page and asks first', () => {
        router.visit('/agenda');

        expect(leaveQuestion.open).toBe(true);
        expect(sent()).toEqual([]);
    });

    it('stays when the person says so', async () => {
        router.visit('/agenda');
        answerLeave(false);
        await Promise.resolve();

        expect(sent()).toEqual([]);
    });

    it('makes the visit when the person leaves without saving', async () => {
        router.visit('/agenda');
        answerLeave(true);
        await vi.waitFor(() => expect(sent()).toEqual(['GET /agenda']));

        expect(leaveQuestion.open).toBe(false);
    });

    it('lets a visit through when no form has changes', () => {
        dirty = false;

        router.visit('/agenda');

        expect(leaveQuestion.open).toBe(false);
        expect(sent()).toEqual(['GET /agenda']);
    });

    it('lets the saving and the deleting through', () => {
        router.put('/reminders/5');
        router.delete('/reminders/5');

        expect(leaveQuestion.open).toBe(false);
        expect(sent()).toEqual(['PUT /reminders/5', 'DELETE /reminders/5']);
    });

    it('lets a reload of the page through', () => {
        router.reload({ only: ['reminders'] });

        expect(leaveQuestion.open).toBe(false);
        expect(sent()).toEqual(['GET /reminders/5/edit']);
    });

    it('gives up the changes with a Cancel marked so, and lets its visit through', () => {
        const link = document.createElement('a');
        link.dataset.discardChanges = '';
        link.addEventListener('click', (event) => {
            // Like the Link of Inertia: the visit starts at the click
            event.preventDefault();
            router.visit('/agenda');
        });
        document.body.append(link);
        let reset = false;
        const stop = trackChanges(
            () => !reset,
            () => {
                reset = true;
            },
        );
        dirty = false;

        link.click();

        expect(leaveQuestion.open).toBe(false);
        expect(sent()).toEqual(['GET /agenda']);
        stop();
        link.remove();
    });

    it('asks the browser to confirm before closing or reloading the tab', () => {
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);

        dirty = false;
        const clean = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(clean);
        expect(clean.defaultPrevented).toBe(false);
    });
});
