<script setup lang="ts">
/**
 * The cases of one component of the catalogue alone, without the groups:
 * `/dev/components/{component}`, which *Components* shows in a frame 390 px
 * wide, so the cases are drawn as on a phone.
 */
import { Head } from '@inertiajs/vue3';
import DevCases from '@/components/dev/DevCases.vue';

const { component } = defineProps<{
    /** The name of the component, as `FmRow`. */
    component: string;
}>();
</script>

<template>
    <Head :title="component" />
    <main class="min-h-dvh bg-canvas p-4 text-ink">
        <DevCases :key="component" :name="component" />
    </main>
</template>
