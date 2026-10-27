<script setup lang="ts">
/**
 * A level of the tree of the document categories: each category with the number
 * of its documents and, if it has any, the arrow that opens the subcategories.
 * It calls itself for the levels below.
 */
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import { areaFill, areaOf, areaTile } from '@/lib/areas';
import { categoryIcon } from '@/lib/icons';
import FmCategoryTree from './FmCategoryTree.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

const {
    categories,
    level = 0,
    selectable = false,
} = defineProps<{
    /** The categories of this level. */
    categories: App.Data.CategoryData[];
    /** How deep this level is: 0 for the main ones. */
    level?: number;
    /** The categories are tapped to choose them (to edit them). */
    selectable?: boolean;
}>();

const emit = defineEmits<{
    /** A category was tapped. */
    select: [category: App.Data.CategoryData];
}>();

/** The ids of the open categories. */
const open = ref(new Set<number>());

/**
 * Opens or closes the subcategories.
 *
 * @param id - the category
 */
function toggle(id: number): void {
    if (!open.value.delete(id)) {
        open.value.add(id);
    }
}
</script>

<template>
    <ul class="m-0 list-none p-0">
        <li
            v-for="category in categories"
            :key="category.id"
            class="border-divider not-first:border-t"
        >
            <div
                class="flex items-center"
                :style="{ paddingLeft: `${level * 28}px` }"
            >
                <button
                    v-if="category.children.length"
                    type="button"
                    class="ml-2 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent text-[#9AA6B6] hover:bg-canvas"
                    :aria-expanded="open.has(category.id)"
                    :aria-label="
                        open.has(category.id)
                            ? $t('Close :name', { name: category.name })
                            : $t('Open :name', { name: category.name })
                    "
                    @click="toggle(category.id)"
                >
                    <FmIcon
                        :icon="
                            open.has(category.id) ? ChevronDown : ChevronRight
                        "
                        :size="18"
                    />
                </button>
                <span v-else class="ml-2 size-9 shrink-0" />
                <FmRow
                    class="min-w-0 grow pl-1"
                    :title="category.name"
                    :button="selectable"
                    @click="emit('select', category)"
                >
                    <template #before>
                        <span
                            v-if="level === 0"
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                areaTile[areaOf(category.color) ?? 'general']
                            "
                        >
                            <FmIcon
                                :icon="categoryIcon(category.icon)"
                                :size="18"
                            />
                        </span>
                        <span
                            v-else
                            class="size-2.5 shrink-0 rounded-full"
                            :class="
                                areaFill[areaOf(category.color) ?? 'general']
                            "
                        />
                    </template>
                    <template #after>
                        <span class="text-[13px] font-normal text-ink-soft">{{
                            category.documents_count
                        }}</span>
                    </template>
                </FmRow>
            </div>
            <FmCategoryTree
                v-if="open.has(category.id)"
                :categories="category.children"
                :level="level + 1"
                :selectable="selectable"
                @select="emit('select', $event)"
            />
        </li>
    </ul>
</template>
