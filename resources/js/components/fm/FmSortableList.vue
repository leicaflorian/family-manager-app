<script setup lang="ts" generic="T extends { key: string }">
/**
 * A list the person reorders by dragging the handle of a row, or with the
 * "move up" and "move down" that the row gives to its own menu (the keyboard
 * and the phone). The list does not keep the order: it says the new order of
 * the keys with `sort`, and the page saves it and sends the items again.
 * Nothing in the list is draggable when it is disabled.
 *
 * @group basics
 */
import { GripVertical } from '@lucide/vue';
import { dragAndDrop } from '@formkit/drag-and-drop/vue';
import { shallowRef, watch, onMounted, useTemplateRef } from 'vue';
import { cn } from '@/lib/utils';

const { items, disabled = false } = defineProps<{
    /** The items in their order; each has a `key` that identifies it. */
    items: T[];
    /** Turns dragging off: no handles, and the order does not change. */
    disabled?: boolean;
    /** The accessible name of the list. */
    label: string;
}>();

const emit = defineEmits<{
    /** The new order, as the keys of the items, after a drag or a move. */
    sort: [keys: string[]];
}>();

defineSlots<{
    /** The content of a row; `moveUp` and `moveDown` swap it with its neighbour. */
    item: (props: {
        item: T;
        index: number;
        moveUp: () => void;
        moveDown: () => void;
    }) => unknown;
}>();

const list = useTemplateRef<HTMLElement>('list');
/** The order shown while dragging; the library reorders this copy. */
const values = shallowRef<T[]>([...items]);

watch(
    () => items,
    (next) => {
        values.value = [...next];
    },
);

onMounted(() => {
    if (list.value === null) {
        return;
    }

    dragAndDrop<T>({
        parent: list.value,
        values,
        dragHandle: '[data-drag-handle]',
        draggingClass: 'opacity-60',
        onDragend: () => {
            const keys = values.value.map((item) => item.key);

            if (keys.join() !== items.map((item) => item.key).join()) {
                emit('sort', keys);
            }
        },
    });
});

/**
 * Swaps an item with a neighbour and says the new order.
 *
 * @param index - the position of the item
 * @param step - -1 for up, 1 for down
 */
function move(index: number, step: -1 | 1): void {
    const target = index + step;

    if (disabled || target < 0 || target >= items.length) {
        return;
    }

    const keys = items.map((item) => item.key);

    [keys[index], keys[target]] = [keys[target]!, keys[index]!];
    emit('sort', keys);
}
</script>

<template>
    <ul ref="list" :aria-label="label" role="list">
        <li
            v-for="(item, index) in values"
            :key="item.key"
            :class="cn('flex items-center gap-2')"
        >
            <span
                v-if="!disabled"
                data-drag-handle
                role="img"
                :aria-label="$t('Drag to move')"
                class="cursor-grab touch-none text-ink-soft"
            >
                <GripVertical aria-hidden="true" class="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <slot
                    name="item"
                    :item="item"
                    :index="index"
                    :move-up="() => move(index, -1)"
                    :move-down="() => move(index, 1)"
                />
            </div>
        </li>
    </ul>
</template>
