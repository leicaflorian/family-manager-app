import { describe, expect, it } from 'vite-plus/test';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ScanLayout from '@/layouts/ScanLayout.vue';
import { pageLayout } from './pageLayout';

describe('pageLayout', () => {
    it('puts the sign-in and welcome pages in the sign-in layout', () => {
        expect(pageLayout('auth/Login')).toBe(AuthLayout);
        expect(pageLayout('welcome/Family')).toBe(AuthLayout);
        expect(pageLayout('connect/Connect')).toBe(AuthLayout);
    });

    it('puts the scan in its own layout, without the bar of the sections', () => {
        expect(pageLayout('scan/Scan')).toBe(ScanLayout);
        expect(pageLayout('scan/Scanner')).toBe(ScanLayout);
        expect(pageLayout('scan/DewarpCheck')).toBe(AppLayout);
    });

    it('puts every other page in the layout of the app', () => {
        expect(pageLayout('Areas')).toBe(AppLayout);
        expect(pageLayout('documents/Documents')).toBe(AppLayout);
    });
});
