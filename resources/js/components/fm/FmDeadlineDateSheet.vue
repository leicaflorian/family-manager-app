<script setup lang="ts">
/**
 * The panel of a passed deadline in the Agenda: *Update the date* corrects
 * the date written on the card, *Renewed* writes the new one (proposed a year
 * later) and marks the reminder of the old date done. It sends the date to the
 * `update_url` of the entry.
 *
 * @group panels
 */
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { addYears } from '@/lib/days';
import { errors } from '@/lib/errors';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

const { entry, renewed = false } = defineProps<{
    /** The passed deadline; it has an `update_url`. */
    entry: App.Data.AgendaEntryData;
    /** It was renewed: the new date is proposed a year later and the reminder is marked done. */
    renewed?: boolean;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { date: formatDate } = useFormat();

/**
 * The date the field starts from: the same one to correct it, a year later for a renewal.
 *
 * @returns `yyyy-mm-dd`
 */
function proposed(): string {
    return renewed ? addYears(entry.date, 1) : entry.date;
}

/** The new date, `yyyy-mm-dd`. */
const day = ref(proposed());

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The date is being sent. */
const processing = ref(false);

// Opened again, the field starts again from the proposed date
watch(open, (isOpen) => {
    if (isOpen) {
        day.value = proposed();
        problems.value = {};
    }
});

/** Sends the new date. */
function save(): void {
    if (entry.update_url === null) {
        return;
    }

    processing.value = true;
    problems.value = {};

    router.put(
        entry.update_url,
        { date: day.value, renewed },
        {
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
        :title="renewed ? $t('Renewed') : $t('Update the date')"
    >
        <div class="flex flex-col rounded-2xl bg-canvas p-3.5">
            <span class="truncate text-base font-bold">{{ entry.name }}</span>
            <span class="truncate text-[13px] text-ink-soft">{{
                `${entry.detail} · ${formatDate(entry.date)}`
            }}</span>
        </div>
        <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
            <FormKit
                v-model="day"
                type="datepicker"
                name="date"
                :label="renewed ? $t('New expiry date') : $t('Correct date')"
                :help="
                    renewed
                        ? $t(
                              'The reminder is marked done, and a new one waits for the new date.',
                          )
                        : $t(
                              'The date changes on the card, and the reminder moves with it.',
                          )
                "
                :errors="errors(problems.date)"
            />
            <div class="flex justify-end gap-2.5">
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
