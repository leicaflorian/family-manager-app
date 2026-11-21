<script setup lang="ts">
/**
 * A short text that says what an element does, in a dark bubble beside it:
 * it opens under the mouse after a moment, or when the element is reached with
 * Tab, and goes away with Escape. The phone has no mouse, so it is only for
 * hints the computer adds: nothing the person needs on the phone goes here.
 * Its slot is the element, which keeps its own role and name.
 *
 * @group basics
 */
import {
    TooltipContent,
    TooltipPortal,
    TooltipProvider,
    TooltipRoot,
    TooltipTrigger,
} from 'reka-ui';
import { computed } from 'vue';

const { side = 'bottom', anchor } = defineProps<{
    /**
     * What the element does, for example «Upload a document from the
     * computer». A line break (`\n`) starts a new line in the bubble.
     */
    text: string;
    /** The side of the element where the bubble opens. */
    side?: 'top' | 'right' | 'bottom' | 'left';
    /**
     * Where the bubble opens, when not beside the whole element: a box on the
     * page, read when the bubble opens (the top of a bar in a chart).
     */
    anchor?: () => DOMRect;
}>();

/** The place the bubble follows: the box of `anchor`, or the element itself. */
const reference = computed(() =>
    anchor ? { getBoundingClientRect: anchor } : undefined,
);

defineSlots<{
    /** The element the text is about: a link or a button. */
    default: () => unknown;
}>();
</script>

<template>
    <TooltipProvider :delay-duration="400">
        <TooltipRoot>
            <TooltipTrigger as-child>
                <slot />
            </TooltipTrigger>
            <TooltipPortal>
                <TooltipContent
                    :side="side"
                    :side-offset="6"
                    :collision-padding="8"
                    :reference="reference"
                    class="z-50 max-w-60 rounded-tag bg-ink px-2.5 py-1.5 text-meta whitespace-pre-line text-card shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0 data-[state=instant-open]:animate-in data-[state=instant-open]:fade-in-0 motion-reduce:animate-none"
                >
                    {{ text }}
                </TooltipContent>
            </TooltipPortal>
        </TooltipRoot>
    </TooltipProvider>
</template>
