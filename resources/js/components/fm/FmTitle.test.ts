import { render, screen, within } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const state = vi.hoisted(() => ({
    component: 'documents/Documents',
    mode: 'phone',
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        component: state.component,
        props: { name: 'Fameja.app', unread_notices: 0 },
    }),
}));

vi.mock('@/composables/useLayoutMode', async (original) => {
    const { ref } = await import('vue');

    return {
        ...(await original<typeof import('@/composables/useLayoutMode')>()),
        useLayoutMode: () => ref(state.mode),
    };
});

beforeEach(() => {
    state.component = 'cards/Card';
    state.mode = 'phone';
});

import { defineComponent, h, provide, ref } from 'vue';
import { PAGE_INFO } from '@/lib/titles';
import FmTitle from './FmTitle.vue';

describe('FmTitle', () => {
    it('is the title of the page with the sentence below and the actions on the right', () => {
        render(FmTitle, {
            props: { title: 'Documents', subtitle: '18 documents' },
            slots: { actions: '<button>Upload</button>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Documents' }),
        ).toBeInTheDocument();
        expect(screen.getByText('18 documents')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Upload' }),
        ).toBeInTheDocument();
    });

    it('replaces the sentence with the subtitle slot when it is given', () => {
        render(FmTitle, {
            props: { title: 'Mail', subtitle: 'Plain sentence' },
            slots: { subtitle: '<button>Own sentence</button>' },
        });

        expect(
            screen.getByRole('button', { name: 'Own sentence' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('Plain sentence')).not.toBeInTheDocument();
    });

    it('can have something beside the title, on the same line', () => {
        render(FmTitle, {
            props: { title: 'Agenda' },
            slots: { beside: '<nav aria-label="Views">Tabs</nav>' },
        });

        expect(
            screen.getByRole('navigation', { name: 'Views' }),
        ).toBeInTheDocument();
    });

    it('has an overline above the title in the same column', () => {
        render(FmTitle, {
            props: { title: 'Fiat Panda' },
            slots: { overline: '<span>Vehicles</span>' },
        });

        const overline = screen.getByText('Vehicles');
        expect(
            overline.compareDocumentPosition(
                screen.getByRole('heading', { level: 1 }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        // The heading sits in a row with the «i», inside the same column
        expect(overline.parentElement).toBe(
            screen.getByRole('heading', { level: 1 }).closest('.flex-col'),
        );
    });

    it('has the leading slot before the column of the title', () => {
        render(FmTitle, {
            props: { title: 'Marco' },
            slots: { leading: '<span>Photo</span>' },
        });

        expect(
            screen
                .getByText('Photo')
                .compareDocumentPosition(
                    screen.getByRole('heading', { level: 1 }),
                ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('has no link back of its own', () => {
        render(FmTitle, { props: { title: 'Documents' } });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('is 28 px on the phone and the tablet and 32 px only on the computer', () => {
        render(FmTitle, { props: { title: 'Documents' } });

        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading).toHaveClass('text-title-mobile', 'computer:text-title');
        expect(heading.className).not.toContain('lg:');
    });

    describe('search and bell', () => {
        it('are beside the title of a section page on the phone', () => {
            state.component = 'documents/Documents';
            state.mode = 'phone';
            render(FmTitle, { props: { title: 'Documents' } });

            expect(
                screen.getByRole('link', { name: 'Search in Fameja.app' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /^Notices/ }),
            ).toBeInTheDocument();
        });

        it('are beside the title of From the bank on the phone, which has its trail', () => {
            state.component = 'budget/BankTransactions';
            state.mode = 'phone';
            render(FmTitle, { props: { title: 'From the bank' } });

            expect(
                screen.getByRole('link', { name: 'Search in Fameja.app' }),
            ).toBeInTheDocument();
        });

        it('are not on an inner page', () => {
            state.component = 'cards/Card';
            state.mode = 'phone';
            render(FmTitle, { props: { title: 'Fiat Panda' } });

            expect(screen.queryByRole('link')).not.toBeInTheDocument();
        });

        it('are not on the tablet', () => {
            state.component = 'documents/Documents';
            state.mode = 'tablet';
            render(FmTitle, { props: { title: 'Documents' } });

            expect(screen.queryByRole('link')).not.toBeInTheDocument();
        });

        it('stay in the row of a long title', () => {
            state.component = 'documents/Documents';
            state.mode = 'phone';
            render(FmTitle, { props: { title: 'A'.repeat(80) } });

            const row = screen
                .getByRole('heading', {
                    level: 1,
                })
                .closest('.justify-between');
            expect(row).not.toBeNull();
            expect(
                within(row as HTMLElement).getByRole('link', {
                    name: 'Search in Fameja.app',
                }),
            ).toBeInTheDocument();
        });
    });
    describe('the «i» of the page', () => {
        it('is beside the title when the page gives its key', () => {
            const Page = defineComponent({
                setup() {
                    provide(PAGE_INFO, ref('documents' as const));

                    return () => h(FmTitle, { title: 'Documents' });
                },
            });
            render(Page);

            const heading = screen.getByRole('heading', {
                level: 1,
                name: 'Documents',
            });
            expect(
                within(heading.parentElement as HTMLElement).getByRole(
                    'button',
                    { name: 'What is this page' },
                ),
            ).toBeInTheDocument();
        });

        it('is there with the info prop too, without a page around', () => {
            render(FmTitle, { props: { title: 'Family', info: 'people' } });

            expect(
                screen.getByRole('button', { name: 'What is this page' }),
            ).toBeInTheDocument();
        });

        it('is not there without a key', () => {
            render(FmTitle, { props: { title: 'Documents' } });

            expect(
                screen.queryByRole('button', { name: 'What is this page' }),
            ).not.toBeInTheDocument();
        });
    });
});
