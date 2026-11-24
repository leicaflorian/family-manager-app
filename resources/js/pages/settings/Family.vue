<script setup lang="ts">
/**
 * *Settings → Family name*: the name of the family, seen in the menu, in the
 * emails and in the subscribed calendar. Everyone sees it; an administrator
 * changes it and saves with *Save*. The address for the bills, made from the
 * name once, stays the same.
 */
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFeature } from '@/composables/useFeatures';
import { usePageMeta } from '@/composables/usePageMeta';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { update } from '@/routes/settings/family';

const { settings } = defineProps<{
    /** The name and whether who looks can change it. */
    settings: App.Data.FamilyNameSettingsData;
}>();

/** Whether the family has the mail for the bills: the help about its address shows only then. */
const mailOn = useFeature('mail');

const name = ref(settings.name);
const saving = ref(false);
/** The message of the server about the name, when it refused it. */
const nameError = ref<string | undefined>(undefined);

const changes = useUnsavedChanges(() => ({ name: name.value }));

// A save redirects here with the name the server kept: start from it
watch(
    () => settings.name,
    (saved) => {
        name.value = saved;
        changes.reset();
    },
);

/** Saves the name. */
function save(): void {
    saving.value = true;
    nameError.value = undefined;

    router.put(
        update.url(),
        { name: name.value },
        {
            preserveScroll: true,
            onSuccess: () => changes.reset(),
            onError: (problems) => {
                nameError.value = problems.name;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

useSaveShortcut(save, () => settings.can_edit && !saving.value);

const { title, head } = usePageMeta({
    info: 'settings-family',
    title: () => trans('Family name'),
    parent: 'settings',
});
</script>

<template>
    <Head :title="head" />

    <FmSettingsPage current="family">
        <template #title>
            <FmTitle
                :title="title"
                :subtitle="
                    $t(
                        'You see it in the menu, in the emails and in the calendar',
                    )
                "
            />
        </template>

        <FmCard>
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <FormKit
                    v-model="name"
                    type="text"
                    name="name"
                    :label="$t('Family name')"
                    :help="
                        mailOn
                            ? $t('The address for the bills stays the same')
                            : undefined
                    "
                    :readonly="!settings.can_edit"
                    :errors="errors(nameError)"
                />
                <div v-if="settings.can_edit" class="flex justify-end">
                    <FmButton type="submit" :disabled="saving">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </form>
        </FmCard>
    </FmSettingsPage>
</template>
