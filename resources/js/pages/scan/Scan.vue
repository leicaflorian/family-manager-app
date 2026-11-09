<script setup lang="ts">
/**
 * *Scan*: for now only the title, until the camera arrives. A phone linked with a code opens the app here.
 */
import { Head } from '@inertiajs/vue3';
import FmTitle from '@/components/fm/FmTitle.vue';
</script>

<template>
    <Head :title="$t('Scan')" />
    <FmTitle :title="$t('Scan')" />
</template>
