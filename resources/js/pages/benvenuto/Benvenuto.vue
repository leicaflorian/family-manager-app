<script setup lang="ts">
/**
 * Il *Benvenuto* dopo l'iscrizione, in due passi: chi siete (le persone della
 * famiglia) e la casa, che si può lasciare per dopo. *Fatto, entriamo* salva
 * tutto insieme e porta alla Home. Il passo delle bollette arriva con le schede.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmIntestazione from '@/components/fm/FmIntestazione.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmRiga from '@/components/fm/FmRiga.vue';
import { errori } from '@/lib/errori';
import { store } from '@/routes/benvenuto';

defineOptions({
    layout: {
        pannello: {
            titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
            testo: 'Due domande e la tua famiglia è pronta. Potrai cambiare tutto anche dopo.',
            punti: ['Chi siete', 'La casa'],
        },
    },
});

const { persone, ruoli } = defineProps<{
    /** Le persone che ci sono già: chi si è iscritto. */
    persone: App.Data.PersonaBreveData[];
    /** Chi può essere una persona nella famiglia. */
    ruoli: App.Data.OpzioneData[];
}>();

/** Una persona da aggiungere, come la si scrive nel modulo. */
type PersonaNuova = { first_name: string; last_name: string; role: string };

/** I due passi, con il titolo e la frase di ognuno. */
const passi = [
    {
        titolo: 'Chi siete',
        frase: 'Chi vive con te? Aggiungi le persone della famiglia: potrai invitarle a entrare anche dopo.',
    },
    {
        titolo: 'La casa',
        frase: 'Dove vivete? Alla casa si legano le bollette, i contratti e i documenti.',
    },
] as const;

const passo = ref<0 | 1>(0);
const questo = computed(() => passi[passo.value]);

const form = useForm({
    persone: [] as PersonaNuova[],
    casa: { name: 'Casa', address: '', city: '', is_owned: false },
});

/**
 * Aggiunge una riga per una persona, con il cognome della famiglia già scritto.
 */
function aggiungi(): void {
    form.persone.push({
        first_name: '',
        last_name: persone[0]?.last_name ?? '',
        role: ruoli[0]?.value ?? '',
    });
}

/**
 * Salva e porta alla Home; con `senzaCasa` la casa resta per dopo.
 *
 * @param senzaCasa - true per «La aggiungo dopo»
 */
function fatto(senzaCasa = false): void {
    form.transform((dati) => ({
        ...dati,
        casa: senzaCasa ? null : dati.casa,
    })).submit(store(), {
        onError: (errori) => {
            if (
                Object.keys(errori).some((campo) => campo.startsWith('persone'))
            ) {
                passo.value = 0;
            }
        },
    });
}
</script>

<template>
    <Head title="Benvenuto" />

    <form class="flex grow flex-col gap-5" novalidate @submit.prevent="fatto()">
        <div class="flex flex-col gap-2.5">
            <span class="text-sm font-semibold text-secondario"
                >Passo {{ passo + 1 }} di {{ passi.length }}</span
            >
            <div class="grid grid-cols-2 gap-1.5" aria-hidden="true">
                <span
                    v-for="(p, i) in passi"
                    :key="p.titolo"
                    class="h-1.5 rounded-full"
                    :class="i <= passo ? 'bg-azione' : 'bg-riga'"
                />
            </div>
        </div>
        <FmIntestazione :titolo="questo.titolo" :sottotitolo="questo.frase" />

        <div v-if="passo === 0" class="flex flex-col gap-3">
            <FmRiga
                v-for="persona in persone"
                :key="persona.id"
                :titolo="`${persona.first_name} ${persona.last_name}`"
                sottotitolo="Tu"
            />
            <fieldset
                v-for="(persona, i) in form.persone"
                :key="i"
                class="m-0 flex flex-col gap-3 rounded-2xl border-[1.5px] border-bordo p-4"
            >
                <legend class="sr-only">Persona {{ i + 1 }}</legend>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="persona.first_name"
                        type="text"
                        label="Nome"
                        :name="`persone.${i}.first_name`"
                        :errors="errori(form.errors[`persone.${i}.first_name`])"
                    />
                    <FormKit
                        v-model="persona.last_name"
                        type="text"
                        label="Cognome"
                        :name="`persone.${i}.last_name`"
                        :errors="errori(form.errors[`persone.${i}.last_name`])"
                    />
                </div>
                <FormKit
                    v-model="persona.role"
                    type="select"
                    label="Nella famiglia è"
                    :name="`persone.${i}.role`"
                    :options="ruoli"
                    :errors="errori(form.errors[`persone.${i}.role`])"
                />
                <FmPulsante
                    variante="pericolo"
                    :icona="X"
                    @click="form.persone.splice(i, 1)"
                    >Togli</FmPulsante
                >
            </fieldset>
            <FmPulsante variante="tenue" :icona="UserPlus" @click="aggiungi"
                >Aggiungi una persona</FmPulsante
            >
        </div>

        <div v-else class="flex flex-col gap-3">
            <FormKit
                v-model="form.casa.name"
                type="text"
                label="Come la chiamate"
                name="casa.name"
                :errors="errori(form.errors['casa.name'])"
            />
            <FormKit
                v-model="form.casa.address"
                type="text"
                label="Indirizzo"
                name="casa.address"
                autocomplete="street-address"
                :errors="errori(form.errors['casa.address'])"
            />
            <FormKit
                v-model="form.casa.city"
                type="text"
                label="Città"
                name="casa.city"
                autocomplete="address-level2"
                :errors="errori(form.errors['casa.city'])"
            />
            <FormKit
                v-model="form.casa.is_owned"
                type="checkbox"
                label="È di vostra proprietà"
                name="casa.is_owned"
            />
        </div>

        <span class="min-h-6 grow" />
        <div class="flex flex-col gap-1">
            <template v-if="passo === 0">
                <FmPulsante largo @click="passo = 1">Avanti</FmPulsante>
            </template>
            <template v-else>
                <FmPulsante tipo="submit" largo :disabled="form.processing"
                    >Fatto, entriamo</FmPulsante
                >
                <FmPulsante
                    variante="testo"
                    :disabled="form.processing"
                    @click="fatto(true)"
                    >La aggiungo dopo</FmPulsante
                >
                <FmPulsante variante="testo" @click="passo = 0"
                    >Indietro</FmPulsante
                >
            </template>
        </div>
    </form>
</template>
