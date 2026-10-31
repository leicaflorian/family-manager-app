/**
 * The layout each page of the app is drawn in, chosen from the name of the page.
 */
import type { Component } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * The layout of a page.
 *
 * @param name - The name of the page, as `auth/Login` or `Areas`.
 * @returns The layout, or `null` for the pages of the component catalogue,
 *   which draw the whole screen themselves (Inertia then mounts the page alone).
 */
export function pageLayout(name: string): Component | null {
    switch (true) {
        case name.startsWith('auth/'):
        case name.startsWith('welcome/'):
            return AuthLayout;
        case name.startsWith('dev/'):
            return null;
        default:
            return AppLayout;
    }
}
