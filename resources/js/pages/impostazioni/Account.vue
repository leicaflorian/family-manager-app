<script setup lang="ts">
/**
 * *Il mio account*: nome, cognome, email e foto; la password; la famiglia e il
 * livello; *Esci* ed *Elimina il mio account*. L'unico amministratore della
 * famiglia non può eliminarsi, e la pagina gli dice perché.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, LogOut, Users } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmIcona from '@/components/fm/FmIcona.vue';
import FmPannelloDalBasso from '@/components/fm/FmPannelloDalBasso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmRiga from '@/components/fm/FmRiga.vue';
import FmRiquadro from '@/components/fm/FmRiquadro.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import { errori } from '@/lib/errori';
import { logout } from '@/routes';
import { destroy, foto, password, update } from '@/routes/account';

const { account } = defineProps<{
    /** Il mio account. */
    account: App.Data.AccountData;
}>();

const dati = useForm({
    nome: account.nome,
    cognome: account.cognome,
    email: account.email,
});

const nuovaFoto = useForm({ foto: null as File | null });
const sceltaFoto = useTemplateRef<HTMLInputElement>('sceltaFoto');

/**
 * Manda la foto appena scelta.
 */
function caricaFoto(): void {
    nuovaFoto.foto = sceltaFoto.value?.files?.[0] ?? null;

    if (nuovaFoto.foto !== null) {
        nuovaFoto.submit(foto(), { preserveScroll: true });
    }
}

const pannelloPassword = ref(false);
const cambio = useForm({ password_attuale: '', password: '' });

/**
 * Cambia la password e chiude il pannello.
 */
function cambiaPassword(): void {
    cambio.submit(password(), {
        preserveScroll: true,
        onSuccess: () => (pannelloPassword.value = false),
        onFinish: () => cambio.reset(),
    });
}

const pannelloElimina = ref(false);
const eliminazione = useForm({ password: '' });

/**
 * Elimina l'account: il server porta ad Accedi.
 */
function elimina(): void {
    eliminazione.submit(destroy(), { onFinish: () => eliminazione.reset() });
}
</script>

<template>
    <Head title="Il mio account" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitolo
            titolo="Il mio account"
            sottotitolo="Solo tuo: il resto delle impostazioni vale per tutta la famiglia"
        />

        <form
            novalidate
            @submit.prevent="dati.submit(update(), { preserveScroll: true })"
        >
            <FmRiquadro>
                <div class="flex items-center gap-4">
                    <FmAvatar
                        :iniziali="account.iniziali"
                        :foto="account.foto"
                        :nome="`${account.nome} ${account.cognome}`"
                        :dimensione="64"
                    />
                    <div class="flex flex-col gap-1">
                        <span class="text-lg font-extrabold"
                            >{{ account.nome }} {{ account.cognome }}</span
                        >
                        <button
                            type="button"
                            class="cursor-pointer border-0 bg-transparent p-0 text-left text-sm font-semibold text-azione hover:text-azione-scuro"
                            :disabled="nuovaFoto.processing"
                            @click="sceltaFoto?.click()"
                        >
                            Cambia la foto
                        </button>
                        <input
                            ref="sceltaFoto"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="caricaFoto"
                        />
                        <span
                            v-if="nuovaFoto.errors.foto"
                            class="text-[13px] text-scaduto"
                            >{{ nuovaFoto.errors.foto }}</span
                        >
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="dati.nome"
                        type="text"
                        label="Nome"
                        name="nome"
                        autocomplete="given-name"
                        :errors="errori(dati.errors.nome)"
                    />
                    <FormKit
                        v-model="dati.cognome"
                        type="text"
                        label="Cognome"
                        name="cognome"
                        autocomplete="family-name"
                        :errors="errori(dati.errors.cognome)"
                    />
                </div>
                <FormKit
                    v-model="dati.email"
                    type="email"
                    label="Email"
                    name="email"
                    autocomplete="email"
                    help="Cambiandola, ti mandiamo un'email per confermarla"
                    :errors="errori(dati.errors.email)"
                />
                <div class="flex justify-end">
                    <FmPulsante tipo="submit" :disabled="dati.processing"
                        >Salva</FmPulsante
                    >
                </div>
            </FmRiquadro>
        </form>

        <FmRiquadro titolo="Accesso" elenco>
            <FmRiga
                titolo="Password"
                sottotitolo="Almeno 10 caratteri"
                pulsante
                @click="pannelloPassword = true"
            >
                <template #prima>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sfondo text-[#4A5565]"
                    >
                        <FmIcona :icona="KeyRound" :dimensione="22" />
                    </span>
                </template>
                <template #destra>
                    <span class="text-[15px] font-semibold text-azione"
                        >Cambia</span
                    >
                </template>
            </FmRiga>
        </FmRiquadro>

        <FmRiquadro titolo="La famiglia" elenco>
            <FmRiga
                :titolo="`Famiglia ${account.famiglia}`"
                :sottotitolo="
                    account.admin
                        ? 'Sei amministratore: puoi cambiare le impostazioni e invitare'
                        : 'Vedi tutto e carichi i documenti; le impostazioni le cambia un amministratore'
                "
            >
                <template #prima>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sfondo text-[#4A5565]"
                    >
                        <FmIcona :icona="Users" :dimensione="22" />
                    </span>
                </template>
            </FmRiga>
        </FmRiquadro>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <FmPulsante
                variante="bordo"
                :icona="LogOut"
                @click="router.post(logout().url)"
                >Esci</FmPulsante
            >
            <FmPulsante
                v-if="!account.ultimoAdmin"
                variante="pericolo"
                @click="pannelloElimina = true"
                >Elimina il mio account</FmPulsante
            >
        </div>
        <p v-if="account.ultimoAdmin" class="m-0 text-sm text-secondario">
            Sei l'unico amministratore della famiglia: per eliminare il tuo
            account, prima dai il livello «Tutto, come te» a un'altra persona.
        </p>

        <FmPannelloDalBasso
            v-model:open="pannelloPassword"
            titolo="Cambia la password"
        >
            <form
                class="flex flex-col gap-3"
                novalidate
                @submit.prevent="cambiaPassword"
            >
                <FmCampoPassword
                    v-model="cambio.password_attuale"
                    label="Password di adesso"
                    name="password_attuale"
                    autocomplete="current-password"
                    :errors="errori(cambio.errors.password_attuale)"
                />
                <FmCampoPassword
                    v-model="cambio.password"
                    label="Password nuova"
                    name="password"
                    help="Almeno 10 caratteri"
                    autocomplete="new-password"
                    :errors="errori(cambio.errors.password)"
                />
                <div
                    class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:gap-2.5"
                >
                    <FmPulsante tipo="submit" :disabled="cambio.processing"
                        >Cambia la password</FmPulsante
                    >
                    <FmPulsante
                        variante="testo"
                        @click="pannelloPassword = false"
                        >Annulla</FmPulsante
                    >
                </div>
            </form>
        </FmPannelloDalBasso>

        <FmPannelloDalBasso
            v-model:open="pannelloElimina"
            titolo="Elimina il mio account"
        >
            <form
                class="flex flex-col gap-3"
                novalidate
                @submit.prevent="elimina"
            >
                <FmAvviso tono="attenzione">
                    Il tuo account sparisce e non si recupera. La tua persona
                    resta nella famiglia, con i suoi documenti.
                </FmAvviso>
                <FmCampoPassword
                    v-model="eliminazione.password"
                    label="La tua password"
                    name="password"
                    autocomplete="current-password"
                    :errors="errori(eliminazione.errors.password)"
                />
                <div
                    class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:gap-2.5"
                >
                    <FmPulsante
                        tipo="submit"
                        variante="pericolo"
                        :disabled="eliminazione.processing"
                        >Elimina per sempre</FmPulsante
                    >
                    <FmPulsante
                        variante="testo"
                        @click="pannelloElimina = false"
                        >Annulla</FmPulsante
                    >
                </div>
            </form>
        </FmPannelloDalBasso>
    </div>
</template>
