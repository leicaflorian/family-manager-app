import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent } from 'vue';
import { useFeature, useFeatures, useMailAvailable } from './useFeatures';

const shared = vi.hoisted(() => ({
    family: null as {
        name: string;
        features?: Record<string, boolean>;
        mail_available?: boolean;
    } | null,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { family: shared.family } }),
}));

/** Mounts a component that reads the features and gives back what it saw. */
function seen(): {
    all: Partial<Record<App.Enums.Feature, boolean>> | null;
    gallery: boolean;
    consumption: boolean;
} {
    const out = {
        all: null as Partial<Record<App.Enums.Feature, boolean>> | null,
        gallery: false,
        consumption: false,
    };

    render(
        defineComponent({
            setup() {
                out.all = useFeatures().value;
                out.gallery = useFeature('gallery').value;
                out.consumption = useFeature('consumption').value;

                return (): null => null;
            },
        }),
    );

    return out;
}

describe('useFeatures', () => {
    it('gives the features of the family that the server shares', () => {
        shared.family = {
            name: 'Bianchi',
            features: { bank_feed: false, consumption: true, gallery: false },
        };

        const out = seen();

        expect(out.all).toEqual({
            bank_feed: false,
            consumption: true,
            gallery: false,
        });
        expect(out.consumption).toBe(true);
        expect(out.gallery).toBe(false);
    });

    it('has every feature on without a family', () => {
        shared.family = null;

        const out = seen();

        expect(out.all).toBeNull();
        expect(out.gallery).toBe(true);
        expect(out.consumption).toBe(true);
    });
});

describe('useMailAvailable', () => {
    /** Mounts a component that reads whether the mail is offered. */
    function offered(): boolean {
        let value = false;

        render(
            defineComponent({
                setup() {
                    value = useMailAvailable().value;

                    return (): null => null;
                },
            }),
        );

        return value;
    }

    it('follows what the server shares', () => {
        shared.family = { name: 'Bianchi', mail_available: false };
        expect(offered()).toBe(false);

        shared.family = { name: 'Bianchi', mail_available: true };
        expect(offered()).toBe(true);
    });

    it('is available without a family', () => {
        shared.family = null;
        expect(offered()).toBe(true);
    });
});
