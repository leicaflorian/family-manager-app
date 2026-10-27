<script setup lang="ts">
/**
 * *Categorie dei documenti*: l'albero con il numero di documenti di ognuna. Un
 * amministratore aggiunge, rinomina, sposta ed elimina nel pannello; una
 * categoria con documenti o sottocategorie non si elimina.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmAlberoCategorie from '@/components/fm/FmAlberoCategorie.vue';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmPannelloDalBasso from '@/components/fm/FmPannelloDalBasso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmRiquadro from '@/components/fm/FmRiquadro.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import { errori } from '@/lib/errori';
import { destroy, store, update } from '@/routes/categorie';

const { categorie, puoModificare } = defineProps<{
    /** L'albero, dalle principali. */
    categorie: App.Data.CategoriaData[];
    /** Se chi guarda può cambiare le categorie (solo un amministratore). */
    puoModificare: boolean;
}>();

/** La categoria nel pannello: null per una nuova. */
const scelta = ref<App.Data.CategoriaData | null>(null);
const aperto = ref(false);
const form = useForm({ name: '', parent_id: '' });
// L'eliminazione manda solo l'indirizzo; il server risponde con l'errore `categoria`.
const eliminazione = useForm<{ categoria?: string }>({});

/**
 * Tutte le categorie in fila, con il percorso per nome («Casa e utenze › Luce»).
 *
 * @param rami - un livello dell'albero
 * @param prima - il percorso del livello sopra
 */
function inFila(
    rami: App.Data.CategoriaData[],
    prima = '',
): { categoria: App.Data.CategoriaData; percorso: string }[] {
    return rami.flatMap((categoria) => {
        const percorso = prima
            ? `${prima} › ${categoria.name}`
            : categoria.name;

        return [{ categoria, percorso }, ...inFila(categoria.figlie, percorso)];
    });
}

/**
 * Gli id di una categoria e di quelle sotto: lì non si può spostare.
 *
 * @param categoria - la categoria da spostare
 */
function conDiscendenti(categoria: App.Data.CategoriaData): number[] {
    return [categoria.id, ...categoria.figlie.flatMap(conDiscendenti)];
}

/** I posti in cui la categoria del pannello può stare. */
const posti = computed(() => {
    const esclusi = scelta.value ? conDiscendenti(scelta.value) : [];

    return [
        { value: '', label: 'Nessuna: è una principale' },
        ...inFila(categorie)
            .filter(({ categoria }) => !esclusi.includes(categoria.id))
            .map(({ categoria, percorso }) => ({
                value: String(categoria.id),
                label: percorso,
            })),
    ];
});

/** Perché la categoria del pannello si può eliminare o no. */
const nota = computed(() => {
    const categoria = scelta.value;

    if (categoria === null) {
        return '';
    }

    if (categoria.documenti > 0) {
        return categoria.documenti === 1
            ? "C'è 1 documento in questa categoria: per eliminarla, prima spostalo in un'altra."
            : `Ci sono ${categoria.documenti} documenti in questa categoria: per eliminarla, prima spostali in un'altra.`;
    }

    if (categoria.figlie.length > 0) {
        return 'Ha delle sottocategorie: per eliminarla, prima spostale o eliminale.';
    }

    return 'Nessun documento è in questa categoria: si può eliminare.';
});

const eliminabile = computed(
    () =>
        scelta.value !== null &&
        scelta.value.documenti === 0 &&
        scelta.value.figlie.length === 0,
);

/**
 * Apre il pannello per una categoria, o per una nuova.
 *
 * @param categoria - la categoria da modificare, null per una nuova
 */
function apri(categoria: App.Data.CategoriaData | null): void {
    scelta.value = categoria;
    form.clearErrors();
    eliminazione.clearErrors();
    form.name = categoria?.name ?? '';
    form.parent_id = categoria?.parent_id ? String(categoria.parent_id) : '';
    aperto.value = true;
}

/**
 * Salva la categoria e chiude il pannello.
 */
function salva(): void {
    form.transform((dati) => ({
        ...dati,
        parent_id: dati.parent_id === '' ? null : Number(dati.parent_id),
    })).submit(scelta.value ? update(scelta.value.id) : store(), {
        preserveScroll: true,
        onSuccess: () => (aperto.value = false),
    });
}

/**
 * Elimina la categoria del pannello.
 */
function elimina(): void {
    if (scelta.value !== null) {
        eliminazione.submit(destroy(scelta.value.id), {
            preserveScroll: true,
            onSuccess: () => (aperto.value = false),
        });
    }
}
</script>

<template>
    <Head title="Categorie dei documenti" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitolo
            titolo="Categorie dei documenti"
            sottotitolo="Dicono che tipo di documento è; di chi è e a cosa si riferisce lo dicono i collegamenti"
        >
            <template v-if="puoModificare" #azioni>
                <FmPulsante :icona="Plus" @click="apri(null)"
                    >Nuova categoria</FmPulsante
                >
            </template>
        </FmTitolo>

        <FmRiquadro elenco>
            <FmAlberoCategorie
                :categorie="categorie"
                :scegli="puoModificare"
                @scelta="apri"
            />
        </FmRiquadro>

        <FmPannelloDalBasso
            v-model:open="aperto"
            :titolo="scelta ? scelta.name : 'Nuova categoria'"
        >
            <form
                class="flex flex-col gap-3"
                novalidate
                @submit.prevent="salva"
            >
                <FormKit
                    v-model="form.name"
                    type="text"
                    label="Nome"
                    name="name"
                    :errors="errori(form.errors.name)"
                />
                <FormKit
                    v-model="form.parent_id"
                    type="select"
                    label="Dentro"
                    name="parent_id"
                    :options="posti"
                    :errors="errori(form.errors.parent_id)"
                />
                <p v-if="nota" class="m-0 text-[13px] text-secondario">
                    {{ nota }}
                </p>
                <FmAvviso
                    v-if="eliminazione.errors.categoria"
                    tono="attenzione"
                    >{{ eliminazione.errors.categoria }}</FmAvviso
                >
                <div
                    class="flex flex-wrap items-center justify-between gap-2.5 pt-2"
                >
                    <FmPulsante
                        v-if="eliminabile"
                        variante="pericolo"
                        :icona="Trash2"
                        :disabled="eliminazione.processing"
                        @click="elimina"
                        >Elimina</FmPulsante
                    >
                    <span v-else />
                    <div class="flex gap-2.5">
                        <FmPulsante variante="testo" @click="aperto = false"
                            >Annulla</FmPulsante
                        >
                        <FmPulsante tipo="submit" :disabled="form.processing"
                            >Salva</FmPulsante
                        >
                    </div>
                </div>
            </form>
        </FmPannelloDalBasso>
    </div>
</template>
