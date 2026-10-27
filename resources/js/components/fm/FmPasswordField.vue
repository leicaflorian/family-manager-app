<script setup lang="ts">
/**
 * The password field with the eye to see it, as in the sign-in boards.
 * It is a `<FormKit>`: it takes the classes of the fields from `formkit.config.ts`.
 */
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';
import FmIcon from './FmIcon.vue';

const {
    label,
    name,
    help,
    errors = [],
    autocomplete = 'current-password',
} = defineProps<{
    /** The text above the field. */
    label: string;
    /** The name of the field in the form. */
    name: string;
    /** The hint below the field, for example «At least 10 characters». */
    help?: string;
    /** The errors that come from the server. */
    errors?: string[];
    /** The hint for the browser: `current-password` or `new-password`. */
    autocomplete?: string;
}>();

/** The password written. */
const value = defineModel<string>({ default: '' });
const visible = ref(false);
</script>

<template>
    <FormKit
        v-model="value"
        :type="visible ? 'text' : 'password'"
        :label="label"
        :name="name"
        :help="help"
        :errors="errors"
        :autocomplete="autocomplete"
        :classes="{ inner: 'pr-1.5' }"
    >
        <template #suffix>
            <button
                type="button"
                :aria-label="
                    visible ? $t('Hide the password') : $t('Show the password')
                "
                class="flex size-10 shrink-0 items-center justify-center text-ink-soft"
                @click="visible = !visible"
            >
                <FmIcon :icon="visible ? EyeOff : Eye" :size="20" />
            </button>
        </template>
    </FormKit>
</template>
