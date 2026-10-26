<script setup lang="ts">
/**
 * La barra in basso del telefono (sotto i 1024 px): Home, Aree, il + di Carica
 * al centro, Documenti e Altro. Altro apre un pannello con Famiglia, Impostazioni
 * ed Esci, e resta segnato quando si è in una di quelle pagine.
 */
import { Link } from '@inertiajs/vue3';
import { LogOut, Menu, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmIcona from './FmIcona.vue';
import FmPannelloDalBasso from './FmPannelloDalBasso.vue';
import FmRiga from './FmRiga.vue';
import type { Sezione, VoceNavigazione, VociDellaBarra } from './navigazione';

const { attiva, voci } = defineProps<{
    /** La sezione della pagina in cui si è. */
    attiva: Sezione;
    /** Le voci della navigazione. */
    voci: VociDellaBarra;
}>();

const altroAperto = ref(false);

/** Le voci che stanno nella barra: Home, Aree, Documenti. */
const inBarra = computed((): VoceNavigazione[] =>
    voci.sezioni.filter((v) => v.sezione !== 'famiglia'),
);

/** Le voci dentro Altro. */
const inAltro = computed((): VoceNavigazione[] => [
    ...voci.sezioni.filter((v) => v.sezione === 'famiglia'),
    voci.impostazioni,
]);

const altroAttivo = computed((): boolean =>
    inAltro.value.some((v) => v.sezione === attiva),
);

/**
 * Le classi di una voce, blu se è quella della pagina.
 *
 * @param segnata - la voce è quella della pagina in cui si è
 */
function classiVoce(segnata: boolean): string {
    return [
        'flex min-h-13 flex-col items-center justify-center gap-1 text-xs no-underline',
        segnata ? 'font-semibold text-azione' : 'font-medium text-secondario',
    ].join(' ');
}
</script>

<template>
    <nav
        aria-label="Sezioni"
        class="grid h-[84px] shrink-0 grid-cols-5 items-center border-t border-[#E3E8EF] bg-white px-1 pb-3"
    >
        <template v-for="(voce, i) in inBarra" :key="voce.sezione">
            <span v-if="i === 2" class="flex justify-center">
                <Link
                    :href="voci.carica"
                    aria-label="Carica un documento"
                    class="-mt-7 flex size-15 items-center justify-center rounded-full bg-azione text-white shadow-[0_6px_16px_rgba(0,112,224,0.35)]"
                >
                    <FmIcona :icona="Plus" :dimensione="28" :spessore="2.5" />
                </Link>
            </span>
            <Link
                :href="voce.href"
                :aria-current="voce.sezione === attiva ? 'page' : undefined"
                :class="classiVoce(voce.sezione === attiva)"
            >
                <FmIcona :icona="voce.icona" />
                {{ voce.etichetta }}
            </Link>
        </template>
        <button
            type="button"
            :aria-current="altroAttivo ? 'page' : undefined"
            :class="classiVoce(altroAttivo)"
            @click="altroAperto = true"
        >
            <FmIcona :icona="Menu" />
            Altro
        </button>
    </nav>

    <FmPannelloDalBasso v-model:open="altroAperto" titolo="Altro">
        <div
            class="flex flex-col divide-y divide-riga rounded-2xl border border-riga"
        >
            <FmRiga
                v-for="voce in inAltro"
                :key="voce.sezione"
                :titolo="voce.etichetta"
                :href="voce.href"
            >
                <template #prima>
                    <FmIcona
                        :icona="voce.icona"
                        :dimensione="22"
                        class="text-secondario"
                    />
                </template>
            </FmRiga>
            <Link
                :href="voci.esci"
                method="post"
                as="button"
                class="flex min-h-13 items-center gap-3 px-4 text-left text-base font-semibold text-scaduto"
            >
                <FmIcona :icona="LogOut" :dimensione="22" />
                Esci
            </Link>
        </div>
    </FmPannelloDalBasso>
</template>
