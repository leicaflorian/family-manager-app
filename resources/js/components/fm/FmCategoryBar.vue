<script setup lang="ts">
/**
 * A row of *By category*: the name, a bar with what was spent, a mark where the
 * plan reaches, the comment («193,40 € less») and «spent of planned». On the phone the name and the amount are
 * on one line, then the bar and the comment under it; `wide` (the wide layout of the page) puts the name on the
 * left and, on the right, the comment and the amount on one line with the bar under them; on the tablet, narrower,
 * the comment, the amount and the bar go under the name.
 *
 * @group figures
 */
import { createReusableTemplate } from '@vueuse/core';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { comparisonComment } from '@/lib/budget';
import { cn } from '@/lib/utils';

const {
    category,
    spent,
    planned,
    estimated = false,
    lateTitle = null,
    color = null,
    wide = false,
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
    /** The wide arrangement: name left, comment and amount over the bar on the right. */
    wide?: boolean;
}>();

const { euro } = useFormat();
const [DefineBar, ReuseBar] = createReusableTemplate();
const [DefineName, ReuseName] = createReusableTemplate();

/** The longer of the two: the length of the whole bar. */
const scale = computed(() => Math.max(spent, planned, 0.01));

/** Where the bar of the spending ends, in percent. */
const spentWidth = computed(() => `${(spent / scale.value) * 100}%`);

/** Where the mark of the plan is, in percent. */
const plannedLeft = computed(() => `${(planned / scale.value) * 100}%`);

/** More was spent than planned: what goes over is drawn in red. */
const over = computed((): boolean => spent > planned);

/** The comment under the name, for example «193,40 € less · luce late». */
const comment = computed((): string =>
    comparisonComment(spent, planned, estimated, lateTitle),
);
</script>

<template>
    <DefineName>
        <span class="flex min-w-0 items-center gap-2 text-body-strong">
            <span
                data-dot
                class="size-2.5 shrink-0 rounded-full bg-expenses"
                :style="color ? { backgroundColor: color } : undefined"
            />
            <span class="truncate">{{ category }}</span>
        </span>
    </DefineName>
    <DefineBar>
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
                v-if="over"
                data-over
                class="absolute inset-y-0 right-0 rounded-full bg-overdue"
                :style="{ left: plannedLeft }"
            />
            <span
                v-if="planned > 0"
                data-planned
                class="absolute -top-0.5 h-3.5 w-0.5 bg-ink ring-2 ring-card"
                :style="{ left: `calc(${plannedLeft} - 1px)` }"
            />
        </div>
    </DefineBar>

    <div
        v-if="wide"
        data-row
        class="flex flex-col gap-2 px-4.5 py-3 computer:grid computer:grid-cols-[minmax(0,220px)_minmax(0,1fr)] computer:items-center computer:gap-4"
    >
        <ReuseName />
        <div class="flex min-w-0 flex-col gap-2">
            <div class="flex items-baseline justify-between gap-3">
                <span class="min-w-0 text-meta text-ink-soft">{{
                    comment
                }}</span>
                <span
                    class="shrink-0 text-meta whitespace-nowrap text-ink-soft"
                >
                    <strong
                        :class="
                            cn('text-body', over ? 'text-overdue' : 'text-ink')
                        "
                        >{{ euro(spent) }}</strong
                    >
                    <span>{{
                        ' ' + $t('of :planned', { planned: euro(planned) })
                    }}</span>
                </span>
            </div>
            <ReuseBar />
        </div>
    </div>
    <div v-else class="flex flex-col gap-1.5 px-4 py-3">
        <div class="flex items-baseline justify-between gap-3">
            <ReuseName />
            <span class="shrink-0 text-meta whitespace-nowrap text-ink-soft">
                <strong
                    :class="cn('text-body', over ? 'text-overdue' : 'text-ink')"
                    >{{ euro(spent) }}</strong
                >
                <span>{{
                    ' ' + $t('of :planned', { planned: euro(planned) })
                }}</span>
            </span>
        </div>
        <ReuseBar />
        <span class="text-meta text-ink-soft">{{ comment }}</span>
    </div>
</template>
