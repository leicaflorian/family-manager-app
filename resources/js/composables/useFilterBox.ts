import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { useWideLayout } from '@/composables/useWideLayout';

/**
 * The state of the box of the filters under the search bar of a list: whether
 * the layout has the box (the wide layout, or what `active` says) and whether
 * it is open. The box shuts when the layout stops having it.
 *
 * @param active - when the box exists, if not the wide layout (the computer layout of *To sort*)
 * @returns `wide`, true when the box exists, and `boxOpen`, whether it is open
 */
export function useFilterBox(active?: () => boolean): {
    wide: Readonly<Ref<boolean>>;
    boxOpen: Ref<boolean>;
} {
    const layout = useWideLayout();
    const wide = computed((): boolean => (active ? active() : layout.value));
    const boxOpen = ref(false);

    watch(wide, (isWide) => {
        if (!isWide) {
            boxOpen.value = false;
        }
    });

    return { wide, boxOpen };
}
