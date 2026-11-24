<script setup lang="ts">
/**
 * *Report a problem or an idea*: the panel that the account menu of the
 * sidebar and *More* of the phone open, to write to the owner. It asks what it
 * is about (something that does not work, chosen at the opening, or an idea),
 * the message and an optional screenshot or PDF, and sends with them the page
 * the person is on and the layout of the app (the server adds the device and
 * the time). For a problem it also shows the photo of the page taken when it
 * opened (which can be removed) and *What we send*, the technical details that
 * go with the report; an idea sends neither. Once sent it closes, and the toast of the server thanks; opened
 * again it starts empty.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { Bug, ChevronDown, ImagePlus, Lightbulb, Send, X } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import {
    computed,
    onBeforeUnmount,
    reactive,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { collectDiagnostics, serializeDiagnostics } from '@/lib/diagnostics';
import type { FeedbackDiagnostics } from '@/lib/diagnostics';
import { errors } from '@/lib/errors';
import { cn } from '@/lib/utils';
import { store } from '@/routes/feedback';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';

const props = defineProps<{
    /** The photo of the page taken when the panel opened; `null` while it is being taken or if it failed. */
    screenshot?: File | null;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

/** What a report is about, as the server names it. */
type FeedbackKind = App.Enums.FeedbackKind;

/** A choice of *What is it about*. */
type KindChoice = {
    /** The value sent. */
    value: FeedbackKind;
    /** The text of the tile, already translated. */
    label: string;
    /** The icon of the tile. */
    icon: LucideIcon;
};

const kinds = computed((): KindChoice[] => [
    { value: 'problem', label: trans('Something does not work'), icon: Bug },
    { value: 'idea', label: trans('I have an idea'), icon: Lightbulb },
]);

/** What the person writes. */
const values = reactive({
    kind: 'problem' as FeedbackKind,
    message: '',
    file: null as File | null,
});

/** The photo of the page was removed by the person, for this opening. */
const screenshotRemoved = ref(false);

/** The photo that is shown and sent: a problem only, and not once removed. */
const photo = computed((): File | null =>
    values.kind === 'problem' && !screenshotRemoved.value
        ? (props.screenshot ?? null)
        : null,
);

/** The note under the form: it names the photo only when one goes with the report. */
const note = computed((): string => {
    if (values.kind === 'idea') {
        return trans(
            'Along with the message we send the page you were on, the device you use and the time. We answer you by email.',
        );
    }

    return photo.value
        ? trans(
              'Along with the message we send a photo of this page and some technical details, which you can read above. We answer you by email.',
          )
        : trans(
              'Along with the message we send some technical details, which you can read above. We answer you by email.',
          );
});

/** The address of the thumbnail of the photo. */
const photoUrl = ref<string | null>(null);

/** Frees the address of the thumbnail, if there is one. */
function revokePhotoUrl(): void {
    if (photoUrl.value) {
        URL.revokeObjectURL(photoUrl.value);
        photoUrl.value = null;
    }
}

// A thumbnail per file: the address is freed when the file changes or goes away
watch(
    photo,
    (file) => {
        revokePhotoUrl();
        photoUrl.value = file ? URL.createObjectURL(file) : null;
    },
    { immediate: true },
);

onBeforeUnmount(revokePhotoUrl);

/** The page the panel was opened on (path and query), read at each opening: the layout outlives the pages. */
const pagePath = ref(currentPage());

/**
 * The page the person is on now.
 *
 * @returns the path and the query
 */
function currentPage(): string {
    return window.location.pathname + window.location.search;
}

/** *What we send* is open. */
const detailsOpen = ref(false);

/** What the browser would send now, read when *What we send* opens. */
const details = ref<FeedbackDiagnostics | null>(null);

/** Opens or closes *What we send*, reading the details afresh when it opens. */
function toggleDetails(): void {
    detailsOpen.value = !detailsOpen.value;
    details.value = detailsOpen.value ? readDiagnostics() : null;
}

/**
 * Reads the diagnostics of the browser; a failure means none, never a report lost.
 *
 * @returns the diagnostics, or `null` if they could not be read
 */
function readDiagnostics(): FeedbackDiagnostics | null {
    try {
        return collectDiagnostics();
    } catch {
        return null;
    }
}

/**
 * The `diagnostics` field of a report of a problem, within the size the server reads.
 *
 * @returns the field, or nothing if the details could not be read
 */
function diagnosticsField(): { diagnostics?: string } {
    const read = readDiagnostics();

    return read ? { diagnostics: serializeDiagnostics(read) } : {};
}

/** A line of *What we send*. */
type DetailRow = {
    /** The name of the line, already translated. */
    label: string;
    /** The lines of its value. */
    lines: string[];
};

/** The lines of *What we send*, from the details read when it opened. */
const detailRows = computed((): DetailRow[] => {
    const d = details.value;

    if (!d) {
        return [];
    }

    const failed = d.requests.map(
        (r) => `${r.method} ${r.path} · ${r.status ?? '—'}`,
    );
    const theme =
        d.theme === 'dark' ? trans('dark theme') : trans('light theme');
    // Counted like the panel of the owner and the email, which count errors only; the list also shows the warnings
    const errorCount = d.console.filter((c) => c.level === 'error').length;

    return [
        { label: trans('Page'), lines: [pagePath.value] },
        {
            label: trans('Last pages'),
            lines: [d.pages.map((p) => p.path).join(' → ')],
        },
        {
            label: trans('Page errors (:count)', {
                count: String(errorCount),
            }),
            lines: d.console.map((c) => c.message),
        },
        {
            label: trans('Failed requests (:count)', {
                count: String(failed.length),
            }),
            lines: failed,
        },
        {
            label: trans('Device'),
            lines: [`${d.viewport.width} × ${d.viewport.height}, ${theme}`],
        },
        { label: trans('Version'), lines: [d.client_build ?? '—'] },
    ];
});

/** The messages of the server (or of the empty message), by field. */
const problems = ref<Record<string, string>>({});

/** The report is being sent. */
const processing = ref(false);

const layout = useLayoutMode();

/** The hidden picker that the dashed button opens. */
const picker = useTemplateRef<HTMLInputElement>('picker');

/** Asks before closing with the handle, the × or outside once something is written. */
const changes = useUnsavedChanges(
    () => [values.kind, values.message, values.file?.name ?? ''],
    { active: () => open.value },
);

/** Empties the form, for a new report. */
function reset(): void {
    values.kind = 'problem';
    values.message = '';
    values.file = null;
    screenshotRemoved.value = false;
    pagePath.value = currentPage();
    detailsOpen.value = false;
    details.value = null;
    problems.value = {};
    changes.reset();
}

// Opened again, it starts empty
watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

/**
 * Keeps the file the person chose.
 *
 * @param event - the change of the picker
 */
function pickFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    values.file = input.files?.[0] ?? null;
    // The same file can be chosen again after Remove
    input.value = '';
}

/** Sends the report. A file makes it a form with files, which PHP only reads as a POST. */
function send(): void {
    problems.value = {};

    if (values.message.trim() === '') {
        problems.value = { message: trans('Write what happened.') };

        return;
    }

    processing.value = true;

    router.post(
        store().url,
        {
            kind: values.kind,
            message: values.message,
            page: pagePath.value,
            layout: layout.value,
            ...(values.file ? { file: values.file } : {}),
            ...(values.kind === 'problem'
                ? {
                      ...(photo.value ? { screenshot: photo.value } : {}),
                      ...diagnosticsField(),
                  }
                : {}),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (messages) => {
                problems.value = messages;
            },
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :before-close="changes.confirmClose"
        :title="$t('Report a problem or an idea')"
    >
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="send">
            <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                <legend class="mb-1.5 p-0 text-label text-ink-soft">
                    {{ $t('What is it about') }}
                </legend>
                <div class="flex gap-2">
                    <label
                        v-for="kind in kinds"
                        :key="kind.value"
                        class="flex min-h-13 min-w-0 flex-1 cursor-pointer items-center gap-2.5 rounded-lg border-[1.5px] border-edge bg-card px-3 text-row has-checked:border-2 has-checked:border-action has-checked:bg-action-soft has-focus-visible:ring-2 has-focus-visible:ring-action/30"
                    >
                        <input
                            v-model="values.kind"
                            type="radio"
                            name="kind"
                            :value="kind.value"
                            class="peer sr-only"
                        />
                        <FmIcon
                            :icon="kind.icon"
                            :size="20"
                            class="shrink-0 text-ink-soft peer-checked:text-action-dark"
                        />
                        {{ kind.label }}
                    </label>
                </div>
                <p
                    v-if="problems.kind"
                    class="m-0 text-meta-strong text-overdue"
                >
                    {{ problems.kind }}
                </p>
            </fieldset>

            <FormKit
                v-model="values.message"
                type="textarea"
                name="message"
                rows="4"
                :label="$t('Tell us what happened')"
                :errors="errors(problems.message)"
            />

            <div class="flex flex-col gap-1.5">
                <div
                    v-if="photoUrl"
                    class="flex items-center gap-3 rounded-lg border border-edge-soft bg-card py-1.5 pr-1 pl-1.5"
                >
                    <img
                        :src="photoUrl"
                        :alt="$t('Photo of this page')"
                        class="h-14 w-10 shrink-0 rounded-tag border border-edge-soft bg-canvas object-cover object-top"
                    />
                    <span class="flex min-w-0 grow flex-col">
                        <span class="text-row text-ink">{{
                            $t('Photo of this page')
                        }}</span>
                        <span class="truncate text-meta text-ink-soft">{{
                            pagePath
                        }}</span>
                    </span>
                    <FmButton
                        :icon="X"
                        variant="text"
                        small
                        :label="$t('Remove the photo of this page')"
                        @click="screenshotRemoved = true"
                    />
                </div>
                <div
                    v-if="values.file"
                    class="flex h-control-lg items-center gap-2 rounded-lg border-[1.5px] border-edge bg-card pr-1 pl-3.5"
                >
                    <FmIcon
                        :icon="ImagePlus"
                        :size="18"
                        class="shrink-0 text-ink-soft"
                    />
                    <span class="grow truncate text-row">{{
                        values.file.name
                    }}</span>
                    <FmButton
                        :icon="X"
                        variant="text"
                        small
                        :label="$t('Remove :name', { name: values.file.name })"
                        @click="values.file = null"
                    />
                </div>
                <button
                    v-else
                    type="button"
                    class="flex h-control-lg cursor-pointer items-center justify-center gap-2 rounded-lg border-[1.5px] border-dashed border-edge bg-card px-3 text-row text-action"
                    @click="picker?.click()"
                >
                    <FmIcon :icon="ImagePlus" :size="18" />
                    <span class="truncate">{{
                        $t('Add a screenshot or a file (optional)')
                    }}</span>
                </button>
                <input
                    ref="picker"
                    type="file"
                    class="hidden"
                    :aria-label="$t('Screenshot or file')"
                    accept="image/*,application/pdf,.heic,.heif"
                    @change="pickFile"
                />
                <p
                    v-if="problems.file"
                    class="m-0 text-meta-strong text-overdue"
                >
                    {{ problems.file }}
                </p>
            </div>

            <div
                v-if="values.kind === 'problem'"
                class="rounded-lg border border-edge-soft bg-card"
            >
                <button
                    type="button"
                    class="flex min-h-control w-full cursor-pointer items-center gap-2 px-3.5 text-left text-row text-ink"
                    :aria-expanded="detailsOpen"
                    @click="toggleDetails"
                >
                    <FmIcon
                        :icon="ChevronDown"
                        :size="18"
                        :class="
                            cn(
                                'shrink-0 text-ink-soft',
                                !detailsOpen && '-rotate-90',
                            )
                        "
                    />
                    <span class="grow">{{
                        $t('What we send (technical details)')
                    }}</span>
                </button>
                <dl
                    v-if="detailsOpen"
                    class="m-0 grid grid-cols-1 gap-x-4 gap-y-2 pr-3.5 pb-3 pl-10 sm:grid-cols-2"
                >
                    <div
                        v-for="row in detailRows"
                        :key="row.label"
                        class="flex min-w-0 flex-col"
                    >
                        <dt class="text-meta-strong text-ink-soft">
                            {{ row.label }}
                        </dt>
                        <dd class="m-0 text-meta wrap-anywhere text-ink">
                            <span
                                v-for="(line, index) in row.lines"
                                :key="index"
                                class="block"
                                >{{ line }}</span
                            >
                        </dd>
                    </div>
                </dl>
            </div>

            <FmNotice>{{ note }}</FmNotice>

            <div
                class="flex flex-col gap-1 wide:flex-row-reverse wide:justify-start wide:gap-2.5"
            >
                <FmButton type="submit" :icon="Send" :disabled="processing">{{
                    $t('Send')
                }}</FmButton>
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
