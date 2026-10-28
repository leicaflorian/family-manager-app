<script setup lang="ts">
/**
 * A category in the open list of a menu, for the `option` slot of a FormKit
 * autocomplete: the name alone, the subcategories indented under their main
 * category. Screen readers read the path instead, as the closed field shows it,
 * so that two «Other» stay different. A choice that is not a category («All»,
 * «None») shows its label.
 */
import type { CategoryOption } from '@/lib/categories';

const { option } = defineProps<{
    /** The option of the menu: a category, or a choice such as «All». */
    option: Pick<CategoryOption, 'label'> &
        Partial<Pick<CategoryOption, 'name' | 'depth'>>;
}>();
</script>

<template>
    <span class="min-w-0 grow truncate">
        <span
            aria-hidden="true"
            :style="{ paddingInlineStart: `${option.depth ?? 0}rem` }"
        >
            {{ option.name ?? option.label }}
        </span>
        <span class="sr-only">{{ option.label }}</span>
    </span>
</template>
