<script setup lang="ts">
/**
 * The cases of one component of the catalogue, mounted for real from its
 * `.examples.ts`: a title and the component for each case, and beside them the
 * *Events* log with what the components sent. A `v-model` works, because each
 * case keeps its own copy of the props, and the visits that would leave the
 * catalogue (a *Delete*, a form) are stopped and written in the log. A
 * component with a boolean `open` prop (a panel, a window) starts closed and
 * gets an *Open* button above each case; the component closes itself with its
 * own `update:open`.
 */
import type { Component, FunctionalComponent } from 'vue';
import {
    h,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    toHandlerKey,
    useId,
} from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import { blockVisits } from '@/stories/blockVisits';
import catalog from '@/catalog/catalog.json';
import type { Example } from '@/catalog/examples';
import { loadExamples } from '@/catalog/examples';
import type { CatalogEntry } from '@/catalog/meta';

const { name } = defineProps<{
    /** The name of the component, as `FmRow`. */
    name: string;
}>();

/** One case ready to mount: its name, the component with its props and listeners, and how to open it. */
type MountedCase = {
    name: string;
    view: FunctionalComponent;
    /** Opens the panel of the case, for a component with a boolean `open`. */
    open: (() => void) | null;
};

const examples = loadExamples()[name];
const entry = (catalog as CatalogEntry[]).find(
    (component) => component.name === name,
);
const events = entry?.events ?? [];
/** A panel or a window: it opens with a button, so it does not cover the page from the start. */
const opens =
    entry?.props.some(
        (prop) => prop.name === 'open' && prop.type === 'boolean',
    ) ?? false;

/** The id of the title of the log, which names it. */
const eventsId = useId();

/** What happened, oldest first. */
const log = ref<string[]>([]);

const cases: MountedCase[] = examples
    ? examples.examples.map((example) => mountCase(examples.component, example))
    : [];

/**
 * Prepares one case: a reactive copy of its props, a listener for each event
 * of the component, and its slots.
 *
 * @param component - The component of the catalogue.
 * @param example - The case.
 * @returns The case, with a view that draws the component.
 */
function mountCase(
    component: Component,
    example: Example<Component>,
): MountedCase {
    const values = reactive<Record<string, unknown>>({ ...example.props });
    const listeners = Object.fromEntries(
        events.map((event) => [
            toHandlerKey(event),
            (...args: unknown[]): void => {
                log.value.push(
                    `${example.name}: ${event}(${args.map(describe).join(', ')})`,
                );

                // The v-model of the case: the component asks for the new value
                if (event.startsWith('update:')) {
                    values[event.slice('update:'.length)] = args[0];
                }
            },
        ]),
    );
    const slots = Object.fromEntries(
        Object.entries(example.slots ?? {}).map(([slot, content]) => [
            slot,
            typeof content === 'string' ? (): string => content : content,
        ]),
    );

    return {
        name: example.name,
        view: () => h(component, { ...values, ...listeners }, slots),
        open: opens
            ? (): void => {
                  values.open = true;
              }
            : null,
    };
}

/**
 * One argument of an event, as the log writes it.
 *
 * @param value - The argument.
 * @returns Its JSON, or its text when it has no JSON (an `Event`, a `File`).
 */
function describe(value: unknown): string {
    try {
        return JSON.stringify(value) ?? String(value);
    } catch {
        return String(value);
    }
}

let stopBlocking: (() => void) | null = null;

onMounted(() => {
    stopBlocking = blockVisits(
        (visit) => log.value.push(visit),
        ['/dev/components'],
    );
});

onBeforeUnmount(() => {
    stopBlocking?.();
});
</script>

<template>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex min-w-0 grow flex-col gap-6">
            <p v-if="!cases.length" class="m-0 text-base text-ink-soft">
                {{ $t('No cases for this component yet.') }}
            </p>
            <section
                v-for="item in cases"
                :key="item.name"
                class="flex flex-col gap-2"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3 class="m-0 text-sm font-bold text-ink-soft">
                        {{ item.name }}
                    </h3>
                    <FmButton
                        v-if="item.open"
                        variant="soft"
                        @click="item.open"
                    >
                        {{ $t('Open') }}
                    </FmButton>
                </div>
                <div class="rounded-lg border border-edge bg-card p-4">
                    <component :is="item.view" />
                </div>
            </section>
        </div>
        <aside
            class="flex w-full shrink-0 flex-col gap-2 lg:sticky lg:top-4 lg:w-80"
        >
            <h3 :id="eventsId" class="m-0 text-sm font-bold text-ink">
                {{ $t('Events') }}
            </h3>
            <div
                role="log"
                :aria-labelledby="eventsId"
                class="max-h-96 overflow-y-auto rounded-lg border border-edge bg-card p-3 font-mono text-xs text-ink"
            >
                <ol
                    v-if="log.length"
                    class="m-0 flex list-none flex-col gap-1 p-0"
                >
                    <li v-for="(line, index) in log" :key="index">
                        {{ line }}
                    </li>
                </ol>
                <p v-else class="m-0 text-ink-soft">
                    {{ $t('Nothing happened yet.') }}
                </p>
            </div>
        </aside>
    </div>
</template>
