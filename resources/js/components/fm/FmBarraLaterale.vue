<script setup lang="ts">
/**
 * La barra laterale del computer (dai 1024 px): il logo e la famiglia, Carica,
 * le sezioni, e in fondo *Il mio account*, che apre Impostazioni ed Esci.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Plus } from '@lucide/vue';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { getInitials } from '@/composables/useInitials';
import FmIcona from './FmIcona.vue';
import FmLogo from './FmLogo.vue';
import type { Sezione, VociDellaBarra } from './navigazione';

const { attiva, voci, famiglia, utente } = defineProps<{
    /** La sezione della pagina in cui si è. */
    attiva: Sezione;
    /** Le voci della navigazione. */
    voci: VociDellaBarra;
    /** Il nome della famiglia, per esempio «Bianchi». */
    famiglia: string;
    /** Il nome di chi è entrato. */
    utente: string;
}>();
</script>

<template>
    <aside
        class="flex w-[260px] shrink-0 flex-col gap-1.5 border-r border-[#E3E8EF] bg-white px-3.5 py-5"
    >
        <div class="flex items-center gap-2.5 px-2 pt-1 pb-4">
            <FmLogo :dimensione="36" />
            <span class="flex flex-col">
                <span class="text-base font-extrabold">Family Manager</span>
                <span class="text-[13px] text-secondario"
                    >Famiglia {{ famiglia }}</span
                >
            </span>
        </div>

        <Link
            :href="voci.carica"
            class="mb-3.5 flex h-12 items-center justify-center gap-2 rounded-[14px] bg-azione text-base font-bold text-white no-underline hover:bg-azione-scuro"
        >
            <FmIcona :icona="Plus" :dimensione="20" :spessore="2.5" />
            Carica
        </Link>

        <nav aria-label="Sezioni" class="flex flex-col gap-0.5">
            <Link
                v-for="voce in voci.sezioni"
                :key="voce.sezione"
                :href="voce.href"
                :aria-current="voce.sezione === attiva ? 'page' : undefined"
                class="flex h-11 items-center gap-3 rounded-xl px-3 text-[15px] no-underline"
                :class="
                    voce.sezione === attiva
                        ? 'bg-azione-tenue font-bold text-azione-scuro'
                        : 'font-medium text-testo hover:bg-sfondo'
                "
            >
                <FmIcona
                    :icona="voce.icona"
                    :dimensione="22"
                    :class="voce.sezione === attiva ? '' : 'text-secondario'"
                />
                {{ voce.etichetta }}
            </Link>
        </nav>

        <span class="min-h-6 grow" />

        <div class="border-t border-riga pt-2">
            <DropdownMenuRoot>
                <DropdownMenuTrigger
                    class="flex min-h-13 w-full items-center gap-2.5 rounded-xl px-2 text-left hover:bg-sfondo data-[state=open]:bg-sfondo"
                >
                    <span
                        class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-azione-tenue text-sm font-bold text-azione-scuro"
                        >{{ getInitials(utente) }}</span
                    >
                    <span class="flex grow flex-col">
                        <span class="text-sm font-semibold">{{ utente }}</span>
                        <span class="text-xs text-secondario"
                            >Il mio account</span
                        >
                    </span>
                    <FmIcona
                        :icona="ChevronDown"
                        :dimensione="18"
                        class="text-secondario"
                    />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        side="top"
                        :side-offset="8"
                        class="z-50 flex w-[232px] flex-col gap-0.5 rounded-[14px] border border-[#E3E8EF] bg-white p-1.5 text-testo shadow-[0_12px_32px_rgba(24,33,47,0.16)]"
                    >
                        <DropdownMenuItem as-child>
                            <Link
                                :href="voci.impostazioni.href"
                                class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-[15px] font-medium no-underline outline-none data-[highlighted]:bg-sfondo"
                            >
                                <FmIcona
                                    :icona="voci.impostazioni.icona"
                                    :dimensione="20"
                                    class="text-secondario"
                                />
                                {{ voci.impostazioni.etichetta }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator
                            class="mx-1.5 my-1 h-px bg-riga"
                        />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="voci.esci"
                                method="post"
                                as="button"
                                class="flex min-h-11 w-full items-center gap-3 rounded-[10px] px-3 text-left text-[15px] font-medium text-scaduto outline-none data-[highlighted]:bg-sfondo"
                            >
                                <FmIcona :icona="LogOut" :dimensione="20" />
                                Esci
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>
    </aside>
</template>
