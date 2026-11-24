<script setup lang="ts">
/**
 * The index of the *Settings*: my account, mine only, and the settings that apply
 * to the whole family, and *The app* with the version and what is new in it. In the
 * wide layout the sections stand beside every page of the settings, so the index
 * opens *My account* in its place. The other items of the board (bills, subscription) come with their stages.
 * *Linked banks* is there only while the link to the bank is on for the family.
 */
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';
import { usePageMeta } from '@/composables/usePageMeta';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFeatures } from '@/composables/useFeatures';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import type { SettingsGroup, SettingsSection } from '@/lib/settingsSections';
import { settingsSections } from '@/lib/settingsSections';
import { edit } from '@/routes/account';

const {
    account,
    mainCategories,
    mainExpenseCategories,
    areaCount,
    hiddenAreas,
    bankCount,
    release,
    releasedOn,
} = defineProps<{
    /** My account. */
    account: App.Data.AccountData;
    /** How many main document categories there are. */
    mainCategories: number;
    /** How many main expense categories there are. */
    mainExpenseCategories: number;
    /** How many areas there are. */
    areaCount: number;
    /** How many areas the family hid. */
    hiddenAreas: number;
    /** How many banks the family linked. */
    bankCount: number;
    /** The version that runs (`dev` in development). */
    release: string;
    /** The day the version came out (`yyyy-mm-dd`), or `null` without notes for it. */
    releasedOn: string | null;
}>();

const { date } = useFormat();

const wide = useWideLayout();

/** The features of the family: *Linked banks* is there only while the link to the bank is on. */
const features = useFeatures();

/** The cards of the index, in order: mine (no title), the family and the app. */
const cards = computed(() => {
    const sections = settingsSections(features.value);
    const card = (
        key: SettingsGroup,
        title: string | undefined,
    ): {
        key: SettingsGroup;
        title: string | undefined;
        sections: SettingsSection[];
    } => ({
        key,
        title,
        sections: sections.filter((section) => section.group === key),
    });

    return [
        card('mine', undefined),
        card('family', trans('The family')),
        card('app', trans('The app')),
    ];
});

/** The props of the page the subtitles of the rows are written from. */
const subtitleProps = computed(() => ({
    account,
    mainCategories,
    mainExpenseCategories,
    areaCount,
    hiddenAreas,
    bankCount,
    features: features.value,
    release,
    releasedOn,
    date,
}));

// In the wide layout the index would only repeat the sections beside the pages:
// it gives its place in the history to My account, so Back does not come here.
watch(
    wide,
    (isWide) => {
        if (isWide) {
            router.visit(edit().url, { replace: true });
        }
    },
    { immediate: true },
);

const { title, head } = usePageMeta({
    info: 'settings',
    title: () => trans('Settings'),
});
</script>

<template>
    <Head :title="head" />

    <div v-if="!wide" class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="title"
            :subtitle="
                $t(
                    'They apply to the whole :family family; my account is yours only',
                    { family: account.family },
                )
            "
        />
        <FmCard v-for="card in cards" :key="card.key" :title="card.title" list>
            <FmRow
                v-for="section in card.sections"
                :key="section.key"
                :title="section.label"
                :subtitle="section.subtitle?.(subtitleProps) ?? undefined"
                :href="section.href"
            >
                <template #before>
                    <FmAvatar
                        v-if="section.key === 'account'"
                        :initials="account.initials"
                        :photo="account.photo"
                        :name="`${account.first_name} ${account.last_name}`"
                    />
                    <span
                        v-else
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon
                            :icon="section.phoneIcon ?? section.icon"
                            :size="22"
                        />
                    </span>
                </template>
            </FmRow>
        </FmCard>
    </div>
</template>
