import { afterEach, describe, expect, it } from 'vite-plus/test';
import { progressColor } from './progress';

describe('progressColor', () => {
    afterEach(() => {
        document.documentElement.style.removeProperty('--action');
    });

    it('is the colour of the action token, as the page has it', () => {
        document.documentElement.style.setProperty('--action', ' #0070e0 ');

        expect(progressColor()).toBe('#0070e0');
    });

    it('is empty when the tokens are not loaded', () => {
        expect(progressColor()).toBe('');
    });
});
