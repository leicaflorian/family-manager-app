<script setup lang="ts">
/**
 * Pagina *Invita un familiare*: l'email, chi è (una persona della famiglia che
 * non ha ancora un account, o una persona nuova) e cosa può fare.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import { errori } from '@/lib/errori';
import { famiglia } from '@/routes';
import { store } from '@/routes/famiglia/inviti';

/** Il valore di «Chi è» per una persona che non c'è ancora. */
const NUOVA = 'nuova';

const { persone, livelli, ruoli } = defineProps<{
    /** Le persone della famiglia senza account. */
    persone: App.Data.PersonaBreveData[];
    /** I due livelli, con la loro spiegazione. */
    livelli: App.Data.OpzioneData[];
    /** Chi può essere una persona nella famiglia. */
    ruoli: App.Data.OpzioneData[];
}>();

const chi = ref<string>(persone[0] ? String(persone[0].id) : NUOVA);
const nuova = computed(() => chi.value === NUOVA);

const opzioniChi = computed(() => [
    ...persone.map((p) => ({
        value: String(p.id),
        label: `${p.first_name} ${p.last_name}`,
    })),
    { value: NUOVA, label: 'Una persona nuova' },
]);

const form = useForm({
    email: '',
    role: 'member',
    first_name: '',
    last_name: '',
    person_role: ruoli.at(-1)?.value ?? '',
    person_id: null as number | null,
});

/**
 * Manda l'invito: con la persona scelta, o con nome e cognome di quella nuova.
 */
function manda(): void {
    form.transform((dati) => ({
        ...dati,
        person_id: nuova.value ? null : Number(chi.value),
    })).submit(store());
}
</script>

<template>
    <Head title="Invita un familiare" />

    <form
        class="flex max-w-[560px] flex-col gap-3.5"
        novalidate
        @submit.prevent="manda"
    >
        <FmTitolo titolo="Invita un familiare" />
        <FormKit
            v-model="form.email"
            type="email"
            label="La sua email"
            name="email"
            autocomplete="off"
            :errors="errori(form.errors.email)"
        />
        <FormKit
            v-model="chi"
            type="select"
            label="Chi è"
            name="chi"
            :options="opzioniChi"
            :errors="errori(form.errors.person_id)"
        />
        <template v-if="nuova">
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="form.first_name"
                    type="text"
                    label="Nome"
                    name="first_name"
                    :errors="errori(form.errors.first_name)"
                />
                <FormKit
                    v-model="form.last_name"
                    type="text"
                    label="Cognome"
                    name="last_name"
                    :errors="errori(form.errors.last_name)"
                />
            </div>
            <FormKit
                v-model="form.person_role"
                type="select"
                label="Nella famiglia è"
                name="person_role"
                :options="ruoli"
                :errors="errori(form.errors.person_role)"
            />
        </template>
        <FormKit
            v-model="form.role"
            type="radio"
            label="Cosa può fare"
            name="role"
            :options="livelli"
            :errors="errori(form.errors.role)"
        />
        <FmAvviso :icona="Mail">
            Gli arriva una mail con il link per entrare, che vale 7 giorni.
            Diventa anche una persona della famiglia, con la sua scheda.
        </FmAvviso>
        <div
            class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:justify-start lg:gap-2.5"
        >
            <FmPulsante tipo="submit" :disabled="form.processing"
                >Manda l'invito</FmPulsante
            >
            <FmPulsante variante="testo" :href="famiglia().url"
                >Annulla</FmPulsante
            >
        </div>
    </form>
</template>
