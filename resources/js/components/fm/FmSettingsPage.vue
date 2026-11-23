<script setup lang="ts">
/**
 * The frame of a page of the *Settings*: the title on top and the content under it.
 * In the wide layout *Settings* is the title on top, the sections of the settings
 * stand beside the content in the groups of the index (with the section of the page
 * marked), and the title of the page is the title of its section, above its content,
 * as the wide boards of the settings draw them; on the phone there are no sections
 * (the page has *Back* and the bottom bar) and the title of the page is the title.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { computed, provide } from 'vue';
import { useFeatures } from '@/composables/useFeatures';
import { useWideLayout } from '@/composables/useWideLayout';
import type { SettingsGroup, SettingsKey } from '@/lib/settingsSections';
import { settingsSections } from '@/lib/settingsSections';
import { SECTION_TITLE } from '@/lib/titles';
import { trans } from 'laravel-vue-i18n';
import FmIcon from './FmIcon.vue';

const { current } = defineProps<{
    /** The section the page is, marked in the list. */
    current: SettingsKey;
}>();

defineSlots<{
    /** The title of the page (`FmTitle`). */
    title(): unknown;
    /** The content of the page. */
    default(): unknown;
}>();

const wide = useWideLayout();

/** The features of the family: *Linked banks* is in the list only while the link to the bank is on. */
const features = useFeatures();

provide(SECTION_TITLE, wide);

/** The names of the groups of the index; mine has none. */
const GROUP_LABELS: Record<SettingsGroup, () => string | null> = {
    mine: () => null,
    family: () => trans('The family'),
    app: () => trans('The app'),
};

/** The sections of the settings in the groups of the index: mine first, without a name. */
const groups = computed(() => {
    const sections = settingsSections(features.value);

    return (['mine', 'family', 'app'] as const).map((group) => ({
        label: GROUP_LABELS[group](),
        sections: sections
            .filter((section) => section.group === group)
            .map((section) => ({
                ...section,
                // The version that runs, after the name of *What's new*
                note: section.key === 'news' ? __APP_BUILD__ : undefined,
            })),
    }));
});
</script>

<template>
    <div v-if="wide" class="flex flex-col gap-6">
        <!-- Not an FmTitle: the titles under this frame are those of the sections. -->
        <h1 class="m-0 text-title-mobile computer:text-title">
            {{ $t('Settings') }}
        </h1>
        <div class="flex flex-wrap items-start gap-6">
            <nav
                :aria-label="$t('Settings sections')"
                class="flex min-w-60 flex-[0_1_270px] flex-col gap-0.5 rounded-card bg-card p-2.5 shadow-card"
            >
                <div
                    v-for="group in groups"
                    :key="group.label ?? 'mine'"
                    :role="group.label ? 'group' : undefined"
                    :aria-label="group.label ?? undefined"
                    class="flex flex-col gap-0.5"
                >
                    <span
                        v-if="group.label"
                        aria-hidden="true"
                        class="block px-3 pt-3.5 pb-1 text-meta-strong text-ink-soft"
                        >{{ group.label }}</span
                    >
                    <Link
                        v-for="section in group.sections"
                        :key="section.key"
                        :href="section.href"
                        :aria-current="
                            section.key === current ? 'page' : undefined
                        "
                        class="flex min-h-touch items-center gap-3 rounded-md px-3 no-underline"
                        :class="
                            section.key === current
                                ? 'bg-action-soft text-label text-action-dark'
                                : 'text-body-sm text-ink hover:bg-canvas'
                        "
                    >
                        <FmIcon :icon="section.icon" :size="20" />
                        {{ section.label }}
                        <span
                            v-if="section.note"
                            class="ml-auto text-meta text-ink-soft"
                            >{{ section.note }}</span
                        >
                    </Link>
                </div>
            </nav>
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <slot name="title" />
                <slot />
            </div>
        </div>
    </div>
    <div v-else class="flex max-w-[720px] flex-col gap-5">
        <slot name="title" />
        <slot />
    </div>
</template>
