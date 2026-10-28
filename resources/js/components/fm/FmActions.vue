<script setup lang="ts">
/**
 * The actions of a page, beside its title: the first three as buttons, fewer
 * when the room is short, down to none, and the others in the ⋯ menu. An
 * action with `confirm` asks in the panel from below before deleting, from the
 * button and from the menu alike.
 */
import { Ellipsis } from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import type { Action } from '@/lib/actions';
import { MAX_VISIBLE_ACTIONS, visibleActionCount } from '@/lib/actions';
import FmButton from './FmButton.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const { actions } = defineProps<{
    /** The actions, the most frequent first: the last ones go in the menu first. */
    actions: Action[];
}>();

/** The gap between the buttons, `gap-2.5`. */
const GAP = 10;

/** The actions that can be buttons, in order. */
const buttonActions = computed((): Action[] =>
    actions.filter((action) => !action.menuOnly),
);

/** The actions with a confirmation panel. */
const confirmActions = computed((): Action[] =>
    actions.filter((action) => action.confirm),
);

const root = useTemplateRef<HTMLElement>('root');
const measure = useTemplateRef<HTMLElement>('measure');

/** How many buttons fit; until the page is measured, as many as allowed. */
const count = ref(MAX_VISIBLE_ACTIONS);

/** The buttons in view. */
const shown = computed((): Action[] =>
    buttonActions.value.slice(0, count.value),
);

/** The entries of the menu: the buttons that did not fit, then the others. */
const inMenu = computed((): Action[] => [
    ...buttonActions.value.slice(count.value),
    ...actions.filter((action) => action.menuOnly),
]);

/** The label of the action whose confirmation panel is open. */
const confirming = ref<string | null>(null);

/**
 * Measures the room and the hidden copy of the buttons, the ⋯ last, and keeps
 * as many buttons as fit.
 */
function fit(): void {
    if (!root.value || !measure.value) {
        return;
    }

    const widths = [...measure.value.children].map(
        (element) => (element as HTMLElement).offsetWidth,
    );
    const menuWidth = widths.pop() ?? 0;

    count.value = visibleActionCount({
        widths,
        menuWidth,
        gap: GAP,
        available: root.value.clientWidth,
        menuOnly: actions.some((action) => action.menuOnly),
    });
}

useResizeObserver(root, fit);

/**
 * Does what an action without an address says: asks first, for one that
 * deletes.
 *
 * @param action - the action chosen
 */
function choose(action: Action): void {
    if (action.confirm) {
        confirming.value = action.label;
    } else {
        action.onSelect?.();
    }
}
</script>

<template>
    <div
        ref="root"
        class="relative flex min-w-0 flex-1 items-center justify-end gap-2.5"
    >
        <!-- A hidden copy of every button and of the ⋯, to know their widths
             also of the buttons that are in the menu now -->
        <div
            ref="measure"
            aria-hidden="true"
            inert
            class="invisible absolute top-0 right-0 flex w-max gap-2.5"
        >
            <FmButton
                v-for="action in buttonActions"
                :key="action.label"
                :variant="action.variant"
                :icon="action.icon"
                >{{ action.label }}</FmButton
            >
            <FmButton
                variant="soft"
                :icon="Ellipsis"
                :label="$t('More actions')"
            />
        </div>

        <FmButton
            v-for="action in shown"
            :key="action.label"
            :variant="action.variant"
            :icon="action.icon"
            :href="action.href"
            :download="action.download"
            :external="action.external"
            @click="choose(action)"
            >{{ action.label }}</FmButton
        >
        <FmMenu v-if="inMenu.length > 0">
            <FmMenuItem
                v-for="action in inMenu"
                :key="action.label"
                :icon="action.icon"
                :href="action.href"
                :download="action.download"
                :external="action.external"
                :danger="action.variant === 'danger'"
                @select="choose(action)"
                >{{ action.label }}</FmMenuItem
            >
        </FmMenu>
        <FmConfirmDelete
            v-for="action in confirmActions"
            :key="action.label"
            :open="confirming === action.label"
            :url="action.confirm!.url"
            :question="action.confirm!.question"
            :text="action.confirm!.text"
            :confirm-label="action.confirm!.confirmLabel"
            @update:open="(open) => (confirming = open ? action.label : null)"
        />
    </div>
</template>
