<script setup lang="ts">
/**
 * *Components*, the local catalogue of the canvas (`/dev/components`): on the
 * left the components under the title of their group, with a search by name;
 * on the right the chosen one, with its description, its props, the files
 * that use it and its cases mounted for real. The cases are shown at full
 * width or, in a frame 390 px wide, as on a phone. It has no layout of the
 * app: it is a page for whoever writes the app, not for the family.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { MousePointerClick, SearchX } from '@lucide/vue';
import { computed, ref } from 'vue';
import DevCases from '@/components/dev/DevCases.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFold from '@/components/fm/FmFold.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import catalog from '@/catalog/catalog.json';
import type { Group } from '@/catalog/groups';
import { groupLabel, groups } from '@/catalog/groups';
import type { CatalogEntry } from '@/catalog/meta';
import { components } from '@/routes/dev';
import { show } from '@/routes/dev/components';

const { selected } = defineProps<{
    /** The name of the chosen component, as `FmRow`; `null` before choosing. */
    selected: string | null;
}>();

/** A group of the left column: its title and its components. */
type Section = { key: string; label: string; entries: CatalogEntry[] };

/** How the cases are shown: at the width of the page, or of a phone. */
type Width = 'full' | 'phone';

const entries = catalog as CatalogEntry[];

/** The text in the search box. */
const query = ref('');

/** How the cases are shown now. */
const width = ref<Width>('full');

/** The component chosen, when it is in the catalogue. */
const entry = computed((): CatalogEntry | undefined =>
    entries.find((component) => component.name === selected),
);

/** The components whose name holds the search, whatever the case. */
const found = computed((): CatalogEntry[] => {
    const text = query.value.trim().toLowerCase();

    return entries
        .filter((component) => component.name.toLowerCase().includes(text))
        .sort((a, b) => a.name.localeCompare(b.name));
});

/** The groups with at least one component found, then *Ungrouped* while some have no known group. */
const sections = computed((): Section[] => {
    const known: readonly string[] = groups;
    const grouped = groups.map((group: Group): Section => ({
        key: group,
        label: groupLabel(group),
        entries: found.value.filter((component) => component.group === group),
    }));
    const ungrouped: Section = {
        key: 'ungrouped',
        label: trans('Ungrouped'),
        entries: found.value.filter(
            (component) =>
                component.group === null || !known.includes(component.group),
        ),
    };

    return [...grouped, ungrouped].filter(
        (section) => section.entries.length > 0,
    );
});
</script>

<template>
    <Head :title="$t('Components')" />
    <div class="min-h-dvh bg-canvas text-ink">
        <div
            class="mx-auto flex max-w-[1440px] flex-col gap-6 px-4 py-6 lg:flex-row lg:items-start lg:px-8 lg:py-8"
        >
            <div
                class="flex w-full shrink-0 flex-col gap-4 lg:sticky lg:top-8 lg:max-h-[calc(100dvh-4rem)] lg:w-72 lg:overflow-y-auto"
            >
                <FmTitle :title="$t('Components')" />
                <FmSearchField
                    v-model="query"
                    :label="$t('Search a component')"
                />
                <nav :aria-label="$t('Components')" class="flex flex-col gap-4">
                    <FmEmpty
                        v-if="!sections.length"
                        :icon="SearchX"
                        :title="
                            $t('No component matches «:query».', {
                                query: query.trim(),
                            })
                        "
                    />
                    <FmCard
                        v-for="section in sections"
                        :key="section.key"
                        :title="section.label"
                        list
                    >
                        <FmRow
                            v-for="component in section.entries"
                            :key="component.name"
                            :title="component.name"
                            :href="
                                components.url({
                                    query: { component: component.name },
                                })
                            "
                            :current="component.name === selected"
                            :arrow="false"
                            preserve-state
                            preserve-scroll
                        />
                    </FmCard>
                </nav>
            </div>

            <main class="flex min-w-0 grow flex-col gap-6">
                <FmEmpty
                    v-if="!entry"
                    :icon="MousePointerClick"
                    :title="$t('Choose a component on the left.')"
                />
                <template v-else>
                    <div class="flex flex-col gap-2">
                        <h2
                            class="m-0 text-[28px] leading-tight font-extrabold"
                        >
                            {{ entry.name }}
                        </h2>
                        <code class="text-sm break-all text-ink-soft">{{
                            entry.path
                        }}</code>
                        <p
                            v-if="entry.description"
                            class="m-0 text-base leading-normal whitespace-pre-line"
                        >
                            {{ entry.description }}
                        </p>
                    </div>

                    <FmCard v-if="entry.props.length" :title="$t('Props')">
                        <div class="-mx-5 overflow-x-auto px-5">
                            <table
                                :aria-label="$t('Props')"
                                class="w-full border-collapse text-left text-sm"
                            >
                                <thead>
                                    <tr class="text-ink-soft">
                                        <th class="py-2 pr-4 font-semibold">
                                            {{ $t('Name') }}
                                        </th>
                                        <th class="py-2 pr-4 font-semibold">
                                            {{ $t('Type') }}
                                        </th>
                                        <th class="py-2 pr-4 font-semibold">
                                            {{ $t('Required') }}
                                        </th>
                                        <th class="py-2 pr-4 font-semibold">
                                            {{ $t('Default') }}
                                        </th>
                                        <th class="py-2 font-semibold">
                                            {{ $t('Description') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-divider">
                                    <tr
                                        v-for="prop in entry.props"
                                        :key="prop.name"
                                        class="align-top"
                                    >
                                        <td class="py-2 pr-4">
                                            <code class="font-semibold">{{
                                                prop.name
                                            }}</code>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <code class="break-words">{{
                                                prop.type
                                            }}</code>
                                        </td>
                                        <td class="py-2 pr-4">
                                            {{
                                                prop.required
                                                    ? $t('Yes')
                                                    : $t('No')
                                            }}
                                        </td>
                                        <td class="py-2 pr-4">
                                            <code>{{
                                                prop.default ?? '—'
                                            }}</code>
                                        </td>
                                        <td class="min-w-48 py-2">
                                            {{ prop.description }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </FmCard>

                    <FmFold
                        v-if="entry.usedBy.length"
                        :label="
                            $t('Used by (:count)', {
                                count: String(entry.usedBy.length),
                            })
                        "
                    >
                        <FmRow
                            v-for="file in entry.usedBy"
                            :key="file"
                            :title="file"
                        />
                    </FmFold>
                    <FmCard v-else :title="$t('Used by')">
                        <p class="m-0 text-base text-ink-soft">
                            {{ $t('Not used yet.') }}
                        </p>
                    </FmCard>

                    <div class="flex flex-col gap-4">
                        <FormKit
                            v-model="width"
                            type="togglebuttons"
                            enforced
                            :label="$t('Width')"
                            name="width"
                            :options="[
                                { label: $t('Full width'), value: 'full' },
                                { label: $t('Phone width'), value: 'phone' },
                            ]"
                        />
                        <iframe
                            v-if="width === 'phone'"
                            :src="show.url(entry.name)"
                            :title="
                                $t(':name at phone width', {
                                    name: entry.name,
                                })
                            "
                            class="h-[760px] w-[390px] max-w-full self-center rounded-card-lg border border-edge bg-canvas"
                        />
                        <DevCases v-else :key="entry.name" :name="entry.name" />
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
