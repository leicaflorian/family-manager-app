import { describe, expect, it, vi } from 'vite-plus/test';
import { SHEET } from './crop';
import {
    MAX_UPLOAD_BYTES,
    ScanTooHeavyError,
    addPages,
    filesToSend,
    movePage,
    oneSheetState,
    pageCount,
    removePage,
    selectPage,
    sheetPlan,
    type Backend,
    type PageImage,
    type ScanDocument,
    type ScanPage,
} from './pages';

const EMPTY: ScanDocument = { pages: [], chosenId: null };

/** A page, with what the test cares about. */
function page(id: string, over: Partial<ScanPage> = {}): ScanPage {
    return {
        id,
        file: new File(['x'], `${id}.jpg`, { type: 'image/jpeg' }),
        kind: 'photo',
        corners: null,
        turns: 0,
        improve: { enhance: true, grey: true, dewarp: false },
        cut: false,
        jpeg: null,
        ...over,
    };
}

/** A photo that has been cut out. */
const cut = (id: string): ScanPage =>
    page(id, { cut: true, jpeg: new Blob(['j'], { type: 'image/jpeg' }) });

/** A PDF as the person chose it. */
const pdf = (id: string, size = 1): ScanPage =>
    page(id, {
        kind: 'pdf',
        file: new File([new Uint8Array(size)], `${id}.pdf`, {
            type: 'application/pdf',
        }),
    });

const doc = (pages: ScanPage[], chosen: string | null): ScanDocument => ({
    pages,
    chosenId: chosen,
});

describe('addPages', () => {
    it('adds at the end and chooses the last one added', () => {
        const first = addPages(EMPTY, [page('a'), page('b')]);
        expect(first.pages.map((p) => p.id)).toEqual(['a', 'b']);
        expect(first.chosenId).toBe('b');

        const second = addPages(first, [page('c')]);
        expect(second.pages.map((p) => p.id)).toEqual(['a', 'b', 'c']);
        expect(second.chosenId).toBe('c');
    });

    it('keeps the choice when nothing is added', () => {
        expect(addPages(doc([page('a')], 'a'), []).chosenId).toBe('a');
    });

    it('does not change the document it was given', () => {
        const before = doc([page('a')], 'a');
        addPages(before, [page('b')]);
        expect(before.pages).toHaveLength(1);
    });
});

describe('removePage', () => {
    const three = doc([page('a'), page('b'), page('c')], 'b');

    it('moves the choice to the next page', () => {
        const after = removePage(three, 'b');
        expect(after.pages.map((p) => p.id)).toEqual(['a', 'c']);
        expect(after.chosenId).toBe('c');
    });

    it('moves the choice to the one before when the last goes', () => {
        const after = removePage(doc(three.pages, 'c'), 'c');
        expect(after.chosenId).toBe('b');
    });

    it('keeps the choice when another page goes', () => {
        expect(removePage(three, 'a').chosenId).toBe('b');
    });

    it('chooses nothing when the last page goes, and ignores an unknown id', () => {
        expect(removePage(doc([page('a')], 'a'), 'a')).toEqual(EMPTY);
        expect(removePage(three, 'zz')).toEqual(three);
    });
});

describe('movePage', () => {
    it('puts a page at a place and keeps the choice', () => {
        const start = doc([page('a'), page('b'), page('c')], 'a');
        const after = movePage(start, 0, 2);
        expect(after.pages.map((p) => p.id)).toEqual(['b', 'c', 'a']);
        expect(after.chosenId).toBe('a');
        expect(movePage(start, 2, 0).pages.map((p) => p.id)).toEqual([
            'c',
            'a',
            'b',
        ]);
    });

    it('does nothing for places outside the document', () => {
        const start = doc([page('a'), page('b')], 'a');
        expect(movePage(start, 0, 5)).toEqual(start);
        expect(movePage(start, -1, 0)).toEqual(start);
    });
});

describe('selectPage', () => {
    it('chooses a page of the document and nothing else', () => {
        const start = doc([page('a'), page('b')], 'a');
        expect(selectPage(start, 'b').chosenId).toBe('b');
        expect(selectPage(start, 'zz').chosenId).toBe('a');
    });
});

describe('pageCount', () => {
    it('counts photos as pages', () => {
        expect(pageCount([page('a')])).toBe('1 page');
        expect(pageCount([page('a'), page('b'), page('c')])).toBe('3 pages');
    });

    it('counts files when there is a PDF among them', () => {
        expect(pageCount([page('a'), page('b'), page('c'), pdf('d')])).toBe(
            '4 files',
        );
        expect(pageCount([pdf('a')])).toBe('1 file');
    });

    it('is empty without pages', () => {
        expect(pageCount([])).toBe('');
    });
});

describe('oneSheetState', () => {
    it('is hidden under two photos and over four', () => {
        expect(oneSheetState([cut('a')])).toBe('hidden');
        expect(oneSheetState([])).toBe('hidden');
        expect(oneSheetState(['a', 'b', 'c', 'd', 'e'].map(cut))).toBe(
            'hidden',
        );
    });

    it('is blocked while a photo is not cut out', () => {
        expect(oneSheetState([cut('a'), page('b')])).toBe('blocked');
        expect(oneSheetState([page('a'), page('b')])).toBe('blocked');
    });

    it('is ready with two to four photos all cut out', () => {
        expect(oneSheetState([cut('a'), cut('b')])).toBe('ready');
        expect(oneSheetState(['a', 'b', 'c', 'd'].map(cut))).toBe('ready');
    });

    it('does not count the PDF files', () => {
        expect(oneSheetState([cut('a'), pdf('p')])).toBe('hidden');
        expect(oneSheetState([cut('a'), cut('b'), pdf('p')])).toBe('ready');
    });
});

describe('sheetPlan', () => {
    it('makes an A4 at 300 dots per inch with the sides in a column', () => {
        const plan = sheetPlan([
            { width: 1600, height: 1000 },
            { width: 1600, height: 1000 },
        ]);

        expect(plan.width).toBe(Math.round((SHEET.width / 25.4) * 300));
        expect(plan.height).toBe(Math.round((SHEET.height / 25.4) * 300));
        expect(plan.places).toHaveLength(2);
        expect(plan.places[0].y).toBeLessThan(plan.places[1].y);
        // a card: the shape of a credit card, drawn with round corners
        expect(plan.rounded).toEqual([true, true]);
        expect(plan.radius).toBeGreaterThan(0);
        // 85.6 mm wide at 300 dpi
        expect(plan.places[0].width).toBeCloseTo((85.6 / 25.4) * 300, 0);
    });

    it('does not round what is not a card', () => {
        expect(sheetPlan([{ width: 1000, height: 1400 }]).rounded).toEqual([
            false,
        ]);
    });
});

/** A backend that draws nothing: each page gives a small fixed image. */
function fakeBackend(): Backend & {
    sheet: ReturnType<typeof vi.fn>;
    load: ReturnType<typeof vi.fn>;
} {
    const image = (id: string, grey = false): PageImage => ({
        jpeg: new TextEncoder().encode(`jpeg-${id}`),
        width: 1600,
        height: 1000,
        grey,
    });

    return {
        load: vi.fn(async (p: ScanPage) => image(p.id, p.improve.grey)),
        sheet: vi.fn(async () => ({
            ...image('sheet'),
            width: 2480,
            height: 3508,
        })),
    };
}

describe('filesToSend', () => {
    it('sends one photo as a JPEG', async () => {
        const files = await filesToSend([cut('a')], false, fakeBackend());

        expect(files).toHaveLength(1);
        expect(files[0].type).toBe('image/jpeg');
        expect(files[0].name).toMatch(/\.jpg$/);
        expect(await files[0].text()).toBe('jpeg-a');
    });

    it('sends several photos as one PDF with a page each', async () => {
        const files = await filesToSend(
            [cut('a'), cut('b'), cut('c')],
            false,
            fakeBackend(),
        );

        expect(files).toHaveLength(1);
        expect(files[0].type).toBe('application/pdf');
        const text = await files[0].text();
        expect(text.startsWith('%PDF-1.4')).toBe(true);
        expect(text).toContain('/Count 3');
    });

    it('makes the PDF of photos that are not cut out', async () => {
        const files = await filesToSend(
            [page('a'), page('b')],
            false,
            fakeBackend(),
        );

        expect(files).toHaveLength(1);
        expect(await files[0].text()).toContain('/Count 2');
    });

    it('sends every PDF on its own, as it is', async () => {
        const files = await filesToSend(
            [cut('a'), pdf('one'), cut('b'), pdf('two')],
            false,
            fakeBackend(),
        );

        expect(files.map((f) => f.type)).toEqual([
            'application/pdf',
            'application/pdf',
            'application/pdf',
        ]);
        expect(files.map((f) => f.name)).toEqual([
            expect.stringMatching(/\.pdf$/),
            'one.pdf',
            'two.pdf',
        ]);
    });

    it('draws the photos on one A4 sheet with "On a single page"', async () => {
        const backend = fakeBackend();
        const files = await filesToSend([cut('a'), cut('b')], true, backend);

        expect(backend.sheet).toHaveBeenCalledTimes(1);
        expect(files).toHaveLength(1);
        expect(await files[0].text()).toContain('/Count 1');
    });

    it('ignores "On a single page" when it is not ready', async () => {
        const backend = fakeBackend();
        const files = await filesToSend([cut('a'), page('b')], true, backend);

        expect(backend.sheet).not.toHaveBeenCalled();
        expect(await files[0].text()).toContain('/Count 2');
    });

    it('refuses a file over 25 MB and says which', async () => {
        expect(MAX_UPLOAD_BYTES).toBe(26214400);
        const big = pdf('big', MAX_UPLOAD_BYTES + 1);

        const error = await filesToSend([cut('a'), big], false, fakeBackend())
            .then(() => null)
            .catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ScanTooHeavyError);
        expect((error as ScanTooHeavyError).label).toBe('big.pdf');
        expect((error as ScanTooHeavyError).bytes).toBe(MAX_UPLOAD_BYTES + 1);
        expect((error as ScanTooHeavyError).message).toContain('25 MB');
    });

    it('accepts a file of exactly 25 MB', async () => {
        const files = await filesToSend(
            [pdf('edge', MAX_UPLOAD_BYTES)],
            false,
            fakeBackend(),
        );
        expect(files).toHaveLength(1);
    });
});
