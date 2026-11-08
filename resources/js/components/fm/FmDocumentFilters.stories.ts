/**
 * The stories of `FmDocumentFilters`: no filter,
 * some filters in use and only the expiring documents.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { documentFilterOptions } from '@/test/fixtures';
import FmDocumentFilters from './FmDocumentFilters.vue';

const none = {
    category: null,
    person: null,
    organization: null,
    status: null,
    expiring: false,
    search: null,
};

const meta = {
    title: 'Forms/FmDocumentFilters',
    component: FmDocumentFilters,
} satisfies Meta<typeof FmDocumentFilters>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NoFilter: Story = {
    name: 'No filter',
    args: { filters: none, options: documentFilterOptions },
};

export const FiltersInUse: Story = {
    name: 'Filters in use',
    args: {
        filters: { ...none, category: 4, person: 7, status: 'active' },
        options: documentFilterOptions,
    },
};

export const Expiring: Story = {
    args: {
        filters: { ...none, expiring: true },
        options: documentFilterOptions,
    },
};
