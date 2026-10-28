import { fireEvent, render, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { h } from 'vue';
import { pickFile, stubObjectUrls } from '@/test/page';
import FmImagePanel from './FmImagePanel.vue';

/** What the slot shows: the image it gets, or the placeholder. */
const slots = {
    default: ({ image }: { image: string | null }) =>
        image ? h('img', { src: image, alt: 'Giulia' }) : h('span', 'GB'),
};

/** The labels of a photo. */
const labels = { addLabel: 'Add a photo', changeLabel: 'Change the photo' };

beforeEach(() => {
    stubObjectUrls();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('FmImagePanel', () => {
    it('without an image shows the placeholder and offers to add one', () => {
        render(FmImagePanel, { props: { image: null, ...labels }, slots });

        expect(screen.getByText('GB')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add a photo' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Remove' })).toBeNull();
    });

    it('with an image shows it and offers to change or remove it', () => {
        render(FmImagePanel, {
            props: { image: '/files/3', ...labels },
            slots,
        });

        expect(screen.getByRole('img', { name: 'Giulia' })).toHaveAttribute(
            'src',
            '/files/3',
        );
        expect(
            screen.getByRole('button', { name: 'Change the photo' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Remove' }),
        ).toBeInTheDocument();
    });

    it('the image chosen shows at once and goes to the form', async () => {
        const file = new File(['x'], 'giulia.jpg', { type: 'image/jpeg' });
        const view = render(FmImagePanel, {
            props: { image: null, ...labels },
            slots,
        });

        await pickFile('Add a photo', file);

        expect(screen.getByRole('img', { name: 'Giulia' })).toHaveAttribute(
            'src',
            'blob:chosen',
        );
        expect(view.emitted('update:file')).toEqual([[file]]);
    });

    it('only images can be chosen', () => {
        render(FmImagePanel, { props: { image: null, ...labels }, slots });

        expect(
            screen.getByLabelText('Add a photo', { selector: 'input' }),
        ).toHaveAttribute('accept', expect.stringContaining('.heic'));
    });

    it('choosing after removing takes the removal back', async () => {
        const file = new File(['x'], 'giulia.jpg', { type: 'image/jpeg' });
        const view = render(FmImagePanel, {
            props: { image: '/files/3', ...labels },
            slots,
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        await pickFile('Add a photo', file);

        expect(view.emitted('update:removed')).toEqual([[true], [false]]);
        expect(screen.getByRole('img', { name: 'Giulia' })).toHaveAttribute(
            'src',
            'blob:chosen',
        );
    });

    it('removing goes back to the placeholder and tells the form', async () => {
        const view = render(FmImagePanel, {
            props: { image: '/files/3', ...labels },
            slots,
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(screen.getByText('GB')).toBeInTheDocument();
        expect(view.emitted('update:removed')).toEqual([[true]]);
    });

    it('shows the error of the image under it', () => {
        render(FmImagePanel, {
            props: {
                image: null,
                ...labels,
                error: 'The photo must be an image.',
            },
            slots,
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'The photo must be an image.',
        );
    });
});
