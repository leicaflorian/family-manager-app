<script setup lang="ts">
/**
 * The lines of the box *Data* of a page: the label on the left, the value on
 * the right. A value that is a person, an organization or a card is a link;
 * `newTab` opens it in a new tab, so the page it comes from stays open.
 *
 * @group rows
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import FmIcon from './FmIcon.vue';
import FmNewTabLink from './FmNewTabLink.vue';

/** A line: the value of a card, with an icon to show before the text when it needs one (the bell of a reminder). */
export type ValueLine = App.Data.CardValueData & {
    /** The icon before the text. */
    icon?: LucideIcon;
};

const { values, newTab = false } = defineProps<{
    /** The lines. */
    values: ValueLine[];
    /** A link opens in a new tab. */
    newTab?: boolean;
}>();
</script>

<template>
    <dl
        class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-body-sm"
    >
        <template v-for="value in values" :key="value.label">
            <dt class="text-ink-soft">{{ value.label }}</dt>
            <dd class="m-0 font-semibold break-words">
                <template v-if="value.href">
                    <FmNewTabLink
                        v-if="newTab"
                        class="hover:text-action"
                        :href="value.href"
                        :text="value.text"
                    />
                    <Link
                        v-else
                        :href="value.href"
                        class="underline-offset-2 hover:text-action hover:underline"
                        >{{ value.text }}</Link
                    >
                </template>
                <span
                    v-else-if="value.icon"
                    class="inline-flex items-start gap-1.5"
                >
                    <FmIcon
                        class="mt-0.5 shrink-0 text-ink-soft"
                        :icon="value.icon"
                        :size="14"
                    />
                    <span>{{ value.text }}</span>
                </span>
                <template v-else>{{ value.text }}</template>
            </dd>
        </template>
    </dl>
</template>
