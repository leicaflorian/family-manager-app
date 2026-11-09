<script setup lang="ts">
/**
 * The big title of a sign-in page written by the page itself, when it depends
 * on the data («Hi, Rosa»): like the one of `AuthLayout`.
 *
 * @group navigation
 */
const { title, subtitle } = defineProps<{
    /** The title. */
    title: string;
    /** The sentence below. */
    subtitle?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="m-0 text-title-mobile lg:text-title">
            {{ title }}
        </h1>
        <p v-if="subtitle" class="m-0 text-body text-ink-soft">
            {{ subtitle }}
        </p>
    </div>
</template>
