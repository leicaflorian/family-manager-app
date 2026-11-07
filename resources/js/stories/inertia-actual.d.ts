/**
 * The real `@inertiajs/vue3`, under the name `resources/js/stories/inertia.ts`
 * imports it by (alias in `vite.config.ts`): Storybook gives the plain name to
 * the stories' own version.
 */
declare module '@inertiajs/vue3-actual' {
    export * from '@inertiajs/vue3';
}
