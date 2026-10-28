<script setup lang="ts">
/**
 * *A card*: the coloured top with its name and data, with *Edit* and the ⋯
 * menu (Delete, for an `admin`, after asking); then the notices, the dates,
 * the data of the card (the person, the organization and the card it names
 * open in a new tab), its documents (with *Upload* and *Link*, open to a
 * `member` too, and beside each the buttons of `FmCardDocumentActions`) and
 * the cards it is linked to, with the + that adds a new one already linked
 * (for an `admin`). *Link* opens the documents not yet linked, searched while
 * typing, 15 at a time with the next ones loaded at the end of the list.
 */
import { Head, router } from '@inertiajs/vue3';
import { Link2, Pencil, Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCardHeader from '@/components/fm/FmCardHeader.vue';
import FmCardDocumentActions from '@/components/fm/FmCardDocumentActions.vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmDateTile from '@/components/fm/FmDateTile.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmLinkDocumentSheet from '@/components/fm/FmLinkDocumentSheet.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmNewTabLink from '@/components/fm/FmNewTabLink.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { Action } from '@/lib/actions';
import { upload } from '@/routes';
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

/** The address of the card in the routes: its type and its number. */
const address = computed(() => ({ type: card.card.type, id: card.card.id }));

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
    <Head :title="card.card.label" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmBackLink
            :href="showArea.url(card.card.area)"
            :label="card.card.area_label"
        />

        <FmCardHeader :card="card.card" :heading="card.heading">
            <template v-if="actions.length > 0" #actions>
                <FmActions :actions="actions" />
            </template>
        </FmCardHeader>

        <FmNotice v-for="notice in card.notices" :key="notice" tone="warning">{{
            notice
        }}</FmNotice>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <div
                    v-if="card.dates.length > 0"
                    class="grid grid-cols-2 gap-3"
                >
                    <FmDateTile
                        v-for="date in card.dates"
                        :key="date.label"
                        :date="date"
                    />
                </div>

                <FmCard v-if="card.values.length > 0" :title="$t('Data')">
                    <dl
                        class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-[15px]"
                    >
                        <template
                            v-for="value in card.values"
                            :key="value.label"
                        >
                            <dt class="text-ink-soft">{{ value.label }}</dt>
                            <dd class="m-0 font-semibold break-words">
                                <FmNewTabLink
                                    v-if="value.href"
                                    class="hover:text-action"
                                    :href="value.href"
                                    :text="value.text"
                                />
                                <template v-else>{{ value.text }}</template>
                            </dd>
                        </template>
                    </dl>
                </FmCard>

                <FmCard
                    v-if="card.related.length > 0 || card.addable.length > 0"
                    :title="$t('Linked to')"
                    list
                >
                    <template v-if="card.addable.length > 0" #actions>
                        <FmMenu
                            :icon="Plus"
                            :label="$t('Add a linked card')"
                            small
                        >
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
                        class="m-0 px-4 py-3.5 text-[15px] text-ink-soft"
                    >
                        {{ $t('Nothing linked yet.') }}
                    </p>
                </FmCard>
            </div>

            <section class="flex min-w-0 flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="m-0 text-[17px] font-bold">
                        {{ $t('Documents') }}
                    </h2>
                    <div class="flex gap-4">
                        <FmButton
                            v-if="hasLinkable"
                            variant="text"
                            inline
                            :icon="Link2"
                            @click="linking = true"
                            >{{ $t('Link document') }}</FmButton
                        >
                        <FmButton
                            variant="text"
                            inline
                            :icon="Plus"
                            :href="
                                upload({
                                    query: {
                                        link: `${card.card.type}:${card.card.id}`,
                                    },
                                }).url
                            "
                            >{{ $t('Upload') }}</FmButton
                        >
                    </div>
                </div>
                <div
                    v-auto-animate
                    class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
                >
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
                        class="m-0 p-5 text-[15px] text-ink-soft"
                    >
                        {{ $t('No documents yet.') }}
                    </p>
                </div>
            </section>
        </div>

        <FmLinkDocumentSheet
            v-model:open="linking"
            :linkable="linkable"
            :linkable-next="linkableNext"
            :linkable-search="linkableSearch"
            @pick="link($event.id)"
        />
    </div>
</template>
