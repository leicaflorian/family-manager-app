<script setup lang="ts">
/**
 * Pagina *Iscriviti*: chi si iscrive crea la sua famiglia ed è il primo account,
 * con il livello *Tutto, come te*. L'aspetto della tela arriva con le pagine di accesso.
 */
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    /** Le regole della password, per il suggerimento del browser. */
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Iscrivi la tua famiglia',
        description:
            'Crea lo spazio della tua famiglia: poi potrai invitare gli altri',
    },
});
</script>

<template>
    <Head title="Iscriviti" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="nome">Il tuo nome</Label>
                <Input
                    id="nome"
                    type="text"
                    required
                    v-focus
                    autocomplete="given-name"
                    name="nome"
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-2">
                <Label for="cognome">Cognome della famiglia</Label>
                <Input
                    id="cognome"
                    type="text"
                    required
                    autocomplete="family-name"
                    name="cognome"
                />
                <InputError :message="errors.cognome" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="condizioni" class="flex items-center gap-3">
                    <Checkbox id="condizioni" name="condizioni" value="1" />
                    <span>Accetto le condizioni d'uso</span>
                </Label>
                <InputError :message="errors.condizioni" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Iscriviti
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Hai già un account?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Accedi</TextLink
            >
        </div>
    </Form>
</template>
