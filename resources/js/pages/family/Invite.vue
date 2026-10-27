<script setup lang="ts">
/**
 * *Invite a family member* page: the email, who they are (a person of the family
 * who has no account yet, or a new person) and what they can do.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { errors } from '@/lib/errors';
import { family } from '@/routes';
import { store } from '@/routes/family/invitations';

/** The value of «Who they are» for a person who is not there yet. */
const NEW_PERSON = 'new';

const { people, levels, roles } = defineProps<{
    /** The people of the family without an account. */
    people: App.Data.PersonSummaryData[];
    /** The two levels, with their explanation. */
    levels: App.Data.OptionData[];
    /** Who a person can be in the family. */
    roles: App.Data.OptionData[];
}>();

const who = ref<string>(people[0] ? String(people[0].id) : NEW_PERSON);
const isNew = computed(() => who.value === NEW_PERSON);

const whoOptions = computed(() => [
    ...people.map((p) => ({
        value: String(p.id),
        label: `${p.first_name} ${p.last_name}`,
    })),
    { value: NEW_PERSON, label: trans('A new person') },
]);

const form = useForm({
    email: '',
    role: 'member',
    first_name: '',
    last_name: '',
    person_role: roles.at(-1)?.value ?? '',
    person_id: null as number | null,
});

/**
 * Sends the invitation: with the chosen person, or with first and last name of
 * the new one.
 */
function send(): void {
    form.transform((data) => ({
        ...data,
        person_id: isNew.value ? null : Number(who.value),
    })).submit(store());
}
</script>

<template>
    <Head :title="$t('Invite a family member')" />

    <form
        class="flex max-w-[560px] flex-col gap-3.5"
        novalidate
        @submit.prevent="send"
    >
        <FmTitle :title="$t('Invite a family member')" />
        <FormKit
            v-model="form.email"
            type="email"
            :label="$t('Their email')"
            name="email"
            autocomplete="off"
            :errors="errors(form.errors.email)"
        />
        <FormKit
            v-model="who"
            type="select"
            :label="$t('Who they are')"
            name="who"
            :options="whoOptions"
            :errors="errors(form.errors.person_id)"
        />
        <template v-if="isNew">
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="form.first_name"
                    type="text"
                    :label="$t('First name')"
                    name="first_name"
                    :errors="errors(form.errors.first_name)"
                />
                <FormKit
                    v-model="form.last_name"
                    type="text"
                    :label="$t('Last name')"
                    name="last_name"
                    :errors="errors(form.errors.last_name)"
                />
            </div>
            <FormKit
                v-model="form.person_role"
                type="select"
                :label="$t('In the family they are')"
                name="person_role"
                :options="roles"
                :errors="errors(form.errors.person_role)"
            />
        </template>
        <FormKit
            v-model="form.role"
            type="radio"
            :label="$t('What they can do')"
            name="role"
            :options="levels"
            :errors="errors(form.errors.role)"
        />
        <FmNotice :icon="Mail">
            {{
                $t(
                    'They get an email with the link to join, valid for 7 days. They also become a person of the family, with their own card.',
                )
            }}
        </FmNotice>
        <div
            class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:justify-start lg:gap-2.5"
        >
            <FmButton type="submit" :disabled="form.processing">{{
                $t('Send the invitation')
            }}</FmButton>
            <FmButton variant="text" :href="family().url">{{
                $t('Cancel')
            }}</FmButton>
        </div>
    </form>
</template>
