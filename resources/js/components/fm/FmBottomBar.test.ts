import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmBottomBar from './FmBottomBar.vue';
import type { Section } from './navigation';
import { navigationItems } from './navigation';

/**
 * The bar with the real items of the app, on the given page.
 */
function bar(active: Section) {
    return render(FmBottomBar, {
        props: { active, items: navigationItems('1.2.0') },
    });
}

describe('FmBottomBar', () => {
    it('marks the item of the page that is open', () => {
        bar('areas');

        expect(screen.getByRole('link', { name: 'Areas' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('opens Add with the +: upload a file, scan, a new expense, a new reminder', async () => {
        bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'Add' }));

        const sheet = screen.getByRole('dialog', { name: 'Add' });
        const rows: [string, string, string][] = [
            ['Upload a file', 'PDF or photos from the phone', '/upload'],
            ['Scan a sheet', 'Photograph a sheet with the camera', '/scan'],
            [
                'New expense',
                'An expense already made, without a document',
                '/budget/movements/create',
            ],
            [
                'New task',
                'Something to remember, with the date',
                '/reminders/create',
            ],
        ];
        const links = within(sheet).getAllByRole('link');
        expect(links).toHaveLength(rows.length);
        rows.forEach(([title, hint, href], i) => {
            expect(links[i]).toHaveTextContent(title);
            expect(links[i]).toHaveTextContent(hint);
            expect(links[i]).toHaveAttribute('href', href);
        });
    });

    it('puts To sort first in Add, with how many files wait, only when some do', async () => {
        const view = render(FmBottomBar, {
            props: { active: 'home', items: navigationItems(), uploadCount: 5 },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Add, 5 to sort' }),
        );

        const sheet = screen.getByRole('dialog', { name: 'Add' });
        const first = within(sheet).getAllByRole('link')[0];
        expect(first).toHaveAttribute('href', '/to-sort');
        expect(first).toHaveAccessibleName('To sort, 5 files');
        expect(first).toHaveTextContent('Files uploaded to complete');
        view.unmount();

        bar('home');
        await userEvent.click(screen.getByRole('button', { name: 'Add' }));
        expect(
            within(screen.getByRole('dialog', { name: 'Add' })).queryByRole(
                'link',
                { name: /To sort/ },
            ),
        ).toBeNull();
    });

    it('has Home, Budget, Add, Areas and More, in this order', () => {
        bar('home');

        const nav = screen.getByRole('navigation', { name: 'Sections' });
        expect(
            [...nav.querySelectorAll('a, button')].map(
                (item) =>
                    item.getAttribute('aria-label') ?? item.textContent?.trim(),
            ),
        ).toEqual(['Home', 'Budget', 'Add', 'Areas', 'More']);
        expect(screen.getByRole('link', { name: 'Budget' })).toHaveAttribute(
            'href',
            '/budget',
        );
    });

    it('marks Budget on a page of the budget', () => {
        bar('budget');

        expect(screen.getByRole('link', { name: 'Budget' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('opens Documents, Family, Settings and Log out from More', async () => {
        bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'More' }));

        const sheet = screen.getByRole('dialog', { name: 'More' });
        expect(sheet).toHaveTextContent('Documents');
        expect(sheet).toHaveTextContent('Family');
        expect(sheet).toHaveTextContent('Settings');
        expect(
            screen.getByRole('button', { name: 'Log out' }),
        ).toBeInTheDocument();
    });

    it("has the version after Report a problem or an idea in More: it closes More and asks to open What's new", async () => {
        const view = bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'More' }));
        const sheet = screen.getByRole('dialog', { name: 'More' });
        expect(sheet.textContent).toMatch(
            /Report a problem or an idea.*Version 1\.2\.0.*What's new in the app.*Log out/,
        );

        await userEvent.click(
            within(sheet).getByRole('button', { name: /^Version 1\.2\.0/ }),
        );

        expect(view.emitted('news')).toHaveLength(1);
        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'More' }),
            ).not.toBeInTheDocument(),
        );
    });

    it('has Report a problem or an idea after Settings in More: it closes More and asks to open the panel', async () => {
        const view = bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'More' }));
        const sheet = screen.getByRole('dialog', { name: 'More' });
        expect(sheet.textContent).toMatch(
            /Settings.*Report a problem or an idea.*It reaches who makes Family Manager.*Log out/,
        );

        await userEvent.click(
            within(sheet).getByRole('button', {
                name: /^Report a problem or an idea/,
            }),
        );

        expect(view.emitted('report')).toHaveLength(1);
        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'More' }),
            ).not.toBeInTheDocument(),
        );
    });

    it('has Received mail in More, after Documents and before Family, with its line', async () => {
        bar('home');

        await userEvent.click(screen.getByRole('button', { name: 'More' }));

        const links = within(
            screen.getByRole('dialog', { name: 'More' }),
        ).getAllByRole('link');
        const titles = links.map((link) => link.textContent ?? '');
        const documents = titles.findIndex((t) => t.includes('Documents'));
        const family = titles.findIndex((t) => t.includes('Family'));
        expect(titles[documents + 1]).toContain('Received mail');
        expect(titles[documents + 1]).toContain(
            'The mail that arrived at the address for bills',
        );
        expect(links[documents + 1]).toHaveAttribute('href', '/mail');
        expect(family).toBe(documents + 2);
    });

    it('marks More on Received mail', () => {
        bar('mail');

        expect(screen.getByRole('button', { name: 'More' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('marks More on a page of More', () => {
        bar('documents');

        expect(screen.getByRole('button', { name: 'More' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('has Agenda as the first item of More, with what it shows', async () => {
        bar('agenda');

        expect(screen.getByRole('button', { name: 'More' })).toHaveAttribute(
            'aria-current',
            'page',
        );

        await userEvent.click(screen.getByRole('button', { name: 'More' }));

        const sheet = screen.getByRole('dialog', { name: 'More' });
        const links = within(sheet).getAllByRole('link');
        expect(links[0]).toHaveTextContent('Agenda');
        expect(links[0]).toHaveTextContent('What expires, month by month');
        expect(links[0]).toHaveAttribute('href', '/agenda');
    });

    it('marks More on Family too', () => {
        bar('family');

        expect(screen.getByRole('button', { name: 'More' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('shows how many files wait to be sorted on the +, nothing at zero and 99+ above 99', () => {
        const none = render(FmBottomBar, {
            props: { active: 'home', items: navigationItems(), uploadCount: 0 },
        });
        expect(screen.getByRole('button', { name: 'Add' })).toBeInTheDocument();
        none.unmount();

        const four = render(FmBottomBar, {
            props: { active: 'home', items: navigationItems(), uploadCount: 4 },
        });
        expect(
            screen.getByRole('button', { name: 'Add, 4 to sort' }),
        ).toBeInTheDocument();
        four.unmount();

        render(FmBottomBar, {
            props: {
                active: 'home',
                items: navigationItems(),
                uploadCount: 120,
            },
        });
        expect(screen.getByText('99+')).toBeInTheDocument();
    });
});
