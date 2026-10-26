<script setup lang="ts">
/**
 * Il titolo grande di una pagina di accesso scritto dalla pagina stessa,
 * quando dipende dai dati («Ciao, Rosa»): come quello di `AuthLayout`.
 */
const { titolo, sottotitolo } = defineProps<{
    /** Il titolo. */
    titolo: string;
    /** La frase sotto. */
    sottotitolo?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <h1 class="m-0 text-[28px] font-extrabold lg:text-[30px]">
            {{ titolo }}
        </h1>
        <p
            v-if="sottotitolo"
            class="m-0 text-base leading-[1.45] text-[#4A5565]"
        >
            {{ sottotitolo }}
        </p>
    </div>
</template>
