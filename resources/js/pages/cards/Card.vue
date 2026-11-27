<script setup lang="ts">
/**
 * *A card*: the coloured top with its name and data, with *Edit* and the ⋯
 * menu (Delete, for an `admin`, after asking); then the notices (the deadline
 * near or past, the missing planned expense, the documents that expire or are
 * missing, each with its action if it has one); on a vehicle the section
 * *Deadlines* (each planned expense with its next due date and amount, the
 * dates of the card still without their planned expense, with *Add the
 * expense*, and the missing ones last, with *Add the date*), and its status
 * first in the data; the
 * payments of the card around its dates and data (`FmCardBudget`: the next
 * due date, the figures, the due dates to pay), the data of the card (the
 * person, the organization and the card it names open in a new tab) and the
 * cards it is linked to, with the + that adds a new one already linked (for an
 * `admin`); beside them its first 5 documents and, with more, *See all the documents* (with *Upload*, which opens
 * the panel with the zone for the files, and *Link*, open to a `member` too,
 * and beside each the buttons of `FmCardDocumentActions`), and under the
 * documents the planned expenses and the payments (`FmCardPayments`). *Link* opens the documents not yet linked, searched while
 * typing, 15 at a time with the next ones loaded at the end of the list.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChartColumn,
    ChevronRight,
    Droplet,
    Flame,
    Link2,
    Pencil,
    Plus,
    Trash2,
    Zap,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmAmount from '@/components/fm/FmAmount.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCardBudget from '@/components/fm/FmCardBudget.vue';
import FmCardHeader from '@/components/fm/FmCardHeader.vue';
import FmCardPayments from '@/components/fm/FmCardPayments.vue';
import FmCardDocumentActions from '@/components/fm/FmCardDocumentActions.vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmDateTile from '@/components/fm/FmDateTile.vue';
import FmNumberTile from '@/components/fm/FmNumberTile.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmLinkDocumentSheet from '@/components/fm/FmLinkDocumentSheet.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmUploadSheet from '@/components/fm/FmUploadSheet.vue';
import FmValueList from '@/components/fm/FmValueList.vue';
import { useFormat } from '@/composables/useFormat';
import { usePageMeta } from '@/composables/usePageMeta';
import type { Action } from '@/lib/actions';
import {
    daysFromToday,
    formatQuantity,
    relativeDays,
    unitLabel,
} from '@/lib/format';
import { show as showArea } from '@/routes/areas';
import { destroy, edit } from '@/routes/cards';
import { store as linkDocument } from '@/routes/cards/documents';
import { show as showDocument } from '@/routes/documents';

const {
    card,
    linkable,
    linkableNext = null,
    linkableSearch = '',
    hasLinkable = false,
} = defineProps<{
    /** The card with its dates, data, documents and linked cards. */
    card: App.Data.CardDetailData;
    /** The pages loaded so far of the documents not yet linked to the card that match the search. */
    linkable: App.Data.DocumentData[];
    /** The number of the next page of `linkable`, null at the last one. */
    linkableNext?: number | null;
    /** The search that `linkable` answers. */
    linkableSearch?: string;
    /** At least one document of the family is not yet linked to the card. */
    hasLinkable?: boolean;
}>();

/** The panel with the documents that can be linked is open. */
const linking = ref(false);

/** The panel with the zone for the files to upload is open. */
const uploading = ref(false);

/** Writes the amounts and the days. */
const { date, euro } = useFormat();

/**
 * The line under a deadline: the day, how far it is and how often it repeats
 * (or its policy); for a missing date, that it is missing.
 *
 * @param deadline - the row of the section *Deadlines*
 */
function deadlineLine(deadline: App.Data.CardDeadlineData): string {
    if (deadline.date === null) {
        return trans('The date of the next deadline is missing');
    }

    return [date(deadline.date), relativeDays(deadline.date), deadline.detail]
        .filter((part) => part)
        .join(' · ');
}

/**
 * Where a deadline leads: its planned expense (or its policy), else the form
 * that adds what is missing; nowhere for a plain date.
 *
 * @param deadline - the row of the section *Deadlines*
 */
function deadlineHref(deadline: App.Data.CardDeadlineData): string | undefined {
    return (
        deadline.href ??
        deadline.add_expense_href ??
        deadline.add_date_href ??
        undefined
    );
}

/** The icon of the unit of the consumption: a bolt for kWh, a flame for gas, a drop for water. */
const consumptionIcon = computed(() =>
    card.consumption?.unit === 'kwh'
        ? Zap
        : card.consumption?.unit === 'smc'
          ? Flame
          : Droplet,
);

/** The last 12 months in one line, like `2.711 kWh · 0,31 € a kWh`. */
const consumptionText = computed((): string => {
    const consumption = card.consumption;

    if (!consumption) {
        return '';
    }

    const quantity = formatQuantity(consumption.quantity, consumption.unit);

    return consumption.price === null
        ? quantity
        : `${quantity} · ${trans(':price per :unit', {
              price: euro(consumption.price),
              unit: unitLabel(consumption.unit),
          })}`;
});

/** The address of the card in the routes: its type and its number. */
const address = computed(() => ({ type: card.card.type, id: card.card.id }));

const { head } = usePageMeta({
    info: 'card',
    title: () => card.card.label,
    parent: () => [
        'areas',
        { label: card.card.area_label, href: showArea.url(card.card.area) },
    ],
});

/** *Edit* beside the title, *Delete* in the ⋯ menu. */
const actions = computed((): Action[] => [
    ...(card.can_edit
        ? [
              {
                  label: trans('Edit'),
                  icon: Pencil,
                  variant: 'soft',
                  href: edit.url(address.value),
              } satisfies Action,
          ]
        : []),
    ...(card.can_delete
        ? [
              {
                  label: trans('Delete'),
                  icon: Trash2,
                  variant: 'danger',
                  menuOnly: true,
                  confirm: {
                      url: destroy.url(address.value),
                      question: trans('Delete this card?'),
                      text: trans('The documents stay, only the card goes.'),
                      confirmLabel: trans('Delete the card'),
                  },
              } satisfies Action,
          ]
        : []),
]);

/**
 * Links an already uploaded document to the card.
 *
 * @param id - the id of the document
 */
function link(id: number): void {
    router.post(
        linkDocument.url(address.value),
        { document_id: id },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmCardHeader :card="card.card" :heading="card.heading">
            <template v-if="actions.length > 0" #actions>
                <FmActions :actions="actions" />
            </template>
        </FmCardHeader>

        <FmNotice
            v-for="notice in card.notices"
            :key="notice.key"
            :tone="notice.tone"
            :title="notice.title || undefined"
        >
            {{ notice.text }}
            <template v-if="notice.action_label && notice.action_href" #action>
                <Link :href="notice.action_href" class="no-underline">{{
                    notice.action_label
                }}</Link>
            </template>
        </FmNotice>

        <div
            class="grid gap-5 wide:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] wide:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <FmCard
                    v-if="card.deadlines && card.deadlines.length > 0"
                    :title="$t('Deadlines')"
                    list
                >
                    <FmRow
                        v-for="(deadline, index) in card.deadlines"
                        :key="`${deadline.label}-${index}`"
                        :title="deadline.label"
                        :subtitle="deadlineLine(deadline)"
                        :tone="
                            deadline.date !== null &&
                            daysFromToday(deadline.date) < 0
                                ? 'overdue'
                                : 'normal'
                        "
                        :href="deadlineHref(deadline)"
                        :arrow="false"
                    >
                        <template
                            v-if="
                                deadline.amount !== null ||
                                deadline.add_expense_href ||
                                deadline.add_date_href
                            "
                            #after
                        >
                            <FmAmount
                                v-if="deadline.amount !== null"
                                :amount="deadline.amount"
                                :estimated="deadline.estimated"
                            />
                            <span
                                v-else-if="deadline.add_expense_href"
                                class="inline-flex items-center gap-1 rounded-tag bg-warning-soft px-2 py-1 text-meta-strong text-warning"
                                ><FmIcon :icon="Plus" :size="14" />{{
                                    $t('Add the expense')
                                }}</span
                            >
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-tag bg-action-soft px-2 py-1 text-meta-strong text-action"
                                ><FmIcon :icon="Plus" :size="14" />{{
                                    $t('Add the date')
                                }}</span
                            >
                        </template>
                    </FmRow>
                </FmCard>

                <FmCardBudget :budget="card.budget" :card="address">
                    <template
                        v-if="card.dates.length > 0 || card.status"
                        #tiles
                    >
                        <FmDateTile
                            v-for="date in card.dates"
                            :key="date.label"
                            :date="date"
                            inset
                        />
                        <FmNumberTile
                            v-if="card.status"
                            :number="card.status"
                            inset
                        />
                    </template>
                    <FmCard v-if="card.values.length > 0" :title="$t('Data')">
                        <FmValueList :values="card.values" new-tab />
                    </FmCard>
                </FmCardBudget>

                <div
                    v-if="
                        card.related.length > 0 ||
                        card.addable.length > 0 ||
                        card.related_more
                    "
                    class="flex flex-col gap-2"
                >
                    <FmCard :title="$t('Linked to')" list>
                        <template v-if="card.addable.length > 0" #actions>
                            <FmMenu :icon="Plus" :label="$t('Add')" text>
                                <FmMenuItem
                                    v-for="entry in card.addable"
                                    :key="entry.type"
                                    :href="entry.href"
                                    >{{ entry.label }}</FmMenuItem
                                >
                            </FmMenu>
                        </template>
                        <FmCardRow
                            v-for="related in card.related"
                            :key="`${related.type}-${related.id}`"
                            :card="related"
                        />
                        <p
                            v-if="card.related.length === 0"
                            class="m-0 px-4 py-3.5 text-body-sm text-ink-soft"
                        >
                            {{ $t('Nothing linked yet.') }}
                        </p>
                    </FmCard>
                    <Link
                        v-if="card.related_more"
                        :href="card.related_more.href"
                        class="px-1 text-label text-action underline underline-offset-2"
                        >{{
                            $tChoice(
                                '{1} See the link (:count)|[2,*] See all the links (:count)',
                                card.related_more.count,
                            )
                        }}</Link
                    >
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-2">
                <FmCard :title="$t('Documents')" list>
                    <template #actions>
                        <FmSectionLink
                            v-if="hasLinkable"
                            :icon="Link2"
                            @click="linking = true"
                            >{{ $t('Link document') }}</FmSectionLink
                        >
                        <FmSectionLink :icon="Plus" @click="uploading = true">{{
                            $t('Upload')
                        }}</FmSectionLink>
                    </template>
                    <div
                        v-for="document in card.documents"
                        :key="document.id"
                        class="flex items-center"
                    >
                        <FmDocumentRow
                            class="min-w-0 grow"
                            :document="document"
                            :href="showDocument.url(document.id)"
                        />
                        <FmCardDocumentActions
                            :document="document"
                            :card="address"
                            :can-delete="card.can_delete_documents"
                        />
                    </div>
                    <p
                        v-if="card.documents.length === 0"
                        class="m-0 p-5 text-body-sm text-ink-soft"
                    >
                        {{ $t('No documents yet.') }}
                    </p>
                </FmCard>
                <Link
                    v-if="card.documents_more"
                    :href="card.documents_more"
                    class="px-1 text-label text-action underline underline-offset-2"
                    >{{ $t('See all the documents') }}</Link
                >

                <div
                    v-if="
                        card.budget &&
                        (card.budget.planned_label ||
                            card.budget.payments_label)
                    "
                    class="mt-3 flex flex-col gap-5"
                >
                    <FmCardPayments :budget="card.budget" :card="address" />
                </div>

                <FmCard
                    v-if="card.consumption"
                    class="mt-3"
                    :title="$t('Usage')"
                    list
                >
                    <template #actions>
                        <FmSectionLink
                            :href="card.consumption.href"
                            :icon="ChartColumn"
                            >{{ $t('See the consumption') }}</FmSectionLink
                        >
                    </template>
                    <Link
                        :href="card.consumption.href"
                        class="flex items-center gap-3 px-4 py-3 text-ink no-underline"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-card bg-home-soft text-home-text"
                        >
                            <FmIcon :icon="consumptionIcon" :size="20" />
                        </span>
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-meta text-ink-soft">{{
                                $t('Last 12 months')
                            }}</span>
                            <span class="text-row">{{ consumptionText }}</span>
                        </span>
                        <FmIcon
                            :icon="ChevronRight"
                            :size="20"
                            class="shrink-0 text-ink-faint"
                        />
                    </Link>
                </FmCard>
            </div>
        </div>

        <FmUploadSheet v-model:open="uploading" :link="address" />

        <FmLinkDocumentSheet
            v-model:open="linking"
            :linkable="linkable"
            :linkable-next="linkableNext"
            :linkable-search="linkableSearch"
            @pick="link($event.id)"
        />
    </div>
</template>
