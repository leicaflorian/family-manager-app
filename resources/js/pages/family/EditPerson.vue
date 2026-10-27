<script setup lang="ts">
/**
 * *New person* and *Edit a person* (only an `admin`): name, who they are, birth
 * date, tax code, the initials of the circle and the notes. A new person has no
 * account: that comes with an invitation.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { errors } from '@/lib/errors';
import { personValues } from '@/lib/family';
import { index, show, store, update } from '@/routes/people';

const { person, roles } = defineProps<{
    /** The person to change, or null for a new one. */
    person: App.Data.PersonDetailData | null;
    /** Who a person can be in the family. */
    roles: App.Data.OptionData[];
}>();

const form = useForm(personValues(person));

/** Where *Close* goes back to. */
const back = computed(() => (person ? show.url(person.id) : index().url));

/** Sends the person. */
function save(): void {
    form.submit(person ? update(person.id) : store());
}
</script>

<template>
    <Head
        :title="
            person
                ? $t('Edit :name', { name: person.first_name })
                : $t('New person')
        "
    />

    <div class="flex max-w-[640px] flex-col gap-4">
        <FmTitle
            :title="
                person
                    ? $t('Edit :name', { name: person.first_name })
                    : $t('New person')
            "
        >
            <template #actions>
                <Link
                    :href="back"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>
        <form
            class="flex flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
            novalidate
            @submit.prevent="save"
        >
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="form.first_name"
                    type="text"
                    :label="$t('First name')"
                    name="first_name"
                    autocomplete="off"
                    :errors="errors(form.errors.first_name)"
                />
                <FormKit
                    v-model="form.last_name"
                    type="text"
                    :label="$t('Last name')"
                    name="last_name"
                    autocomplete="off"
                    :errors="errors(form.errors.last_name)"
                />
            </div>
            <FormKit
                v-model="form.role"
                type="select"
                :label="$t('Who they are')"
                name="role"
                :options="roles"
                :errors="errors(form.errors.role)"
            />
            <div class="grid grid-cols-2 gap-3">
                <FormKit
                    v-model="form.birth_date"
                    type="date"
                    :label="$t('Date of birth')"
                    name="birth_date"
                    :errors="errors(form.errors.birth_date)"
                />
                <FormKit
                    v-model="form.initials"
                    type="text"
                    :label="$t('Initials')"
                    name="initials"
                    :help="$t('In the circle. Empty, they come from the name.')"
                    :errors="errors(form.errors.initials)"
                />
            </div>
            <FormKit
                v-model="form.tax_code"
                type="text"
                :label="$t('Tax code')"
                name="tax_code"
                autocomplete="off"
                :errors="errors(form.errors.tax_code)"
            />
            <FormKit
                v-model="form.notes"
                type="textarea"
                :label="$t('Notes')"
                name="notes"
                :errors="errors(form.errors.notes)"
            />
            <div class="mt-1.5 flex flex-wrap justify-end gap-2.5">
                <FmButton variant="text" :href="back">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="form.processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </div>
</template>
