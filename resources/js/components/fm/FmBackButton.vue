<script setup lang="ts">
/**
 * The square Back button of the bars: 44 px in the top bar of the computer, 36 px
 * (`small`, with an 18 px arrow) in the row above the page of the tablet. With
 * the address of the page above it is a link; without, a button. Both emit
 * `click` with the event, so the caller (`usePageBack`) decides where to go.
 *
 * @group navigation
 */
import { ArrowLeft } from '@lucide/vue';
import { squareButton } from '@/lib/topBar';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const { href, small = false } = defineProps<{
    /** The address of the page above; without it, a button. */
    href?: string;
    /** The 36 px size of the tablet, with the smaller arrow. */
    small?: boolean;
}>();

defineEmits<{
    /** The link or the button was pressed. */
    click: [event: MouseEvent];
}>();
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :aria-label="$t('Back')"
        :title="$t('Back')"
        :class="cn(squareButton, small && 'size-9')"
        @click="$emit('click', $event)"
    >
        <FmIcon :icon="ArrowLeft" :size="small ? 18 : 20" />
    </component>
</template>
