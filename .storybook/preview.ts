/**
 * Storybook preview: loads the stylesheet of the app, so the stories look as
 * they do in the pages.
 */
import type { Preview } from '@storybook/vue3-vite';
import '../resources/css/app.css';

const preview: Preview = {};

export default preview;
