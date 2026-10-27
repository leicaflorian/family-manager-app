<script setup lang="ts">
/**
 * *A person*: at the top the documents expiring, then the personal documents
 * (linked to no record) and the records that name them; on the side their
 * data and the other documents. An `admin` edits them and deletes a person
 * without an account.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Pencil, UserX } from '@lucide/vue';
import { computed } from 'vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmLinkedRecords from '@/components/fm/FmLinkedRecords.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useFormat } from '@/composables/useFormat';
import { accountText, personSubtitle } from '@/lib/family';
import { show as showDocument } from '@/routes/documents';
import { destroy, edit, index } from '@/routes/people';

const { person, expiring, personal, documents, links } = defineProps<{
    /** The person. */
    person: App.Data.PersonDetailData;
    /** Their documents expiring within 60 days or already expired, the first to expire first. */
    expiring: App.Data.DocumentData[];
    /** Their documents linked to no record, such as the identity card. */
    personal: App.Data.DocumentData[];
    /** Their documents linked to a record. */
    documents: App.Data.DocumentData[];
    /** The records that name them, by type. */
    links: App.Data.LinkGroupData[];
}>();

const { date } = useFormat();

const name = computed(() => `${person.first_name} ${person.last_name}`);
</script>

<template>
    <Head :title="name" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <Link
            :href="index().url"
            class="flex items-center gap-1.5 self-start text-[15px] font-semibold no-underline"
        >
            <FmIcon :icon="ChevronLeft" :size="18" />
            {{ $t('People') }}
        </Link>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <FmAvatar :initials="person.initials" :size="64" />
                <div class="flex flex-col gap-1">
                    <h1 class="m-0 text-[26px] font-extrabold lg:text-[32px]">
                        {{ name }}
                    </h1>
                    <span class="text-[15px] text-ink-soft">{{
                        personSubtitle(person)
                    }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2.5">
                <FmButton
                    v-if="person.can_edit"
                    variant="soft"
                    :icon="Pencil"
                    :href="edit.url(person.id)"
                    >{{ $t('Edit') }}</FmButton
                >
                <FmDeleteButton
                    v-if="person.can_delete"
                    :url="destroy.url(person.id)"
                    :question="$t('Delete :name?', { name: person.first_name })"
                    :text="
                        $t(
                            'Their employments, enrollments and health events go too. The documents stay, without the person.',
                        )
                    "
                    :confirm-label="$t('Delete the person')"
                />
            </div>
        </div>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <FmCard v-if="expiring.length > 0" :title="$t('Expiring')" list>
                    <FmDocumentRow
                        v-for="document in expiring"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                        expiry
                    />
                </FmCard>
                <FmCard
                    :title="$t('Personal documents')"
                    :list="personal.length > 0"
                >
                    <FmDocumentRow
                        v-for="document in personal"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                    />
                    <p
                        v-if="personal.length === 0"
                        class="m-0 text-[15px] text-ink-soft"
                    >
                        {{ $t('No personal documents yet.') }}
                    </p>
                </FmCard>
                <FmLinkedRecords :groups="links" />
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                <div class="rounded-[20px] bg-white p-5">
                    <dl
                        class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-[15px]"
                    >
                        <dt class="text-ink-soft">{{ $t('Role') }}</dt>
                        <dd class="m-0 font-semibold">
                            {{ person.role_label }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Date of birth') }}</dt>
                        <dd class="m-0 font-semibold">
                            {{
                                person.birth_date
                                    ? date(person.birth_date)
                                    : '—'
                            }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Tax code') }}</dt>
                        <dd class="m-0 font-semibold break-all">
                            {{ person.tax_code ?? '—' }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Initials') }}</dt>
                        <dd class="m-0 font-semibold">{{ person.initials }}</dd>
                        <dt class="text-ink-soft">{{ $t('Account') }}</dt>
                        <dd
                            class="m-0"
                            :class="
                                person.account_label
                                    ? 'font-semibold'
                                    : 'text-ink-soft'
                            "
                        >
                            {{ accountText(person) }}
                        </dd>
                    </dl>
                </div>
                <FmNotice v-if="!person.account_label" :icon="UserX">{{
                    $t(
                        ':name has no account: the alerts of their deadlines go to whoever has one.',
                        {
                            name: person.first_name,
                        },
                    )
                }}</FmNotice>
                <FmCard
                    v-if="documents.length > 0"
                    :title="$t('Other documents')"
                    list
                >
                    <FmDocumentRow
                        v-for="document in documents"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                    />
                </FmCard>
                <FmCard v-if="person.notes" :title="$t('Notes')">
                    <p
                        class="m-0 text-[15px] leading-normal whitespace-pre-line text-ink-soft"
                    >
                        {{ person.notes }}
                    </p>
                </FmCard>
            </div>
        </div>
    </div>
</template>
