<script setup lang="ts">
/**
 * Il cerchio di una persona: la sua foto o, senza, le iniziali su un colore.
 */
const {
    iniziali,
    foto = null,
    nome = '',
    dimensione = 48,
    colore = 'bg-azione',
} = defineProps<{
    /** Le iniziali, quando manca la foto. */
    iniziali: string;
    /** L'indirizzo della foto. */
    foto?: string | null;
    /** Il nome, per chi non vede la foto. */
    nome?: string;
    /** Il lato del cerchio, in pixel. */
    dimensione?: number;
    /** La classe del colore di sfondo delle iniziali. */
    colore?: string;
}>();
</script>

<template>
    <img
        v-if="foto"
        :src="foto"
        :alt="nome"
        class="shrink-0 rounded-full object-cover"
        :style="{ width: `${dimensione}px`, height: `${dimensione}px` }"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-full font-bold text-white"
        :class="colore"
        :style="{
            width: `${dimensione}px`,
            height: `${dimensione}px`,
            fontSize: `${Math.round(dimensione * 0.36)}px`,
        }"
        >{{ iniziali }}</span
    >
</template>
