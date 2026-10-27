<script setup lang="ts">
/**
 * L'indice delle *Impostazioni*: il mio account, solo mio, e le impostazioni che
 * valgono per tutta la famiglia. Le altre voci della tavola (avvisi, bollette,
 * calendario, abbonamento) arrivano con le loro tappe.
 */
import { Head } from '@inertiajs/vue3';
import { FolderTree } from '@lucide/vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmIcona from '@/components/fm/FmIcona.vue';
import FmRiga from '@/components/fm/FmRiga.vue';
import FmRiquadro from '@/components/fm/FmRiquadro.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import { edit } from '@/routes/account';
import { index } from '@/routes/categorie';

const { account, categoriePrincipali } = defineProps<{
    /** Il mio account. */
    account: App.Data.AccountData;
    /** Quante sono le categorie principali dei documenti. */
    categoriePrincipali: number;
}>();
</script>

<template>
    <Head title="Impostazioni" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitolo
            titolo="Impostazioni"
            :sottotitolo="`Valgono per tutta la famiglia ${account.famiglia}; il mio account è solo tuo`"
        />
        <FmRiquadro elenco>
            <FmRiga
                titolo="Il mio account"
                :sottotitolo="`${account.nome} ${account.cognome} · ${account.ruolo}`"
                :href="edit().url"
            >
                <template #prima>
                    <FmAvatar
                        :iniziali="account.iniziali"
                        :foto="account.foto"
                        :nome="`${account.nome} ${account.cognome}`"
                    />
                </template>
            </FmRiga>
        </FmRiquadro>
        <FmRiquadro titolo="La famiglia" elenco>
            <FmRiga
                titolo="Categorie dei documenti"
                :sottotitolo="`${categoriePrincipali} categorie principali`"
                :href="index().url"
            >
                <template #prima>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-azione-tenue text-azione-scuro"
                    >
                        <FmIcona :icona="FolderTree" :dimensione="22" />
                    </span>
                </template>
            </FmRiga>
        </FmRiquadro>
    </div>
</template>
