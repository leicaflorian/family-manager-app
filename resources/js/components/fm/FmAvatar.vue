<script setup lang="ts">
/**
 * The circle of a person: their photo or, without one, the initials on a colour.
 */
const {
    initials,
    photo = null,
    name = '',
    size = 48,
    color = 'bg-action',
} = defineProps<{
    /** The initials, when the photo is missing. */
    initials: string;
    /** The address of the photo. */
    photo?: string | null;
    /** The name, for those who cannot see the photo. */
    name?: string;
    /** The side of the circle, in pixels. */
    size?: number;
    /** The class of the background colour of the initials. */
    color?: string;
}>();
</script>

<template>
    <img
        v-if="photo"
        :src="photo"
        :alt="name"
        class="shrink-0 rounded-full object-cover"
        :style="{ width: `${size}px`, height: `${size}px` }"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-full font-bold text-white"
        :class="color"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            fontSize: `${Math.round(size * 0.36)}px`,
        }"
        >{{ initials }}</span
    >
</template>
