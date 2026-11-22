<script setup lang="ts">
/**
 * *Add a drawing*: the panel of the gallery that takes one photo or PDF, the
 * child it belongs to and, if the person wants, a title and a description, and
 * sends them to the gallery. The child starts on the one the gallery is filtered by, or on the
 * only person of the family. The file goes straight to the gallery, without
 * being read by the AI. Closing with something chosen or written asks first.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { errors } from '@/lib/errors';
import { withNone } from '@/lib/forms';
import { store } from '@/routes/documents/gallery';

const { people, person = null } = defineProps<{
    /** The children (and the other people) a drawing can be of. */
    people: { id: number; name: string }[];
    /** The child the gallery is filtered by, chosen when the panel opens. */
    person?: number | null;
}>();

/** The child chosen when the panel opens: the one of the filter, else the only person there is, else nobody. */
const startPerson = computed((): string => {
    const filtered = people.find((candidate) => candidate.id === person);
    const only = people.length === 1 ? people[0] : undefined;
    const chosen = filtered ?? only;

    return chosen === undefined ? '' : String(chosen.id);
});

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

// Opened (again), it starts empty, on the child of the filter or the only person: that is not a change
watch(
    open,
    (isOpen) => {
        if (isOpen) {
            values.file = null;
            values.person_id = startPerson.value;
            values.title = '';
            values.notes = '';
            problems.value = {};
            changes.reset();
        }
    },
    { immediate: true },
);

/** The suggestion in the title field: the child chosen, or a plain one. */
const titleHint = computed((): string => {
    const name = people.find(
        (person) => String(person.id) === values.person_id,
    )?.name;

    return name ? trans('Drawing by :name', { name }) : trans('Drawing');
});

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
                <FmFileDrop
                    sheet
                    single
                    :icon="Camera"
                    :label="$t('Take a photo or choose a file')"
                    :hint="$t('A photo or a PDF, up to 25 MB')"
                    :chosen="values.file?.name"
                    accept="image/*,application/pdf,.heic,.heif"
                    :input-label="$t('Photo or PDF of the drawing')"
                    @files="(files) => (values.file = files[0] ?? null)"
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
                :placeholder="titleHint"
                :errors="errors(problems.title)"
            />
            <FormKit
                v-model="values.notes"
                type="textarea"
                name="notes"
                rows="3"
                optional
                :label="$t('Description')"
                :placeholder="$t('Where they made it, with what…')"
                :errors="errors(problems.notes)"
            />
            <p class="m-0 text-meta text-ink-soft">
                {{
                    $t(
                        'It goes straight to the gallery, without being read by the AI.',
                    )
                }}
            </p>

            <div class="flex gap-2.5">
                <FmButton
                    variant="text"
                    class="shrink-0"
                    @click="open = false"
                    >{{ $t('Cancel') }}</FmButton
                >
                <span class="flex grow">
                    <FmButton type="submit" wide :disabled="processing">{{
                        $t('Save')
                    }}</FmButton>
                </span>
            </div>
        </form>
    </FmBottomSheet>
</template>
