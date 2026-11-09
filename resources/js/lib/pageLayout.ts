/**
 * The layout each page of the app is drawn in, chosen from the name of the page.
 */
import type { Component } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ScanLayout from '@/layouts/ScanLayout.vue';

/**
 * The layout of a page.
 *
 * @param name - The name of the page, as `auth/Login` or `Areas`.
 * @returns The layout.
 */
export function pageLayout(name: string): Component {
    switch (true) {
        case name.startsWith('auth/'):
        case name.startsWith('welcome/'):
        case name.startsWith('connect/'):
            return AuthLayout;
        // The scan has its own bars: no bar of the sections
        case name === 'scan/Scan':
        case name === 'scan/Scanner':
            return ScanLayout;
        default:
            return AppLayout;
    }
}
