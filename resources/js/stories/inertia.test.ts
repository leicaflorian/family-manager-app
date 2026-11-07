import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { sharedPageProps } from '@/test/fixtures';
import { setStoryPage, usePage } from './inertia';

describe('the Inertia page of the stories', () => {
    beforeEach(() => {
        setStoryPage({});
    });

    it('gives the props of the Bianchi family by default', () => {
        expect(usePage().props).toMatchObject(sharedPageProps);
    });

    it('replaces the props one by one and keeps the others', () => {
        setStoryPage({ to_sort_count: 3, locale: 'it' });

        const { props } = usePage();

        expect(props.to_sort_count).toBe(3);
        expect(props.locale).toBe('it');
        expect(props.family).toEqual(sharedPageProps.family);
    });

    it('goes back to the defaults when a story sets nothing', () => {
        setStoryPage({ to_sort_count: 3 });
        setStoryPage({});

        expect(usePage().props.to_sort_count).toBe(0);
    });

    it('reaches the pages that already hold the page', () => {
        const page = usePage();

        setStoryPage({ unread_notices: 5 });

        expect(page.props.unread_notices).toBe(5);
    });
});
