<script setup lang="ts">
/**
 * A file of *To sort*, as the board *Da smistare-pc* draws it: the box that
 * chooses it, the sheet of the file, the name (which opens the file in a new
 * tab) and below the note of how it arrived with the icon of where it came
 * from, or for a copy the label *Copy* and which file it is the same as; then
 * the pages and the size, the short date and the bin. The whole row lights up
 * under the mouse, and stays light blue once chosen. A copy cannot be chosen.
 *
 * With `touch` (the phone, as the board *Da smistare* draws it) the row is one
 * button: a circle of 40 px (the icon of where the file came from, a tick once
 * chosen), the name on one line, the short note below and the short date at
 * the end; the bin stays outside the button. A copy says it can be deleted.
 */
import type { LucideIcon } from '@lucide/vue';
import { Check, Mail, ScanLine, Smartphone, Upload } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmFileTile from '@/components/fm/FmFileTile.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { shortDate } from '@/lib/budget';
import { formatSize } from '@/lib/format';
import { destroy } from '@/routes/upload';

const {
    file,
    chosen,
    touch = false,
} = defineProps<{
    /** The file waiting. */
    file: App.Data.PendingFileData;
    /** Its box is ticked. */
    chosen: boolean;
    /** The phone's row: a button to touch, with the short note. */
    touch?: boolean;
}>();

const emit = defineEmits<{
    /** The box was ticked (`true`) or unticked (`false`). */
    choose: [on: boolean];
}>();

/** A copy is left out of the choice: it can only be deleted. */
const isCopy = computed((): boolean => file.copy_of !== null);

/** The icon of where the file came from, before its note. */
const sourceIcon = computed((): LucideIcon => {
    switch (file.source) {
        case 'mail':
            return Mail;
        case 'phone':
            return Smartphone;
        case 'scanner':
            return ScanLine;
        default:
            return Upload;
    }
});

/** What the file is: «3 pages · 1,1 MB», «Photo · 860 KB», «Text of the email». */
const measure = computed((): string => {
    if (file.mime_type === 'text/plain') {
        return trans('Text of the email');
    }

    const size = formatSize(file.size);

    if (file.mime_type.startsWith('image/')) {
        return trans('Photo · :size', { size });
    }

    return file.pages === null
        ? size
        : transChoice(
              '{1} :count page · :size|[2,*] :count pages · :size',
              file.pages,
              { count: String(file.pages), size },
          );
});

/** How the reading stands, when it is not the usual end: waiting, going on or not done. */
const readingText = computed((): string => {
    switch (file.reading_status) {
        case 'queued':
            return trans('Waiting to be read');
        case 'reading':
            return trans('Reading the document…');
        case 'failed':
        case 'off':
        case 'skipped':
            return file.reading_error ?? '';
        default:
            return '';
    }
});
</script>

<template>
    <li
        v-if="touch"
        class="flex items-center gap-2 px-4 py-3"
        :class="chosen ? 'bg-action-soft' : ''"
    >
        <button
            type="button"
            :aria-pressed="chosen"
            :aria-label="file.name"
            :disabled="isCopy"
            class="flex min-w-0 grow items-center gap-3 border-0 bg-transparent p-0 text-left text-ink"
            @click="emit('choose', !chosen)"
        >
            <span
                aria-hidden="true"
                class="flex size-10 shrink-0 items-center justify-center"
                :class="
                    chosen
                        ? 'rounded-full bg-action text-on-action'
                        : 'rounded-md border border-edge-soft bg-canvas text-ink-soft'
                "
            >
                <FmIcon
                    v-if="chosen"
                    :icon="Check"
                    :size="22"
                    :stroke-width="3"
                />
                <FmIcon v-else :icon="sourceIcon" :size="20" />
            </span>
            <span class="flex min-w-0 grow flex-col gap-0.5">
                <span class="truncate text-body-strong">{{ file.name }}</span>
                <span v-if="isCopy" class="text-meta-strong text-warning">{{
                    $t('It is a copy: it can be deleted')
                }}</span>
                <span
                    v-else-if="file.short_note"
                    class="truncate text-meta text-ink-soft"
                    >{{ file.short_note }}</span
                >
            </span>
            <span class="shrink-0 text-meta text-ink-soft">{{
                shortDate(file.created_on)
            }}</span>
        </button>
        <FmDeleteButton
            v-if="file.can_delete"
            compact
            :label="isCopy ? $t('Delete the copy') : $t('Delete')"
            :url="destroy.url(file.id)"
            :question="
                isCopy ? $t('Delete this copy?') : $t('Delete this file?')
            "
            :text="$t('The file is deleted for good.')"
            :confirm-label="
                isCopy ? $t('Delete the copy') : $t('Delete the file')
            "
        />
    </li>
    <li
        v-else
        class="flex items-center gap-3 px-4 py-3.5 transition-colors"
        :class="chosen ? 'bg-action-soft' : 'hover:bg-canvas'"
    >
        <input
            type="checkbox"
            :aria-label="file.name"
            :checked="chosen"
            :disabled="isCopy"
            class="size-5 shrink-0 accent-action"
            @change="
                emit('choose', ($event.target as HTMLInputElement).checked)
            "
        />
        <FmFileTile class="hidden sm:flex" />
        <div class="flex min-w-0 grow flex-col gap-1">
            <a
                :href="file.url"
                target="_blank"
                rel="noopener"
                class="truncate text-body-strong text-ink no-underline hover:underline"
                >{{ file.name }}</a
            >
            <div
                v-if="isCopy"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-meta"
            >
                <span
                    class="rounded-tag bg-warning-soft px-1.5 py-0.5 font-semibold text-warning"
                    >{{ $t('Copy') }}</span
                >
                <span class="text-warning">{{
                    $t('Same as :name', { name: file.copy_of ?? '' })
                }}</span>
            </div>
            <span
                v-else-if="file.note"
                class="flex items-start gap-1.5 text-meta text-ink-soft"
            >
                <FmIcon :icon="sourceIcon" :size="14" class="mt-0.5 shrink-0" />
                <span class="line-clamp-2">{{ file.note }}</span>
            </span>
            <span v-if="readingText" class="text-meta text-ink-soft">{{
                readingText
            }}</span>
        </div>
        <span
            class="hidden w-36 shrink-0 text-body-sm text-ink-soft md:block"
            >{{ measure }}</span
        >
        <span class="w-12 shrink-0 text-body-sm text-ink-soft">{{
            shortDate(file.created_on)
        }}</span>
        <span class="flex w-10 shrink-0 justify-center">
            <FmDeleteButton
                v-if="file.can_delete"
                compact
                :label="isCopy ? $t('Delete the copy') : $t('Delete')"
                :url="destroy.url(file.id)"
                :question="
                    isCopy ? $t('Delete this copy?') : $t('Delete this file?')
                "
                :text="$t('The file is deleted for good.')"
                :confirm-label="
                    isCopy ? $t('Delete the copy') : $t('Delete the file')
                "
            />
        </span>
    </li>
</template>
