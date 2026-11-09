import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import Connect from './Connect.vue';

let post: ReturnType<typeof vi.spyOn>;

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Link the phone, with the code written by hand', () => {
    it('asks for the code and sends what was written to the address of that code', async () => {
        renderPage(Connect, { code: null, person: null, family: null });

        expect(
            screen.getByRole('heading', { name: 'Link the phone' }),
        ).toBeInTheDocument();
        await userEvent.type(screen.getByLabelText('Code'), 'k7qm xpa9');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Connect' }));

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]![0]).toBe('/connect/k7qm%20xpa9');
    });

    it('does not send an empty field and says what to do', async () => {
        renderPage(Connect, { code: null, person: null, family: null });

        await userEvent.click(screen.getByRole('button', { name: 'Connect' }));

        expect(post).not.toHaveBeenCalled();
        expect(
            await screen.findByText('Write the code first.'),
        ).toBeInTheDocument();
    });
});

describe('Link the phone, from the link of the code', () => {
    const props = { code: 'K7QM-XPA9', person: 'Marco', family: 'Bianchi' };

    it('says who the code is for and uses it only when the button is tapped', async () => {
        renderPage(Connect, props);

        expect(
            screen.getByRole('heading', { name: 'Hi, Marco' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/to the Bianchi family without a password/),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Code')).not.toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();

        await userEvent.click(
            screen.getByRole('button', { name: 'Link this phone' }),
        );

        expect(post.mock.calls[0]![0]).toBe('/connect/K7QM-XPA9');
    });
});
