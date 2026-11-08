/**
 * The stories of `FmCardForm`: a new insurance
 * policy, an existing one (the vehicle shows for a car policy), a utility
 * whose code is named by the type, an error of the server and the form while
 * it sends.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { policyFields, utilityFields } from '@/test/fixtures';
import FmCardForm from './FmCardForm.vue';

const meta = {
    title: 'Forms/FmCardForm',
    component: FmCardForm,
} satisfies Meta<typeof FmCardForm>;

export default meta;

type Story = StoryObj<typeof meta>;

export const New: Story = {
    args: { fields: policyFields, values: { type: 'car' } },
};

export const Edit: Story = {
    args: {
        fields: policyFields,
        values: { title: 'RC auto Panda', type: 'car', vehicle_id: 1 },
    },
};

export const LabelByType: Story = {
    name: 'Label by type',
    args: {
        fields: utilityFields,
        values: { type: 'gas', supply_point_code: '14631000123456' },
    },
};

export const WithAnError: Story = {
    name: 'With an error',
    args: {
        fields: policyFields,
        values: { title: '', type: 'home' },
        serverErrors: { title: 'The title is required.' },
    },
};

export const Processing: Story = {
    args: {
        fields: policyFields,
        values: { title: 'RC auto Panda', type: 'car', vehicle_id: 1 },
        processing: true,
    },
};
