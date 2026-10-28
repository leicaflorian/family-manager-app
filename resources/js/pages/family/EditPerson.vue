<script setup lang="ts">
/**
 * *New person* and *Edit a person* (only an `admin`): name, who they are, birth
 * date, tax code, the initials of the circle and the notes. A new person has no
 * account: that comes with an invitation. Beside the form, the photo, as the
 * file beside *Edit the document*.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmImagePanel from '@/components/fm/FmImagePanel.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { withMethod } from '@/lib/forms';
import { errors } from '@/lib/errors';
import { personValues } from '@/lib/family';
import { initialsOf } from '@/lib/names';
import { index, show, store, update } from '@/routes/people';

const { person, roles } = defineProps<{
    /** The person to change, or null for a new one. */
    person: App.Data.PersonDetailData | null;
    /** Who a person can be in the family. */
    roles: App.Data.OptionData[];
}>();

const form = useForm({
    ...personValues(person),
    photo: null as File | null,
    remove_photo: false,
});

/** Where *Close* goes back to. */
const back = computed(() => (person ? show.url(person.id) : index().url));

/** The initials in the circle: those written, or those the server will make. */
const initials = computed(
    () => form.initials || initialsOf(form.first_name, form.last_name),
);

/** Sends the person, with the photo chosen or its removal. */
function save(): void {
    const target = person ? update(person.id) : store();

    form.transform((data) => withMethod(data, target.method)).post(target.url);
}

useSaveShortcut(save, () => !form.processing);
</script>

<template>
    <Head
        :title="
            person
                ? $t('Edit :name', { name: person.first_name })
                : $t('New person')
        "
    />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle
            :title="
                person
                    ? $t('Edit :name', { name: person.first_name })
                    : $t('New person')
            "
            :back="back"
            :back-label="
                person
                    ? `${person.first_name} ${person.last_name}`
                    : $t('People')
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
        <div
            class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start"
        >
            <FmImagePanel
                v-model:file="form.photo"
                v-model:removed="form.remove_photo"
                :image="person?.photo ?? null"
                :add-label="$t('Add a photo')"
                :change-label="$t('Change the photo')"
                :error="form.errors.photo"
            >
                <template #default="{ image }">
                    <FmAvatar
                        :initials="initials"
                        :photo="image"
                        :name="`${form.first_name} ${form.last_name}`"
                        :size="160"
                    />
                </template>
            </FmImagePanel>
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
                    type="dropdown"
                    :label="$t('Who they are')"
                    name="role"
                    :options="roles"
                    :errors="errors(form.errors.role)"
                />
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="form.birth_date"
                        type="datepicker"
                        :label="$t('Date of birth')"
                        name="birth_date"
                        :errors="errors(form.errors.birth_date)"
                    />
                    <FormKit
                        v-model="form.initials"
                        type="text"
                        :label="$t('Initials')"
                        name="initials"
                        :help="
                            $t('In the circle. Empty, they come from the name.')
                        "
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
    </div>
</template>
