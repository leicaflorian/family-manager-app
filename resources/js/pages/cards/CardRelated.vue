<script setup lang="ts">
/**
 * *Links of a card*: every card linked to this one, the ones with a date coming first, then the others from the
 * most recent, the closed ones at the bottom, with *Add* to add a new one already linked. The card itself shows
 * only the first five open ones.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Plus } from '@lucide/vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { show as showArea } from '@/routes/areas';

const { card, back } = defineProps<{
    /** The card the links belong to. */
    card: App.Data.CardData;
    /** Every linked card, in the order they are shown. */
    related: App.Data.CardData[];
    /** The cards that can be added already linked to this one. */
    addable: App.Data.CardAddData[];
    /** The address of the card, where the trail and Back lead. */
    back: string;
}>();

const { title, head } = usePageMeta({
    info: 'card-related',
    title: () => trans('Links of :name', { name: card.label }),
    head: () => trans('Links of :name', { name: card.label }),
    parent: () => [
        'areas',
        { label: card.area_label, href: showArea.url(card.area) },
        { label: card.label, href: back },
    ],
});
</script>

<template>
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle :title="title">
            <template #overline>
                <span class="text-label text-ink-soft">{{
                    `${card.area_label} · ${card.type_label}`
                }}</span>
            </template>
            <template v-if="addable.length > 0" #actions>
                <FmMenu :icon="Plus" :label="$t('Add')" text>
                    <FmMenuItem
                        v-for="entry in addable"
                        :key="entry.type"
                        :href="entry.href"
                        >{{ entry.label }}</FmMenuItem
                    >
                </FmMenu>
            </template>
        </FmTitle>

        <div
            data-testid="related"
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-card shadow-card"
        >
            <FmCardRow
                v-for="row in related"
                :key="`${row.type}-${row.id}`"
                :card="row"
            />
            <p
                v-if="related.length === 0"
                class="m-0 px-4 py-3.5 text-body-sm text-ink-soft"
            >
                {{ $t('Nothing linked yet.') }}
            </p>
        </div>

        <p class="m-0 text-meta text-ink-soft">
            {{
                $t(
                    'First the cards with a date coming, then the others, the most recent first. The closed ones (cancelled, sold, expired) are at the bottom.',
                )
            }}
        </p>
    </div>
</template>
