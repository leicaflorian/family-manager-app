<script setup lang="ts">
/**
 * A link that opens its address in a new tab, with the ↗ icon beside the
 * text: the person, the organization or the card a card names, so the card
 * stays open behind.
 */
import { ExternalLink } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

defineProps<{
    /** The address to open. */
    href: string;
    /** The text of the link, for example «Studio Ferri». */
    text: string;
}>();
</script>

<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener"
        :title="$t('Open :title in a new tab', { title: text })"
        class="inline-flex items-center gap-1 underline-offset-2 hover:underline"
        >{{ text }}<FmIcon :icon="ExternalLink" :size="14"
    /></a>
</template>
