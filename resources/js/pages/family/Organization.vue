<script setup lang="ts">
/**
 * *An organization*: the records that name it and its documents; on the side
 * its contacts (phone, website, email) and the notes, such as the customer
 * code. An `admin` edits and deletes it: its documents and records stay.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    Globe,
    Mail,
    Pencil,
    Phone,
    StickyNote,
} from '@lucide/vue';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmOrganizationMark from '@/components/fm/FmOrganizationMark.vue';
import FmLinkedRecords from '@/components/fm/FmLinkedRecords.vue';
import { websiteHref } from '@/lib/family';
import { show as showDocument } from '@/routes/documents';
import { destroy, edit, index } from '@/routes/organizations';

const { organization, links, documents } = defineProps<{
    /** The organization. */
    organization: App.Data.OrganizationDetailData;
    /** The records that name it, by type. */
    links: App.Data.LinkGroupData[];
    /** Its documents, the most recent first. */
    documents: App.Data.DocumentData[];
}>();

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
</script>

<template>
    <Head :title="organization.name" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <Link
            :href="index().url"
            class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />
            {{ $t('Organizations') }}
        </Link>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <FmOrganizationMark
                    :types="organization.types"
                    :logo="organization.logo"
                    :name="organization.name"
                    :icon-size="28"
                    class="size-14 rounded-2xl bg-white lg:size-16"
                />
                <div class="flex flex-col gap-1">
                    <h1 class="m-0 text-[26px] font-extrabold lg:text-[32px]">
                        {{ organization.name }}
                    </h1>
                    <span
                        v-if="organization.type_labels.length > 0"
                        class="text-[15px] text-ink-soft"
                        >{{ organization.type_labels.join(', ') }}</span
                    >
                </div>
            </div>
            <div class="flex flex-wrap gap-2.5">
                <FmButton
                    v-if="organization.can_edit"
                    variant="soft"
                    :icon="Pencil"
                    :href="edit.url(organization.id)"
                    >{{ $t('Edit') }}</FmButton
                >
                <FmDeleteButton
                    v-if="organization.can_delete"
                    :url="destroy.url(organization.id)"
                    :question="$t('Delete :name?', { name: organization.name })"
                    :text="
                        $t(
                            'Its documents and records stay, without the organization.',
                        )
                    "
                    :confirm-label="$t('Delete the organization')"
                />
            </div>
        </div>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <FmLinkedRecords :groups="links" />
                <FmCard :title="$t('Documents')" :list="documents.length > 0">
                    <FmDocumentRow
                        v-for="document in documents"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                    />
                    <p
                        v-if="documents.length === 0"
                        class="m-0 text-[15px] text-ink-soft"
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
                    <span class="min-w-0 text-[15px] font-semibold break-all">{{
                        contact.text
                    }}</span>
                </a>
                <p
                    v-if="organization.notes"
                    class="m-0 flex gap-1.5 px-4 py-3 text-sm leading-normal whitespace-pre-line text-[#4A5565]"
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
                    class="m-0 text-[15px] text-ink-soft"
                >
                    {{ $t('No contacts yet.') }}
                </p>
            </FmCard>
        </div>
    </div>
</template>
