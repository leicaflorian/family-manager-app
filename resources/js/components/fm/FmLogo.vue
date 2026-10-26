<script setup lang="ts">
/**
 * Il logo di Family Manager: il tetto e la famiglia, bianchi sul quadrato azzurro.
 * La versione bianca, senza quadrato, va sul pannello azzurro delle pagine di accesso.
 */
const { dimensione = 36, bianco = false } = defineProps<{
    /** Il lato in pixel. */
    dimensione?: number;
    /** Solo il disegno bianco, senza il quadrato. */
    bianco?: boolean;
}>();
</script>

<template>
    <svg
        aria-hidden="true"
        :width="dimensione"
        :height="dimensione"
        viewBox="0 0 500 500"
        class="shrink-0"
    >
        <rect v-if="!bianco" width="500" height="500" fill="#3399FF" rx="80" />
        <path
            d="M 60,300 L 250,110 L 440,300"
            stroke="#FFFFFF"
            stroke-width="55"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
        />
        <circle cx="155" cy="300" r="65" fill="#FFFFFF" />
        <circle cx="345" cy="300" r="65" fill="#FFFFFF" />
        <circle cx="250" cy="315" r="30" fill="#FFFFFF" />
    </svg>
</template>
