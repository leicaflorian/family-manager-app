<script setup lang="ts">
/**
 * Il titolo in cima a una pagina: 28 px sul telefono, 32 px sul computer, con
 * la frase sotto e, a destra, le azioni della pagina.
 */
const { titolo, sottotitolo } = defineProps<{
    /** Il titolo della pagina. */
    titolo: string;
    /** La frase sotto il titolo. */
    sottotitolo?: string;
}>();

defineSlots<{
    /** I pulsanti a destra del titolo. */
    azioni?: () => unknown;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 text-[28px] font-extrabold lg:text-[32px]">
                {{ titolo }}
            </h1>
            <span
                v-if="sottotitolo"
                class="text-sm text-secondario lg:text-[15px]"
                >{{ sottotitolo }}</span
            >
        </div>
        <div v-if="$slots.azioni" class="flex flex-wrap gap-2.5">
            <slot name="azioni" />
        </div>
    </div>
</template>
