<script setup lang="ts">
/**
 * The big title of a sign-in page written by the page itself, when it depends
 * on the data («Hi, Rosa»): like the one of `AuthLayout`.
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
        <h1 class="m-0 text-[28px] font-extrabold lg:text-[30px]">
            {{ title }}
        </h1>
        <p v-if="subtitle" class="m-0 text-base leading-[1.45] text-[#4A5565]">
            {{ subtitle }}
        </p>
    </div>
</template>
