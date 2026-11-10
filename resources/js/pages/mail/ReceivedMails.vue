<script setup lang="ts">
/**
 * *Received mail*, as the boards *Posta* and *Posta-pc* draw it: the mails
 * that reached the address for the bills, newest first, 50 at a time and the
 * next ones while scrolling, searched on the server in the sender, the
 * subject and the recipients while typing. Each row says who wrote, when, the
 * subject and what was done with the mail. The list asks the server again
 * every 15 seconds. A mail opens beside the list on the computer and on the
 * whole screen on the phone: who wrote and to whom, what was done with each
 * file and the files left out, the notes, the code of Gmail to copy, *Salva
 * come avviso* for a mail discarded or under review, and its text in a
 * sandboxed frame. An `admin` deletes it.
 */
import { Head, Link, router, WhenVisible } from '@inertiajs/vue3';
import {
    Check,
    ChevronRight,
    Copy,
    FilePlus,
    FileText,
    ImageOff,
    Mail,
    X,
} from '@lucide/vue';
import { useIntervalFn, watchDebounced } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, useTemplateRef, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmMailOutcome from '@/components/fm/FmMailOutcome.vue';
import FmMailText from '@/components/fm/FmMailText.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useCopy } from '@/composables/useCopy';
import { formatDateTime, formatShortDateTime } from '@/lib/format';
import { toSort } from '@/routes';
import { destroy, index, notice, show } from '@/routes/mail';
import { usePageTrail } from '@/composables/usePageTrail';

const props = defineProps<{
    /** The address for the bills of the family, null when it has none yet. */
    address: string | null;
    /** The search the list answers. */
    search: string;
    /** The blocks of the list loaded so far. */
    mails: App.Data.ReceivedMailRowData[];
    /** The number of the next block of the list, null at the last one. */
    next: number | null;
    /** The mail open, null for the list alone. */
    mail: App.Data.ReceivedMailData | null;
}>();

/** How often the list is asked again, in milliseconds. */
const POLL_INTERVAL = 15_000;

/** The text in the search box. */
const text = ref(props.search);

// Back to another search brings its text back in the box
watch(
    () => props.search,
    (search) => {
        text.value = search;
    },
);

/** The query that keeps the search in the addresses of the page. */
const query = computed(() =>
    props.search === '' ? undefined : { search: props.search },
);

/**
 * The mails newest first. The blocks are added below, and a mail the poll
 * brings for the first time arrives at the end: the order is made here.
 */
const rows = computed((): App.Data.ReceivedMailRowData[] =>
    [...props.mails].sort(
        (a, b) =>
            Date.parse(b.received_at) - Date.parse(a.received_at) ||
            b.id - a.id,
    ),
);

// A new search starts again from the first mails, instead of adding below
watchDebounced(
    text,
    (value) => {
        const search = value.trim();

        if (search === props.search) {
            return;
        }

        router.get(
            props.mail ? show.url(props.mail.row.id) : index.url(),
            search === '' ? {} : { search },
            {
                only: ['mails', 'next', 'search'],
                reset: ['mails'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    },
    { debounce: 300 },
);

// Only the list, asked to the address of the list and not to the one of the
// mail open: a mail deleted meanwhile by someone else would answer 404. The
// address shown stays, and so do the mail open and what is typed.
useIntervalFn(() => {
    if (document.visibilityState === 'hidden') {
        return;
    }

    router.visit(index.url({ query: query.value }), {
        only: ['mails'],
        preserveUrl: true,
        preserveState: true,
        preserveScroll: true,
        async: true,
        showProgress: false,
    });
}, POLL_INTERVAL);

/** Why *Salva come avviso* did not save, from the server. */
const noticeError = ref<string | null>(null);

/** The code of Gmail, selected when it cannot be copied. */
const codeElement = useTemplateRef<HTMLElement>('codeText');
const copier = useCopy();

watch(
    () => props.mail?.row.id,
    () => {
        noticeError.value = null;
        copier.reset();
    },
);

/** Whether a file of the mail is no longer there, which says how to get it back. */
const anyGone = computed(
    (): boolean =>
        props.mail?.files.some((file) => file.state === 'gone') ?? false,
);

/** What *Delete the mail* says happens to the files, before deleting. */
const deleteText = computed((): string =>
    props.mail?.pending_notice
        ? trans(
              'The mail, its text and its deliveries disappear from here, and so does the notice «:name», still in To sort and not a document. The attachments stay where they are.',
              { name: props.mail.pending_notice },
          )
        : trans(
              'The mail, its text and its deliveries disappear from here. The files it brought stay where they are, in To sort or among the documents.',
          ),
);

/**
 * Where a file of the mail is now, in words.
 *
 * @param file - the file the mail saved
 * @returns «Saved · in To sort», «Became the document …», «Saved» or «no longer here»
 */
function fileState(file: App.Data.ReceivedMailFileData): string {
    switch (file.state) {
        case 'to_sort':
            return trans('Saved · in To sort');
        case 'document':
            return trans('Became the document :title', {
                title: file.document_title ?? '',
            });
        case 'saved':
            return trans('Saved');
        case 'gone':
            return trans('no longer here');
    }
}

/** *Salva come avviso*: the notice goes to *To sort*, or the reason it cannot. */
function saveAsNotice(): void {
    if (props.mail === null) {
        return;
    }

    noticeError.value = null;
    router.post(
        notice.url(props.mail.row.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                noticeError.value = errors.notice ?? null;
            },
        },
    );
}

/** Copies the code of Gmail; when the browser refuses, selects it for the person to copy. */
async function copyCode(): Promise<void> {
    if (props.mail?.gmail_code && !(await copier.copy(props.mail.gmail_code))) {
        const element = codeElement.value;

        if (element) {
            window.getSelection()?.selectAllChildren(element);
        }
    }
}

usePageTrail(() => [
    { label: trans('To sort'), href: toSort().url },
    { label: trans('Received mail') },
]);
</script>

<template>
    <Head :title="$t('Received mail')" />

    <div class="flex max-w-[1120px] flex-col gap-4 lg:gap-6">
        <div
            class="flex flex-col gap-3.5 lg:flex-row lg:items-end lg:justify-between lg:gap-4"
            :class="mail && 'max-lg:hidden'"
        >
            <FmTitle
                :title="$t('Received mail')"
                :subtitle="
                    address
                        ? $t('The mails that arrived at :address', { address })
                        : undefined
                "
                :back="toSort().url"
                :back-label="$t('To sort')"
                class="min-w-0 lg:flex-1"
            />
            <FmSearchField
                v-model="text"
                class="lg:w-80 lg:shrink-0"
                :label="$t('Search sender or subject')"
            />
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <div
                class="min-w-0 lg:flex-[3_1_460px]"
                :class="mail && 'max-lg:hidden'"
            >
                <FmEmpty
                    v-if="rows.length === 0"
                    :icon="Mail"
                    :title="
                        search === ''
                            ? $t('No mail received yet')
                            : $t('No mail found')
                    "
                    :text="
                        search === ''
                            ? $t(
                                  'The mails sent to the address for the bills arrive here, with what was done with them.',
                              )
                            : undefined
                    "
                />
                <div
                    v-else
                    v-auto-animate
                    class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-card"
                >
                    <Link
                        v-for="row in rows"
                        :key="row.id"
                        :href="show.url(row.id, { query })"
                        :only="['mail']"
                        preserve-scroll
                        preserve-state
                        :aria-current="
                            mail?.row.id === row.id ? 'true' : undefined
                        "
                        class="grid gap-1 px-4 py-3 text-ink no-underline lg:grid-cols-[136px_minmax(0,1fr)] lg:gap-x-4 lg:px-5 lg:py-3.5"
                        :class="
                            mail?.row.id === row.id
                                ? 'bg-action-soft'
                                : 'hover:bg-canvas'
                        "
                    >
                        <span
                            class="hidden text-body-sm text-ink-soft lg:block"
                            >{{ formatDateTime(row.received_at) }}</span
                        >
                        <span class="flex min-w-0 justify-between gap-2.5">
                            <span class="truncate text-row-amount">{{
                                row.sender
                            }}</span>
                            <span
                                class="shrink-0 text-meta text-ink-soft lg:hidden"
                                >{{
                                    formatShortDateTime(row.received_at)
                                }}</span
                            >
                        </span>
                        <span
                            class="min-w-0 max-lg:order-last lg:[&>span]:max-w-full lg:[&>span]:whitespace-normal"
                        >
                            <FmMailOutcome
                                :label="row.outcome_label"
                                :tone="row.tone"
                            />
                        </span>
                        <span class="truncate text-body-sm text-ink-soft">{{
                            row.subject ?? $t('no subject')
                        }}</span>
                    </Link>
                    <WhenVisible
                        v-if="next"
                        :key="next"
                        always
                        :params="{
                            data: { page: next, ...query },
                            only: ['mails', 'next'],
                            preserveUrl: true,
                        }"
                    />
                </div>
            </div>

            <section
                v-if="mail"
                :aria-label="$t('The mail')"
                class="flex min-w-0 flex-col gap-4 rounded-card-lg bg-card p-5 shadow-float lg:flex-[2_1_400px] lg:p-6"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="m-0 text-stat">
                        {{ mail.row.subject ?? $t('no subject') }}
                    </h2>
                    <span class="flex shrink-0 gap-1">
                        <FmDeleteButton
                            v-if="mail.can_delete"
                            compact
                            small
                            plain
                            :label="$t('Delete the mail')"
                            :url="destroy.url(mail.row.id)"
                            :question="$t('Delete this mail?')"
                            :text="deleteText"
                            :confirm-label="$t('Delete')"
                        />
                        <Link
                            :href="index.url({ query })"
                            :only="['mail']"
                            preserve-scroll
                            preserve-state
                            :aria-label="$t('Close')"
                            class="flex size-10 items-center justify-center rounded-md text-ink-soft hover:bg-canvas"
                        >
                            <FmIcon :icon="X" :size="22" />
                        </Link>
                    </span>
                </div>

                <dl
                    class="m-0 grid grid-cols-[88px_minmax(0,1fr)] items-baseline gap-x-3 gap-y-2"
                >
                    <dt class="text-meta text-ink-soft">
                        {{ $t('Sender') }}
                    </dt>
                    <dd class="m-0 text-body-sm [overflow-wrap:anywhere]">
                        {{ mail.from }}
                    </dd>
                    <dt class="text-meta text-ink-soft">
                        {{ $t('Recipient') }}
                    </dt>
                    <dd class="m-0 text-body-sm [overflow-wrap:anywhere]">
                        {{ mail.recipients.join(', ') || mail.recipient }}
                    </dd>
                    <dt class="text-meta text-ink-soft">
                        {{ $t('Subject') }}
                    </dt>
                    <dd class="m-0 text-body-sm [overflow-wrap:anywhere]">
                        {{ mail.row.subject ?? $t('no subject') }}
                    </dd>
                    <dt class="text-meta text-ink-soft">
                        {{ $t('Arrived on') }}
                    </dt>
                    <dd class="m-0 text-body-sm">
                        {{ formatDateTime(mail.row.received_at) }}
                    </dd>
                </dl>

                <span class="flex items-center gap-2 text-meta text-ink-soft">
                    <FmIcon :icon="ImageOff" :size="16" />
                    {{ $t('The images of the mail are not shown.') }}
                </span>

                <div
                    v-if="mail.gmail_code"
                    class="flex flex-wrap items-center gap-3 rounded-lg bg-action-soft p-4"
                >
                    <span class="flex grow flex-col gap-0.5">
                        <span class="text-meta text-action-dark">{{
                            $t('Gmail confirmation code')
                        }}</span>
                        <strong
                            ref="codeText"
                            class="text-stat-lg tracking-wider"
                            >{{ mail.gmail_code }}</strong
                        >
                    </span>
                    <FmButton
                        variant="outline"
                        :icon="copier.copied.value ? Check : Copy"
                        @click="copyCode"
                        >{{ $t('Copy') }}</FmButton
                    >
                    <span
                        v-if="copier.copied.value"
                        role="status"
                        class="w-full text-body-sm"
                        >{{ $t('Code copied') }}</span
                    >
                    <span
                        v-if="copier.failed.value"
                        role="status"
                        class="w-full text-body-sm"
                        >{{
                            $t('Could not copy: select the code and copy it.')
                        }}</span
                    >
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-label">{{
                        $t('What was done with it')
                    }}</span>
                    <FmMailOutcome
                        class="self-start"
                        :label="mail.row.outcome_label"
                        :tone="mail.row.tone"
                    />
                    <template
                        v-for="(file, position) in mail.files"
                        :key="position"
                    >
                        <component
                            :is="
                                file.state === 'gone'
                                    ? 'div'
                                    : file.state === 'saved'
                                      ? 'a'
                                      : Link
                            "
                            :href="file.url ?? undefined"
                            :target="
                                file.state === 'saved' ? '_blank' : undefined
                            "
                            :rel="
                                file.state === 'saved' ? 'noopener' : undefined
                            "
                            class="flex items-center gap-3 rounded-lg border border-edge-soft px-3 py-2.5 text-ink no-underline"
                            :class="file.state !== 'gone' && 'hover:bg-canvas'"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-md border border-edge-soft bg-canvas text-ink-soft"
                            >
                                <FmIcon :icon="FileText" :size="18" />
                            </span>
                            <span class="flex min-w-0 grow flex-col gap-0.5">
                                <span
                                    class="truncate text-row"
                                    :class="
                                        file.state === 'gone' && 'text-ink-soft'
                                    "
                                    >{{ file.name }}</span
                                >
                                {{ ' ' }}
                                <span class="text-meta text-ink-soft">{{
                                    fileState(file)
                                }}</span>
                            </span>
                            <FmIcon
                                v-if="file.state !== 'gone'"
                                :icon="ChevronRight"
                                :size="18"
                                class="text-ink-soft"
                            />
                        </component>
                    </template>
                    <FmNotice v-if="anyGone">
                        {{
                            $t(
                                'A file that is no longer here cannot be recovered from here: only the text of this mail is left. To get it back, forward the original mail to the address of the family from the mailbox it was first sent to, or ask the sender for it again.',
                            )
                        }}
                    </FmNotice>
                    <span
                        v-for="(skipped, position) in mail.skipped"
                        :key="position"
                        class="text-meta text-ink-soft"
                        >{{
                            skipped.reason === ''
                                ? $t('Not saved: :name.', {
                                      name: skipped.name,
                                  })
                                : $t('Not saved: :name, :reason.', {
                                      name: skipped.name,
                                      reason: skipped.reason.toLocaleLowerCase(),
                                  })
                        }}</span
                    >
                    <FmNotice
                        v-for="(note, position) in mail.notes"
                        :key="position"
                        tone="warning"
                        >{{ note }}</FmNotice
                    >
                    <div
                        v-if="mail.can_save_as_notice"
                        class="flex flex-col gap-2"
                    >
                        <FmButton
                            class="self-start"
                            variant="soft"
                            :icon="FilePlus"
                            @click="saveAsNotice"
                            >{{ $t('Save as notice') }}</FmButton
                        >
                        <FmNotice v-if="noticeError" tone="danger">{{
                            noticeError
                        }}</FmNotice>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-label">{{ $t('The text') }}</span>
                    <FmMailText
                        :src="mail.has_text ? mail.text_url : null"
                        :both="mail.has_both_texts"
                        :too-large="mail.html_too_large"
                    />
                </div>
            </section>
        </div>
    </div>
</template>
