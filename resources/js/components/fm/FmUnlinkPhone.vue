<script setup lang="ts">
/**
 * The question that comes before unlinking a phone, from the panel *Link the
 * phone* and from *My account*: it names the phone and says what happens, and
 * *Unlink* sends the deletion. It is shown while `device` is set.
 *
 * @group panels
 */
import { computed } from 'vue';
import { destroy } from '@/routes/devices';
import FmConfirmDelete from './FmConfirmDelete.vue';

const { device, preserveState = false } = defineProps<{
    /** The phone to unlink, or null when no question is asked. */
    device: App.Data.LinkedDeviceData | null;
    /** The page stays as it is, as the panel over a page does. */
    preserveState?: boolean;
}>();

const emit = defineEmits<{
    /** The question was closed, with or without unlinking. */
    close: [];
}>();

/** Whether the question is open: it follows `device`, and closing it asks the page to let the phone go. */
const open = computed({
    get: (): boolean => device !== null,
    set: (value: boolean): void => {
        if (!value) {
            emit('close');
        }
    },
});
</script>

<template>
    <FmConfirmDelete
        v-if="device !== null"
        v-model:open="open"
        :url="destroy(device.id).url"
        :question="$t('Unlink this phone?')"
        :text="
            $t(':name stops uploading documents until you link it again.', {
                name: device.name,
            })
        "
        :confirm-label="$t('Unlink')"
        :preserve-state="preserveState"
    />
</template>
