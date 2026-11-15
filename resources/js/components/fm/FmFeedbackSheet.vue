<script setup lang="ts">
/**
 * *Report a problem or an idea*: the panel that the account menu of the
 * sidebar and *More* of the phone open, to write to the owner. It asks what it
 * is about (something that does not work, chosen at the opening, or an idea),
 * the message and an optional screenshot or PDF, and sends with them the page
 * the person is on and the layout of the app (the server adds the device and
 * the time). Once sent it closes, and the toast of the server thanks; opened
 * again it starts empty.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { Bug, ImagePlus, Lightbulb, Send, X } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, useTemplateRef, watch } from 'vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { store } from '@/routes/feedback';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';

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
            page: window.location.pathname + window.location.search,
            layout: layout.value,
            ...(values.file ? { file: values.file } : {}),
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
                        :label="
                            $t('Remove :name', { name: values.file.name })
                        "
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

            <FmNotice>
                {{
                    $t(
                        'Along with the message we send the page you were on, the device you use and the time. We answer you by email.',
                    )
                }}
            </FmNotice>

            <div
                class="flex flex-col gap-1 lg:flex-row-reverse lg:justify-start lg:gap-2.5"
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
