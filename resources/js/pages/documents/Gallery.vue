<script setup lang="ts">
/**
 * Placeholder of the drawings gallery page: the server already sends its props, and Task 8 replaces
 * this file with the real page (pictures by year, filters, viewer, add panel).
 *
 * @group sections
 */
defineProps<{
    years: App.Data.DrawingYearData[];
    people: { id: number; name: string }[];
    filters: { filter: string; person: number | null };
    selected: App.Data.DrawingData | null;
    total: number;
    canAdd: boolean;
    hasGallery: boolean;
    addOptions: { people: { id: number; name: string }[] };
}>();
</script>

<template>
    <div />
</template>
