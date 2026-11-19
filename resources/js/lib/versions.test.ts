import { describe, expect, it } from 'vite-plus/test';
import { isNewerVersion, isReleasedVersion } from './versions';

describe('isNewerVersion', () => {
    it('is true when the server has a higher version', () => {
        expect(isNewerVersion('2.3.0', '2.2.2')).toBe(true);
        expect(isNewerVersion('3.0.0', '2.9.9')).toBe(true);
        expect(isNewerVersion('2.2.3', '2.2.2')).toBe(true);
    });

    it('compares the numbers, not the text', () => {
        expect(isNewerVersion('2.10.0', '2.9.9')).toBe(true);
        expect(isNewerVersion('2.9.9', '2.10.0')).toBe(false);
    });

    it('is false for the same version or an older server', () => {
        expect(isNewerVersion('2.2.2', '2.2.2')).toBe(false);
        expect(isNewerVersion('2.2.1', '2.2.2')).toBe(false);
    });

    it('is false when one of the two is not a version, as in development', () => {
        expect(isNewerVersion('dev', '2.2.2')).toBe(false);
        expect(isNewerVersion('2.3.0', 'dev')).toBe(false);
        expect(isNewerVersion('', '2.2.2')).toBe(false);
        expect(isNewerVersion('2.3', '2.2.2')).toBe(false);
        expect(isNewerVersion('2.3.0-beta', '2.2.2')).toBe(false);
    });
});

describe('isReleasedVersion', () => {
    it('knows a released version from the one of development', () => {
        expect(isReleasedVersion('2.2.2')).toBe(true);
        expect(isReleasedVersion('dev')).toBe(false);
        expect(isReleasedVersion('2.2')).toBe(false);
    });
});
