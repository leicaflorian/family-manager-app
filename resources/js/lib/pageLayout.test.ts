import { describe, expect, it } from 'vite-plus/test';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { pageLayout } from './pageLayout';

describe('pageLayout', () => {
    it('puts the sign-in and welcome pages in the sign-in layout', () => {
        expect(pageLayout('auth/Login')).toBe(AuthLayout);
        expect(pageLayout('welcome/Family')).toBe(AuthLayout);
    });

    it('leaves the pages of the catalogue without a layout', () => {
        expect(pageLayout('dev/Components')).toBeNull();
        expect(pageLayout('dev/ComponentFrame')).toBeNull();
    });

    it('puts every other page in the layout of the app', () => {
        expect(pageLayout('Areas')).toBe(AppLayout);
        expect(pageLayout('documents/Documents')).toBe(AppLayout);
    });
});
