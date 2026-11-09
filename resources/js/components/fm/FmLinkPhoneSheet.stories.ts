/**
 * The stories of `FmLinkPhoneSheet`: the panel with no phone linked,
 * with one that linked a while ago and with one that has just entered. Opened with
 * *Open*; the answers of the server are made up here.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { sampleQr } from '@/stories/qr';
import FmLinkPhoneSheet from './FmLinkPhoneSheet.vue';

/** A phone linked a while ago. */
const iphone: App.Data.LinkedDeviceData = {
    id: 1,
    name: 'iPhone of Marco',
    linked_on: fromToday(-13),
    last_upload_on: fromToday(-1),
    linked_now: false,
};

/**
 * Stands in for the server: the panel asks `/settings/devices` for the code and
 * the phones, and here the answer is made up.
 *
 * @param devices - the phones the panel lists
 * @returns the decorator for the story
 */
function server(devices: App.Data.LinkedDeviceData[]) {
    return (): { template: string } => {
        window.fetch = (): Promise<Response> =>
            Promise.resolve(
                Response.json({
                    code: 'K7QM-XPA9',
                    qr: sampleQr,
                    address: 'familymanager.it/connect',
                    expires_at: new Date(Date.now() + 600_000).toISOString(),
                    person: 'Marco',
                    family: 'Bianchi',
                    devices,
                }),
            );

        return { template: '<story />' };
    };
}

const meta = {
    title: 'Panels/FmLinkPhoneSheet',
    component: FmLinkPhoneSheet,
    parameters: { docs: { story: { inline: false, iframeHeight: 700 } } },
} satisfies Meta<typeof FmLinkPhoneSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NoPhone: Story = {
    name: 'No phone linked',
    args: { open: false },
    decorators: [server([])],
};

export const OnePhone: Story = {
    name: 'One phone linked',
    args: { open: false },
    decorators: [server([iphone])],
};

export const LinkedNow: Story = {
    name: 'A phone has just entered',
    args: { open: false },
    decorators: [
        server([{ ...iphone, last_upload_on: null, linked_now: true }]),
    ],
};
