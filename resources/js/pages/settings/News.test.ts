import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage, screenWidth, withTrail } from '@/test/page';
import News from './News.vue';

const notes: App.Data.ReleaseNoteData[] = [
    {
        version: '1.2.0',
        date: '2026-11-15',
        notes: ['The version is in the settings.', 'New emails.'],
    },
    {
        version: '1.1.0',
        date: '2026-11-01',
        notes: ['A new Agenda.'],
    },
];

describe("Settings, What's new", () => {
    it("writes the trail Settings > What's new into the top bar", () => {
        const { state, provide } = withTrail();
        renderPage(News, { release: '1.2.0', notes }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: "What's new" },
        ]);
    });

    it('shows each version, newest first, with its day and what changed', () => {
        renderPage(News, { release: '1.2.0', notes });

        expect(
            screen.getByRole('heading', { level: 1, name: "What's new" }),
        ).toBeInTheDocument();
        const versions = screen.getAllByRole('region');
        expect(versions.map((v) => v.getAttribute('aria-label'))).toEqual([
            'Version 1.2.0',
            'Version 1.1.0',
        ]);
        expect(within(versions[0]).getByText('15/11/2026')).toBeInTheDocument();
        expect(
            within(versions[0])
                .getAllByRole('listitem')
                .map((item) => item.textContent?.trim()),
        ).toEqual(['The version is in the settings.', 'New emails.']);
        expect(within(versions[1]).getByText('01/11/2026')).toBeInTheDocument();
    });

    it('marks the version that runs', () => {
        renderPage(News, { release: '1.1.0', notes });

        const versions = screen.getAllByRole('region');
        expect(
            within(versions[0]).queryByText('Your version'),
        ).not.toBeInTheDocument();
        expect(
            within(versions[1]).getByText('Your version'),
        ).toBeInTheDocument();
    });

    it('says when there is no news', () => {
        renderPage(News, { release: 'dev', notes: [] });

        expect(screen.getByText('No news yet.')).toBeInTheDocument();
    });

    it("marks What's new among the sections of the settings in the wide layout", () => {
        screenWidth(1280);
        renderPage(News, { release: '1.2.0', notes });

        const nav = screen.getByRole('navigation', {
            name: 'Settings sections',
        });
        expect(
            within(nav).getByRole('link', { name: /What's new/ }),
        ).toHaveAttribute('aria-current', 'page');
    });
});
