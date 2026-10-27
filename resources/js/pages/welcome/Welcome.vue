<script setup lang="ts">
/**
 * The *Welcome* after signing up, in two steps: who you are (the people of the
 * family) and the home, which can be left for later. *Done, let's go in* saves
 * everything together and leads to the Home. The step of the bills comes with
 * the cards.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmHeader from '@/components/fm/FmHeader.vue';
import FmRow from '@/components/fm/FmRow.vue';
import { errors } from '@/lib/errors';
import { store } from '@/routes/welcome';

defineOptions({
    layout: {
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                'Two questions and your family is ready. You can change everything later too.',
            ),
            points: [trans('Who you are'), trans('The home')],
        },
    },
});

const { people, roles } = defineProps<{
    /** The people already there: who signed up. */
    people: App.Data.PersonSummaryData[];
    /** Who a person can be in the family. */
    roles: App.Data.OptionData[];
}>();

/** A person to add, as written in the form. */
type NewPerson = { first_name: string; last_name: string; role: string };

/** The two steps, with the title and the sentence of each. */
const steps = [
    {
        title: trans('Who you are'),
        sentence: trans(
            'Who lives with you? Add the people of the family: you can invite them to join later too.',
        ),
    },
    {
        title: trans('The home'),
        sentence: trans(
            'Where do you live? The bills, the contracts and the documents are linked to the home.',
        ),
    },
] as const;

const step = ref<0 | 1>(0);
const current = computed(() => steps[step.value]);

const form = useForm({
    people: [] as NewPerson[],
    home: { name: trans('House'), address: '', city: '', is_owned: false },
});

/**
 * Adds a row for a person, with the surname of the family already written.
 */
function addPerson(): void {
    form.people.push({
        first_name: '',
        last_name: people[0]?.last_name ?? '',
        role: roles[0]?.value ?? '',
    });
}

/**
 * Saves and leads to the Home; with `withoutHome` the home is left for later.
 *
 * @param withoutHome - true for «I will add it later»
 */
function done(withoutHome = false): void {
    form.transform((data) => ({
        ...data,
        home: withoutHome ? null : data.home,
    })).submit(store(), {
        onError: (fieldErrors) => {
            if (
                Object.keys(fieldErrors).some((field) =>
                    field.startsWith('people'),
                )
            ) {
                step.value = 0;
            }
        },
    });
}
</script>

<template>
    <Head :title="$t('Welcome')" />

    <form class="flex grow flex-col gap-5" novalidate @submit.prevent="done()">
        <div class="flex flex-col gap-2.5">
            <span class="text-sm font-semibold text-ink-soft">{{
                $t('Step :step of :total', {
                    step: String(step + 1),
                    total: String(steps.length),
                })
            }}</span>
            <div class="grid grid-cols-2 gap-1.5" aria-hidden="true">
                <span
                    v-for="(s, i) in steps"
                    :key="s.title"
                    class="h-1.5 rounded-full"
                    :class="i <= step ? 'bg-action' : 'bg-divider'"
                />
            </div>
        </div>
        <FmHeader :title="current.title" :subtitle="current.sentence" />

        <div v-if="step === 0" class="flex flex-col gap-3">
            <FmRow
                v-for="person in people"
                :key="person.id"
                :title="`${person.first_name} ${person.last_name}`"
                :subtitle="$t('You')"
            />
            <fieldset
                v-for="(person, i) in form.people"
                :key="i"
                class="m-0 flex flex-col gap-3 rounded-2xl border-[1.5px] border-edge p-4"
            >
                <legend class="sr-only">
                    {{ $t('Person :number', { number: String(i + 1) }) }}
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="person.first_name"
                        type="text"
                        :label="$t('First name')"
                        :name="`people.${i}.first_name`"
                        :errors="errors(form.errors[`people.${i}.first_name`])"
                    />
                    <FormKit
                        v-model="person.last_name"
                        type="text"
                        :label="$t('Last name')"
                        :name="`people.${i}.last_name`"
                        :errors="errors(form.errors[`people.${i}.last_name`])"
                    />
                </div>
                <FormKit
                    v-model="person.role"
                    type="select"
                    :label="$t('In the family they are')"
                    :name="`people.${i}.role`"
                    :options="roles"
                    :errors="errors(form.errors[`people.${i}.role`])"
                />
                <FmButton
                    variant="danger"
                    :icon="X"
                    @click="form.people.splice(i, 1)"
                    >{{ $t('Remove') }}</FmButton
                >
            </fieldset>
            <FmButton variant="soft" :icon="UserPlus" @click="addPerson">{{
                $t('Add a person')
            }}</FmButton>
        </div>

        <div v-else class="flex flex-col gap-3">
            <FormKit
                v-model="form.home.name"
                type="text"
                :label="$t('What you call it')"
                name="home.name"
                :errors="errors(form.errors['home.name'])"
            />
            <FormKit
                v-model="form.home.address"
                type="text"
                :label="$t('Address')"
                name="home.address"
                autocomplete="street-address"
                :errors="errors(form.errors['home.address'])"
            />
            <FormKit
                v-model="form.home.city"
                type="text"
                :label="$t('City')"
                name="home.city"
                autocomplete="address-level2"
                :errors="errors(form.errors['home.city'])"
            />
            <FormKit
                v-model="form.home.is_owned"
                type="checkbox"
                :label="$t('It is your property')"
                name="home.is_owned"
            />
        </div>

        <span class="min-h-6 grow" />
        <div class="flex flex-col gap-1">
            <template v-if="step === 0">
                <FmButton wide @click="step = 1">{{ $t('Next') }}</FmButton>
            </template>
            <template v-else>
                <FmButton type="submit" wide :disabled="form.processing">{{
                    $t("Done, let's go in")
                }}</FmButton>
                <FmButton
                    variant="text"
                    :disabled="form.processing"
                    @click="done(true)"
                    >{{ $t('I will add it later') }}</FmButton
                >
                <FmButton variant="text" @click="step = 0">{{
                    $t('Back')
                }}</FmButton>
            </template>
        </div>
    </form>
</template>
