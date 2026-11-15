<script setup lang="ts">
/**
 * *What's new*: the panel that the item with the version opens, in the account menu of the sidebar
 * and in *More* of the phone. It lists the versions of the app, newest first, each with its day and
 * what changed in simple sentences; the version that runs has the pill *Your version*. It asks for
 * the notes each time it opens (they are not sent with the pages) and says so while they arrive.
 *
 * @group panels
 */
import { watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import FmBottomSheet from './FmBottomSheet.vue';

const { release, notes } = defineProps<{
    /** The version that runs, `dev` when the code has none. */
    release: string;
    /** The versions with their notes; missing until the server sends them. */
    notes?: App.Data.ReleaseNoteData[];
}>();

const emit = defineEmits<{
    /** The panel opened and needs the notes. */
    load: [];
}>();

/** Open or closed. */
const open = defineModel<boolean>('open', { required: true });

const { date } = useFormat();

watch(
    open,
    (isOpen: boolean): void => {
        if (isOpen) {
            emit('load');
        }
    },
    { immediate: true },
);
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('What\'s new')">
        <p v-if="notes === undefined" class="text-body-sm text-ink-soft">
            {{ $t('Loading…') }}
        </p>
        <p v-else-if="notes.length === 0" class="text-body-sm text-ink-soft">
            {{ $t('No news yet.') }}
        </p>
        <div v-else class="flex flex-col gap-4">
            <section
                v-for="(version, index) in notes"
                :key="version.version"
                :aria-label="
                    $t('Version :version', { version: version.version })
                "
                :class="[
                    'flex flex-col gap-2',
                    index > 0 && 'border-t border-divider pt-4',
                ]"
            >
                <div class="flex flex-wrap items-center gap-2.5">
                    <h3 class="text-label">
                        {{
                            $t('Version :version', { version: version.version })
                        }}
                    </h3>
                    <span
                        v-if="version.version === release"
                        class="inline-flex h-6 items-center rounded-tag bg-action-soft px-2.5 text-caption text-action-dark"
                        >{{ $t('Your version') }}</span
                    >
                    <span class="ml-auto text-meta text-ink-soft">{{
                        date(version.date)
                    }}</span>
                </div>
                <ul class="flex list-disc flex-col gap-1.5 pl-5 text-body-sm">
                    <li v-for="line in version.notes" :key="line">
                        {{ line }}
                    </li>
                </ul>
            </section>
        </div>
    </FmBottomSheet>
</template>
