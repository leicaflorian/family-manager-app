<script
    setup
    lang="ts"
    generic="
        T extends {
            id: number;
            name: string;
            icon: string | null;
            color: string | null;
            children: T[];
        }
    "
>
/**
 * A level of the tree of the categories, of documents or of expenses: each
 * category with, on the right, the number of its documents (or what the `after`
 * slot shows) and, if it has any, the arrow that opens the subcategories. It
 * calls itself for the levels below.
 *
 * @group forms
 */
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import { areaFill, areaOf, areaTile, colorFill, colorTile } from '@/lib/areas';
import { categoryIcon } from '@/lib/icons';
import FmCategoryTree from './FmCategoryTree.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

const {
    categories,
    level = 0,
    selectable = false,
    mainOnly = false,
} = defineProps<{
    /** The categories of this level. */
    categories: T[];
    /** How deep this level is: 0 for the main ones. */
    level?: number;
    /** The categories are tapped to choose them (to edit them). */
    selectable?: boolean;
    /** Only the main categories are tapped: the subcategories are just read. */
    mainOnly?: boolean;
}>();

const emit = defineEmits<{
    /** A category was tapped. */
    select: [category: T];
}>();

defineSlots<{
    /** What is on the right of a category, in place of the number of its documents. */
    after?: (props: { category: T }) => unknown;
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
                    class="ml-2 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-tag border-0 bg-transparent text-ink-faint hover:bg-canvas"
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
                    :button="selectable && !(mainOnly && level > 0)"
                    @click="emit('select', category)"
                >
                    <template #before>
                        <span
                            v-if="level === 0"
                            class="flex size-8 shrink-0 items-center justify-center rounded-tag"
                            :class="
                                areaTile[areaOf(category.color) ?? 'general']
                            "
                            :style="colorTile(category.color)"
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
                            :style="colorFill(category.color)"
                        />
                    </template>
                    <template #after>
                        <slot name="after" :category="category">
                            <span
                                v-if="'documents_count' in category"
                                class="text-meta text-ink-soft"
                                >{{ category.documents_count }}</span
                            >
                        </slot>
                    </template>
                </FmRow>
            </div>
            <FmCategoryTree
                v-if="open.has(category.id)"
                :categories="category.children"
                :level="level + 1"
                :selectable="selectable"
                :main-only="mainOnly"
                @select="emit('select', $event)"
            >
                <template v-if="$slots.after" #after="{ category: child }">
                    <slot name="after" :category="child" />
                </template>
            </FmCategoryTree>
        </li>
    </ul>
</template>
