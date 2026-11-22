<script setup lang="ts">
/**
 * *Add a drawing*: the panel of the gallery that takes one photo or PDF, the
 * child it belongs to and, if the person wants, a title and a description, and
 * sends them to the gallery. The file goes straight to the gallery, without
 * being read by the AI. Closing with something chosen or written asks first.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, useTemplateRef, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { withNone } from '@/lib/forms';
import { store } from '@/routes/documents/gallery';

const { people } = defineProps<{
    /** The children (and the other people) a drawing can be of. */
    people: { id: number; name: string }[];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

/** What the person chooses and writes. */
const values = reactive({
    file: null as File | null,
    person_id: '',
    title: '',
    notes: '',
});

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The drawing is being sent. */
const processing = ref(false);

/** The hidden picker that the dashed button opens. */
const picker = useTemplateRef<HTMLInputElement>('picker');

/** The people as the options of the menu. */
const options = computed(() =>
    withNone(
        people.map((person) => ({
            value: String(person.id),
            label: person.name,
        })),
    ),
);

/** Asks before closing with something chosen or written. */
const changes = useUnsavedChanges(
    () => [
        values.file?.name ?? '',
        values.person_id,
        values.title,
        values.notes,
    ],
    { active: () => open.value },
);

// Opened again, it starts empty
watch(open, (isOpen) => {
    if (isOpen) {
        values.file = null;
        values.person_id = '';
        values.title = '';
        values.notes = '';
        problems.value = {};
        changes.reset();
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
    input.value = '';
}

/** Sends the drawing. A file makes it a form with files, which PHP only reads as a POST. */
function save(): void {
    problems.value = {};

    if (values.file === null) {
        problems.value = { file: trans('Choose a photo or a PDF.') };

        return;
    }

    processing.value = true;

    router.post(
        store().url,
        {
            file: values.file,
            person_id: values.person_id === '' ? null : values.person_id,
            title: values.title === '' ? null : values.title,
            notes: values.notes === '' ? null : values.notes,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: (messages) => {
                problems.value = messages;
            },
            onSuccess: () => {
                changes.reset();
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
        :title="$t('Add a drawing')"
    >
        <form class="flex flex-col gap-3.5" novalidate @submit.prevent="save">
            <div class="flex flex-col gap-1.5">
                <span class="text-label text-ink">{{ $t('Drawing') }}</span>
                <button
                    type="button"
                    class="flex min-h-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-[1.5px] border-dashed border-edge bg-card px-3 py-3 text-row text-action"
                    @click="picker?.click()"
                >
                    <FmIcon :icon="ImagePlus" :size="22" />
                    <span class="truncate">{{
                        values.file
                            ? values.file.name
                            : $t('Take a photo or choose a file')
                    }}</span>
                    <span v-if="!values.file" class="text-meta text-ink-soft">{{
                        $t('A photo or a PDF, up to 25 MB')
                    }}</span>
                </button>
                <input
                    ref="picker"
                    type="file"
                    class="hidden"
                    :aria-label="$t('Photo or PDF of the drawing')"
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

            <FormKit
                v-model="values.person_id"
                type="autocomplete"
                open-on-click
                name="person_id"
                optional
                :label="$t('Child')"
                :options="options"
                :errors="errors(problems.person_id)"
            />
            <FormKit
                v-model="values.title"
                type="text"
                name="title"
                optional
                :label="$t('Title')"
                :errors="errors(problems.title)"
            />
            <FormKit
                v-model="values.notes"
                type="textarea"
                name="notes"
                rows="3"
                optional
                :label="$t('Description')"
                :errors="errors(problems.notes)"
            />
            <p class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        'It goes straight to the gallery, without being read by the AI.',
                    )
                }}
            </p>

            <div
                class="flex flex-col gap-1 lg:flex-row-reverse lg:justify-start lg:gap-2.5"
            >
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
