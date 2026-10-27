<script setup lang="ts">
/**
 * The Home, empty for now: the deadlines and the latest documents come with the
 * real Home.
 */
import { Head, usePage } from '@inertiajs/vue3';
import { LayoutDashboard } from '@lucide/vue';
import { computed } from 'vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTitle from '@/components/fm/FmTitle.vue';

const page = usePage();
const firstName = computed(
    (): string => page.props.auth?.user?.name.split(' ')[0] ?? '',
);
</script>

<template>
    <Head :title="$t('Home')" />

    <FmTitle :title="$t('Hi, :name', { name: firstName })" />
    <FmEmpty :icon="LayoutDashboard" :title="$t('The Home is coming soon')" />
</template>
