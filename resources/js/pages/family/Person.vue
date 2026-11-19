<script setup lang="ts">
/**
 * *A person* (the boards *Persona-tanti* and *Persona-tanti-pc*): the first
 * late reminder in red, then *To do* with their open reminders five at a time
 * and the link to the *Agenda* (`/reminders?person=` remembers the person in
 * the filters of the Agenda and opens it), the personal documents (linked to
 * no record) five at a time, and the records that name them; on the side their
 * data and the other documents, ten at a time, with the link to the Archive
 * filtered on them. The documents come from the server a page at a time, added
 * below with a partial reload that keeps the address. An `admin` edits them and
 * deletes a person without an account.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserX } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmLinkedRecords from '@/components/fm/FmLinkedRecords.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmShowMore from '@/components/fm/FmShowMore.vue';
import { useFormat } from '@/composables/useFormat';
import { accountText, personSubtitle } from '@/lib/family';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { daysFromToday, relativeDays } from '@/lib/format';
import { showMoreLabel } from '@/lib/showMore';
import {
    index as documentsIndex,
    show as showDocument,
} from '@/routes/documents';
import { index as remindersIndex } from '@/routes/reminders';
import type { Action } from '@/lib/actions';
import { destroy, edit, index } from '@/routes/people';
import { usePageTrail } from '@/composables/usePageTrail';
import { family, upload } from '@/routes';

const {
    person,
    reminders,
    remindersCount,
    personal,
    personalNext = null,
    personalTotal,
    documents,
    documentsNext = null,
    documentsTotal,
} = defineProps<{
    /** The person. */
    person: App.Data.PersonDetailData;
    /** Their open reminders, the late ones first. */
    reminders: App.Data.ReminderData[];
    /** How many open reminders they have: the number beside *To do*. */
    remindersCount: number;
    /** The pages of their documents linked to no record (such as the identity card) loaded so far, the most recent first. */
    personal: App.Data.DocumentData[];
    /** The number of the next page of `personal`, null at the last one. */
    personalNext?: number | null;
    /** How many documents linked to no record they have in all. */
    personalTotal: number;
    /** The pages of their documents linked to a record loaded so far, the most recent first. */
    documents: App.Data.DocumentData[];
    /** The number of the next page of `documents`, null at the last one. */
    documentsNext?: number | null;
    /** How many documents linked to a record they have in all. */
    documentsTotal: number;
    /** The records that name them, by type. */
    links: App.Data.LinkGroupData[];
}>();

/** How many reminders a tap on *Show more* adds to *To do*. */
const REMINDERS_STEP = 5;

/** How many personal documents a page holds (`PersonController::PERSONAL_PER_PAGE`). */
const PERSONAL_STEP = 5;

/** How many other documents a page holds (`PersonController::DOCUMENTS_PER_PAGE`). */
const DOCUMENTS_STEP = 10;

const { date } = useFormat();

const name = computed(() => `${person.first_name} ${person.last_name}`);

/** The Agenda filtered on the person. */
const agenda = computed(
    (): string => remindersIndex({ query: { person: person.id } }).url,
);

/** The first late reminder, shown in red above *To do*. */
const late = computed(() =>
    reminders.find((reminder) => daysFromToday(reminder.due_date) < 0),
);

/** How many reminders of *To do* are shown. */
const shownReminders = ref(REMINDERS_STEP);

/** The lists of documents loading their next page: each on its own, because both can load at the same time. */
const loading = ref<Record<'personal' | 'documents', boolean>>({
    personal: false,
    documents: false,
});

/**
 * Adds the next page of a list of documents below the rows already shown,
 * without reloading the rest of the page nor changing the address.
 *
 * @param list - the list: the personal documents or the other ones
 * @param page - the number of the page to add
 */
function loadMore(list: 'personal' | 'documents', page: number): void {
    loading.value[list] = true;
    router.reload({
        data: { [`${list}_page`]: page },
        only: [list, `${list}Next`, `${list}Total`],
        preserveUrl: true,
        onFinish: () => {
            loading.value[list] = false;
        },
    });
}

/**
 * How far the day of a reminder is: «overdue by 26 days» when it is passed.
 *
 * @param reminder - an open reminder
 * @returns the sentence, with the name of the thing it refers to in front
 */
function reminderText(reminder: App.Data.ReminderData): string {
    const days = daysFromToday(reminder.due_date);
    const when =
        days < 0
            ? transChoice(
                  '{1} Overdue by :count day|[2,*] Overdue by :count days',
                  -days,
                  { count: String(-days) },
              )
            : relativeDays(reminder.due_date);

    return [reminder.subject?.label, when].filter(Boolean).join(' · ');
}

/** *Edit* and *Delete*, for an `admin` and a person without an account. */
const actions = computed((): Action[] => [
    ...(person.can_edit
        ? [
              {
                  label: trans('Edit'),
                  icon: Pencil,
                  variant: 'soft',
                  href: edit.url(person.id),
              } satisfies Action,
          ]
        : []),
    ...(person.can_delete
        ? [
              {
                  label: trans('Delete'),
                  icon: Trash2,
                  variant: 'danger',
                  confirm: {
                      url: destroy.url(person.id),
                      question: trans('Delete :name?', {
                          name: person.first_name,
                      }),
                      text: trans(
                          'Their employments, enrollments and health events go too. The documents stay, without the person.',
                      ),
                      confirmLabel: trans('Delete the person'),
                  },
              } satisfies Action,
          ]
        : []),
]);

usePageTrail(() => [
    { label: trans('Family'), href: family().url },
    { label: name.value },
]);
</script>

<template>
    <Head :title="name" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmBackLink :href="index().url" :label="$t('People')" />

        <!-- As in FmTitle: the actions take the room the name leaves -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex max-w-[60%] min-w-0 shrink-0 items-center gap-4">
                <FmAvatar
                    :initials="person.initials"
                    :photo="person.photo"
                    :name="name"
                    :size="64"
                />
                <div class="flex flex-col gap-1">
                    <h1 class="m-0 text-title-mobile wide:text-title">
                        {{ name }}
                    </h1>
                    <span class="text-body-sm text-ink-soft">{{
                        personSubtitle(person)
                    }}</span>
                </div>
            </div>
            <FmActions :actions="actions" />
        </div>

        <div
            class="grid gap-5 wide:grid-cols-[minmax(0,1fr)_360px] wide:items-start"
        >
            <div class="flex min-w-0 flex-col gap-5">
                <FmNotice v-if="late" tone="danger" :title="late.title">
                    {{ reminderText(late) }}
                    <template #action>
                        <Link :href="agenda" class="no-underline">{{
                            $t('Agenda')
                        }}</Link>
                    </template>
                </FmNotice>
                <FmCard
                    v-if="reminders.length > 0"
                    :title="$t('To do')"
                    :count="remindersCount"
                    list
                >
                    <template #actions>
                        <FmSectionLink :href="agenda">{{
                            $t('Open in the Agenda')
                        }}</FmSectionLink>
                    </template>
                    <FmRow
                        v-for="reminder in reminders.slice(0, shownReminders)"
                        :key="reminder.id"
                        :title="reminder.title"
                        :subtitle="reminderText(reminder)"
                        :tone="
                            daysFromToday(reminder.due_date) < 0
                                ? 'overdue'
                                : 'normal'
                        "
                        :href="agenda"
                    >
                        <template #before>
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-md"
                                :class="
                                    areaTile[
                                        areaOf(
                                            reminder.subject?.area ?? null,
                                        ) ?? 'general'
                                    ]
                                "
                            >
                                <FmIcon
                                    :icon="
                                        areaIcon(
                                            reminder.subject?.area ?? 'general',
                                        )
                                    "
                                    :size="18"
                                />
                            </span>
                        </template>
                    </FmRow>
                    <FmShowMore
                        v-if="reminders.length > shownReminders"
                        :label="
                            showMoreLabel(
                                reminders.length - shownReminders,
                                REMINDERS_STEP,
                            )
                        "
                        @more="shownReminders += REMINDERS_STEP"
                    />
                </FmCard>
                <FmCard
                    :title="$t('Personal documents')"
                    :count="personalTotal"
                    :list="personal.length > 0"
                >
                    <template #actions>
                        <FmSectionLink :href="upload().url" :icon="Plus">{{
                            $t('Add')
                        }}</FmSectionLink>
                    </template>
                    <FmDocumentRow
                        v-for="document in personal"
                        :key="document.id"
                        :document="document"
                        :href="showDocument.url(document.id)"
                    />
                    <FmShowMore
                        v-if="personalNext"
                        :label="
                            showMoreLabel(
                                personalTotal - personal.length,
                                PERSONAL_STEP,
                                'documents',
                            )
                        "
                        :loading="loading.personal"
                        @more="loadMore('personal', personalNext)"
                    />
                    <p
                        v-if="personal.length === 0"
                        class="m-0 text-body-sm text-ink-soft"
                    >
                        {{ $t('No personal documents yet.') }}
                    </p>
                </FmCard>
                <FmLinkedRecords :groups="links" />
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                <div class="rounded-card-lg bg-card p-5 shadow-card">
                    <dl
                        class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-body-sm"
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
                    :count="documentsTotal"
                    list
                >
                    <template #actions>
                        <FmSectionLink
                            :href="
                                documentsIndex({ query: { person: person.id } })
                                    .url
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
                                DOCUMENTS_STEP,
                                'documents',
                            )
                        "
                        :loading="loading.documents"
                        @more="loadMore('documents', documentsNext)"
                    />
                </FmCard>
                <FmCard v-if="person.notes" :title="$t('Notes')">
                    <p
                        class="m-0 text-body-sm whitespace-pre-line text-ink-soft"
                    >
                        {{ person.notes }}
                    </p>
                </FmCard>
            </div>
        </div>
    </div>
</template>
