<script setup lang="ts">
/**
 * *New organization* and *Edit an organization* (only an `admin`): the name,
 * its types (more than one: a bank can also be the insurance), the contacts
 * and the notes. Beside the form, the logo, as the file beside *Edit the
 * document*.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmImagePanel from '@/components/fm/FmImagePanel.vue';
import FmOrganizationMark from '@/components/fm/FmOrganizationMark.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { withMethod } from '@/lib/forms';
import { errors, listErrors } from '@/lib/errors';
import { organizationValues } from '@/lib/family';
import { index, show, store, update } from '@/routes/organizations';

const { organization, types } = defineProps<{
    /** The organization to change, or null for a new one. */
    organization: App.Data.OrganizationDetailData | null;
    /** Every type of organization. */
    types: App.Data.OptionData[];
}>();

const form = useForm({
    ...organizationValues(organization),
    logo: null as File | null,
    remove_logo: false,
});

/** Where *Close* goes back to. */
const back = computed(() =>
    organization ? show.url(organization.id) : index().url,
);

/** The errors of the types (`types.0`, …), all under the field. */
const typeErrors = computed(() => listErrors(form.errors, 'types'));

/** Sends the organization, with the logo chosen or its removal. */
function save(): void {
    const target = organization ? update(organization.id) : store();

    form.transform((data) => withMethod(data, target.method)).post(target.url);
}

useSaveShortcut(save, () => !form.processing);
</script>

<template>
    <Head
        :title="
            organization
                ? $t('Edit :name', { name: organization.name })
                : $t('New organization')
        "
    />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle
            :title="
                organization
                    ? $t('Edit :name', { name: organization.name })
                    : $t('New organization')
            "
            :back="back"
            :back-label="organization?.name ?? $t('Organizations')"
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
                v-model:file="form.logo"
                v-model:removed="form.remove_logo"
                :image="organization?.logo ?? null"
                :add-label="$t('Add a logo')"
                :change-label="$t('Change the logo')"
                :error="form.errors.logo"
            >
                <template #default="{ image }">
                    <FmOrganizationMark
                        :types="form.types"
                        :logo="image"
                        :name="form.name"
                        :icon-size="64"
                        class="size-40 rounded-3xl bg-white"
                    />
                </template>
            </FmImagePanel>
            <form
                class="flex flex-col gap-3.5 rounded-3xl bg-white p-5 lg:p-7"
                novalidate
                @submit.prevent="save"
            >
                <FormKit
                    v-model="form.name"
                    type="text"
                    :label="$t('Name')"
                    name="name"
                    autocomplete="off"
                    :errors="errors(form.errors.name)"
                />
                <FormKit
                    v-model="form.types"
                    type="togglebuttons"
                    multiple
                    :label="$t('What kind it is')"
                    name="types"
                    :options="types"
                    options-class="$reset m-0 flex list-none flex-wrap gap-2 p-0"
                    option-class="$reset"
                    input-class="$reset h-10 cursor-pointer rounded-full border border-edge bg-white px-4 text-[15px] text-ink transition-[background-color,border-color,color] duration-150 ease-out aria-pressed:border-action aria-pressed:bg-action aria-pressed:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action motion-reduce:transition-none"
                    :errors="typeErrors"
                />
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="form.phone"
                        type="tel"
                        :label="$t('Phone')"
                        name="phone"
                        :errors="errors(form.errors.phone)"
                    />
                    <FormKit
                        v-model="form.email"
                        type="email"
                        :label="$t('Email')"
                        name="email"
                        :errors="errors(form.errors.email)"
                    />
                </div>
                <FormKit
                    v-model="form.website"
                    type="text"
                    inputmode="url"
                    :label="$t('Website')"
                    name="website"
                    :errors="errors(form.errors.website)"
                />
                <FormKit
                    v-model="form.notes"
                    type="textarea"
                    :label="$t('Notes')"
                    name="notes"
                    :help="
                        $t(
                            'For example the customer code they ask for on the phone.',
                        )
                    "
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
