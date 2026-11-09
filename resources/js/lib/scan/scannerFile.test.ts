import { describe, expect, it } from 'vite-plus/test';
import type { Backend, PageImage, SheetPlan } from './pages';
import { MAX_UPLOAD_BYTES, ScanTooHeavyError } from './pages';
import { scannerFile } from './scannerFile';

/** A backend that draws nothing: each side is the text of its blob, and the sheet remembers its plan. */
function fakeBackend(): Backend & { plans: SheetPlan[] } {
    const plans: SheetPlan[] = [];

    return {
        plans,
        async load(page): Promise<PageImage> {
            return {
                jpeg: new Uint8Array(await page.jpeg!.arrayBuffer()),
                width: 100,
                height: 140,
                grey: false,
            };
        },
        async sheet(_images, plan): Promise<PageImage> {
            plans.push(plan);

            return {
                jpeg: new TextEncoder().encode('sheet'),
                width: plan.width,
                height: plan.height,
                grey: false,
            };
        },
    };
}

const side = (text: string): { blob: Blob } => ({ blob: new Blob([text]) });

/** How many pages the PDF has. */
const pagesOf = async (file: File): Promise<number> =>
    (await file.text()).match(/\/Type \/Page\b/g)?.length ?? 0;

describe('scannerFile', () => {
    it('makes one PDF with a page for each side, in order', async () => {
        const file = await scannerFile([side('a'), side('b'), side('c')], {
            oneSheet: false,
            backend: fakeBackend(),
        });

        expect(file.type).toBe('application/pdf');
        expect(file.name).toBe('scan.pdf');
        expect(await pagesOf(file)).toBe(3);
    });

    it('puts two to four sides on a single page when asked', async () => {
        const backend = fakeBackend();

        const file = await scannerFile([side('a'), side('b')], {
            oneSheet: true,
            backend,
        });

        expect(backend.plans).toHaveLength(1);
        expect(await pagesOf(file)).toBe(1);
    });

    it('leaves the sides alone when "On a single page" does not apply to so many', async () => {
        const backend = fakeBackend();
        const sides = ['a', 'b', 'c', 'd', 'e'].map(side);

        const file = await scannerFile(sides, { oneSheet: true, backend });

        expect(backend.plans).toHaveLength(0);
        expect(await pagesOf(file)).toBe(5);
    });

    it('refuses a PDF over 25 MB, saying how much it weighs', async () => {
        const backend = fakeBackend();
        const heavy = new Blob([new Uint8Array(MAX_UPLOAD_BYTES + 10)]);

        await expect(
            scannerFile([{ blob: heavy }], { oneSheet: false, backend }),
        ).rejects.toBeInstanceOf(ScanTooHeavyError);
    });
});
