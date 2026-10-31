<script setup lang="ts">
/**
 * The cases of one component of the catalogue, mounted for real from its
 * `.examples.ts`: a title and the component for each case, and beside them the
 * *Events* log with what the components sent. A `v-model` works, because each
 * case keeps its own copy of the props, and the visits that would leave the
 * catalogue (a *Delete*, a form) are stopped and written in the log.
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
import { blockVisits } from '@/catalog/blockVisits';
import catalog from '@/catalog/catalog.json';
import type { Example } from '@/catalog/examples';
import { loadExamples } from '@/catalog/examples';
import type { CatalogEntry } from '@/catalog/meta';

const { name } = defineProps<{
    /** The name of the component, as `FmRow`. */
    name: string;
}>();

/** One case ready to mount: its name and the component with its props and listeners. */
type MountedCase = { name: string; view: FunctionalComponent };

const examples = loadExamples()[name];
const events =
    (catalog as CatalogEntry[]).find((entry) => entry.name === name)?.events ??
    [];

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
    stopBlocking = blockVisits((visit) => log.value.push(visit));
});

onBeforeUnmount(() => {
    stopBlocking?.();
});
</script>

<template>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex min-w-0 grow flex-col gap-6">
            <section
                v-for="item in cases"
                :key="item.name"
                class="flex flex-col gap-2"
            >
                <h3 class="m-0 text-sm font-bold text-ink-soft">
                    {{ item.name }}
                </h3>
                <div class="rounded-xl border border-edge bg-white p-4">
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
                class="max-h-96 overflow-y-auto rounded-xl border border-edge bg-white p-3 font-mono text-xs text-ink"
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
