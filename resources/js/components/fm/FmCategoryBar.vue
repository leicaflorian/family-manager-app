<script setup lang="ts">
/**
 * A row of *By category*: the name, a bar with what was spent, a mark where the
 * plan reaches, the comment («193,40 € less») and «spent of planned».
 *
 * @group figures
 */
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { comparisonComment } from '@/lib/budget';

const {
    category,
    spent,
    planned,
    estimated = false,
    lateTitle = null,
    color = null,
} = defineProps<{
    /** The name of the category. */
    category: string;
    /** What was spent. */
    spent: number;
    /** What was planned. */
    planned: number;
    /** The plan is only an estimate. */
    estimated?: boolean;
    /** The title of a due date of the category that is late. */
    lateTitle?: string | null;
    /** The colour of the category, for example `#e35169`. */
    color?: string | null;
}>();

const { euro } = useFormat();

/** The longer of the two: the length of the whole bar. */
const scale = computed(() => Math.max(spent, planned, 0.01));

/** Where the bar of the spending ends, in percent. */
const spentWidth = computed(() => `${(spent / scale.value) * 100}%`);

/** Where the mark of the plan is, in percent. */
const plannedLeft = computed(() => `${(planned / scale.value) * 100}%`);
</script>

<template>
    <div class="flex flex-col gap-1.5 px-4 py-3">
        <div class="flex items-baseline justify-between gap-3">
            <span
                class="flex min-w-0 items-center gap-2 text-base font-semibold"
            >
                <span
                    data-dot
                    class="size-2.5 shrink-0 rounded-full bg-expenses"
                    :style="color ? { backgroundColor: color } : undefined"
                />
                <span class="truncate">{{ category }}</span>
            </span>
            <span class="shrink-0 text-[13px] whitespace-nowrap text-ink-soft">
                <strong class="text-base text-ink">{{ euro(spent) }}</strong>
                <span>{{
                    ' ' + $t('of :planned', { planned: euro(planned) })
                }}</span>
            </span>
        </div>
        <div
            class="relative h-2.5 rounded-full bg-divider"
            :title="
                $t(':category: spent :spent, planned :planned', {
                    category,
                    spent: euro(spent),
                    planned: euro(planned),
                })
            "
        >
            <span
                class="absolute inset-y-0 left-0 rounded-full bg-action"
                :style="{ width: spentWidth }"
            />
            <span
                v-if="planned > 0"
                data-planned
                class="absolute -top-0.5 h-3.5 w-0.5 bg-ink shadow-[0_0_0_2px_#fff]"
                :style="{ left: `calc(${plannedLeft} - 1px)` }"
            />
        </div>
        <span class="text-[13px] text-ink-soft">{{
            comparisonComment(spent, planned, estimated, lateTitle)
        }}</span>
    </div>
</template>
