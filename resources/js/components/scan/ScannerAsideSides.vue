<script setup lang="ts">
/**
 * The sides put aside of *Scan the sheets*, at the foot of the column of the pages, as the board `Scanner-pc` draws
 * it: a square with the layers and how many there are, *Put aside* under it. It opens a bubble on its right with each
 * side (blank or taken away) and *Put back*, which brings it back among the pages. They do not go in the PDF. The
 * bubble takes its name from the button, as every popover of reka-ui.
 */
import { Layers, Undo2, X } from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import type { Side } from '@/composables/useScanner';

const { sides, disabled = false } = defineProps<{
    /** The sides put aside. */
    sides: Side[];
    /** They cannot be put back (during the upload). */
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** *Put back*: the side goes back among the pages. */
    putBack: [id: string];
}>();
</script>

<template>
    <PopoverRoot>
        <div class="flex flex-col items-center gap-1">
            <PopoverTrigger
                :aria-label="
                    $t('Sides put aside, :count', {
                        count: String(sides.length),
                    })
                "
                class="relative flex size-[58px] cursor-pointer items-center justify-center rounded-lg border border-edge-soft bg-card text-ink-soft hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action"
            >
                <FmIcon :icon="Layers" :size="24" />
                <span
                    aria-hidden="true"
                    class="absolute -top-[7px] -right-[7px] flex h-[22px] min-w-[22px] items-center justify-center rounded-full border-2 border-canvas bg-action px-1.5 text-badge text-on-action"
                    >{{ sides.length }}</span
                >
            </PopoverTrigger>
            <span
                class="max-w-[100px] text-center text-caption text-ink-soft"
                >{{ $t('Put aside') }}</span
            >
        </div>
        <PopoverPortal>
            <PopoverContent
                side="right"
                align="end"
                :side-offset="16"
                :collision-padding="16"
                class="z-50 flex w-80 flex-col gap-3 rounded-card border border-edge-soft bg-card p-4 shadow-menu"
            >
                <div class="flex items-start gap-2">
                    <span class="flex grow flex-col gap-0.5">
                        <span class="text-amount">{{
                            $t('Sides put aside: they do not go in the PDF')
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            $t(
                                'They do not go in the PDF. Put one back to take it again.',
                            )
                        }}</span>
                    </span>
                    <PopoverClose as-child>
                        <FmButton
                            :icon="X"
                            :label="$t('Close')"
                            small
                            plain
                            muted
                            class="-mt-2 -mr-2"
                        />
                    </PopoverClose>
                </div>
                <ul class="m-0 flex list-none flex-wrap gap-3 p-0">
                    <li
                        v-for="side in sides"
                        :key="side.id"
                        class="flex w-[88px] flex-col items-center gap-1.5"
                    >
                        <img
                            :src="side.url"
                            :alt="$t('Side put aside')"
                            class="h-[110px] w-[78px] rounded-tag border border-edge-soft bg-card object-contain"
                        />
                        <span class="text-caption text-ink-soft">{{
                            side.aside === 'blank'
                                ? $t('blank')
                                : $t('taken away')
                        }}</span>
                        <FmButton
                            variant="text"
                            :icon="Undo2"
                            :disabled="disabled"
                            @click="emit('putBack', side.id)"
                            >{{ $t('Put back') }}</FmButton
                        >
                    </li>
                </ul>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
