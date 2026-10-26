<script setup lang="ts" generic="T extends string">
/**
 * Una scelta tra poche voci, una accanto all'altra («Da pagare» o «Già pagata»).
 * Sono pulsanti radio veri: si scelgono anche con le frecce della tastiera.
 */
import { useId } from 'vue';

const { etichetta, opzioni } = defineProps<{
    /** Cosa si sceglie, per chi usa un lettore di schermo. */
    etichetta: string;
    /** Le voci, con il valore e il testo. */
    opzioni: { value: T; label: string }[];
}>();

/** La voce scelta. */
const scelta = defineModel<T>();
const nome = useId();
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="etichetta"
        class="grid auto-cols-fr grid-flow-col gap-1 rounded-[14px] bg-sfondo p-1"
    >
        <label
            v-for="opzione in opzioni"
            :key="opzione.value"
            class="relative flex h-11 cursor-pointer items-center justify-center rounded-[11px] text-[15px] has-[:checked]:bg-white has-[:checked]:font-bold has-[:checked]:text-testo has-[:checked]:shadow-[0_1px_3px_rgba(24,33,47,0.12)] has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-azione"
            :class="
                opzione.value === scelta ? '' : 'font-semibold text-secondario'
            "
        >
            <input
                v-model="scelta"
                type="radio"
                :name="nome"
                :value="opzione.value"
                class="absolute size-px opacity-0"
            />
            {{ opzione.label }}
        </label>
    </div>
</template>
