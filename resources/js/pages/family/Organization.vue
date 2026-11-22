<script setup lang="ts">
/**
 * *An organization*: the records that name it and its documents, ten at a
 * time (a partial reload adds the next page below, keeping the address) with
 * the link to the Archive filtered on it; on the side
 * its contacts (phone, website, email) and the notes, such as the customer
 * code. An `admin` edits and deletes it: its documents and records stay.
 */
import { Head } from '@inertiajs/vue3';
import { Globe, Mail, Pencil, Phone, StickyNote, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmOrganizationMark from '@/components/fm/FmOrganizationMark.vue';
import FmLinkedRecords from '@/components/fm/FmLinkedRecords.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmShowMore from '@/components/fm/FmShowMore.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useMorePages } from '@/composables/useMorePages';
import { websiteHref } from '@/lib/family';
import { showMoreLabel } from '@/lib/showMore';
import {
    index as documentsIndex,
    show as showDocument,
} from '@/routes/documents';
import type { Action } from '@/lib/actions';
import { destroy, edit, index } from '@/routes/organizations';
import { usePageTrail } from '@/composables/usePageTrail';
import { family } from '@/routes';

const {
    organization,
    links,
    documents,
    documentsNext = null,
    documentsTotal,
    pageSizes,
} = defineProps<{
    /** The organization. */
    organization: App.Data.OrganizationDetailData;
    /** The records that name it, by type. */
    links: App.Data.LinkGroupData[];
    /** The pages of its documents loaded so far, the most recent first. */
    documents: App.Data.DocumentData[];
    /** The number of the next page of `documents`, null at the last one. */
    documentsNext?: number | null;
    /** How many documents it has in all. */
    documentsTotal: number;
    /** How many documents a page holds, as the server sends them: what *Show more* promises. */
    pageSizes: { documents: number };
}>();

const { loading, loadMore } = useMorePages(['documents']);

/** The ways to reach it, as links. */
const contacts = computed(() =>
    [
        organization.phone && {
            icon: Phone,
            text: organization.phone,
            href: `tel:${organization.phone.replace(/[^\d+]/g, '')}`,
        },
        organization.website && {
            icon: Globe,
            text: organization.website,
            href: websiteHref(organization.website),
        },
        organization.email && {
            icon: Mail,
            text: organization.email,
            href: `mailto:${organization.email}`,
        },
    ].filter((contact) => !!contact),
);

/** *Edit* and *Delete*, for an `admin`. */
const actions = computed((): Action[] => [
    ...(organization.can_edit
        ? [
              {
                  label: trans('Edit'),
                  icon: Pencil,
                  variant: 'soft',
                  href: edit.url(organization.id),
              } satisfies Action,
          ]
        : []),
    ...(organization.can_delete
        ? [
              {
                  label: trans('Delete'),
                  icon: Trash2,
                  variant: 'danger',
                  confirm: {
                      url: destroy.url(organization.id),
                      question: trans('Delete :name?', {
                          name: organization.name,
                      }),
                      text: trans(
                          'Its documents and records stay, without the organization.',
                      ),
                      confirmLabel: trans('Delete the organization'),
                  },
              } satisfies Action,
          ]
        : []),
]);

usePageTrail(() => [
    { label: trans('Family'), href: family().url },
    { label: trans('Organizations'), href: index().url },
    { label: organization.name },
]);
</script>

<template>
    <Head :title="organization.name" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle
            :title="organization.name"
            :subtitle="
                organization.type_labels.length > 0
                    ? organization.type_labels.join(', ')
                    : undefined
            "
        >
            <template #leading>
                <FmOrganizationMark
                    :types="organization.types"
                    :logo="organization.logo"
                    :name="organization.name"
                    :icon-size="28"
                    class="size-14 rounded-card bg-card shadow-card wide:size-16"
                />
            </template>
            <template #actions>
                <FmActions :actions="actions" />
            </template>
        </FmTitle>

        <div
            class="grid gap-5 wide:grid-cols-[minmax(0,1fr)_360px] wide:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <FmLinkedRecords :groups="links" />
                <FmCard
                    :title="$t('Documents')"
                    :count="documentsTotal"
                    :list="documents.length > 0"
                >
                    <template v-if="documents.length > 0" #actions>
                        <FmSectionLink
                            :href="
                                documentsIndex({
                                    query: { organization: organization.id },
                                }).url
                            "
                            >{{ $t('Open in the Archive') }}</FmSectionLink
                        >
                    </template>
                    <FmDocumentRow
                        v-for="document in documents"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                    />
                    <FmShowMore
                        v-if="documentsNext"
                        :label="
                            showMoreLabel(
                                documentsTotal - documents.length,
                                pageSizes.documents,
                                'documents',
                            )
                        "
                        :loading="loading.documents"
                        @more="loadMore('documents', documentsNext)"
                    />
                    <p
                        v-if="documents.length === 0"
                        class="m-0 text-body-sm text-ink-soft"
                    >
                        {{ $t('No documents yet.') }}
                    </p>
                </FmCard>
            </div>

            <FmCard
                :title="$t('Contacts')"
                :list="contacts.length > 0 || !!organization.notes"
            >
                <a
                    v-for="contact in contacts"
                    :key="contact.href"
                    :href="contact.href"
                    :target="contact.icon === Globe ? '_blank' : undefined"
                    :rel="contact.icon === Globe ? 'noopener' : undefined"
                    class="flex items-center gap-3 px-4 py-3 text-inherit no-underline"
                >
                    <FmIcon
                        :icon="contact.icon"
                        :size="20"
                        class="text-action"
                    />
                    <span class="min-w-0 text-row break-all">{{
                        contact.text
                    }}</span>
                </a>
                <p
                    v-if="organization.notes"
                    class="m-0 flex gap-1.5 px-4 py-3 text-body-sm whitespace-pre-line text-ink-soft"
                >
                    <FmIcon
                        :icon="StickyNote"
                        :size="16"
                        class="mt-0.5 shrink-0 text-ink-soft"
                    />
                    {{ organization.notes }}
                </p>
                <p
                    v-if="contacts.length === 0 && !organization.notes"
                    class="m-0 text-body-sm text-ink-soft"
                >
                    {{ $t('No contacts yet.') }}
                </p>
            </FmCard>
        </div>
    </div>
</template>
