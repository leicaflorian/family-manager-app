<script setup lang="ts">
/**
 * The page of a section that does not exist yet: the menu items already lead
 * there, and the real page comes with its task.
 */
import { Head } from '@inertiajs/vue3';
import { Hammer } from '@lucide/vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTitle from '@/components/fm/FmTitle.vue';

const { title } = defineProps<{
    /** The name of the section, in English: the page translates it. */
    title: string;
}>();
</script>

<template>
    <Head :title="$t(title)" />

    <FmTitle :title="$t(title)" />
    <FmEmpty :icon="Hammer" :title="$t('This page is coming soon')" />
</template>
