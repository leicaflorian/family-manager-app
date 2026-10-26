<script setup lang="ts">
/**
 * Il campo della password con l'occhio per vederla, come nelle tavole di accesso.
 * È un `<FormKit>`: prende le classi dei campi da `formkit.config.ts`.
 */
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';
import FmIcona from './FmIcona.vue';

const {
    label,
    name,
    help,
    errors = [],
    autocomplete = 'current-password',
} = defineProps<{
    /** Il testo sopra il campo. */
    label: string;
    /** Il nome del campo nel modulo. */
    name: string;
    /** Il suggerimento sotto il campo, per esempio «Almeno 10 caratteri». */
    help?: string;
    /** Gli errori che arrivano dal server. */
    errors?: string[];
    /** Il suggerimento per il browser: `current-password` o `new-password`. */
    autocomplete?: string;
}>();

/** La password scritta. */
const valore = defineModel<string>({ default: '' });
const visibile = ref(false);
</script>

<template>
    <FormKit
        v-model="valore"
        :type="visibile ? 'text' : 'password'"
        :label="label"
        :name="name"
        :help="help"
        :errors="errors"
        :autocomplete="autocomplete"
        :classes="{ inner: 'pr-1.5' }"
    >
        <template #suffix>
            <button
                type="button"
                :aria-label="
                    visibile ? 'Nascondi la password' : 'Mostra la password'
                "
                class="flex size-10 shrink-0 items-center justify-center text-secondario"
                @click="visibile = !visibile"
            >
                <FmIcona :icona="visibile ? EyeOff : Eye" :dimensione="20" />
            </button>
        </template>
    </FormKit>
</template>
