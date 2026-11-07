import { composeStories, setProjectAnnotations } from '@storybook/vue3';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import preview from '../../../.storybook/preview';
import { loadStories } from './files';

// The stories get what the preview gives them in Storybook: the plugins of
// the app, the language, the fake page, the blocked visits and the theme
beforeAll(() => {
    setProjectAnnotations([preview]);
});

/** A composed story, as far as this test uses it. */
type ComposedStory = {
    run: (context: { canvasElement: HTMLElement }) => Promise<void>;
};

describe('every story of the components', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it.each(Object.entries(loadStories()))(
        '%s mounts without warnings',
        async (_name, module) => {
            const warn = vi.spyOn(console, 'warn');
            const error = vi.spyOn(console, 'error');

            // The stories as the file exports them: the `meta` and one export for each story
            const stories = Object.values(
                composeStories(module),
            ) as ComposedStory[];

            expect(stories.length).toBeGreaterThan(0);
            // `run` mounts the story as Storybook does, with the plugins and the decorators of the preview
            for (const Story of stories) {
                const canvasElement = document.body.appendChild(
                    document.createElement('div'),
                );

                await Story.run({ canvasElement });
            }

            expect(warn).not.toHaveBeenCalled();
            expect(error).not.toHaveBeenCalled();
        },
    );
});
