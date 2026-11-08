<script setup lang="ts">
/**
 * The form around the field of a story: a `<FormKit type="form">` without
 * buttons that holds one `<FormKit>` field, as a page of the app does. The
 * fields of FormKit are not components of ours, so their stories use this one
 * to show them with the plugins and the classes of `formkit.config.ts`. It is
 * only for the stories. The label, hint, errors, placeholder and the labels of the
 * options are English texts, translated here like the pages translate theirs.
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

// The attributes that are not props (`allow-new-values`, `autocomplete`, …) go to the field
defineOptions({ inheritAttrs: false });

const {
    type,
    name,
    label,
    value,
    help,
    errors = [],
    disabled = false,
    options,
    placeholder,
} = defineProps<{
    /** The type of the field: `text`, `currency`, `datepicker`, … */
    type: string;
    /** The name of the field in the form. */
    name: string;
    /** The text above the field. */
    label: string;
    /** The value the field starts with, as the form gets it: `2026-10-15` for a date, `84.2` for an amount. It is `unknown`, not a union with `boolean`: Vue would turn a missing prop of such a type into `false`. */
    value?: unknown;
    /** The hint below the field. */
    help?: string;
    /** The errors shown at once, as after a *Save* that failed. */
    errors?: string[];
    /** The field cannot be changed. */
    disabled?: boolean;
    /** The choices of a field with a list, as `{ value, label }`. */
    options?: { value: string | number; label: string }[];
    /** The text shown while the field is empty. */
    placeholder?: string;
}>();

/**
 * The options with their labels in the language of the page, as the pages of
 * the app give them.
 */
const choices = computed(() =>
    options?.map((option) => ({ ...option, label: trans(option.label) })),
);

/**
 * The props only some fields take: a field without options (a text, a
 * checkbox) breaks when it is given `options` as `undefined`.
 */
const extra = computed(() =>
    choices.value ? { options: choices.value, 'open-on-click': true } : {},
);
</script>

<template>
    <FormKit type="form" :actions="false" form-class="w-full max-w-sm">
        <FormKit
            :type="type"
            :name="name"
            :label="$t(label)"
            :value="value"
            :help="help ? $t(help) : undefined"
            :errors="errors.map((error) => $t(error))"
            :disabled="disabled"
            :placeholder="placeholder ? $t(placeholder) : undefined"
            v-bind="{ ...$attrs, ...extra }"
        />
    </FormKit>
</template>
