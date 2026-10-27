<script setup lang="ts">
/**
 * Un livello dell'albero delle categorie dei documenti: ogni categoria con il
 * numero dei suoi documenti e, se ne ha, la freccia che apre le sottocategorie.
 * Si richiama da solo per i livelli sotto.
 */
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import { areaDi, sfondoArea, tesseraArea } from '@/lib/aree';
import { iconaCategoria } from '@/lib/icone';
import FmAlberoCategorie from './FmAlberoCategorie.vue';
import FmIcona from './FmIcona.vue';
import FmRiga from './FmRiga.vue';

const {
    categorie,
    livello = 0,
    scegli = false,
} = defineProps<{
    /** Le categorie di questo livello. */
    categorie: App.Data.CategoriaData[];
    /** Quanto è in fondo questo livello: 0 per le principali. */
    livello?: number;
    /** Le categorie si toccano per sceglierle (per modificarle). */
    scegli?: boolean;
}>();

const emit = defineEmits<{
    /** Toccata una categoria. */
    scelta: [categoria: App.Data.CategoriaData];
}>();

/** Gli id delle categorie aperte. */
const aperte = ref(new Set<number>());

/**
 * Apre o chiude le sottocategorie.
 *
 * @param id - la categoria
 */
function apriChiudi(id: number): void {
    if (!aperte.value.delete(id)) {
        aperte.value.add(id);
    }
}
</script>

<template>
    <ul class="m-0 list-none p-0">
        <li
            v-for="categoria in categorie"
            :key="categoria.id"
            class="border-riga not-first:border-t"
        >
            <div
                class="flex items-center"
                :style="{ paddingLeft: `${livello * 28}px` }"
            >
                <button
                    v-if="categoria.figlie.length"
                    type="button"
                    class="ml-2 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent text-[#9AA6B6] hover:bg-sfondo"
                    :aria-expanded="aperte.has(categoria.id)"
                    :aria-label="`${aperte.has(categoria.id) ? 'Chiudi' : 'Apri'} ${categoria.name}`"
                    @click="apriChiudi(categoria.id)"
                >
                    <FmIcona
                        :icona="
                            aperte.has(categoria.id)
                                ? ChevronDown
                                : ChevronRight
                        "
                        :dimensione="18"
                    />
                </button>
                <span v-else class="ml-2 size-9 shrink-0" />
                <FmRiga
                    class="min-w-0 grow pl-1"
                    :titolo="categoria.name"
                    :pulsante="scegli"
                    @click="emit('scelta', categoria)"
                >
                    <template #prima>
                        <span
                            v-if="livello === 0"
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                tesseraArea[
                                    areaDi(categoria.color) ?? 'trasversali'
                                ]
                            "
                        >
                            <FmIcona
                                :icona="iconaCategoria(categoria.icon)"
                                :dimensione="18"
                            />
                        </span>
                        <span
                            v-else
                            class="size-2.5 shrink-0 rounded-full"
                            :class="
                                sfondoArea[
                                    areaDi(categoria.color) ?? 'trasversali'
                                ]
                            "
                        />
                    </template>
                    <template #destra>
                        <span class="text-[13px] font-normal text-secondario">{{
                            categoria.documenti
                        }}</span>
                    </template>
                </FmRiga>
            </div>
            <FmAlberoCategorie
                v-if="aperte.has(categoria.id)"
                :categorie="categoria.figlie"
                :livello="livello + 1"
                :scegli="scegli"
                @scelta="emit('scelta', $event)"
            />
        </li>
    </ul>
</template>
