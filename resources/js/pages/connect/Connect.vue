<script setup lang="ts">
/**
 * *Link the phone*: where the link of the code leads, or the address `connect` for who could not scan it.
 * With a valid code (the link) it says who it is for and waits for a tap on *Link this phone*, so a preview of
 * the link does not use the code; without one it asks for the code written by hand. Nothing here needs a login.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmHeader from '@/components/fm/FmHeader.vue';
import { errors } from '@/lib/errors';
import { use } from '@/routes/connect';

defineOptions({
    layout: {
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                'Link your phone and upload the documents without a password.',
            ),
        },
    },
});

const { code, person } = defineProps<{
    /** The code of the link, already valid, or null when it is to be written. */
    code: string | null;
    /** The first name of whom the code is for, with a valid code. */
    person: string | null;
    /** The name of the family the code is for, with a valid code. */
    family: string | null;
}>();

const form = useForm({ code: code ?? '' });

/**
 * Uses the code: the written one, or the one of the link. An empty field is not sent.
 */
function link(): void {
    const written = form.code.trim();

    if (written === '') {
        form.setError('code', trans('Write the code first.'));

        return;
    }

    form.clearErrors();
    form.submit(use(encodeURIComponent(written)));
}
</script>

<template>
    <Head :title="$t('Link the phone')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="link">
        <template v-if="code && person">
            <FmHeader
                :title="$t('Hi, :name', { name: person })"
                :subtitle="
                    $t(
                        'Link this phone and you upload the documents to the :family family without a password.',
                        { family: family ?? '' },
                    )
                "
            />
            <p v-if="form.errors.code" role="alert" class="m-0 text-ink-soft">
                {{ form.errors.code }}
            </p>
            <span class="min-h-6 grow lg:hidden" />
            <FmButton type="submit" wide :disabled="form.processing">{{
                $t('Link this phone')
            }}</FmButton>
        </template>
        <template v-else>
            <FmHeader
                :title="$t('Link the phone')"
                :subtitle="$t('Write the code you see on the computer.')"
            />
            <FormKit
                v-model="form.code"
                type="text"
                :label="$t('Code')"
                name="code"
                placeholder="K7QM-XPA9"
                autocomplete="off"
                autocapitalize="characters"
                autocorrect="off"
                spellcheck="false"
                :errors="errors(form.errors.code)"
            />
            <span class="min-h-6 grow lg:hidden" />
            <FmButton type="submit" wide :disabled="form.processing">{{
                $t('Connect')
            }}</FmButton>
        </template>
    </form>
</template>
