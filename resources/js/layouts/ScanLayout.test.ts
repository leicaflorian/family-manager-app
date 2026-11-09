import { render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { describe, expect, it } from 'vite-plus/test';
import { trackChanges } from '@/lib/unsavedChanges';
import ScanLayout from './ScanLayout.vue';

describe('ScanLayout', () => {
    it('shows the page without the bar of the sections', () => {
        render(ScanLayout, { slots: { default: '<h1>New document</h1>' } });

        expect(
            screen.getByRole('heading', { name: 'New document' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    });

    it('asks before leaving with pages not uploaded', async () => {
        const stop = trackChanges(() => true);
        const view = render(ScanLayout, { slots: { default: '<p>Scan</p>' } });

        router.visit('/to-sort');

        expect(
            await screen.findByRole('dialog', {
                name: 'Leave without saving?',
            }),
        ).toBeInTheDocument();

        stop();
        view.unmount();
    });
});
