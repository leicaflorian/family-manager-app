<script setup lang="ts">
/**
 * *Settings → What's new*: what changes in the app, version by version, newest
 * first, with the version that runs marked. The notes come from
 * `resources/release/notes.json`, in the language of the person.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { usePageMeta } from '@/composables/usePageMeta';

const { release, notes } = defineProps<{
    /** The version that runs (`dev` in development). */
    release: string;
    /** The versions with their notes, newest first. */
    notes: App.Data.ReleaseNoteData[];
}>();

const { date } = useFormat();

const { title, head } = usePageMeta({
    title: () => trans("What's new"),
    parent: 'settings',
});
</script>

<template>
    <Head :title="head" />

    <FmSettingsPage current="news">
        <template #title>
            <FmTitle
                :title="title"
                :subtitle="$t('What changes in the app, version by version')"
            />
        </template>
        <section
            v-for="note in notes"
            :key="note.version"
            :aria-label="$t('Version :version', { version: note.version })"
            class="flex flex-col gap-2.5 rounded-card-lg bg-card px-5 py-4.5 shadow-card"
        >
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="m-0 text-section">
                    {{ $t('Version :version', { version: note.version }) }}
                </h2>
                <span
                    v-if="note.version === release"
                    class="inline-flex h-6 items-center rounded-tag bg-action-soft px-2.5 text-caption text-action-dark"
                    >{{ $t('Your version') }}</span
                >
                <span class="ml-auto text-meta text-ink-soft">{{
                    date(note.date)
                }}</span>
            </div>
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-body-sm">
                <li v-for="(line, index) in note.notes" :key="index">
                    {{ line }}
                </li>
            </ul>
        </section>
        <p v-if="notes.length === 0" class="m-0 text-body-sm text-ink-soft">
            {{ $t('No news yet.') }}
        </p>
    </FmSettingsPage>
</template>
