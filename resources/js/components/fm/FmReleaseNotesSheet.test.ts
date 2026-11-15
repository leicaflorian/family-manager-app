import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmReleaseNotesSheet from './FmReleaseNotesSheet.vue';

const notes: App.Data.ReleaseNoteData[] = [
    {
        version: '1.2.0',
        date: '2026-11-15',
        notes: ['The version is in the account menu.', 'New emails.'],
    },
    {
        version: '1.1.0',
        date: '2026-11-01',
        notes: ['A new Agenda.'],
    },
];

describe('FmReleaseNotesSheet', () => {
    it('asks for the notes when it opens', async () => {
        const view = renderPage(FmReleaseNotesSheet, {
            open: true,
            release: '1.2.0',
        });
        await screen.findByRole('dialog', { name: "What's new" });

        expect(view.emitted('load')).toHaveLength(1);
        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });

    it('does not ask for the notes while closed', () => {
        const view = renderPage(FmReleaseNotesSheet, {
            open: false,
            release: '1.2.0',
        });

        expect(view.emitted('load')).toBeUndefined();
    });

    it('shows each version, newest first, with its day and what changed', async () => {
        renderPage(FmReleaseNotesSheet, {
            open: true,
            release: '1.2.0',
            notes,
        });
        await screen.findByRole('dialog', { name: "What's new" });

        const versions = screen.getAllByRole('region');
        expect(versions.map((v) => v.getAttribute('aria-label'))).toEqual([
            'Version 1.2.0',
            'Version 1.1.0',
        ]);
        expect(within(versions[0]).getByText('15/11/2026')).toBeInTheDocument();
        expect(
            within(versions[0])
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['The version is in the account menu.', 'New emails.']);
        expect(within(versions[1]).getByText('01/11/2026')).toBeInTheDocument();
    });

    it('marks the version that runs', async () => {
        renderPage(FmReleaseNotesSheet, {
            open: true,
            release: '1.1.0',
            notes,
        });
        await screen.findByRole('dialog', { name: "What's new" });

        const versions = screen.getAllByRole('region');
        expect(
            within(versions[0]).queryByText('Your version'),
        ).not.toBeInTheDocument();
        expect(
            within(versions[1]).getByText('Your version'),
        ).toBeInTheDocument();
    });

    it('says when there is no news', async () => {
        renderPage(FmReleaseNotesSheet, {
            open: true,
            release: 'dev',
            notes: [],
        });
        await screen.findByRole('dialog', { name: "What's new" });

        expect(screen.getByText('No news yet.')).toBeInTheDocument();
    });
});
