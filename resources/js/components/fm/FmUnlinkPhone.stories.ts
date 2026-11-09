/**
 * The stories of `FmUnlinkPhone`: the question before unlinking a phone,
 * opened by choosing a phone with the `device` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmUnlinkPhone from './FmUnlinkPhone.vue';

const meta = {
    title: 'Panels/FmUnlinkPhone',
    component: FmUnlinkPhone,
    parameters: { docs: { story: { inline: false, iframeHeight: 400 } } },
} satisfies Meta<typeof FmUnlinkPhone>;

export default meta;

type Story = StoryObj<typeof meta>;

export const UnlinkAPhone: Story = {
    name: 'Unlink a phone',
    args: {
        device: {
            id: 1,
            name: 'iPhone of Marco',
            linked_on: '2026-10-12',
            last_upload_on: '2026-10-24',
            linked_now: false,
        },
    },
};
