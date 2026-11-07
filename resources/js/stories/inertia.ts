/**
 * The Inertia of Storybook (`.storybook/main.ts` aliases `@inertiajs/vue3` to
 * this file, for the stories only). It gives back everything the real package
 * gives, except `usePage`, which reads a fake page: a story has no server to
 * share the props of the page, so it sets them with the `page` parameter
 * (`setStoryPage`).
 */
import type { InertiaConfig, Page } from '@inertiajs/core';
import { reactive } from 'vue';
import { sharedPageProps } from '@/test/fixtures';

// The real package, by an alias that Storybook's `@inertiajs/vue3` one does not catch
export * from '@inertiajs/vue3-actual';

/** The props every page shares. */
type SharedProps = InertiaConfig['sharedPageProps'];

/** The fake page every `usePage()` of the stories reads. */
const page: Page<SharedProps> = reactive({
    component: 'Story',
    props: { ...sharedPageProps, errors: {} },
    url: '/',
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    flash: {},
    rememberedState: {},
});

/**
 * Sets the shared props of the fake page: the defaults of the Bianchi family,
 * with the given ones in their place. The page object stays the same, so
 * whoever already holds it sees the change.
 *
 * @param props - The shared props the story wants different from the default.
 */
export function setStoryPage(props: Partial<SharedProps>): void {
    page.props = { ...sharedPageProps, ...props, errors: {} };
}

/**
 * The fake page of the story, in place of the one Inertia gives the real app.
 *
 * @returns The reactive page, with the props set by `setStoryPage`.
 */
export function usePage(): Page<SharedProps> {
    return page;
}
