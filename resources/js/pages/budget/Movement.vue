<script setup lang="ts">
/**
 * A movement on its own page, on every layout: the form (`FmMovementPanel`) and
 * the box of its documents (`FmCardFiles`), which sit side by side on the
 * computer (documents on the left, as the card form) and one under the other on
 * the phone (the form first). The files, the documents chosen in *Link* and
 * those marked to unlink are sent with the movement. Without a movement it is
 * the page of *New expense or income*, which can start from a planned expense
 * or income, or from a card (*Add expense*): then the link leads back to the
 * card. The page leads back to `back` when the server gives it, else to the
 * card, else to the month.
 */
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmCardFiles from '@/components/fm/FmCardFiles.vue';
import FmMovementPanel from '@/components/fm/FmMovementPanel.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { MovementFormOptions, MovementStart } from '@/lib/budget';
import { usePageTrail } from '@/composables/usePageTrail';
import { useWideLayout } from '@/composables/useWideLayout';
import { movementsUrl } from '@/lib/budget';
import { budget } from '@/routes';
import { show as showCard } from '@/routes/cards';

const {
    movement,
    start,
    options,
    back = null,
    linkable = [],
    linkableNext = null,
    linkableSearch = '',
    hasLinkable = false,
    canDeleteDocuments = false,
} = defineProps<{
    /** The movement, or null for a new expense or income. */
    movement: App.Data.MovementData | null;
    /** What a new movement starts from. */
    start: MovementStart;
    /** The due dates that the movement can settle. */
    occurrences: App.Data.OptionData[];
    /** The choices of the form. */
    options: MovementFormOptions;
    /** The page to go back to (a local path), when the person came from one. */
    back?: string | null;
    /** The pages loaded so far of the documents that can be linked, for *Link*. */
    linkable?: App.Data.DocumentData[];
    /** The number of the next page of `linkable`, null at the last one. */
    linkableNext?: number | null;
    /** The search that `linkable` answers. */
    linkableSearch?: string;
    /** At least one document of the family can be linked. */
    hasLinkable?: boolean;
    /** Whether whoever edits may delete the linked documents. */
    canDeleteDocuments?: boolean;
}>();

/** The card a new expense comes from, with its name, or null. */
const fromCard = computed((): { href: string; label: string } | null => {
    const [type, id] = start.card?.split(':') ?? [];

    if (movement || !type || !id) {
        return null;
    }

    return {
        href: showCard.url({ type, id: Number(id) }),
        label:
            options.cards.find((card) => card.value === start.card)?.label ??
            trans('Card'),
    };
});

/** Where the link back and *Cancel* go: what the server gave, else the card a new expense comes from, else the month. */
const backHref = computed(
    () =>
        back ??
        fromCard.value?.href ??
        movementsUrl({ month: (movement?.date ?? start.date).slice(0, 7) }),
);

/** The title of the page. */
const title = computed(() => movement?.title ?? trans('New expense or income'));

usePageTrail(() => [
    { label: trans('Budget'), href: budget().url },
    { label: title.value },
]);

/** The page is wide: the title is the page's, and the form has none. */
const wide = useWideLayout();

/** The files of new documents, sent with the movement. */
const files = ref<File[]>([]);

/** The documents chosen in *Link*, linked when the movement is saved. */
const link = ref<App.Data.DocumentData[]>([]);

/** The ids of the linked documents to unlink when the movement is saved. */
const unlink = ref<number[]>([]);

/** The form, to read the messages about the files it was refused. */
const panel = ref<InstanceType<typeof FmMovementPanel> | null>(null);

/** Goes back to the card or to the month. */
function close(): void {
    router.get(backHref.value);
}
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle v-if="wide" :title="title" />
        <div
            class="flex flex-col gap-6 wide:grid wide:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] wide:items-start"
        >
            <div class="order-2 min-w-0 wide:order-1">
                <FmCardFiles
                    v-model:files="files"
                    v-model:link="link"
                    v-model:unlink="unlink"
                    :documents="movement?.documents ?? []"
                    :can-delete="canDeleteDocuments"
                    :errors="panel?.fileErrors ?? []"
                    :linkable="linkable"
                    :linkable-next="linkableNext"
                    :linkable-search="linkableSearch"
                    :has-linkable="hasLinkable"
                    :readonly="movement !== null && !movement.can_edit"
                />
            </div>
            <div class="order-1 min-w-0 wide:order-2">
                <FmMovementPanel
                    ref="panel"
                    v-model:files="files"
                    v-model:link="link"
                    v-model:unlink="unlink"
                    :movement="movement"
                    :options="options"
                    :start="start"
                    :occurrences="occurrences"
                    :back="back"
                    @close="close"
                />
            </div>
        </div>
    </div>
</template>
