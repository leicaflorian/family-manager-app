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
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    Palette,
    Pencil,
    Pin,
    Plus,
    Trash2,
    UserX,
} from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmLinkedRecords from '@/components/fm/FmLinkedRecords.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmPinnedDocument from '@/components/fm/FmPinnedDocument.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmShowMore from '@/components/fm/FmShowMore.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { useDocumentPin } from '@/composables/useDocumentPin';
import { useMorePages } from '@/composables/useMorePages';
import { useWideLayout } from '@/composables/useWideLayout';
import { accountText, personSubtitle } from '@/lib/family';
import type { Area } from '@/lib/areas';
import { cn } from '@/lib/utils';
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
import { usePageMeta } from '@/composables/usePageMeta';
import { family, upload } from '@/routes';

const {
    person,
    reminders,
    remindersCount,
    pinned,
    canPin,
    personal,
    personalNext = null,
    personalTotal,
    documents,
    documentsNext = null,
    documentsTotal,
    pageSizes,
} = defineProps<{
    /** The person. */
    person: App.Data.PersonDetailData;
    /** Their open reminders, the late ones first. */
    reminders: App.Data.ReminderData[];
    /** How many open reminders they have: the number beside *To do*. */
    remindersCount: number;
    /** Their documents pinned at the top, the whole list in the order they were pinned. */
    pinned: App.Data.PinnedDocumentData[];
    /** Whoever sees the page can pin and unpin documents: the pin of a tile shows. */
    canPin: boolean;
    /** The pages of their documents linked to no record and not pinned (such as the identity card) loaded so far, the most recent first. */
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
    /** How many documents a page of each list holds, as the server sends them: what *Show more* promises. */
    pageSizes: { personal: number; documents: number };
    /** The records that name them, by type. */
    links: App.Data.LinkGroupData[];
}>();

/** How many reminders a tap on *Show more* adds to *To do*. */
const REMINDERS_STEP = 5;

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

/** Each list of documents loads its next page on its own, because both can load at the same time. */
const { loading, loadMore } = useMorePages(['personal', 'documents']);

const wide = useWideLayout();

/** What a pin or an unpin reloads: the pinned list, the others and their numbers, the others from the first page. */
const pinReload = {
    only: ['pinned', 'personal', 'personalNext', 'personalTotal'],
    reset: ['personal'],
};

const { togglePin } = useDocumentPin();

/**
 * Takes a pinned document off the top. The server answers with the page again:
 * the document goes back among the others. `pinReload` makes the visit partial,
 * naming what must refresh (the pinned list, the others and their numbers) and
 * dropping the pages of the others loaded so far, starting again from the first.
 *
 * @param id - the id of the document
 */
function unpinDocument(id: number): void {
    togglePin({ id, pinned: true }, pinReload);
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

/**
 * The area of the thing a reminder refers to, for its tile and its icon: the
 * general one when it refers to nothing or to an area the app does not know.
 *
 * @param reminder - an open reminder
 * @returns the area
 */
function reminderArea(reminder: App.Data.ReminderData): Area {
    return areaOf(reminder.subject?.area ?? null) ?? 'general';
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

usePageMeta({
    title: () => name.value,
    parent: ['family', { label: trans('People'), href: index().url }],
});
</script>

<template>
    <Head :title="name" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle :title="name" :subtitle="personSubtitle(person)">
            <template #leading>
                <FmAvatar
                    :initials="person.initials"
                    :photo="person.photo"
                    :name="name"
                    :size="64"
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
                                :class="areaTile[reminderArea(reminder)]"
                            >
                                <FmIcon
                                    :icon="areaIcon(reminderArea(reminder))"
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
                    :count="pinned.length + personalTotal"
                    :list="pinned.length === 0 && personal.length > 0"
                    :bare="pinned.length > 0"
                >
                    <template #actions>
                        <FmSectionLink :href="upload().url" :icon="Plus">{{
                            $t('Add')
                        }}</FmSectionLink>
                    </template>
                    <template v-if="pinned.length > 0">
                        <div
                            class="flex items-baseline justify-between gap-3 text-meta text-ink-soft"
                        >
                            <span class="text-meta-strong">{{
                                $t('Pinned')
                            }}</span>
                            <span
                                v-if="wide && canPin"
                                class="flex items-center gap-1"
                            >
                                <FmIcon :icon="Pin" :size="14" />{{
                                    $t(
                                        'Mark a document as favourite from its menu',
                                    )
                                }}
                            </span>
                        </div>
                        <div class="grid gap-2.5 wide:grid-cols-2">
                            <FmPinnedDocument
                                v-for="item in pinned"
                                :key="item.document.id"
                                :pinned="item"
                                :href="showDocument.url(item.document.id)"
                                :can-unpin="canPin"
                                @unpin="unpinDocument(item.document.id)"
                            />
                        </div>
                        <p
                            v-if="personal.length > 0"
                            class="m-0 mt-1 text-meta-strong text-ink-soft"
                        >
                            {{ $t('The others, most recent first') }}
                        </p>
                    </template>
                    <div
                        v-if="personal.length > 0"
                        :class="
                            cn(
                                'flex flex-col divide-y divide-divider',
                                pinned.length > 0 &&
                                    'overflow-hidden rounded-card bg-card shadow-card',
                            )
                        "
                    >
                        <FmDocumentRow
                            v-for="document in personal"
                            :key="document.id"
                            :document="document"
                            :href="showDocument.url(document.id)"
                            menu
                            :pin-options="pinReload"
                        />
                        <FmShowMore
                            v-if="personalNext"
                            :label="
                                showMoreLabel(
                                    personalTotal - personal.length,
                                    pageSizes.personal,
                                    'documents',
                                )
                            "
                            :loading="loading.personal"
                            @more="loadMore('personal', personalNext)"
                        />
                    </div>
                    <p
                        v-if="personal.length === 0 && pinned.length === 0"
                        class="m-0 text-body-sm text-ink-soft"
                    >
                        {{ $t('No personal documents yet.') }}
                    </p>
                </FmCard>
                <Link
                    v-if="person.drawings_url"
                    :href="person.drawings_url"
                    class="flex items-center gap-3 rounded-card bg-card px-4 py-3 text-ink no-underline shadow-card"
                >
                    <span
                        aria-hidden="true"
                        class="flex size-[38px] shrink-0 items-center justify-center rounded-md bg-school-soft text-school-text"
                        ><FmIcon :icon="Palette" :size="20"
                    /></span>
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-body-strong">{{
                            $t(":name's drawings", {
                                name: person.first_name,
                            })
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            $tChoice(
                                '{1} :count in the drawings gallery|[2,*] :count in the drawings gallery',
                                person.drawings_count,
                            )
                        }}</span>
                    </span>
                    <FmIcon
                        :icon="ChevronRight"
                        :size="20"
                        class="text-ink-soft"
                    />
                </Link>
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
                        menu
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
